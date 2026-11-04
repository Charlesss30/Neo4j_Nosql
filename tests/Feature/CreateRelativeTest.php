<?php

namespace Tests\Feature;

use App\Services\FamilyRepository;
use Tests\Neo4jTestCase;

class CreateRelativeTest extends Neo4jTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $family = new class($this->neo4j) extends FamilyRepository
        {
            public function createPerson(string $id, string $name, string $gender, int $birthYear, ?int $deathYear, ?string $birthPlace, ?string $note): array
            {
                return parent::createPerson(str_starts_with($id, 'T_') ? $id : 'T_NEW', $name, $gender, $birthYear, $deathYear, $birthPlace, $note);
            }
        };
        $this->family = $family;
        $this->app->instance(FamilyRepository::class, $family);
    }

    /** @return array<string, string> */
    private function input(): array
    {
        return ['name' => 'Người thân mới', 'gender' => 'F', 'birthYear' => '2000'];
    }

    public function test_child_and_two_parent_links_are_saved_together_without_creating_a_marriage(): void
    {
        $this->createTestPerson('T_001', 1970);
        $this->createTestPerson('T_002', 1975, 'F');
        $this->get('/persons/create?relative_id=T_001&relation_type=child')->assertOk()->assertSee('Gắn quan hệ ngay khi thêm người');
        $this->post('/persons', [...$this->input(), 'relation_type' => 'child', 'relative_id' => 'T_001', 'second_parent_id' => 'T_002'])
            ->assertRedirect('/persons/T_NEW')->assertSessionHasNoErrors();
        $this->assertSame(2, $this->family->parentCount('T_NEW')[0]['total']);
        $this->assertSame([], $this->family->spouses('T_001'));
    }

    public function test_a_failed_second_parent_rolls_back_both_the_person_and_the_first_link(): void
    {
        $this->createTestPerson('T_001', 1970);
        $this->post('/persons', [...$this->input(), 'relation_type' => 'child', 'relative_id' => 'T_001', 'second_parent_id' => 'T_MISSING'])
            ->assertSessionHasErrors('family');
        $this->assertSame([], $this->family->getPerson('T_NEW'));
        $this->assertSame([], $this->family->children('T_001'));
    }

    public function test_a_third_parent_is_rejected_without_leaving_an_orphan_person(): void
    {
        $this->createTestPerson('T_001', 1970);
        $this->createTestPerson('T_002', 1975, 'F');
        $this->createTestPerson('T_003', 2000);
        $this->service->addParent('T_001', 'T_003');
        $this->service->addParent('T_002', 'T_003');
        $this->post('/persons', [...$this->input(), 'birthYear' => '1960', 'relation_type' => 'parent', 'relative_id' => 'T_003'])
            ->assertSessionHasErrors(['family' => 'Một người chỉ được có tối đa hai cha mẹ.']);
        $this->assertSame([], $this->family->getPerson('T_NEW'));
        $this->assertSame(2, $this->family->parentCount('T_003')[0]['total']);
    }

    public function test_new_parent_becomes_the_tree_root_so_it_is_visible_after_redirect(): void
    {
        $this->createTestPerson('T_001', 2000);
        $this->post('/persons', [...$this->input(), 'birthYear' => '1970', 'relation_type' => 'parent', 'relative_id' => 'T_001', 'return_to' => 'tree', 'tree_root' => 'T_001'])
            ->assertRedirect(route('tree.index', ['root' => 'T_NEW', 'selected' => 'T_NEW']))->assertSessionHasNoErrors();
        $this->assertTrue($this->family->parentExists('T_NEW', 'T_001')[0]['exists']);
    }

    public function test_spouse_year_is_an_integer_and_invalid_years_roll_back(): void
    {
        $this->createTestPerson('T_001', 1970);
        $input = [...$this->input(), 'birthYear' => '1975', 'relation_type' => 'spouse', 'relative_id' => 'T_001'];
        $this->post('/persons', [...$input, 'marriageYear' => '1974'])->assertSessionHasErrors('family');
        $this->assertSame([], $this->family->getPerson('T_NEW'));
        $this->post('/persons', [...$input, 'marriageYear' => '1995'])->assertSessionHasNoErrors();
        $this->assertSame(1995, $this->family->spouses('T_001')[0]['marriageYear']);
    }

    public function test_an_equal_birth_year_or_a_removed_anchor_cannot_create_a_person(): void
    {
        $this->createTestPerson('T_001', 2000);
        $this->post('/persons', [...$this->input(), 'relation_type' => 'child', 'relative_id' => 'T_001'])->assertSessionHasErrors('family');
        $this->post('/persons', [...$this->input(), 'relation_type' => 'child', 'relative_id' => 'T_MISSING'])->assertSessionHasErrors('family');
        $this->assertSame([], $this->family->getPerson('T_NEW'));
        $this->get('/persons/create?relative_id=T_MISSING&relation_type=child')->assertNotFound();
    }
}
