<?php

namespace App\Services;

class FamilyRepository
{
    /** Cypher copied verbatim from Member A's named query catalog. */
    private const QUERIES = [
        'getPerson' => <<<'CYPHER_GETPERSON'
MATCH (p:Person {id: $id})
RETURN p {.*} AS person
CYPHER_GETPERSON,
        'searchPersons' => <<<'CYPHER_SEARCHPERSONS'
MATCH (p:Person)
WHERE toLower(p.name) CONTAINS toLower($q)
RETURN p {.*} AS person
ORDER BY person.name, person.id
SKIP $skip LIMIT $limit
CYPHER_SEARCHPERSONS,
        'countPersons' => <<<'CYPHER_COUNTPERSONS'
MATCH (p:Person)
WHERE toLower(p.name) CONTAINS toLower($q)
RETURN count(p) AS total
CYPHER_COUNTPERSONS,
        'parents' => <<<'CYPHER_PARENTS'
MATCH (parent:Person)-[:PARENT_OF]->(:Person {id: $id})
RETURN DISTINCT parent {.*} AS person ORDER BY person.id
CYPHER_PARENTS,
        'children' => <<<'CYPHER_CHILDREN'
MATCH (:Person {id: $id})-[:PARENT_OF]->(child:Person)
RETURN DISTINCT child {.*} AS person ORDER BY person.id
CYPHER_CHILDREN,
        'siblings' => <<<'CYPHER_SIBLINGS'
MATCH (p:Person {id: $id})<-[:PARENT_OF]-(:Person)-[:PARENT_OF]->(sibling:Person)
WHERE sibling <> p
RETURN DISTINCT sibling {.*} AS person ORDER BY person.id
CYPHER_SIBLINGS,
        'uncles' => <<<'CYPHER_UNCLES'
MATCH (p:Person {id: $id})<-[:PARENT_OF]-(parent:Person)
      <-[:PARENT_OF]-(:Person)-[:PARENT_OF]->(relative:Person)
WHERE relative <> parent AND relative <> p
RETURN DISTINCT relative {.*} AS person ORDER BY person.id
CYPHER_UNCLES,
        'cousins' => <<<'CYPHER_COUSINS'
MATCH (p:Person {id: $id})<-[:PARENT_OF]-(parent:Person)
      <-[:PARENT_OF]-(:Person)-[:PARENT_OF]->(uncle:Person)-[:PARENT_OF]->(cousin:Person)
WHERE uncle <> parent AND cousin <> p
  AND NOT EXISTS { MATCH (p)<-[:PARENT_OF]-(:Person)-[:PARENT_OF]->(cousin) }
RETURN DISTINCT cousin {.*} AS person ORDER BY person.id
CYPHER_COUSINS,
        'ancestors' => <<<'CYPHER_ANCESTORS'
MATCH (ancestor:Person)-[:PARENT_OF*1..]->(:Person {id: $id})
RETURN DISTINCT ancestor {.*} AS person ORDER BY person.id
CYPHER_ANCESTORS,
        'descendants' => <<<'CYPHER_DESCENDANTS'
MATCH (:Person {id: $id})-[:PARENT_OF*1..]->(descendant:Person)
RETURN DISTINCT descendant {.*} AS person ORDER BY person.id
CYPHER_DESCENDANTS,
        'spouses' => <<<'CYPHER_SPOUSES'
MATCH (:Person {id: $id})-[r:SPOUSE_OF]-(spouse:Person)
RETURN spouse {.*} AS person, r.marriageYear AS marriageYear ORDER BY person.id
CYPHER_SPOUSES,
        'relationPath' => <<<'CYPHER_RELATIONPATH'
MATCH (a:Person {id: $a}), (b:Person {id: $b})
MATCH path = shortestPath((a)-[:PARENT_OF|SPOUSE_OF*0..]-(b))
RETURN [p IN nodes(path) | p {.id, .name}] AS nodes,
       [r IN relationships(path) | type(r)] AS relTypes,
       [r IN relationships(path) | startNode(r).id] AS relFrom
CYPHER_RELATIONPATH,
        'generations' => <<<'CYPHER_GENERATIONS'
MATCH path = (:Person {id: $root})-[:PARENT_OF*0..]->(p:Person)
WITH p, min(length(path)) + 1 AS ownGeneration
WITH collect({member: p, generation: ownGeneration}) AS members
UNWIND members AS member
WITH member.member AS memberPerson, member.generation AS candidate, members
UNWIND [memberPerson] + [(memberPerson)-[:SPOUSE_OF]-(spouse:Person) | spouse] AS p
WITH p, min(coalesce(head([m IN members WHERE m.member = p | m.generation]), candidate)) AS generation
RETURN p {.*} AS person, generation ORDER BY generation, person.id
CYPHER_GENERATIONS,
        'statsByGender' => <<<'CYPHER_STATSBYGENDER'
MATCH (p:Person)
RETURN p.gender AS gender, count(*) AS total ORDER BY gender
CYPHER_STATSBYGENDER,
        'statsByGeneration' => <<<'CYPHER_STATSBYGENERATION'
MATCH path = (:Person {id: $root})-[:PARENT_OF*0..]->(p:Person)
WITH p, min(length(path)) + 1 AS ownGeneration
WITH collect({member: p, generation: ownGeneration}) AS members
UNWIND members AS member
WITH member.member AS memberPerson, member.generation AS candidate, members
UNWIND [memberPerson] + [(memberPerson)-[:SPOUSE_OF]-(spouse:Person) | spouse] AS p
WITH p, min(coalesce(head([m IN members WHERE m.member = p | m.generation]), candidate)) AS generation
RETURN generation, count(*) AS total ORDER BY generation
CYPHER_STATSBYGENERATION,
        'topParents' => <<<'CYPHER_TOPPARENTS'
MATCH (p:Person)-[:PARENT_OF]->(child:Person)
RETURN p.id AS id, p.name AS name, count(DISTINCT child) AS childCount
ORDER BY childCount DESC, id LIMIT 5
CYPHER_TOPPARENTS,
        'livingVsDeceased' => <<<'CYPHER_LIVINGVSDECEASED'
MATCH (p:Person)
RETURN count(CASE WHEN p.deathYear IS NULL THEN 1 END) AS living,
       count(p.deathYear) AS deceased
CYPHER_LIVINGVSDECEASED,
        'oldestPerson' => <<<'CYPHER_OLDESTPERSON'
MATCH (p:Person)
WHERE p.birthYear IS NOT NULL
RETURN p {.*} AS person ORDER BY person.birthYear, person.id LIMIT 1
CYPHER_OLDESTPERSON,
        'roots' => <<<'CYPHER_ROOTS'
MATCH (p:Person)
WHERE NOT ()-[:PARENT_OF]->(p)
RETURN p {.*} AS person ORDER BY person.id
CYPHER_ROOTS,
        'graphAll' => <<<'CYPHER_GRAPHALL'
MATCH (p:Person)
WITH p ORDER BY p.id
WITH collect(p {.*}) AS nodes
OPTIONAL MATCH (a:Person)-[r:PARENT_OF|SPOUSE_OF]->(b:Person)
WITH nodes, a, r, b ORDER BY a.id, b.id, type(r)
RETURN nodes, collect(CASE WHEN r IS NOT NULL THEN
  {from: a.id, to: b.id, type: type(r), marriageYear: r.marriageYear} END) AS edges
CYPHER_GRAPHALL,
        'subtree' => <<<'CYPHER_SUBTREE'
MATCH (:Person {id: $root})-[:PARENT_OF*0..]->(p:Person)
WITH collect(DISTINCT p) AS descendants
UNWIND descendants AS p
OPTIONAL MATCH (p)-[:SPOUSE_OF]-(spouse:Person)
WITH descendants, collect(DISTINCT spouse) AS spouses
UNWIND descendants + spouses AS p
WITH DISTINCT p ORDER BY p.id
WITH collect(p) AS included
OPTIONAL MATCH (a:Person)-[r:PARENT_OF|SPOUSE_OF]->(b:Person)
WHERE a IN included AND b IN included
WITH included, a, r, b ORDER BY a.id, b.id, type(r)
RETURN [p IN included | p {.*}] AS nodes,
       collect(CASE WHEN r IS NOT NULL THEN
         {from: a.id, to: b.id, type: type(r), marriageYear: r.marriageYear} END) AS edges
CYPHER_SUBTREE,
        'parentCount' => <<<'CYPHER_PARENTCOUNT'
MATCH (:Person)-[r:PARENT_OF]->(:Person {id: $id})
RETURN count(r) AS total
CYPHER_PARENTCOUNT,
        'wouldCreateCycle' => <<<'CYPHER_WOULDCREATECYCLE'
RETURN EXISTS {
  MATCH (:Person {id: $childId})-[:PARENT_OF*0..]->(:Person {id: $parentId})
} AS exists
CYPHER_WOULDCREATECYCLE,
        'parentExists' => <<<'CYPHER_PARENTEXISTS'
RETURN EXISTS {
  MATCH (:Person {id: $parentId})-[:PARENT_OF]->(:Person {id: $childId})
} AS exists
CYPHER_PARENTEXISTS,
        'spouseExists' => <<<'CYPHER_SPOUSEEXISTS'
RETURN EXISTS { MATCH (:Person {id: $a})-[:SPOUSE_OF]-(:Person {id: $b}) } AS exists
CYPHER_SPOUSEEXISTS,
        'maxPersonNumber' => <<<'CYPHER_MAXPERSONNUMBER'
MATCH (p:Person)
RETURN coalesce(max(toInteger(substring(p.id, 1))), 0) AS maxNum
CYPHER_MAXPERSONNUMBER,
        'relationshipCount' => <<<'CYPHER_RELATIONSHIPCOUNT'
MATCH (:Person {id: $id})-[r]-()
RETURN count(r) AS total
CYPHER_RELATIONSHIPCOUNT,
        'createPerson' => <<<'CYPHER_CREATEPERSON'
CREATE (p:Person {id: $id, name: $name, gender: $gender, birthYear: $birthYear,
                  deathYear: $deathYear, birthPlace: $birthPlace, note: $note})
RETURN p {.*} AS person
CYPHER_CREATEPERSON,
        'updatePerson' => <<<'CYPHER_UPDATEPERSON'
MATCH (p:Person {id: $id})
SET p.name = $name, p.gender = $gender, p.birthYear = $birthYear,
    p.deathYear = $deathYear, p.birthPlace = $birthPlace, p.note = $note
RETURN p {.*} AS person
CYPHER_UPDATEPERSON,
        'deletePerson' => <<<'CYPHER_DELETEPERSON'
MATCH (p:Person {id: $id}) DETACH DELETE p
CYPHER_DELETEPERSON,
        'addParentOf' => <<<'CYPHER_ADDPARENTOF'
MATCH (parent:Person {id: $parentId}), (child:Person {id: $childId})
MERGE (parent)-[:PARENT_OF]->(child)
CYPHER_ADDPARENTOF,
        'addSpouseOf' => <<<'CYPHER_ADDSPOUSEOF'
MATCH (a:Person {id: $a}), (b:Person {id: $b})
MERGE (a)-[r:SPOUSE_OF]-(b)
SET r.marriageYear = $marriageYear
CYPHER_ADDSPOUSEOF,
        'removeParentOf' => <<<'CYPHER_REMOVEPARENTOF'
MATCH (:Person {id: $parentId})-[r:PARENT_OF]->(:Person {id: $childId}) DELETE r
CYPHER_REMOVEPARENTOF,
        'removeSpouseOf' => <<<'CYPHER_REMOVESPOUSEOF'
MATCH (:Person {id: $a})-[r:SPOUSE_OF]-(:Person {id: $b}) DELETE r
CYPHER_REMOVESPOUSEOF,
    ];

