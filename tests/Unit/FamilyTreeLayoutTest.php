<?php

namespace Tests\Unit;

use App\Services\FamilyTreeLayout;
use PHPUnit\Framework\TestCase;

class FamilyTreeLayoutTest extends TestCase
{
    public function test_seed_siblings_are_ordered_by_birth_with_each_branch_kept_together(): void
    {
        $diagram = (new FamilyTreeLayout)->arrange($this->seed(), [], 'P001');
        $positions = $diagram['positions'];

        foreach ([['P003', 'P004', 'P005'], ['P010', 'P011'], ['P012', 'P013'], ['P014', 'P015', 'P016'], ['P021', 'P022'], ['P028', 'P029']] as $siblings) {
            $x = array_map(fn (string $id): float => $positions[$id]['x'], $siblings);
            $sorted = $x;
            sort($sorted);
            $this->assertSame($sorted, $x);
            $this->assertCount(1, array_unique(array_map(fn (string $id): int => $positions[$id]['level'], $siblings)));
        }
        $this->assertLessThan($positions['P004']['x'], $positions['P010']['x']);
        $this->assertGreaterThan($positions['P004']['x'], $positions['P014']['x']);
        $this->assertSame(4, $positions['P028']['level']);
    }

    public function test_two_marriages_keep_the_correct_mothers_and_children_separate(): void
    {
        $diagram = (new FamilyTreeLayout)->arrange($this->seed(), [], 'P001');
        $positions = $diagram['positions'];
        $families = [];
        foreach ($diagram['families'] as $family) {
            $families[implode('|', $family['parents'])] = $family;
        }

        $this->assertSame(['P014', 'P015'], $families['P005|P008']['children']);
        $this->assertSame(['P016'], $families['P005|P009']['children']);
        $this->assertTrue($families['P005|P008']['married']);
        $this->assertLessThan($positions['P005']['x'], $positions['P008']['x']);
        $this->assertGreaterThan($positions['P005']['x'], $positions['P009']['x']);
        $this->assertSame($positions['P005']['y'], $positions['P008']['y']);
        $this->assertSame($positions['P005']['y'], $positions['P009']['y']);
    }

    public function test_every_person_has_a_unique_slot_and_every_parent_edge_is_preserved(): void
    {
        $graph = $this->seed();
        $diagram = (new FamilyTreeLayout)->arrange($graph, [], 'P001');
        $this->assertCount(30, $diagram['positions']);
        $rows = [];
        foreach ($diagram['positions'] as $position) {
            $rows[$position['level']][] = $position['x'];
            $this->assertTrue($position['fixed']);
        }
        foreach ($rows as $x) {
            sort($x);
            for ($index = 1; $index < count($x); $index++) {
                $this->assertGreaterThanOrEqual(FamilyTreeLayout::NODE_WIDTH, $x[$index] - $x[$index - 1]);
            }
        }
        $actual = [];
        foreach ($diagram['families'] as $family) {
            foreach ($family['parents'] as $parent) {
                foreach ($family['children'] as $child) {
                    $actual[] = $parent.'>'.$child;
                    $this->assertLessThan($diagram['positions'][$child]['y'], $diagram['positions'][$parent]['y']);
                }
            }
        }
        $expected = array_map(fn (array $edge): string => $edge['from'].'>'.$edge['to'], array_filter($graph['edges'], fn (array $edge): bool => $edge['type'] === 'PARENT_OF'));
        sort($actual);
        sort($expected);
        $this->assertSame($expected, $actual);
    }

    public function test_layout_is_deterministic_even_if_query_row_and_edge_order_changes(): void
    {
        $graph = $this->seed();
        $layout = new FamilyTreeLayout;
        $expected = $layout->arrange($graph, [], 'P001');
        $graph['nodes'] = array_reverse($graph['nodes']);
        $graph['edges'] = array_reverse($graph['edges']);

        $this->assertSame($expected, $layout->arrange($graph, [], 'P001'));
    }

