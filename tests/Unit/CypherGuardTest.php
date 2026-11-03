<?php

namespace Tests\Unit;

use App\Services\CypherGuard;
use App\Services\Neo4jService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CypherGuardTest extends TestCase
{
    #[DataProvider('allowedQueries')]
    public function test_read_queries_and_keywords_inside_literals_or_identifiers_are_allowed(string $query): void
    {
        (new CypherGuard)->validate($query);
        $this->addToAssertionCount(1);
    }

    /** @return array<string, array{string}> */
    public static function allowedQueries(): array
    {
        return [
            'read' => ['MATCH (p:Person) RETURN p {.*} AS person LIMIT 10'],
            'trailing semicolon' => ['RETURN 1 AS value;'],
            'keywords in string' => ["RETURN 'CREATE MERGE DELETE DETACH SET REMOVE DROP LOAD CSV FOREACH CALL' AS text"],
            'double quoted string' => ['RETURN "CREATE; CALL dbms.kill()" AS text'],
            'escaped quote' => ["RETURN 'it\\'s CREATE; DELETE' AS text"],
            'doubled quote' => ["RETURN 'it''s CREATE; DELETE' AS text"],
            'property name' => ['MATCH (p:Person) RETURN p.createdAt'],
            'quoted property' => ['MATCH (p:Person) RETURN p.`CREATE`'],
            'line comment' => ["// CREATE; DELETE\nRETURN 1; // MERGE;"],
            'block comment' => ['/* DROP; */ RETURN 1'],
            'nested block comment' => ['/* DROP /* CREATE */ DELETE */ RETURN 1'],
            'labels' => ['CALL db.labels() YIELD label RETURN label'],
            'relationship types' => ['call db.relationshipTypes()'],
            'property keys' => ['CALL db.propertyKeys()'],
            'call comments' => ['CALL /* note */ db.labels(/* comment */)'],
            'call text in literal' => ["RETURN 'CALL apoc.cypher.run(\"DELETE\")' AS example"],
        ];
    }

    #[DataProvider('rejectedQueries')]
    public function test_writes_procedures_multiple_statements_and_incomplete_input_are_rejected(string $query): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new CypherGuard)->validate($query);
    }

    /** @return array<string, array{string}> */
    public static function rejectedQueries(): array
    {
        return [
            'empty' => [''], 'whitespace' => [" \n\t"], 'semicolon' => [';'],
            'comment only' => ['/* RETURN 1 */'],
            'create' => ['create (:Person)'], 'merge' => ['MERGE (:Person {id: $id})'],
            'delete' => ['MATCH (p) DELETE p'], 'detach' => ['MATCH (p) DETACH DELETE p'],
            'detach token independently' => ['dEtAcH'],
            'set' => ['MATCH (p) SET p.name = $name'], 'remove' => ['MATCH (p) REMOVE p.note'],
            'drop' => ['DROP INDEX person_name'], 'load csv' => ["LOAD CSV FROM 'file:///persons.csv' AS row RETURN row"],
            'load csv comment gap' => ["LOAD /* separated */ CSV FROM 'x' AS row RETURN row"],
            'foreach' => ['FOREACH (i IN [1] | CREATE (:Person))'],
            'foreach token independently' => ['fOrEaCh'],
            'insert alias' => ['INSERT (:Person {id: $id})'],
            'schema show kept in admin' => ['SHOW INDEXES'],
            'alter' => ["ALTER CURRENT USER SET PASSWORD FROM 'x' TO 'y'"],
            'rename' => ['RENAME USER a TO b'], 'terminate' => ['TERMINATE TRANSACTION $id'],
            'write procedure' => ['CALL db.createLabel($label)'],
            'apoc' => ["CALL apoc.cypher.run('RETURN 1', {})"],
            'read subquery' => ['CALL { RETURN 1 AS x } RETURN x'],
            'second call' => ['CALL db.labels() CALL dbms.components()'],
            'procedure arguments' => ["CALL db.labels('x')"],
            'procedure prefix' => ['CALL db.labelsOther()'],
            'multiple statements' => ['RETURN 1; RETURN 2'],
            'empty second statement' => ['RETURN 1;;'],
            'literal after statement' => ["RETURN 1; 'another statement'"],
            'comment between statements' => ['RETURN 1; /* note */ RETURN 2'],
            'unclosed string' => ["RETURN 'not closed"],
            'unclosed block' => ['RETURN 1 /* not closed'],
            'unclosed identifier' => ['MATCH (p) RETURN p.`name'],
        ];
    }

    public function test_length_limit_counts_unicode_characters(): void
    {
        (new CypherGuard)->validate("RETURN '".str_repeat('ệ', 1991)."'");
        $this->addToAssertionCount(1);
        $this->expectException(InvalidArgumentException::class);
        (new CypherGuard)->validate("RETURN '".str_repeat('ệ', 1992)."'");
    }

    public function test_statement_splitter_keeps_semicolons_in_strings_identifiers_and_comments(): void
    {
        $source = <<<'CYPHER'
// Initial comment with ;
CREATE (p:Person {note: 'A; B', name: "C; D"});
/* outer ; /* inner ; */ */ RETURN 'it\'s; fine' AS text;
RETURN 'it''s; fine' AS text;
MATCH (p) RETURN p.`semi;colon`;
// Trailing comment only ;
CYPHER;
        $statements = (new CypherGuard)->splitStatements($source);
        $this->assertCount(4, $statements);
        $this->assertStringContainsString("'A; B'", $statements[0]);
        $this->assertStringContainsString("'it\\'s; fine'", $statements[1]);
        $this->assertStringContainsString("'it''s; fine'", $statements[2]);
        $this->assertStringContainsString('`semi;colon`', $statements[3]);
        $this->assertSame([], (new CypherGuard)->splitStatements('// Comment only;'));
        $this->assertCount(6, (new CypherGuard)->splitStatements(file_get_contents(base_path('neo4j/seed.cypher'))));
    }

    public function test_console_rejects_writes_before_contacting_the_database(): void
    {
        $neo4j = $this->createMock(Neo4jService::class);
        $neo4j->expects($this->never())->method('run');
        $this->app->instance(Neo4jService::class, $neo4j);
        $this->post('/console/run', ['query' => 'CREATE (:Person)'])->assertStatus(422)->assertSee('chỉ cho phép đọc dữ liệu');
    }

    public function test_console_caps_rows_and_escapes_nested_results(): void
    {
        $rows = [];

        for ($index = 1; $index <= 501; $index++) {
            $rows[] = ['name' => 'person-'.$index, 'nested' => ['text' => '<script>alert(1)</script>']];
        }

        $neo4j = $this->createMock(Neo4jService::class);
        $neo4j->expects($this->once())->method('run')->with('RETURN 1')->willReturn($rows);
        $this->app->instance(Neo4jService::class, $neo4j);
        $this->post('/console/run', ['query' => 'RETURN 1'])->assertOk()->assertSee('500/501 dòng')
            ->assertSee('person-500')->assertDontSee('person-501')->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_console_handles_no_rows_and_backend_errors(): void
    {
        $neo4j = $this->createMock(Neo4jService::class);
        $neo4j->method('run')->willReturn([]);
        $this->app->instance(Neo4jService::class, $neo4j);
        $this->post('/console/run', ['query' => 'RETURN 1'])->assertOk()->assertSee('không trả về dòng nào');
        $failed = $this->createMock(Neo4jService::class);
        $failed->method('run')->willThrowException(new \RuntimeException('Lỗi kết nối thử nghiệm.'));
        $this->app->instance(Neo4jService::class, $failed);
        $this->post('/console/run', ['query' => 'RETURN 1'])->assertStatus(422)->assertSee('Không chạy được truy vấn.');
    }

    public function test_maintenance_actions_are_forbidden_outside_local(): void
    {
        $neo4j = $this->createMock(Neo4jService::class);
        $neo4j->expects($this->never())->method('run');
        $this->app->instance(Neo4jService::class, $neo4j);
        $this->post('/admin/reset', ['confirm_reset' => 'yes'])->assertForbidden();
        $this->post('/admin/profile', ['sample' => 'nameIndex', 'name' => 'Nguyễn Minh Khang'])->assertForbidden();
    }

    public function test_admin_reports_connection_errors_without_breaking_the_page(): void
    {
        $neo4j = $this->createMock(Neo4jService::class);
        $neo4j->method('run')->willThrowException(new \RuntimeException('Connection refused'));
        $this->app->instance(Neo4jService::class, $neo4j);
        $this->get('/admin')->assertOk()->assertSee('Không đọc được thông tin quản trị.')
            ->assertSee('Không thể kết nối Neo4j.');
    }

    public function test_index_maintenance_restores_the_index_even_when_the_comparison_fails(): void
    {
        $this->app->detectEnvironment(fn (): string => 'local');
        $calls = [];
        $neo4j = $this->createMock(Neo4jService::class);
        $neo4j->expects($this->never())->method('client');
        $neo4j->method('run')->willReturnCallback(function (string $query, array $params = []) use (&$calls): array {
            $calls[] = $query;

            if ($query === 'DROP INDEX person_name IF EXISTS') {
                throw new \RuntimeException('Injected maintenance failure');
            }

            return in_array($query, [
                'MATCH (n) RETURN count(n) AS total',
                'MATCH ()-[r]->() RETURN count(r) AS total',
            ], true) ? [['total' => 0]] : [];
        });
        $this->app->instance(Neo4jService::class, $neo4j);
        $this->withSession(['_token' => 'maintenance-test-token'])->post('/admin/profile', [
            '_token' => 'maintenance-test-token', 'sample' => 'nameIndex', 'name' => 'Nguyễn Minh Khang',
        ])
            ->assertOk()->assertSee('Không thể phân tích truy vấn.');
        $this->assertSame([
            'DROP INDEX person_name IF EXISTS',
            'CREATE INDEX person_name IF NOT EXISTS FOR (p:Person) ON (p.name)',
            'CALL db.awaitIndexes(30)',
        ], array_slice($calls, 0, 3));
    }

    public function test_local_reset_still_requires_explicit_confirmation(): void
    {
        $this->app->detectEnvironment(fn (): string => 'local');
        $neo4j = $this->createMock(Neo4jService::class);
        $neo4j->expects($this->never())->method('run');
        $neo4j->expects($this->never())->method('client');
        $this->app->instance(Neo4jService::class, $neo4j);
        $this->withSession(['_token' => 'reset-test-token'])->post('/admin/reset', ['_token' => 'reset-test-token'])
            ->assertRedirect()->assertSessionHasErrors('confirm_reset');
    }
}
