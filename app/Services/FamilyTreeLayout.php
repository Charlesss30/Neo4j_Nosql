<?php

namespace App\Services;

class FamilyTreeLayout
{
    public const NODE_WIDTH = 180;

    public const NODE_HEIGHT = 80;

    public const ROW_HEIGHT = 210;

    private const PARTNER_GAP = 36;

    private const BRANCH_GAP = 64;

    /**
     * Co-parents share a display block without implying a marriage. A primary
     * forest reserves branch space; the family lists retain every real parent.
     *
     * @param  array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}  $graph
     * @param  array<string, int>  $generations
     * @return array{positions: array<string, array{x: float, y: int, level: int, fixed: bool}>, families: list<array{parents: list<string>, children: list<string>, married: bool}>}
     */
    public function arrange(array $graph, array $generations, string $root): array
    {
        $people = array_column($graph['nodes'], null, 'id');
        if ($people === []) {
            return ['positions' => [], 'families' => []];
        }
        ksort($people);
        $groups = array_combine(array_keys($people), array_keys($people));
        $parents = [];
        $spouses = [];

        foreach ($graph['edges'] as $edge) {
            if ($edge['type'] === 'PARENT_OF') {
                $parents[$edge['to']][$edge['from']] = $edge['from'];
            } elseif ($edge['type'] === 'SPOUSE_OF') {
                $this->join($groups, $edge['from'], $edge['to']);
                $spouses[$edge['from']][$edge['to']] = $edge['marriageYear'] ?? PHP_INT_MAX;
                $spouses[$edge['to']][$edge['from']] = $edge['marriageYear'] ?? PHP_INT_MAX;
            }
        }

        foreach ($parents as $parentIds) {
            $first = reset($parentIds);
            foreach ($parentIds as $parentId) {
                $this->join($groups, $first, $parentId);
            }
        }

        $members = [];
        $knownLevels = [];
        foreach ($groups as $id => $group) {
            $members[$group][] = $id;
            if (isset($generations[$id])) {
                $knownLevels[$group] = min($knownLevels[$group] ?? $generations[$id], $generations[$id]);
            }
        }
        $incoming = [];
        foreach ($parents as $child => $parentIds) {
            foreach ($parentIds as $parentId) {
                if ($groups[$child] !== $groups[$parentId]) {
                    $incoming[$groups[$child]][$groups[$parentId]][$child] = $child;
                }
            }
        }

        $primaryParents = [];
        foreach ($members as $group => $ids) {
            $candidates = array_keys($incoming[$group] ?? []);
            usort($candidates, fn (string $a, string $b): int => [$knownLevels[$a] ?? PHP_INT_MAX, $a] <=> [$knownLevels[$b] ?? PHP_INT_MAX, $b]);
            foreach ($candidates as $candidate) {
                if (! $this->createsCycle($group, $candidate, $primaryParents)) {
                    $primaryParents[$group] = $candidate;
                    break;
                }
            }
        }

        $children = [];
        foreach ($primaryParents as $child => $parent) {
            $children[$parent][] = $child;
        }
        foreach ($children as $parent => &$childGroups) {
            usort($childGroups, function (string $a, string $b) use ($incoming, $parent, $people): int {
                return $this->earliest(array_values($incoming[$a][$parent]), $people) <=> $this->earliest(array_values($incoming[$b][$parent]), $people);
            });
        }
        unset($childGroups);

        $orderedMembers = [];
        foreach ($members as $group => $ids) {
            usort($ids, fn (string $a, string $b): int => [isset($parents[$a]) ? 0 : 1, ...$this->personOrder($people[$a])] <=> [isset($parents[$b]) ? 0 : 1, ...$this->personOrder($people[$b])]);
            $anchor = in_array($root, $ids, true) ? $root : $ids[0];
            $partners = array_values(array_diff($ids, [$anchor]));
            usort($partners, fn (string $a, string $b): int => [$spouses[$anchor][$a] ?? PHP_INT_MAX, ...$this->personOrder($people[$a])] <=> [$spouses[$anchor][$b] ?? PHP_INT_MAX, ...$this->personOrder($people[$b])]);
            $orderedMembers[$group] = count($partners) > 1 ? [array_shift($partners), $anchor, ...$partners] : [$anchor, ...$partners];
        }

        $widths = [];
        $measure = function (string $group) use (&$measure, &$widths, $orderedMembers, $children): float {
            $ownWidth = count($orderedMembers[$group]) * self::NODE_WIDTH + (count($orderedMembers[$group]) - 1) * self::PARTNER_GAP;
            $childGroups = $children[$group] ?? [];
            $childWidth = array_sum(array_map($measure, $childGroups)) + max(0, count($childGroups) - 1) * self::BRANCH_GAP;

            return $widths[$group] ??= (float) max($ownWidth, $childWidth);
        };
        $levels = [];
        $generation = function (string $group) use (&$generation, &$levels, $knownLevels, $primaryParents): int {
            return $levels[$group] ??= max($knownLevels[$group] ?? 0, isset($primaryParents[$group]) ? $generation($primaryParents[$group]) + 1 : 0);
        };
        $positions = [];
        $place = function (string $group, float $left) use (&$place, &$positions, $orderedMembers, $children, &$widths, $generation): void {
            $ids = $orderedMembers[$group];
            $ownWidth = count($ids) * self::NODE_WIDTH + (count($ids) - 1) * self::PARTNER_GAP;
            $start = $left + ($widths[$group] - $ownWidth) / 2 + self::NODE_WIDTH / 2;
            $level = $generation($group);
            foreach ($ids as $index => $id) {
                $positions[$id] = ['x' => $start + $index * (self::NODE_WIDTH + self::PARTNER_GAP), 'y' => $level * self::ROW_HEIGHT, 'level' => $level, 'fixed' => true];
            }
            $childGroups = $children[$group] ?? [];
            $childWidth = array_sum(array_map(fn (string $child): float => $widths[$child], $childGroups)) + max(0, count($childGroups) - 1) * self::BRANCH_GAP;
            $cursor = $left + ($widths[$group] - $childWidth) / 2;
            foreach ($childGroups as $child) {
                $place($child, $cursor);
                $cursor += $widths[$child] + self::BRANCH_GAP;
            }
        };

        $roots = array_values(array_diff(array_keys($members), array_keys($primaryParents)));
        usort($roots, fn (string $a, string $b): int => [$a === $groups[$root] ? 0 : 1, $this->earliest($members[$a], $people)] <=> [$b === $groups[$root] ? 0 : 1, $this->earliest($members[$b], $people)]);
        $cursor = 0.0;
        foreach ($roots as $group) {
            $measure($group);
            $place($group, $cursor);
            $cursor += $widths[$group] + self::BRANCH_GAP * 3;
        }
        ksort($positions);

        $families = [];
        foreach ($parents as $child => $parentIds) {
            $parentIds = array_values($parentIds);
            sort($parentIds);
            $key = implode('|', $parentIds);
            $families[$key] ??= ['parents' => $parentIds, 'children' => [], 'married' => count($parentIds) === 2 && isset($spouses[$parentIds[0]][$parentIds[1]])];
            $families[$key]['children'][] = $child;
        }
        ksort($families);
        foreach ($families as &$family) {
            usort($family['children'], fn (string $a, string $b): int => $this->personOrder($people[$a]) <=> $this->personOrder($people[$b]));
        }
        unset($family);

        return ['positions' => $positions, 'families' => array_values($families)];
    }

    /** @param array<string, string> $groups */
    private function join(array &$groups, string $first, string $second): void
    {
        $old = max($groups[$first], $groups[$second]);
        $new = min($groups[$first], $groups[$second]);
        foreach ($groups as $id => $group) {
            if ($group === $old) {
                $groups[$id] = $new;
            }
        }
    }

    /** @param array<string, string> $parents */
    private function createsCycle(string $child, string $parent, array $parents): bool
    {
        while (true) {
            if ($child === $parent) {
                return true;
            }
            if (! isset($parents[$parent])) {
                return false;
            }
            $parent = $parents[$parent];
        }
    }

    /**
     * @param  list<string>  $ids
     * @param  array<string, array<string, mixed>>  $people
     * @return array{int, string}
     */
    private function earliest(array $ids, array $people): array
    {
        $orders = array_map(fn (string $id): array => $this->personOrder($people[$id]), $ids);
        sort($orders);

        return $orders[0];
    }

    /**
     * @param  array<string, mixed>  $person
     * @return array{int, string}
     */
    private function personOrder(array $person): array
    {
        return [$person['birthYear'] ?? PHP_INT_MAX, $person['id']];
    }
}
