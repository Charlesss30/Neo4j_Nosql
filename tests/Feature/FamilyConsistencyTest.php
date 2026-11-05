<?php

namespace Tests\Feature;

use App\Services\FamilyRepository;
use Tests\Neo4jTestCase;

class FamilyConsistencyTest extends Neo4jTestCase
{
    public function test_ancestors_and_descendants_cannot_marry_in_either_direction(): void
    {
        $this->createTestPerson('T_A', 1950);
        $this->createTestPerson('T_B', 1975, 'F');
        $this->createTestPerson('T_C', 2000, 'F');
        $this->service->addParent('T_A', 'T_B');
        $this->service->addParent('T_B', 'T_C');

        foreach ([['T_A', 'T_B'], ['T_A', 'T_C'], ['T_C', 'T_A']] as [$first, $second]) {
            $this->rejectSpouse($first, $second);
        }
    }

    public function test_half_and_full_siblings_cannot_marry_but_first_cousins_can(): void
    {
        foreach (['T_ROOT' => 1930, 'T_OTHER' => 1935, 'T_A' => 1960, 'T_B' => 1965, 'T_C' => 1980, 'T_D' => 1985] as $id => $year) {
            $this->createTestPerson($id, $year);
        }
        $this->service->addParent('T_ROOT', 'T_A');
        $this->service->addParent('T_ROOT', 'T_B');
        $this->rejectSpouse('T_A', 'T_B');
        $this->service->addParent('T_OTHER', 'T_A');
        $this->service->addParent('T_OTHER', 'T_B');
        $this->rejectSpouse('T_B', 'T_A');
        $this->service->addParent('T_A', 'T_C');
        $this->service->addParent('T_B', 'T_D');
        $this->service->addSpouse('T_C', 'T_D', null);
        $this->assertTrue($this->family->spouseExists('T_C', 'T_D')[0]['exists']);
    }

    public function test_a_parent_edge_cannot_turn_existing_spouses_into_parent_and_child_or_siblings(): void
    {
        $this->createTestPerson('T_PARENT', 1950);
        $this->createTestPerson('T_A', 1970);
        $this->createTestPerson('T_B', 1990, 'F');
        $this->service->addSpouse('T_B', 'T_A', null);
        $this->rejectParent('T_A', 'T_B');
        $this->service->addParent('T_PARENT', 'T_A');
        $this->rejectParent('T_PARENT', 'T_B');
    }

    public function test_an_intermediate_edge_cannot_make_other_existing_spouses_ancestor_and_descendant(): void
    {
        foreach (['T_A' => 1950, 'T_B' => 1970, 'T_C' => 1980, 'T_D' => 2000] as $id => $year) {
            $this->createTestPerson($id, $year);
        }
        $this->service->addSpouse('T_A', 'T_D', null);
        $this->service->addParent('T_A', 'T_B');
        $this->service->addParent('T_C', 'T_D');
        $this->rejectParent('T_B', 'T_C');
    }

    public function test_marriage_cannot_follow_either_death_but_the_same_year_and_null_are_allowed(): void
    {
        $this->createDeceased('T_A', 1970, 2000, 'M');
        $this->createDeceased('T_B', 1975, 1999, 'F');
        $this->rejectSpouse('T_A', 'T_B', 2001);
        $this->rejectSpouse('T_A', 'T_B', 2000);
        $this->service->addSpouse('T_A', 'T_B', 1999);
        $before = $this->family->graphAll()[0];
        $this->put('/persons/T_A', ['name' => 'Changed', 'gender' => 'M', 'birthYear' => 1970, 'deathYear' => 1998])
            ->assertSessionHasErrors('family');
        $this->assertSame($before, $this->family->graphAll()[0]);
        $this->service->removeSpouse('T_A', 'T_B');
        $this->service->addSpouse('T_A', 'T_B', null);
        $this->assertNull($this->family->spouses('T_A')[0]['marriageYear']);
    }