    public function __construct(private readonly Neo4jService $neo4j) {}

    /** @return list<array<string, mixed>> */
    public function getPerson(string $id): array
    {
        return $this->neo4j->run(self::QUERIES['getPerson'], compact('id'));
    }

    /** @return list<array<string, mixed>> */
    public function searchPersons(string $q, int $skip, int $limit): array
    {
        return $this->neo4j->run(self::QUERIES['searchPersons'], compact('q', 'skip', 'limit'));
    }

    /** @return list<array<string, mixed>> */
    public function countPersons(string $q): array
    {
        return $this->neo4j->run(self::QUERIES['countPersons'], compact('q'));
    }

    /** @return list<array<string, mixed>> */
    public function parents(string $id): array
    {
        return $this->neo4j->run(self::QUERIES['parents'], compact('id'));
    }

    /** @return list<array<string, mixed>> */
    public function children(string $id): array
    {
        return $this->neo4j->run(self::QUERIES['children'], compact('id'));
    }

    /** @return list<array<string, mixed>> */
    public function siblings(string $id): array
    {
        return $this->neo4j->run(self::QUERIES['siblings'], compact('id'));
    }

    /** @return list<array<string, mixed>> */
    public function uncles(string $id): array
    {
        return $this->neo4j->run(self::QUERIES['uncles'], compact('id'));
    }

