<?php

namespace Tests\Unit;

use App\Services\FamilyGraphService;
use App\Services\FamilyRepository;
use App\Services\FamilyService;
use PHPUnit\Framework\TestCase;

class FamilyGraphServiceTest extends TestCase
{
    public function test_empty_database_has_an_empty_tree(): void
    {
        $family = $this->createMock(FamilyRepository::class);
        $family->method('getPerson')->with('P001')->willReturn([]);
        $family->method('roots')->willReturn([]);
        $service = $this->createMock(FamilyService::class);
        $service->expects($this->never())->method('person');

        $this->assertSame(['root' => null, 'nodes' => [], 'edges' => []], (new FamilyGraphService($family, $service))->tree(null));
    }

    public function test_first_root_is_used_without_p001_and_an_empty_subtree_row_is_safe(): void
    {
        $family = $this->createMock(FamilyRepository::class);
        $family->method('getPerson')->willReturn([]);
        $family->method('roots')->willReturn([['person' => ['id' => 'T_001']]]);
        $family->method('subtree')->with('T_001')->willReturn([]);
        $family->method('generations')->with('T_001')->willReturn([]);
        $service = $this->createMock(FamilyService::class);
        $service->expects($this->once())->method('person')->with('T_001')->willReturn(['id' => 'T_001']);

        $this->assertSame(['root' => 'T_001', 'nodes' => [], 'edges' => []], (new FamilyGraphService($family, $service))->tree(null));
    }

    public function test_sentence_uses_stored_parent_direction_and_loads_gender_for_every_path_node(): void
    {
        $people = [
            'A' => ['id' => 'A', 'name' => 'An', 'gender' => 'F'],
            'B' => ['id' => 'B', 'name' => 'Bình', 'gender' => 'M'],
            'C' => ['id' => 'C', 'name' => 'Chi', 'gender' => 'F'],
            'D' => ['id' => 'D', 'name' => 'Dũng', 'gender' => 'M'],
        ];
        $family = $this->createMock(FamilyRepository::class);
        $family->method('relationPath')->willReturn([[
            'nodes' => array_map(fn (array $person): array => array_intersect_key($person, array_flip(['id', 'name'])), array_values($people)),
            'relTypes' => ['PARENT_OF', 'PARENT_OF', 'SPOUSE_OF'], 'relFrom' => ['B', 'B', 'D'],
        ]]);
        $service = $this->createMock(FamilyService::class);
        $service->method('person')->willReturnCallback(fn (string $id): array => $people[$id]);
        $graph = (new FamilyGraphService($family, $service))->relation('A', 'D');

        $this->assertSame('An là con gái của Bình, Bình là cha của Chi, Chi là vợ của Dũng.', $graph['sentence']);
        $this->assertSame('B', $graph['edges'][0]['from']);
        $this->assertSame('A', $graph['edges'][0]['to']);
        $this->assertSame('D', $graph['edges'][2]['from']);
        $this->assertTrue($graph['edges'][2]['dashes']);
    }
}
