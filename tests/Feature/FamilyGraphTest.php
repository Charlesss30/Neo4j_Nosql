<?php

namespace Tests\Feature;

use Tests\Neo4jTestCase;

class FamilyGraphTest extends Neo4jTestCase
{
    public function test_tree_default_subtree_and_all_graph_with_spouse_levels(): void
    {
        $this->get('/tree')->assertOk()->assertSee('Cây gia phả')->assertSee('vis-network@9.1.9')->assertSee('Con lớn ở bên trái')->assertSee('Vừa khung')->assertDontSee('Cypher Console');
        $this->getJson('/api/tree')->assertOk()->assertJsonPath('root', 'P001')->assertJsonCount(30, 'nodes')->assertJsonCount(47, 'edges');
        $branch = $this->getJson('/api/tree?root=P010')->assertOk()->assertJsonCount(7, 'nodes')->assertJsonCount(10, 'edges')->json();
        $this->assertSame(0, array_column($branch['nodes'], 'level', 'id')['P010']);
        $graph = $this->getJson('/api/tree?all=1')->assertOk()->assertJsonCount(30, 'nodes')->assertJsonCount(47, 'edges')->json();
        $levels = array_column($graph['nodes'], 'level', 'id');
        $this->assertCount(11, $graph['families']);
        $positions = array_column($graph['nodes'], null, 'id');
        $this->assertLessThan($positions['P004']['x'], $positions['P003']['x']);
        $this->assertLessThan($positions['P005']['x'], $positions['P004']['x']);
        $this->assertTrue($positions['P001']['fixed']);
        foreach ($graph['edges'] as $edge) {
            if ($edge['type'] === 'SPOUSE_OF') {
                $this->assertSame($levels[$edge['from']], $levels[$edge['to']]);
                $this->assertTrue($edge['dashes']);
                $this->assertSame('', $edge['arrows']);
                $this->assertFalse($edge['smooth']);
            } else {
                $this->assertSame('to', $edge['arrows']);
            }
        }
    }

    public function test_unknown_tree_roots_have_explicit_html_and_json_messages(): void
    {
        $this->get('/tree?root=UNKNOWN')->assertNotFound()->assertSee('Không tìm thấy thành viên gốc');
        $this->getJson('/api/tree?root=UNKNOWN')->assertNotFound()->assertJsonPath('nodes', [])->assertJsonPath('edges', []);
    }

    public function test_relation_between_p010_and_p012_uses_the_returned_path_without_assuming_a_tie_break(): void
    {
        $response = $this->get('/relation?a=P010&b=P012')->assertOk()->assertSee('Đường đi quan hệ ngắn nhất');
        $graph = $response->viewData('graph');
        $this->assertSame('P010', $graph['nodes'][0]['id']);
        $this->assertSame('P012', $graph['nodes'][array_key_last($graph['nodes'])]['id']);
        $this->assertCount(count($graph['nodes']) - 1, $graph['edges']);
        foreach ($graph['edges'] as $index => $edge) {
            $first = $graph['nodes'][$index];
            $second = $graph['nodes'][$index + 1];
            $role = $edge['type'] === 'SPOUSE_OF'
                ? ($first['gender'] === 'M' ? 'chồng' : 'vợ')
                : ($edge['from'] === $first['id'] ? ($first['gender'] === 'M' ? 'cha' : 'mẹ') : ($first['gender'] === 'M' ? 'con trai' : 'con gái'));
            $this->assertStringContainsString($first['name'].' là '.$role.' của '.$second['name'], $graph['sentence']);
        }
    }

    public function test_same_person_disconnected_people_and_unknown_relation_ids(): void
    {
        $this->get('/relation')->assertOk();
        $same = $this->get('/relation?a=P010&b=P010')->assertOk()->assertSee('Đây là cùng một người.')->viewData('graph');
        $this->assertCount(1, $same['nodes']);
        $this->assertSame([], $same['edges']);
        $this->createTestPerson('T_001');
        $this->createTestPerson('T_002');
        $this->get('/relation?a=T_001&b=T_002')->assertOk()->assertSee('Không có quan hệ huyết thống hoặc hôn nhân trong gia phả.');
        $this->get('/relation?a=P010&b=UNKNOWN')->assertNotFound()->assertSee('Không tìm thấy một trong hai thành viên.');
        $this->getJson('/relation?a=P010')->assertUnprocessable()->assertJsonValidationErrors('b');
    }
}