    /** @return list<array<string, mixed>> */
    public function cousins(string $id): array
    {
        return $this->neo4j->run(self::QUERIES['cousins'], compact('id'));
    }

    /** @return list<array<string, mixed>> */
    public function ancestors(string $id): array
    {
        return $this->neo4j->run(self::QUERIES['ancestors'], compact('id'));
    }

    /** @return list<array<string, mixed>> */
    public function descendants(string $id): array
    {
        return $this->neo4j->run(self::QUERIES['descendants'], compact('id'));
    }

    /** @return list<array<string, mixed>> */
    public function spouses(string $id): array
    {
        return $this->neo4j->run(self::QUERIES['spouses'], compact('id'));
    }

    /** @return list<array<string, mixed>> */
    public function relationPath(string $a, string $b): array
    {
        return $this->neo4j->run(self::QUERIES['relationPath'], compact('a', 'b'));
    }

    /** @return list<array<string, mixed>> */
    public function generations(string $root): array
    {
        return $this->neo4j->run(self::QUERIES['generations'], compact('root'));
    }

    /** @return list<array<string, mixed>> */
    public function statsByGender(): array
    {
        return $this->neo4j->run(self::QUERIES['statsByGender']);
    }

    /** @return list<array<string, mixed>> */
    public function statsByGeneration(string $root): array
    {
        return $this->neo4j->run(self::QUERIES['statsByGeneration'], compact('root'));
    }

