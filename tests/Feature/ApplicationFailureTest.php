<?php

namespace Tests\Feature;

use App\Exceptions\Neo4jQueryException;
use App\Services\FamilyRepository;
use Tests\TestCase;

class ApplicationFailureTest extends TestCase
{
    public function test_person_and_tree_pages_show_a_friendly_database_failure_without_driver_details(): void
    {
        $family = $this->createMock(FamilyRepository::class);
        $family->method('countPersons')->willThrowException(new Neo4jQueryException('Không thể kết nối Neo4j.'));
        $family->method('getPerson')->willThrowException(new Neo4jQueryException('Không thể kết nối Neo4j.'));
        $this->app->instance(FamilyRepository::class, $family);

        foreach (['/persons', '/persons/P001', '/tree', '/relation?a=P010&b=P012'] as $path) {
            $this->get($path)->assertStatus(503)->assertSee('Chưa thể tải gia phả')->assertSee('Không thể kết nối Neo4j.')->assertDontSee('Stack trace');
        }
        $this->getJson('/api/tree')->assertStatus(503)->assertExactJson(['message' => 'Không thể kết nối Neo4j.']);
    }
}
