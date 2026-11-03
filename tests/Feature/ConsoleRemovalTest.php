<?php

namespace Tests\Feature;

use App\Services\Neo4jService;
use Tests\TestCase;

class ConsoleRemovalTest extends TestCase
{
    public function test_console_routes_are_removed_and_navigation_no_longer_links_to_them(): void
    {
        $neo4j = $this->createMock(Neo4jService::class);
        $neo4j->method('ping')->willReturn(false);
        $this->app->instance(Neo4jService::class, $neo4j);

        $this->get('/')->assertOk()->assertDontSee('Cypher Console');
        $this->get('/console')->assertNotFound();
        $this->post('/console/run', ['query' => 'RETURN 1'])->assertNotFound();
        $this->assertFalse(app('router')->has('console.index'));
        $this->assertTrue(app('router')->has('admin.index'));
    }
}
