<?php

namespace App\Services;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class FamilyService
{
    public function __construct(
        private readonly FamilyRepository $family,
        private readonly Neo4jService $neo4j,
    ) {}

    /** @return array<string, mixed> */
    public function person(string $id): array
    {
        $person = $this->family->getPerson($id)[0]['person'] ?? null;
        abort_if($person === null, 404, 'Không tìm thấy thành viên.');

        return $person;
    }

    /** @return array<string, mixed> */
    public function details(string $id): array
    {
        $person = $this->person($id);
        $sections = [];

        foreach (['parents', 'spouses', 'children', 'siblings', 'uncles', 'cousins', 'ancestors', 'descendants'] as $method) {
            $sections[$method] = $this->family->{$method}($id);
        }

        return compact('person', 'sections');
    }

    /**
     * @param  array{name: string, gender: string, birthYear: int, deathYear: ?int, birthPlace: ?string, note: ?string}  $data
     * @return array<string, mixed>
     */
    public function createPerson(array $data): array
    {
        return $this->write(function () use ($data): array {
            $number = (int) ($this->family->maxPersonNumber()[0]['maxNum'] ?? 0) + 1;

            return $this->family->createPerson(sprintf('P%03d', $number), ...$data)[0]['person'];
        });
    }

    /**
     * @param  array{name: string, gender: string, birthYear: int, deathYear: ?int, birthPlace: ?string, note: ?string}  $data
     * @return array<string, mixed>
     */
    public function updatePerson(string $id, array $data): array
    {
        return $this->write(function () use ($id, $data): array {
            $this->person($id);

            foreach ($this->family->parents($id) as $row) {
                $this->validateBirthOrder($row['person'], $data);
            }

            foreach ($this->family->children($id) as $row) {
                $this->validateBirthOrder($data, $row['person']);
            }

            foreach ($this->family->spouses($id) as $row) {
                $this->validateMarriageYear($data, $row['person'], $row['marriageYear']);
            }

            return $this->family->updatePerson($id, ...$data)[0]['person'];
        });
    }

    public function deletePerson(string $id): void
    {
        $this->write(function () use ($id): void {
            $this->person($id);
            $this->family->deletePerson($id);
        });
    }

    /**
     * @param  array<string, mixed>  $parent
     * @param  array<string, mixed>  $child
     */
    private function validateBirthOrder(array $parent, array $child): void
    {
        if (isset($parent['birthYear'], $child['birthYear']) && $parent['birthYear'] >= $child['birthYear']) {
            $this->reject('Cha mẹ phải sinh trước con.');
        }
    }

    /**
     * @param  array<string, mixed>  $first
     * @param  array<string, mixed>  $second
     */
    private function validateMarriageYear(array $first, array $second, ?int $year): void
    {
        if ($year !== null && ($year > (int) date('Y') || $year < max($first['birthYear'] ?? 0, $second['birthYear'] ?? 0))) {
            $this->reject('Năm kết hôn phải từ năm sinh của cả hai người đến năm hiện tại.');
        }
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['family' => $message]);
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function write(callable $callback): mixed
    {
        try {
            return Cache::store('file')->lock('family-write', 30)->block(5, fn (): mixed => $this->neo4j->transaction($callback));
        } catch (LockTimeoutException) {
            $this->reject('Gia phả đang được cập nhật. Vui lòng thử lại sau.');
        }
    }
}
