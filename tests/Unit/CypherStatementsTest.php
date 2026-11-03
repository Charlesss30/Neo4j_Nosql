<?php

namespace Tests\Unit;

use App\Services\CypherStatements;
use PHPUnit\Framework\TestCase;

class CypherStatementsTest extends TestCase
{
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
        $statements = (new CypherStatements)->splitStatements($source);
        $this->assertCount(4, $statements);
        $this->assertStringContainsString("'A; B'", $statements[0]);
        $this->assertStringContainsString("'it\\'s; fine'", $statements[1]);
        $this->assertStringContainsString("'it''s; fine'", $statements[2]);
        $this->assertStringContainsString('`semi;colon`', $statements[3]);
        $this->assertSame([], (new CypherStatements)->splitStatements('// Comment only;'));
        $this->assertCount(6, (new CypherStatements)->splitStatements(file_get_contents(dirname(__DIR__, 2).'/neo4j/seed.cypher')));
    }
}
