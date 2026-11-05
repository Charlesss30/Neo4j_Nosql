<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JsonException;
use RuntimeException;

class FamilyBackupService
{
    private const DIRECTORY = 'family-backups';

    public function __construct(
        private readonly Neo4jService $neo4j,
        private readonly CypherStatements $statements,
        private readonly GraphWriteLock $writeLock,
    ) {}

    public function create(): string
    {
        return $this->maintenance(fn (): string => $this->save($this->snapshot(), 'manual'));
    }

    /** @return list<array{id: string, created_at: string, reason: string, counts: array<string, int>}> */
    public function history(): array
    {
        $disk = Storage::disk('local');
        $files = collect($disk->files(self::DIRECTORY))
            ->filter(fn (string $path): bool => Str::isUuid(pathinfo($path, PATHINFO_FILENAME)))
            ->sortByDesc(fn (string $path): int => $disk->lastModified($path))
            ->take(10);
        $backups = [];

        foreach ($files as $path) {
            try {
                $document = $this->decode($disk->get($path));
                $backups[] = [
                    'id' => pathinfo($path, PATHINFO_FILENAME),
                    'created_at' => $document['created_at'],
                    'reason' => $document['reason'],
                    'counts' => $document['counts'],
                ];
            } catch (ValidationException) {
                continue;
            }
        }

        return $backups;
    }

    public function contents(string $id): string
    {
        abort_unless(Str::isUuid($id), 404);
        $path = self::DIRECTORY.'/'.$id.'.json';
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->get($path);
    }

    /** @return array{counts: array<string, int>, safety_backup: string} */
    public function restore(string $json): array
    {
        $document = $this->decode($json);
        $schema = $this->statements->splitStatements(File::get(base_path('neo4j/schema.cypher')));

        if ($schema === []) {
            throw new RuntimeException('Tệp schema Neo4j rỗng.');
        }

        return $this->maintenance(function () use ($document, $schema): array {
            $safetyBackup = $this->save($this->snapshot(), 'before_restore');

            foreach ($schema as $statement) {
                $this->neo4j->run($statement);
            }

            $this->neo4j->transaction(function () use ($document): void {
                $this->neo4j->run('MATCH (n) DETACH DELETE n');
                $this->neo4j->run('UNWIND $persons AS person CREATE (p:Person) SET p = person', [
                    'persons' => $document['persons'],
                ]);
                $this->neo4j->run(
                    'UNWIND $relationships AS edge MATCH (a:Person {id: edge.from}), (b:Person {id: edge.to}) CREATE (a)-[r:PARENT_OF]->(b)',
                    ['relationships' => array_values(array_filter($document['relationships'], fn (array $edge): bool => $edge['type'] === 'PARENT_OF'))],
                );
                $this->neo4j->run(
                    'UNWIND $relationships AS edge MATCH (a:Person {id: edge.from}), (b:Person {id: edge.to}) CREATE (a)-[r:SPOUSE_OF]->(b) SET r.marriageYear = edge.marriageYear',
                    ['relationships' => array_values(array_map(
                        fn (array $edge): array => ['from' => $edge['from'], 'to' => $edge['to'], 'marriageYear' => $edge['properties']['marriageYear'] ?? null],
                        array_filter($document['relationships'], fn (array $edge): bool => $edge['type'] === 'SPOUSE_OF'),
                    ))],
                );

                $restored = $this->snapshot();

                if ($restored['persons'] !== $document['persons'] || $restored['relationships'] !== $document['relationships']) {
                    throw new RuntimeException('Dữ liệu sau khôi phục không khớp bản sao lưu.');
                }
            });

            return ['counts' => $document['counts'], 'safety_backup' => $safetyBackup];
        });
    }

