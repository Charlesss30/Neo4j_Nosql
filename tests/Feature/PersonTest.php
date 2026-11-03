<?php

namespace Tests\Feature;

use Tests\Neo4jTestCase;

class PersonTest extends Neo4jTestCase
{
    public function test_list_search_and_seed_kinship_details(): void
    {
        $this->get('/persons')->assertOk()->assertViewHas('people', fn ($people): bool => $people->count() === 10 && $people->total() === 30)->assertSee('gõ có dấu');
        $this->get('/persons?q=khôngcótênnày')->assertOk()->assertSee('Không có thành viên phù hợp.');
        $this->get('/persons?page=2')->assertOk()->assertViewHas('people', fn ($people): bool => $people->currentPage() === 2);
        $this->get('/persons/P014')->assertOk()->assertViewHas('sections', fn (array $sections): bool => array_column(array_column($sections['siblings'], 'person'), 'id') === ['P015', 'P016']);
        $this->get('/persons/P010')->assertOk()->assertViewHas('sections', fn (array $sections): bool => array_column(array_column($sections['cousins'], 'person'), 'id') === ['P012', 'P013', 'P014', 'P015', 'P016']);
        $this->get('/persons/create')->assertOk()->assertSee('Thêm thành viên');
    }

    public function test_unknown_people_return_404_on_all_person_pages(): void
    {
        foreach (['/persons/UNKNOWN', '/persons/UNKNOWN/edit', '/persons/UNKNOWN/delete'] as $path) {
            $this->get($path)->assertNotFound();
        }
    }

    public function test_edit_casts_years_and_nulls_and_delete_detaches_only_test_relationships(): void
    {
        $this->createTestPerson('T_001');
        $this->createTestPerson('T_002', 2010);
        $this->family->addParentOf('T_001', 'T_002');
        $this->get('/persons/T_001/edit')->assertOk();
        $this->put('/persons/T_001', [
            'name' => '<script>unsafe</script>', 'gender' => 'F', 'birthYear' => '1981',
            'deathYear' => '2020', 'birthPlace' => '', 'note' => '',
        ])->assertRedirect('/persons/T_001')->assertSessionHasNoErrors();
        $person = $this->family->getPerson('T_001')[0]['person'];
        $this->assertSame(1981, $person['birthYear']);
        $this->assertSame(2020, $person['deathYear']);
        $this->assertArrayNotHasKey('birthPlace', $person);
        $this->assertArrayNotHasKey('note', $person);
        $this->get('/persons/T_001')->assertOk()->assertDontSee('<script>unsafe</script>', false)->assertSee('&lt;script&gt;unsafe&lt;/script&gt;', false);
        $this->get('/persons/T_001/delete')->assertOk()->assertViewHas('relationshipCount', 1);
        $this->delete('/persons/T_001')->assertRedirect('/persons');
        $this->assertSame([], $this->family->getPerson('T_001'));
        $this->assertSame(0, $this->family->parentCount('T_002')[0]['total']);
        $this->assertNotEmpty($this->family->getPerson('T_002'));
    }

    public function test_edit_cannot_invalidate_an_existing_parent_child_birth_order(): void
    {
        $this->createTestPerson('T_001', 1980);
        $this->createTestPerson('T_002', 2000);
        $this->family->addParentOf('T_001', 'T_002');
        $this->from('/persons/T_001/edit')->put('/persons/T_001', [
            'name' => 'Cha mẹ thử', 'gender' => 'M', 'birthYear' => '2000',
        ])->assertRedirect('/persons/T_001/edit')->assertSessionHasErrors('family');
        $this->assertSame(1980, $this->family->getPerson('T_001')[0]['person']['birthYear']);
    }
}
