<?php

namespace Tests\Feature;

use App\Exceptions\Neo4jQueryException;
use App\Services\Neo4jService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\Neo4jTestCase;

class DatabaseSeederTest extends Neo4jTestCase
{
    public function test_db_seed_is_idempotent_and_preserves_other_people_without_a_sql_connection(): void
    {
        config(['database.default' => 'unconfigured-sql']);
        $this->createTestPerson('T_SEED_PARENT', 1980);
        $this->createTestPerson('T_SEED_CHILD', 2010);
        $this->family->addParentOf('T_SEED_PARENT', 'T_SEED_CHILD');
        $originalGraph = $this->family->graphAll()[0];

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->artisan('db:seed')
                ->expectsOutputToContain('Đã nạp schema và dữ liệu mẫu vào Neo4j.')
                ->assertSuccessful();

            $this->assertSame($originalGraph, $this->family->graphAll()[0]);
        }

        $this->assertSame([], $this->app->make('db')->getConnections());
    }

    public function test_seed_writes_are_rolled_back_when_a_later_statement_fails(): void
    {
        File::partialMock();
        File::shouldReceive('get')->once()->with(base_path('neo4j/schema.cypher'))
            ->andReturn(file_get_contents(base_path('neo4j/schema.cypher')));
        File::shouldReceive('get')->once()->with(base_path('neo4j/seed.cypher'))
            ->andReturn("CREATE (:Person {id: 'T_SEED_ROLLBACK', name: 'Rollback test'}); INVALID CYPHER;");

        $this->expectException(Neo4jQueryException::class);

        try {
            Artisan::call('db:seed');
        } finally {
            $this->assertSame([], $this->family->getPerson('T_SEED_ROLLBACK'));
        }
    }

    public function test_empty_seed_is_rejected_before_any_schema_or_data_write(): void
    {
        File::partialMock();
        File::shouldReceive('get')->once()->with(base_path('neo4j/schema.cypher'))
            ->andReturn(file_get_contents(base_path('neo4j/schema.cypher')));
        File::shouldReceive('get')->once()->with(base_path('neo4j/seed.cypher'))
            ->andReturn('// Empty seed');
        $neo4j = $this->createMock(Neo4jService::class);
        $neo4j->expects($this->never())->method('run');
        $neo4j->expects($this->never())->method('transaction');
        $this->app->instance(Neo4jService::class, $neo4j);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tệp schema hoặc seed Neo4j rỗng.');

        Artisan::call('db:seed');
    }
}