    /** @return array<string, mixed> */
    public function decode(string $json): array
    {
        try {
            $document = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->reject('Tệp không phải JSON hợp lệ. Hãy chọn bản sao lưu gia phả đã tải từ trang Quản trị.');
        }

        if (! is_array($document) || ($document['format'] ?? null) !== 'neo4j-family-tree' || ($document['version'] ?? null) !== 1) {
            $this->reject('Tệp không đúng định dạng bản sao lưu gia phả phiên bản 1.');
        }

        $validator = Validator::make($document, [
            'created_at' => ['required', 'string', 'date'],
            'reason' => ['required', 'in:manual,before_restore'],
            'checksum' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'counts' => ['required', 'array:persons,parentOf,spouseOf'],
            'counts.persons' => ['required'],
            'counts.parentOf' => ['required'],
            'counts.spouseOf' => ['required'],
            'counts.*' => ['required', 'integer:strict', 'min:0'],
            'persons' => ['present', 'array', 'list', 'max:20000'],
            'persons.*' => ['required', 'array:id,name,gender,birthYear,deathYear,birthPlace,note'],
            'persons.*.id' => ['required', 'string', 'max:64', 'distinct:strict'],
            'persons.*.name' => ['required', 'string', 'max:100'],
            'persons.*.gender' => ['required', 'in:M,F'],
            'persons.*.birthYear' => ['required', 'integer:strict', 'between:1000,'.date('Y')],
            'persons.*.deathYear' => ['sometimes', 'integer:strict', 'between:1000,'.date('Y')],
            'persons.*.birthPlace' => ['sometimes', 'string', 'max:100'],
            'persons.*.note' => ['sometimes', 'string', 'max:500'],
            'relationships' => ['present', 'array', 'list', 'max:100000'],
            'relationships.*' => ['required', 'array:from,to,type,properties'],
            'relationships.*.from' => ['required', 'string', 'max:64'],
            'relationships.*.to' => ['required', 'string', 'max:64'],
            'relationships.*.type' => ['required', 'in:PARENT_OF,SPOUSE_OF'],
            'relationships.*.properties' => ['present', 'array:marriageYear'],
            'relationships.*.properties.marriageYear' => ['sometimes', 'integer:strict', 'between:1000,'.date('Y')],
        ]);

        if ($validator->fails()) {
            $this->reject('Bản sao lưu có cấu trúc, thuộc tính hoặc kiểu dữ liệu không hợp lệ. Chỉ nhận dữ liệu gia phả của ứng dụng.');
        }

        if (! hash_equals($document['checksum'], $this->checksum($document))) {
            $this->reject('Mã kiểm tra không khớp. Tệp sao lưu đã bị thay đổi hoặc hỏng.');
        }

        $people = array_column($document['persons'], null, 'id');
        $parentCounts = [];
        $parents = [];
        $seen = [];

        foreach ($people as $person) {
            if (isset($person['deathYear']) && $person['deathYear'] < $person['birthYear']) {
                $this->reject('Bản sao lưu có năm mất trước năm sinh.');
            }
        }

        foreach ($document['relationships'] as $edge) {
            if (! isset($people[$edge['from']], $people[$edge['to']]) || $edge['from'] === $edge['to']) {
                $this->reject('Bản sao lưu có quan hệ tới người không tồn tại hoặc quan hệ với chính mình.');
            }

            $pair = [$edge['from'], $edge['to']];
            if ($edge['type'] === 'SPOUSE_OF') {
                sort($pair, SORT_STRING);
            }
            $key = json_encode([$edge['type'], ...$pair], JSON_THROW_ON_ERROR);
            if (isset($seen[$key])) {
                $this->reject('Bản sao lưu có quan hệ bị trùng.');
            }
            $seen[$key] = true;

            if ($edge['type'] === 'PARENT_OF') {
                $parents[$edge['to']][] = $edge['from'];
                $parentCounts[$edge['to']] = ($parentCounts[$edge['to']] ?? 0) + 1;
                if ($edge['properties'] !== [] || $parentCounts[$edge['to']] > 2
                    || $people[$edge['from']]['birthYear'] >= $people[$edge['to']]['birthYear']) {
                    $this->reject('Quan hệ cha mẹ không hợp lệ: tối đa hai cha mẹ, cha mẹ phải sinh trước con và quan hệ không có thuộc tính.');
                }
            }
            try {
                if ($edge['type'] === 'PARENT_OF') {
                    FamilyRelationshipPolicy::validateParent($people[$edge['from']], $people[$edge['to']]);
                } else {
                    FamilyRelationshipPolicy::validateMarriageYear($people[$edge['from']], $people[$edge['to']], $edge['properties']['marriageYear'] ?? null);
                }
            } catch (ValidationException $exception) {
                $this->reject('Bản sao lưu: '.($exception->errors()['family'][0] ?? 'Quan hệ không hợp lệ.'));
            }
        }
        foreach ($document['relationships'] as $edge) {
            if ($edge['type'] === 'SPOUSE_OF') {
                try {
                    FamilyRelationshipPolicy::validateBloodMarriage($edge['from'], $edge['to'], $parents);
                } catch (ValidationException $exception) {
                    $this->reject('Bản sao lưu: '.FamilyRelationshipPolicy::BLOOD_MARRIAGE_MESSAGE);
                }
            }
        }

        if ($document['counts'] != $this->counts($document)) {
            $this->reject('Số lượng người hoặc quan hệ trong bản sao lưu không khớp.');
        }

        usort($document['persons'], fn (array $a, array $b): int => strcmp($a['id'], $b['id']));
        usort($document['relationships'], fn (array $a, array $b): int => [$a['type'], $a['from'], $a['to']] <=> [$b['type'], $b['from'], $b['to']]);
        foreach ($document['persons'] as &$person) {
            ksort($person);
        }
        unset($person);
        foreach ($document['relationships'] as &$edge) {
            ksort($edge['properties']);
            ksort($edge);
        }
        unset($edge);
        $document['counts'] = $this->counts($document);

        return $document;
    }

