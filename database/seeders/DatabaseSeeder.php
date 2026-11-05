<?php

namespace Database\Seeders;

use App\Services\CypherStatements;
use App\Services\GraphWriteLock;
use App\Services\Neo4jService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(Neo4jService $neo4j, CypherStatements $statements, GraphWriteLock $writeLock): void
    {
        $schema = $statements->splitStatements(File::get(base_path('neo4j/schema.cypher')));
        $seed = $statements->splitStatements(File::get(base_path('neo4j/seed.cypher')));

        if ($schema === [] || $seed === []) {
            throw new RuntimeException('Tệp schema hoặc seed Neo4j rỗng.');
        }

        $writeLock->run(function () use ($neo4j, $schema, $seed): void {
            foreach ($schema as $statement) {
                $neo4j->run($statement);
            }

            $neo4j->transaction(function () use ($neo4j, $seed): void {
                foreach ($seed as $statement) {
                    $neo4j->run($statement);
                }
            });
        });

        $this->command?->info('Đã nạp schema và dữ liệu mẫu vào Neo4j.');
    }
}