    public function test_parent_death_policy_is_checked_on_edges_and_person_edits(): void
    {
        $this->createDeceased('T_MOTHER', 1950, 1980, 'F');
        $this->createDeceased('T_FATHER', 1950, 1980, 'M');
        $this->createTestPerson('T_CHILD', 1981);
        $this->createTestPerson('T_LATE', 1982);
        $this->createTestPerson('T_VERY_LATE', 2000);
        $this->rejectParent('T_MOTHER', 'T_CHILD');
        $this->rejectParent('T_MOTHER', 'T_VERY_LATE');
        $this->rejectParent('T_FATHER', 'T_LATE');
        $this->service->addParent('T_FATHER', 'T_CHILD');

        foreach ([
            ['id' => 'T_FATHER', 'gender' => 'F', 'birthYear' => 1950, 'deathYear' => 1980],
            ['id' => 'T_FATHER', 'gender' => 'M', 'birthYear' => 1950, 'deathYear' => 1979],
            ['id' => 'T_CHILD', 'gender' => 'M', 'birthYear' => 1982, 'deathYear' => null],
        ] as $edit) {
            $before = $this->family->graphAll()[0];
            $id = $edit['id'];
            unset($edit['id']);
            $this->put('/persons/'.$id, ['name' => 'Changed', ...$edit])->assertSessionHasErrors('family');
            $this->assertSame($before, $this->family->graphAll()[0]);
        }
        $this->createTestPerson('T_SAME_YEAR', 1980);
        $this->service->addParent('T_MOTHER', 'T_SAME_YEAR');
        $this->assertTrue($this->family->parentExists('T_MOTHER', 'T_SAME_YEAR')[0]['exists']);
    }

    public function test_marriage_in_the_birth_year_is_not_rejected_by_an_unrequested_minimum_age_rule(): void
    {
        $this->createTestPerson('T_A', 1970);
        $this->createTestPerson('T_B', 1970, 'F');
        $this->service->addSpouse('T_A', 'T_B', 1970);
        $this->assertSame(1970, $this->family->spouses('T_A')[0]['marriageYear']);
    }

    public function test_dashboard_statistics_follow_the_requested_real_graph_branch(): void
    {
        $this->get('/?root=P010')->assertOk()
            ->assertViewHas('rootPerson', fn (array $person): bool => $person['id'] === 'P010')
            ->assertViewHas('generations', fn (array $rows): bool => array_sum(array_column($rows, 'total')) === 7)
            ->assertViewHas('life', fn (array $life): bool => $life['living'] + $life['deceased'] === 30);
    }

    public function test_creating_a_relative_rolls_back_new_people_and_the_first_link_when_new_year_rules_fail(): void
    {
        $this->createTestPerson('T_PARENT', 1970);
        $this->createDeceased('T_MOTHER', 1975, 1999, 'F');
        $family = new class($this->neo4j) extends FamilyRepository
        {
            public function createPerson(string $id, string $name, string $gender, int $birthYear, ?int $deathYear, ?string $birthPlace, ?string $note): array
            {
                return parent::createPerson('T_NEW', $name, $gender, $birthYear, $deathYear, $birthPlace, $note);
            }
        };
        $this->app->instance(FamilyRepository::class, $family);

        foreach ([
            ['name' => 'Con mới', 'gender' => 'F', 'birthYear' => 2001, 'relation_type' => 'child', 'relative_id' => 'T_PARENT', 'second_parent_id' => 'T_MOTHER'],
            ['name' => 'Vợ mới', 'gender' => 'F', 'birthYear' => 1975, 'deathYear' => 2000, 'relation_type' => 'spouse', 'relative_id' => 'T_PARENT', 'marriageYear' => 2010],
        ] as $input) {
            $before = $this->family->graphAll()[0];
            $this->post('/persons', $input)->assertSessionHasErrors('family');
            $this->assertSame($before, $this->family->graphAll()[0]);
            $this->assertSame([], $this->family->getPerson('T_NEW'));
        }
    }

    private function createDeceased(string $id, int $birth, int $death, string $gender): void
    {
        $this->createTestPerson($id, $birth, $gender);
        $this->family->updatePerson($id, $id, $gender, $birth, $death, null, null);
    }

    private function rejectSpouse(string $first, string $second, ?int $year = null): void
    {
        $before = $this->family->graphAll()[0];
        $this->post('/relationships/spouse', ['a' => $first, 'b' => $second, 'marriageYear' => $year])->assertSessionHasErrors('family');
        $this->assertSame($before, $this->family->graphAll()[0]);
    }

    private function rejectParent(string $parent, string $child): void
    {
        $before = $this->family->graphAll()[0];
        $this->post('/relationships/parent', ['parentId' => $parent, 'childId' => $child])->assertSessionHasErrors('family');
        $this->assertSame($before, $this->family->graphAll()[0]);
    }
}