    /** @return array<string, mixed> */
    private function snapshot(): array
    {
        $unsupported = $this->neo4j->run('MATCH (n) WHERE NOT n:Person OR size(labels(n)) <> 1 RETURN count(n) AS total')[0]['total'];
        if ($unsupported !== 0) {
            $this->reject('Database chứa nhãn ngoài Person. Sao lưu/khôi phục trên web chỉ dùng cho dữ liệu gia phả của ứng dụng.');
        }

        $graph = $this->neo4j->run(
            'MATCH (p:Person) WITH p ORDER BY p.id WITH collect(properties(p)) AS persons CALL { MATCH (a)-[r]->(b) WITH a, r, b ORDER BY type(r), a.id, b.id RETURN collect({from: a.id, to: b.id, type: type(r), properties: properties(r)}) AS relationships } RETURN persons, relationships',
        )[0];
        $document = [
            'format' => 'neo4j-family-tree', 'version' => 1,
            'created_at' => now()->toIso8601String(), 'reason' => 'manual',
            ...$graph,
        ];
        $document['counts'] = $this->counts($document);
        $document['checksum'] = $this->checksum($document);

        return $this->decode(json_encode($document, JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $document */
    private function save(array $document, string $reason): string
    {
        $id = (string) Str::uuid();
        $document['created_at'] = now()->toIso8601String();
        $document['reason'] = $reason;
        $document['checksum'] = $this->checksum($document);
        $json = json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        if (strlen($json) > 10 * 1024 * 1024) {
            $this->reject('Bản sao lưu vượt quá giới hạn 10 MB của chức năng khôi phục trên web.');
        }
        if (! Storage::disk('local')->put(self::DIRECTORY.'/'.$id.'.json', $json)) {
            throw new RuntimeException('Không thể lưu bản sao dữ liệu.');
        }

        return $id;
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array{persons: int, parentOf: int, spouseOf: int}
     */
    private function counts(array $document): array
    {
        return [
            'persons' => count($document['persons']),
            'parentOf' => count(array_filter($document['relationships'], fn (array $edge): bool => $edge['type'] === 'PARENT_OF')),
            'spouseOf' => count(array_filter($document['relationships'], fn (array $edge): bool => $edge['type'] === 'SPOUSE_OF')),
        ];
    }

    /** @param array<string, mixed> $document */
    private function checksum(array $document): string
    {
        return hash('sha256', json_encode([$document['persons'], $document['relationships']], JSON_THROW_ON_ERROR));
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function maintenance(callable $callback): mixed
    {
        return $this->writeLock->run($callback);
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['backup' => $message]);
    }
}
