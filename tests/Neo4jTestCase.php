<?php

namespace Tests;

use App\Services\FamilyRepository;
use App\Services\FamilyService;
use App\Services\Neo4jService;

abstract class Neo4jTestCase extends TestCase
{
    protected FamilyRepository $family;

    protected FamilyService $service;

    protected Neo4jService $neo4j;

    /** @var array<string, mixed> */
    private array $originalGraph = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->neo4j = $this->app->make(Neo4jService::class);
        $this->assertTrue($this->neo4j->ping(), 'Start Neo4j and load the seed before running integration tests.');
        $this->family = $this->app->make(FamilyRepository::class);
        $this->service = $this->app->make(FamilyService::class);
        $this->originalGraph = $this->withoutTestPeople($this->family->graphAll()[0]);
    }

    protected function tearDown(): void
    {
        try {
            if (isset($this->family)) {
                $this->neo4j->run("MATCH (p:Person) WHERE p.id STARTS WITH 'T_' DETACH DELETE p");
                $this->assertSame($this->originalGraph, $this->family->graphAll()[0], 'Tests must preserve every original person and relationship.');
            }
        } finally {
            parent::tearDown();
        }
    }

    /** @return array<string, mixed> */
    protected function createTestPerson(string $id, int $birthYear = 1980, string $gender = 'M'): array
    {
        $this->assertStringStartsWith('T_', $id);

        return $this->family->createPerson($id, 'Thành viên thử '.$id, $gender, $birthYear, null, null, null)[0]['person'];
    }

    /**
     * @param  array<string, mixed>  $graph
     * @return array<string, mixed>
     */
    private function withoutTestPeople(array $graph): array
    {
        $graph['nodes'] = array_values(array_filter($graph['nodes'], fn (array $person): bool => ! str_starts_with($person['id'], 'T_')));
        $graph['edges'] = array_values(array_filter($graph['edges'], fn (array $edge): bool => ! str_starts_with($edge['from'], 'T_') && ! str_starts_with($edge['to'], 'T_')));

        return $graph;
    }
}