    public function test_showing_all_people_from_a_descendant_keeps_ancestors_above_it(): void
    {
        $diagram = (new FamilyTreeLayout)->arrange($this->seed(), ['P010' => 0, 'P017' => 0, 'P021' => 1, 'P022' => 1, 'P026' => 1, 'P028' => 2, 'P029' => 2], 'P010');

        $this->assertSame(0, $diagram['positions']['P001']['level']);
        $this->assertSame(2, $diagram['positions']['P010']['level']);
        $this->assertSame(4, $diagram['positions']['P028']['level']);
        $this->assertCount(30, $diagram['positions']);
    }

    public function test_single_parents_coparents_without_marriage_and_unrelated_roots_are_supported(): void
    {
        $graph = ['nodes' => [
            ['id' => 'A', 'birthYear' => 1960], ['id' => 'B', 'birthYear' => 1965],
            ['id' => 'C', 'birthYear' => 1990], ['id' => 'D', 'birthYear' => 2000], ['id' => 'E'],
        ], 'edges' => [
            ['from' => 'A', 'to' => 'C', 'type' => 'PARENT_OF'],
            ['from' => 'B', 'to' => 'C', 'type' => 'PARENT_OF'],
            ['from' => 'C', 'to' => 'D', 'type' => 'PARENT_OF'],
        ]];
        $diagram = (new FamilyTreeLayout)->arrange($graph, [], 'A');
        $this->assertCount(5, $diagram['positions']);
        $this->assertFalse($diagram['families'][0]['married']);
        $this->assertSame(['A', 'B'], $diagram['families'][0]['parents']);
        $this->assertSame(['C'], $diagram['families'][1]['parents']);
        $this->assertSame(2, $diagram['positions']['D']['level']);
        $this->assertGreaterThan($diagram['positions']['D']['x'], $diagram['positions']['E']['x']);
        $this->assertSame(['positions' => [], 'families' => []], (new FamilyTreeLayout)->arrange(['nodes' => [], 'edges' => []], [], 'A'));
    }

    public function test_marriage_between_branches_keeps_both_parent_sets_without_duplicating_people(): void
    {
        $graph = ['nodes' => array_map(fn (string $id): array => ['id' => $id], ['A', 'B', 'C', 'D', 'E', 'F']), 'edges' => [
            ['from' => 'A', 'to' => 'C', 'type' => 'PARENT_OF'], ['from' => 'B', 'to' => 'C', 'type' => 'PARENT_OF'],
            ['from' => 'D', 'to' => 'F', 'type' => 'PARENT_OF'], ['from' => 'E', 'to' => 'F', 'type' => 'PARENT_OF'],
            ['from' => 'C', 'to' => 'F', 'type' => 'SPOUSE_OF'],
        ]];
        $diagram = (new FamilyTreeLayout)->arrange($graph, [], 'A');
        $this->assertCount(6, $diagram['positions']);
        $this->assertSame(['A', 'B'], $diagram['families'][0]['parents']);
        $this->assertSame(['D', 'E'], $diagram['families'][1]['parents']);
        $this->assertSame($diagram['positions']['C']['y'], $diagram['positions']['F']['y']);
    }

    /** @return array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>} */
    private function seed(): array
    {
        $people = $this->csv('persons.csv');
        foreach ($people as &$person) {
            $person['birthYear'] = (int) $person['birthYear'];
        }
        unset($person);

        return ['nodes' => $people, 'edges' => $this->csv('relationships.csv')];
    }

    /** @return list<array<string, string>> */
    private function csv(string $filename): array
    {
        $stream = fopen(dirname(__DIR__, 2).'/neo4j/import/'.$filename, 'r');
        $headers = fgetcsv($stream, escape: '\\');
        $rows = [];
        while (($row = fgetcsv($stream, escape: '\\')) !== false) {
            $rows[] = array_combine($headers, $row);
        }
        fclose($stream);

        return $rows;
    }
}
