<?php

namespace Tests\Feature;

use Illuminate\Validation\ValidationException;
use Tests\Neo4jTestCase;

class FamilyRulesTest extends Neo4jTestCase
{
    public function test_self_parent_is_rejected_with_a_vietnamese_message(): void
    {
        $this->createTestPerson('T_001');
        $this->from('/relationships')->post('/relationships/parent', ['parentId' => 'T_001', 'childId' => 'T_001'])
            ->assertRedirect('/relationships')->assertSessionHasErrors(['family' => 'Một người không thể là cha mẹ của chính mình.']);
        $this->assertSame(0, $this->family->parentCount('T_001')[0]['total']);
    }

    public function test_a_third_parent_is_rejected(): void
    {
        foreach (['T_001', 'T_002', 'T_003'] as $id) {
            $this->createTestPerson($id, 1970);
        }
        $this->createTestPerson('T_004', 2000);
        $this->service->addParent('T_001', 'T_004');
        $this->service->addParent('T_002', 'T_004');
        $this->post('/relationships/parent', ['parentId' => 'T_003', 'childId' => 'T_004'])
            ->assertSessionHasErrors(['family' => 'Một người chỉ được có tối đa hai cha mẹ.']);
        $this->assertSame(2, $this->family->parentCount('T_004')[0]['total']);
    }

    public function test_a_descendant_cannot_become_a_parent_of_its_ancestor(): void
    {
        $this->createTestPerson('T_001', 1950);
        $this->createTestPerson('T_002', 1980);
        $this->createTestPerson('T_003', 2000);
        $this->service->addParent('T_001', 'T_002');
        $this->service->addParent('T_002', 'T_003');
        $this->post('/relationships/parent', ['parentId' => 'T_003', 'childId' => 'T_001'])
            ->assertSessionHasErrors(['family' => 'Quan hệ này tạo vòng lặp trong gia phả.']);
        $this->assertFalse($this->family->parentExists('T_003', 'T_001')[0]['exists']);
    }

    public function test_birth_year_order_is_strict_but_has_no_minimum_age_gap(): void
    {
        $this->createTestPerson('T_001', 2000);
        $this->createTestPerson('T_002', 2000);
        $this->createTestPerson('T_003', 1999);
        foreach (['T_002', 'T_003'] as $child) {
            $this->post('/relationships/parent', ['parentId' => 'T_001', 'childId' => $child])
                ->assertSessionHasErrors(['family' => 'Cha mẹ phải sinh trước con.']);
        }
        $this->service->addParent('T_003', 'T_001');
        $this->assertTrue($this->family->parentExists('T_003', 'T_001')[0]['exists']);
    }

    public function test_duplicate_parent_and_reverse_duplicate_spouse_are_rejected(): void
    {
        $this->createTestPerson('T_001', 1970);
        $this->createTestPerson('T_002', 1990, 'F');
        $this->service->addParent('T_001', 'T_002');
        $this->post('/relationships/parent', ['parentId' => 'T_001', 'childId' => 'T_002'])
            ->assertSessionHasErrors(['family' => 'Quan hệ cha mẹ – con đã tồn tại.']);
        $this->service->addSpouse('T_001', 'T_002', null);
        foreach ([['a' => 'T_001', 'b' => 'T_002'], ['a' => 'T_002', 'b' => 'T_001']] as $pair) {
            $this->post('/relationships/spouse', $pair)->assertSessionHasErrors('family');
        }
        $this->assertCount(1, $this->family->spouses('T_001'));
    }

    public function test_valid_relationship_forms_store_integer_years_and_remove_both_edge_types(): void
    {
        $this->createTestPerson('T_001', 1970);
        $this->createTestPerson('T_002', 1975, 'F');
        $this->createTestPerson('T_003', 2000);
        $this->from('/relationships')->post('/relationships/parent', ['parentId' => 'T_001', 'childId' => 'T_003'])->assertRedirect('/relationships')->assertSessionHasNoErrors();
        $this->post('/relationships/spouse', ['a' => 'T_001', 'b' => 'T_002', 'marriageYear' => '1995'])->assertSessionHasNoErrors();
        $this->assertSame(1970, $this->family->getPerson('T_001')[0]['person']['birthYear']);
        $this->assertSame(1995, $this->family->spouses('T_001')[0]['marriageYear']);
        $this->delete('/relationships/parent', ['parentId' => 'T_001', 'childId' => 'T_003'])->assertSessionHasNoErrors();
        $this->delete('/relationships/spouse', ['a' => 'T_002', 'b' => 'T_001'])->assertSessionHasNoErrors();
        $this->assertSame([], $this->family->spouses('T_001'));
        $this->assertSame(0, $this->family->parentCount('T_003')[0]['total']);
    }

    public function test_missing_people_and_missing_edges_are_rejected(): void
    {
        $this->createTestPerson('T_001');
        $this->createTestPerson('T_002');
        $this->get('/relationships')->assertOk()->assertSee('T_001');
        $this->post('/relationships/parent', ['parentId' => 'T_001', 'childId' => 'T_UNKNOWN'])->assertSessionHasErrors('family');
        $this->delete('/relationships/parent', ['parentId' => 'T_001', 'childId' => 'T_002'])->assertSessionHasErrors('family');
        $this->delete('/relationships/spouse', ['a' => 'T_001', 'b' => 'T_002'])->assertSessionHasErrors('family');
        $this->post('/relationships/spouse', ['a' => 'T_001', 'b' => 'T_001'])->assertSessionHasErrors('family');
    }

    public function test_marriage_year_must_follow_both_birth_years_and_not_be_in_the_future(): void
    {
        $this->createTestPerson('T_001', 1980);
        $this->createTestPerson('T_002', 1990, 'F');
        foreach (['1989', (string) (date('Y') + 1), '1999.5', 'abc'] as $year) {
            $this->post('/relationships/spouse', ['a' => 'T_001', 'b' => 'T_002', 'marriageYear' => $year])->assertSessionHasErrors();
            $this->assertSame([], $this->family->spouses('T_001'));
        }
        $this->post('/relationships/spouse', ['a' => 'T_001', 'b' => 'T_002', 'marriageYear' => ''])->assertSessionHasNoErrors();
        $this->assertNull($this->family->spouses('T_001')[0]['marriageYear']);
    }

    public function test_transaction_failure_rolls_back_and_restores_the_runner(): void
    {
        try {
            $this->neo4j->transaction(function (): void {
                $this->createTestPerson('T_099');
                throw ValidationException::withMessages(['family' => 'Rollback test']);
            });
            $this->fail('The transaction should have failed.');
        } catch (ValidationException $exception) {
            $this->assertSame(['family' => ['Rollback test']], $exception->errors());
        }
        $this->assertSame([], $this->family->getPerson('T_099'));
        $this->assertTrue($this->neo4j->ping());
    }
}
