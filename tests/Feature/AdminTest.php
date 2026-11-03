<?php

namespace Tests\Feature;

use App\Services\Neo4jService;
use Tests\TestCase;

class AdminTest extends TestCase
{
    public function test_maintenance_actions_are_forbidden_outside_local(): void
    {
        $neo4j = $this->createMock(Neo4jService::class);
        $neo4j->expects($this->never())->method('run');
        $this->app->instance(Neo4jService::class, $neo4j);
        $this->post('/admin/reset', ['confirm_reset' => 'yes'])->assertForbidden();
        $this->post('/admin/profile', ['sample' => 'nameIndex', 'name' => 'Nguyễn Minh Khang'])->assertForbidden();
    }

    public function test_admin_reports_connection_errors_without_breaking_the_page(): void
    {
        $neo4j = $this->createMock(Neo4jService::class);
        $neo4j->method('run')->willThrowException(new \RuntimeException('Connection refused'));
        $this->app->instance(Neo4jService::class, $neo4j);
        $this->get('/admin')->assertOk()->assertSee('Không đọc được thông tin quản trị.')
            ->assertSee('Không thể kết nối Neo4j.');
    }

    public function test_index_maintenance_restores_the_index_even_when_the_comparison_fails(): void
    {
        $this->app->detectEnvironment(fn (): string => 'local');
        $calls = [];
        $neo4j = $this->createMock(Neo4jService::class);
        $neo4j->expects($this->never())->method('client');
        $neo4j->method('run')->willReturnCallback(function (string $query, array $params = []) use (&$calls): array {
            $calls[] = $query;

            if ($query === 'DROP INDEX person_name IF EXISTS') {
                throw new \RuntimeException('Injected maintenance failure');
            }

            return in_array($query, [
                'MATCH (n) RETURN count(n) AS total',
                'MATCH ()-[r]->() RETURN count(r) AS total',
            ], true) ? [['total' => 0]] : [];
        });
        $this->app->instance(Neo4jService::class, $neo4j);
        $this->withSession(['_token' => 'maintenance-test-token'])->post('/admin/profile', [
            '_token' => 'maintenance-test-token', 'sample' => 'nameIndex', 'name' => 'Nguyễn Minh Khang',
        ])
            ->assertOk()->assertSee('Không thể phân tích truy vấn.');
        $this->assertSame([
            'DROP INDEX person_name IF EXISTS',
            'CREATE INDEX person_name IF NOT EXISTS FOR (p:Person) ON (p.name)',
            'CALL db.awaitIndexes(30)',
        ], array_slice($calls, 0, 3));
    }

    public function test_local_reset_still_requires_explicit_confirmation(): void
    {
        $this->app->detectEnvironment(fn (): string => 'local');
        $neo4j = $this->createMock(Neo4jService::class);
        $neo4j->expects($this->never())->method('run');
        $neo4j->expects($this->never())->method('client');
        $this->app->instance(Neo4jService::class, $neo4j);
        $this->withSession(['_token' => 'reset-test-token'])->post('/admin/reset', ['_token' => 'reset-test-token'])
            ->assertRedirect()->assertSessionHasErrors('confirm_reset');
    }
}
