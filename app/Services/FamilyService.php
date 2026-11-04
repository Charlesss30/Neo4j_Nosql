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
     * @param  array{relative_id: string, relation_type: string, second_parent_id?: ?string, marriageYear?: ?int}|null  $relationship
     * @return array<string, mixed>
     */
    public function createPerson(array $data, ?array $relationship = null): array
    {
        return $this->write(function () use ($data, $relationship): array {
            if ($relationship !== null) {
                $this->relationshipPeople($relationship['relative_id'], $relationship['relative_id']);
            }
            $number = (int) ($this->family->maxPersonNumber()[0]['maxNum'] ?? 0) + 1;
            $person = $this->family->createPerson(sprintf('P%03d', $number), ...$data)[0]['person'];

            if ($relationship !== null) {
                $relative = $relationship['relative_id'];
                switch ($relationship['relation_type']) {
                    case 'child':
                        $this->connectParent($relative, $person['id']);
                        if (! empty($relationship['second_parent_id'])) {
                            $this->connectParent($relationship['second_parent_id'], $person['id']);
                        }
                        break;
                    case 'parent':
                        $this->connectParent($person['id'], $relative);
                        break;
                    case 'spouse':
                        $this->connectSpouse($relative, $person['id'], $relationship['marriageYear'] ?? null);
                        break;
                    default:
                        $this->reject('Loại quan hệ không hợp lệ.');
                }
            }

            return $person;
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

    /** @return list<array<string, mixed>> */
    public function choices(): array
    {
        $total = (int) ($this->family->countPersons('')[0]['total'] ?? 0);

        return $this->family->searchPersons('', 0, max(1, $total));
    }

    public function addParent(string $parentId, string $childId): void
    {
        $this->write(function () use ($parentId, $childId): void {
            $this->connectParent($parentId, $childId);
        });
    }

    private function connectParent(string $parentId, string $childId): void
    {
        [$parent, $child] = $this->relationshipPeople($parentId, $childId);
        if ($parentId === $childId) {
            $this->reject('Một người không thể là cha mẹ của chính mình.');
        }
        if ($this->family->parentExists($parentId, $childId)[0]['exists']) {
            $this->reject('Quan hệ cha mẹ – con đã tồn tại.');
        }
        if ($this->family->parentCount($childId)[0]['total'] >= 2) {
            $this->reject('Một người chỉ được có tối đa hai cha mẹ.');
        }
        if ($this->family->wouldCreateCycle($parentId, $childId)[0]['exists']) {
            $this->reject('Quan hệ này tạo vòng lặp trong gia phả.');
        }
        $this->validateBirthOrder($parent, $child);
        $this->family->addParentOf($parentId, $childId);
    }

    public function removeParent(string $parentId, string $childId): void
    {
        $this->write(function () use ($parentId, $childId): void {
            $this->relationshipPeople($parentId, $childId);
            if (! $this->family->parentExists($parentId, $childId)[0]['exists']) {
                $this->reject('Không tìm thấy quan hệ cha mẹ – con để xóa.');
            }
            $this->family->removeParentOf($parentId, $childId);
        });
    }

    public function addSpouse(string $a, string $b, ?int $marriageYear): void
    {
        $this->write(function () use ($a, $b, $marriageYear): void {
            $this->connectSpouse($a, $b, $marriageYear);
        });
    }

    private function connectSpouse(string $a, string $b, ?int $marriageYear): void
    {
        [$first, $second] = $this->relationshipPeople($a, $b);
        if ($a === $b) {
            $this->reject('Một người không thể kết hôn với chính mình.');
        }
        if ($this->family->spouseExists($a, $b)[0]['exists']) {
            $this->reject('Quan hệ vợ chồng đã tồn tại, kể cả theo chiều ngược lại.');
        }
        $this->validateMarriageYear($first, $second, $marriageYear);
        $this->family->addSpouseOf($a, $b, $marriageYear);
    }

    public function removeSpouse(string $a, string $b): void
    {
        $this->write(function () use ($a, $b): void {
            $this->relationshipPeople($a, $b);
            if (! $this->family->spouseExists($a, $b)[0]['exists']) {
                $this->reject('Không tìm thấy quan hệ vợ chồng để xóa.');
            }
            $this->family->removeSpouseOf($a, $b);
        });
    }

    /** @return array{array<string, mixed>, array<string, mixed>} */
    private function relationshipPeople(string $firstId, string $secondId): array
    {
        $first = $this->family->getPerson($firstId)[0]['person'] ?? null;
        $second = $this->family->getPerson($secondId)[0]['person'] ?? null;
        if ($first === null || $second === null) {
            $this->reject('Cả hai thành viên phải tồn tại trong gia phả.');
        }

        return [$first, $second];
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
