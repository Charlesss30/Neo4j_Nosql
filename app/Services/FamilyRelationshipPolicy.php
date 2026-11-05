<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class FamilyRelationshipPolicy
{
    public const BLOOD_MARRIAGE_MESSAGE = 'Không thể tạo quan hệ vợ chồng giữa tổ tiên–hậu duệ hoặc anh chị em có chung cha/mẹ.';

    /**
     * @param  array{birthYear?: int, deathYear?: ?int, gender?: string}  $parent
     * @param  array{birthYear?: int}  $child
     */
    public static function validateParent(array $parent, array $child): void
    {
        if (isset($parent['birthYear'], $child['birthYear']) && $parent['birthYear'] >= $child['birthYear']) {
            self::reject('Cha mẹ phải sinh trước con.');
        }
        if (isset($parent['deathYear'], $child['birthYear'])) {
            $tolerance = ($parent['gender'] ?? null) === 'M' ? 1 : 0;
            if ($child['birthYear'] > $parent['deathYear'] + $tolerance) {
                self::reject('Năm sinh con không phù hợp năm mất của cha/mẹ: mẹ cho phép đến năm mất, cha đến năm mất cộng một năm.');
            }
        }
    }

    /**
     * @param  array{birthYear?: int, deathYear?: ?int}  $first
     * @param  array{birthYear?: int, deathYear?: ?int}  $second
     */
    public static function validateMarriageYear(array $first, array $second, ?int $year): void
    {
        if ($year === null) {
            return;
        }
        if ($year > (int) date('Y') || $year < max($first['birthYear'] ?? 0, $second['birthYear'] ?? 0)) {
            self::reject('Năm kết hôn phải từ năm sinh của cả hai người đến năm hiện tại.');
        }
        if ((isset($first['deathYear']) && $year > $first['deathYear'])
            || (isset($second['deathYear']) && $year > $second['deathYear'])) {
            self::reject('Năm kết hôn không được sau năm mất của vợ hoặc chồng.');
        }
    }

    /** @param array<string, list<string>> $parents */
    public static function validateBloodMarriage(string $first, string $second, array $parents): void
    {
        if (array_intersect($parents[$first] ?? [], $parents[$second] ?? []) !== []
            || self::hasAncestor($first, $second, $parents) || self::hasAncestor($second, $first, $parents)) {
            self::reject(self::BLOOD_MARRIAGE_MESSAGE);
        }
    }

    /** @param array<string, list<string>> $parents */
    private static function hasAncestor(string $person, string $ancestor, array $parents): bool
    {
        $pending = $parents[$person] ?? [];
        $seen = [];
        while ($pending !== []) {
            $id = array_pop($pending);
            if ($id === $ancestor) {
                return true;
            }
            if (! isset($seen[$id])) {
                $seen[$id] = true;
                array_push($pending, ...($parents[$id] ?? []));
            }
        }

        return false;
    }

    private static function reject(string $message): never
    {
        throw ValidationException::withMessages(['family' => $message]);
    }
}