    /** @return list<array<string, mixed>> */
    public function topParents(): array
    {
        return $this->neo4j->run(self::QUERIES['topParents']);
    }

    /** @return list<array<string, mixed>> */
    public function livingVsDeceased(): array
    {
        return $this->neo4j->run(self::QUERIES['livingVsDeceased']);
    }

    /** @return list<array<string, mixed>> */
    public function oldestPerson(): array
    {
        return $this->neo4j->run(self::QUERIES['oldestPerson']);
    }

    /** @return list<array<string, mixed>> */
    public function roots(): array
    {
        return $this->neo4j->run(self::QUERIES['roots']);
    }

    /** @return list<array<string, mixed>> */
    public function graphAll(): array
    {
        return $this->neo4j->run(self::QUERIES['graphAll']);
    }

    /** @return list<array<string, mixed>> */
    public function subtree(string $root): array
    {
        return $this->neo4j->run(self::QUERIES['subtree'], compact('root'));
    }

    /** @return list<array<string, mixed>> */
    public function parentCount(string $id): array
    {
        return $this->neo4j->run(self::QUERIES['parentCount'], compact('id'));
    }

    /** @return list<array<string, mixed>> */
    public function wouldCreateCycle(string $parentId, string $childId): array
    {
        return $this->neo4j->run(self::QUERIES['wouldCreateCycle'], compact('parentId', 'childId'));
    }

    /** @return list<array<string, mixed>> */
    public function parentExists(string $parentId, string $childId): array
    {
        return $this->neo4j->run(self::QUERIES['parentExists'], compact('parentId', 'childId'));
    }

    /** @return list<array<string, mixed>> */
    public function spouseExists(string $a, string $b): array
    {
        return $this->neo4j->run(self::QUERIES['spouseExists'], compact('a', 'b'));
    }

    /** @return list<array<string, mixed>> */
    public function maxPersonNumber(): array
    {
        return $this->neo4j->run(self::QUERIES['maxPersonNumber']);
    }

    /** @return list<array<string, mixed>> */
    public function relationshipCount(string $id): array
    {
        return $this->neo4j->run(self::QUERIES['relationshipCount'], compact('id'));
    }

    /** @return list<array<string, mixed>> */
    public function createPerson(string $id, string $name, string $gender, int $birthYear, ?int $deathYear, ?string $birthPlace, ?string $note): array
    {
        return $this->neo4j->run(self::QUERIES['createPerson'], compact('id', 'name', 'gender', 'birthYear', 'deathYear', 'birthPlace', 'note'));
    }

    /** @return list<array<string, mixed>> */
    public function updatePerson(string $id, string $name, string $gender, int $birthYear, ?int $deathYear, ?string $birthPlace, ?string $note): array
    {
        return $this->neo4j->run(self::QUERIES['updatePerson'], compact('id', 'name', 'gender', 'birthYear', 'deathYear', 'birthPlace', 'note'));
    }

    /** @return list<array<string, mixed>> */
    public function deletePerson(string $id): array
    {
        return $this->neo4j->run(self::QUERIES['deletePerson'], compact('id'));
    }

    /** @return list<array<string, mixed>> */
    public function addParentOf(string $parentId, string $childId): array
    {
        return $this->neo4j->run(self::QUERIES['addParentOf'], compact('parentId', 'childId'));
    }

    /** @return list<array<string, mixed>> */
    public function addSpouseOf(string $a, string $b, ?int $marriageYear): array
    {
        return $this->neo4j->run(self::QUERIES['addSpouseOf'], compact('a', 'b', 'marriageYear'));
    }

    /** @return list<array<string, mixed>> */
    public function removeParentOf(string $parentId, string $childId): array
    {
        return $this->neo4j->run(self::QUERIES['removeParentOf'], compact('parentId', 'childId'));
    }

    /** @return list<array<string, mixed>> */
    public function removeSpouseOf(string $a, string $b): array
    {
        return $this->neo4j->run(self::QUERIES['removeSpouseOf'], compact('a', 'b'));
    }
}
