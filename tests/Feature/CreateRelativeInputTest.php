<?php

namespace Tests\Feature;

use App\Services\FamilyRepository;
use Tests\TestCase;

class CreateRelativeInputTest extends TestCase
{
    public function test_invalid_link_contexts_fail_before_writing(): void
    {
        $family = $this->createMock(FamilyRepository::class);
        $family->expects($this->never())->method('createPerson');
        $this->app->instance(FamilyRepository::class, $family);
        foreach ([
            ['relation_type' => 'invalid', 'relative_id' => 'P001'],
            ['relation_type' => 'child'], ['relative_id' => 'P001'],
            ['relation_type' => 'child', 'relative_id' => ['P001']],
            ['relation_type' => 'child', 'relative_id' => 'P001', 'second_parent_id' => 'P001'],
            ['relation_type' => 'child', 'relative_id' => 'P001', 'marriageYear' => '2000'],
            ['return_to' => 'https://example.com'],
        ] as $context) {
            $this->post('/persons', ['name' => 'Người mới', 'gender' => 'M', 'birthYear' => '2000', ...$context])->assertSessionHasErrors();
        }
    }
}
