<?php

namespace Tests\Feature;

use App\Services\FamilyRepository;
use App\Services\Neo4jService;
use Tests\TestCase;

class PersonInputTest extends TestCase
{
    public function test_create_allocates_the_next_id_and_passes_integer_years_and_nulls(): void
    {
        $neo4j = $this->createMock(Neo4jService::class);
        $neo4j->expects($this->once())->method('transaction')->willReturnCallback(fn (callable $callback): mixed => $callback());
        $this->app->instance(Neo4jService::class, $neo4j);
        $family = $this->createMock(FamilyRepository::class);
        $family->method('maxPersonNumber')->willReturn([['maxNum' => 30]]);
        $family->expects($this->once())->method('createPerson')->with('P031', 'Người mới', 'F', 1990, 2020, null, null)->willReturn([['person' => ['id' => 'P031']]]);
        $this->app->instance(FamilyRepository::class, $family);

        $this->post('/persons', ['name' => 'Người mới', 'gender' => 'F', 'birthYear' => '1990', 'deathYear' => '2020', 'birthPlace' => '', 'note' => ''])
            ->assertRedirect('/persons/P031')->assertSessionHasNoErrors();
    }

    public function test_invalid_person_fields_are_rejected_before_any_query(): void
    {
        $family = $this->createMock(FamilyRepository::class);
        $family->expects($this->never())->method('createPerson');
        $this->app->instance(FamilyRepository::class, $family);

        foreach ([
            ['name' => ''], ['name' => str_repeat('a', 101)], ['gender' => 'X'],
            ['birthYear' => 'abc'], ['birthYear' => '999'], ['birthYear' => (string) (date('Y') + 1)],
            ['deathYear' => '1980'], ['deathYear' => '2000.5'], ['deathYear' => (string) (date('Y') + 1)],
            ['birthPlace' => str_repeat('a', 101)], ['note' => str_repeat('a', 501)],
        ] as $invalid) {
            $field = array_key_first($invalid);
            $this->post('/persons', array_replace(['name' => 'Người thử', 'gender' => 'M', 'birthYear' => '1990'], $invalid))->assertSessionHasErrors($field);
        }
    }
}
