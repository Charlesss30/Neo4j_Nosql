<?php

namespace Tests\Feature;

use App\Exceptions\Neo4jQueryException;
use App\Services\FamilyRepository;
use App\Services\Neo4jService;
use Laudis\Neo4j\Contracts\ClientInterface;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    public function test_dashboard_displays_connected_statistics_and_escapes_person_names(): void
    {
        $neo4j = $this->createMock(Neo4jService::class);
        $neo4j->method('ping')->willReturn(true);
        $this->app->instance(Neo4jService::class, $neo4j);
        $family = $this->createMock(FamilyRepository::class);
        $family->method('statsByGender')->willReturn([['gender' => 'M', 'total' => 16], ['gender' => 'F', 'total' => 14]]);
        $family->method('livingVsDeceased')->willReturn([['living' => 25, 'deceased' => 5]]);
        $family->method('topParents')->willReturn([['id' => 'P001', 'name' => '<script>alert(1)</script>', 'childCount' => 4]]);
        $family->method('oldestPerson')->willReturn([['person' => ['id' => 'P001', 'name' => 'Nguyễn An', 'birthYear' => 1920]]]);
        $this->app->instance(FamilyRepository::class, $family);

        $this->get('/')->assertOk()->assertSee('Đã kết nối')->assertSee('30')
            ->assertSee('Nguyễn An')->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_dashboard_stays_available_when_neo4j_is_down_without_querying_statistics(): void
    {
        $neo4j = $this->createMock(Neo4jService::class);
        $neo4j->method('ping')->willReturn(false);
        $neo4j->method('lastError')->willReturn('Không thể kết nối Neo4j.');
        $this->app->instance(Neo4jService::class, $neo4j);
        $family = $this->createMock(FamilyRepository::class);
        $family->expects($this->never())->method('statsByGender');
        $this->app->instance(FamilyRepository::class, $family);

        $this->get('/')->assertOk()->assertSee('Chưa kết nối')->assertSee('Không thể kết nối Neo4j.')
            ->assertSee('Thống kê chưa sẵn sàng.')->assertSee('—');
    }

    public function test_query_failure_keeps_the_connection_status_and_hides_incomplete_statistics(): void
    {
        $neo4j = $this->createMock(Neo4jService::class);
        $neo4j->method('ping')->willReturn(true);
        $this->app->instance(Neo4jService::class, $neo4j);
        $family = $this->createMock(FamilyRepository::class);
        $family->method('statsByGender')->willThrowException(new Neo4jQueryException('Không thể thực hiện truy vấn Neo4j.'));
        $this->app->instance(FamilyRepository::class, $family);

        $this->get('/')->assertOk()->assertSee('Đã kết nối')->assertSee('Không thể thực hiện truy vấn Neo4j.')
            ->assertSee('Thống kê chưa sẵn sàng.');
    }

    public function test_future_routes_explain_their_status_and_never_pretend_to_write(): void
    {
        foreach (['/relationships', '/tree', '/relation'] as $path) {
            $this->get($path)->assertOk()->assertSee('Milestone 2');
        }

        $this->getJson('/api/tree')->assertStatus(501)->assertJsonStructure(['message']);
        $this->delete('/relationships/spouse')->assertStatus(501);
    }

    public function test_connection_service_is_a_singleton_and_infrastructure_uses_no_sql_database(): void
    {
        $this->assertSame($this->app->make(Neo4jService::class), $this->app->make(Neo4jService::class));
        $this->assertInstanceOf(ClientInterface::class, $this->app->make(Neo4jService::class)->client());
        $this->assertSame('sync', config('queue.default'));
        $this->assertSame('neo4j', config('neo4j.database'));
    }
}
