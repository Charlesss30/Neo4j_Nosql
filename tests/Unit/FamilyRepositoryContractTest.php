<?php

namespace Tests\Unit;

use App\Services\FamilyRepository;
use App\Services\Neo4jService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class FamilyRepositoryContractTest extends TestCase
{
    public function test_all_34_methods_use_the_exact_catalog_queries_and_named_parameters(): void
    {
        $root = dirname(__DIR__, 2);
        $path = is_file($root.'/neo4j/queries.cypher')
            ? $root.'/neo4j/queries.cypher'
            : $root.'/Neo4j_Nosql-main/neo4j/queries.cypher';
        $catalog = str_replace("\r\n", "\n", file_get_contents($path));
        preg_match_all('~// @name (\w+)\s*\n// @params([^\n]*)\n// @returns[^\n]*\n(.*?)(?=// @name|\z)~s', $catalog, $blocks, PREG_SET_ORDER);
        $this->assertCount(34, $blocks);
        $copied = (new ReflectionClass(FamilyRepository::class))->getConstant('QUERIES');
        $this->assertCount(34, $copied);

        foreach ($blocks as [, $name, $parameters, $body]) {
            $expected = rtrim(preg_replace('~;\s*$~', '', trim($body)));
            $this->assertSame($expected, $copied[$name], $name.' must match the catalog verbatim');
            preg_match_all('~\$(\w+)~', $parameters, $matches);
            $params = [];

            foreach ($matches[1] as $parameter) {
                $params[$parameter] = match ($parameter) {
                    'skip' => 0, 'limit' => 10, 'birthYear' => 1950,
                    'deathYear', 'marriageYear', 'birthPlace', 'note' => null,
                    default => 'P001',
                };
            }

            $neo4j = $this->createMock(Neo4jService::class);
            $neo4j->expects($this->once())->method('run')->with($expected, $params)->willReturn([]);
            $this->assertSame([], (new FamilyRepository($neo4j))->{$name}(...array_values($params)));
        }
    }
}
