<?php

namespace Tests\Feature;

use App\Services\Neo4jService;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $neo4j = $this->createMock(Neo4jService::class);
        $neo4j->method('ping')->willReturn(false);
        $this->app->instance(Neo4jService::class, $neo4j);
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
