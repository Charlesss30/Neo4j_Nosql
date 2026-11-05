<?php

namespace Tests\Feature;

use App\Services\FamilyRepository;
use App\Services\Neo4jService;
use Tests\TestCase;

class DashboardRootTest extends TestCase
{
    public function test_requested_root_controls_the_generation_query_and_label(): void
    {
        $family = $this->repository(['Q001', 'Q002']);
        $family->expects($this->once())->method('statsByGeneration')->with('Q002')->willReturn([['generation' => 1, 'total' => 1]]);
        $this->get('/?root=Q002')->assertOk()->assertViewHas('rootPerson', fn (array $person): bool => $person['id'] === 'Q002')
            ->assertSee('Nhánh Q002')->assertSee('không nhất thiết bằng tổng toàn hệ thống');
    }

    public function test_default_root_falls_back_when_p001_does_not_exist(): void
    {
        $family = $this->repository(['Q001', 'Q002']);
        $family->expects($this->once())->method('statsByGeneration')->with('Q001')->willReturn([['generation' => 1, 'total' => 2]]);
        $this->get('/')->assertOk()->assertViewHas('rootPerson', fn (array $person): bool => $person['id'] === 'Q001')
            ->assertSee('Nhánh Q001')->assertDontSee('Tính từ P001');
    }

    public function test_default_root_prefers_p001_when_it_exists(): void
    {
        $family = $this->repository(['Q001', 'P001']);
        $family->expects($this->once())->method('statsByGeneration')->with('P001')->willReturn([]);
        $this->get('/')->assertOk()->assertViewHas('rootPerson', fn (array $person): bool => $person['id'] === 'P001');
    }

    public function test_missing_requested_root_returns_404_without_switching_to_another_root(): void
    {
        $family = $this->repository(['Q001']);
        $family->expects($this->never())->method('statsByGeneration');
        $this->get('/?root=MISSING')->assertNotFound();
    }

    public function test_empty_database_has_an_empty_selection_and_no_generation_query(): void
    {
        $family = $this->repository([]);
        $family->expects($this->never())->method('statsByGeneration');
        $this->get('/')->assertOk()->assertViewHas('rootPerson', null)->assertViewHas('generations', [])
            ->assertSee('Chưa có thành viên để chọn gốc')->assertSee('Chưa có gốc thống kê.');
    }

    public function test_root_parameter_must_be_a_scalar_member_id(): void
    {
        $family = $this->repository([]);
        $family->expects($this->never())->method('statsByGeneration');
        $this->get('/?root[]=P001')->assertSessionHasErrors('root');
    }

    /** @param list<string> $ids */
    private function repository(array $ids): FamilyRepository
    {
        $neo4j = $this->createMock(Neo4jService::class);
        $neo4j->method('ping')->willReturn(true);
        $this->app->instance(Neo4jService::class, $neo4j);
        $people = array_map(fn (string $id): array => ['id' => $id, 'name' => 'Người '.$id, 'gender' => 'M', 'birthYear' => 1980], $ids);
        $byId = array_column($people, null, 'id');
        $family = $this->createMock(FamilyRepository::class);
        $family->method('getPerson')->willReturnCallback(fn (string $id): array => isset($byId[$id]) ? [['person' => $byId[$id]]] : []);
        $family->method('roots')->willReturn(array_map(fn (array $person): array => ['person' => $person], $people));
        $family->method('countPersons')->willReturn([['total' => count($people)]]);
        $family->method('searchPersons')->willReturn(array_map(fn (array $person): array => ['person' => $person], $people));
        $family->method('livingVsDeceased')->willReturn([['living' => count($people), 'deceased' => 0]]);
        $this->app->instance(FamilyRepository::class, $family);

        return $family;
    }
}
