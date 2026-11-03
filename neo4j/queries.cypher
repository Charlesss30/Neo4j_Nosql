// @name getPerson
// @params $id
// @returns person
MATCH (p:Person {id: $id})
RETURN p {.*} AS person
;

// @name searchPersons
// @params $q, $skip, $limit
// @returns person
MATCH (p:Person)
WHERE toLower(p.name) CONTAINS toLower($q)
RETURN p {.*} AS person
ORDER BY person.name, person.id
SKIP $skip LIMIT $limit
;

// @name countPersons
// @params $q
// @returns total
MATCH (p:Person)
WHERE toLower(p.name) CONTAINS toLower($q)
RETURN count(p) AS total
;

// @name parents
// @params $id
// @returns person
MATCH (parent:Person)-[:PARENT_OF]->(:Person {id: $id})
RETURN DISTINCT parent {.*} AS person ORDER BY person.id
;

// @name children
// @params $id
// @returns person
MATCH (:Person {id: $id})-[:PARENT_OF]->(child:Person)
RETURN DISTINCT child {.*} AS person ORDER BY person.id
;

// @name siblings
// @params $id
// @returns person
MATCH (p:Person {id: $id})<-[:PARENT_OF]-(:Person)-[:PARENT_OF]->(sibling:Person)
WHERE sibling <> p
RETURN DISTINCT sibling {.*} AS person ORDER BY person.id
;

// @name uncles
// @params $id
// @returns person
MATCH (p:Person {id: $id})<-[:PARENT_OF]-(parent:Person)
      <-[:PARENT_OF]-(:Person)-[:PARENT_OF]->(relative:Person)
WHERE relative <> parent AND relative <> p
RETURN DISTINCT relative {.*} AS person ORDER BY person.id
;

// @name cousins
// @params $id
// @returns person
MATCH (p:Person {id: $id})<-[:PARENT_OF]-(parent:Person)
      <-[:PARENT_OF]-(:Person)-[:PARENT_OF]->(uncle:Person)-[:PARENT_OF]->(cousin:Person)
WHERE uncle <> parent AND cousin <> p
  AND NOT EXISTS { MATCH (p)<-[:PARENT_OF]-(:Person)-[:PARENT_OF]->(cousin) }
RETURN DISTINCT cousin {.*} AS person ORDER BY person.id
;

// @name ancestors
// @params $id
// @returns person
MATCH (ancestor:Person)-[:PARENT_OF*1..]->(:Person {id: $id})
RETURN DISTINCT ancestor {.*} AS person ORDER BY person.id
;

// @name descendants
// @params $id
// @returns person
MATCH (:Person {id: $id})-[:PARENT_OF*1..]->(descendant:Person)
RETURN DISTINCT descendant {.*} AS person ORDER BY person.id
;

// @name spouses
// @params $id
// @returns person, marriageYear
MATCH (:Person {id: $id})-[r:SPOUSE_OF]-(spouse:Person)
RETURN spouse {.*} AS person, r.marriageYear AS marriageYear ORDER BY person.id
;

// @name relationPath
// @params $a, $b
// @returns nodes, relTypes, relFrom
MATCH (a:Person {id: $a}), (b:Person {id: $b})
MATCH path = shortestPath((a)-[:PARENT_OF|SPOUSE_OF*0..]-(b))
RETURN [p IN nodes(path) | p {.id, .name}] AS nodes,
       [r IN relationships(path) | type(r)] AS relTypes,
       [r IN relationships(path) | startNode(r).id] AS relFrom
;

// @name generations
// @params $root
// @returns person, generation
MATCH path = (:Person {id: $root})-[:PARENT_OF*0..]->(p:Person)
WITH p, min(length(path)) + 1 AS ownGeneration
WITH collect({member: p, generation: ownGeneration}) AS members
UNWIND members AS member
WITH member.member AS memberPerson, member.generation AS candidate, members
UNWIND [memberPerson] + [(memberPerson)-[:SPOUSE_OF]-(spouse:Person) | spouse] AS p
WITH p, min(coalesce(head([m IN members WHERE m.member = p | m.generation]), candidate)) AS generation
RETURN p {.*} AS person, generation ORDER BY generation, person.id
;

// @name statsByGender
// @params
// @returns gender, total
MATCH (p:Person)
RETURN p.gender AS gender, count(*) AS total ORDER BY gender
;

// @name statsByGeneration
// @params $root
// @returns generation, total
MATCH path = (:Person {id: $root})-[:PARENT_OF*0..]->(p:Person)
WITH p, min(length(path)) + 1 AS ownGeneration
WITH collect({member: p, generation: ownGeneration}) AS members
UNWIND members AS member
WITH member.member AS memberPerson, member.generation AS candidate, members
UNWIND [memberPerson] + [(memberPerson)-[:SPOUSE_OF]-(spouse:Person) | spouse] AS p
WITH p, min(coalesce(head([m IN members WHERE m.member = p | m.generation]), candidate)) AS generation
RETURN generation, count(*) AS total ORDER BY generation
;

// @name topParents
// @params
// @returns id, name, childCount
MATCH (p:Person)-[:PARENT_OF]->(child:Person)
RETURN p.id AS id, p.name AS name, count(DISTINCT child) AS childCount
ORDER BY childCount DESC, id LIMIT 5
;

// @name livingVsDeceased
// @params
// @returns living, deceased
MATCH (p:Person)
RETURN count(CASE WHEN p.deathYear IS NULL THEN 1 END) AS living,
       count(p.deathYear) AS deceased
;

// @name oldestPerson
// @params
// @returns person
MATCH (p:Person)
WHERE p.birthYear IS NOT NULL
RETURN p {.*} AS person ORDER BY person.birthYear, person.id LIMIT 1
;

// @name roots
// @params
// @returns person
MATCH (p:Person)
WHERE NOT ()-[:PARENT_OF]->(p)
RETURN p {.*} AS person ORDER BY person.id
;

// @name graphAll
// @params
// @returns nodes, edges
MATCH (p:Person)
WITH p ORDER BY p.id
WITH collect(p {.*}) AS nodes
OPTIONAL MATCH (a:Person)-[r:PARENT_OF|SPOUSE_OF]->(b:Person)
WITH nodes, a, r, b ORDER BY a.id, b.id, type(r)
RETURN nodes, collect(CASE WHEN r IS NOT NULL THEN
  {from: a.id, to: b.id, type: type(r), marriageYear: r.marriageYear} END) AS edges
;

// @name subtree
// @params $root
// @returns nodes, edges
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
;

// @name parentCount
// @params $id
// @returns total
MATCH (:Person)-[r:PARENT_OF]->(:Person {id: $id})
RETURN count(r) AS total
;

// @name wouldCreateCycle
// @params $parentId, $childId
// @returns exists
RETURN EXISTS {
  MATCH (:Person {id: $childId})-[:PARENT_OF*0..]->(:Person {id: $parentId})
} AS exists
;

// @name parentExists
// @params $parentId, $childId
// @returns exists
RETURN EXISTS {
  MATCH (:Person {id: $parentId})-[:PARENT_OF]->(:Person {id: $childId})
} AS exists
;

// @name spouseExists
// @params $a, $b
// @returns exists
RETURN EXISTS { MATCH (:Person {id: $a})-[:SPOUSE_OF]-(:Person {id: $b}) } AS exists
;

// @name maxPersonNumber
// @params
// @returns maxNum
MATCH (p:Person)
RETURN coalesce(max(toInteger(substring(p.id, 1))), 0) AS maxNum
;

// @name relationshipCount
// @params $id
// @returns total
MATCH (:Person {id: $id})-[r]-()
RETURN count(r) AS total
;

// @name createPerson
// @params $id, $name, $gender, $birthYear, $deathYear, $birthPlace, $note
// @returns person
CREATE (p:Person {id: $id, name: $name, gender: $gender, birthYear: $birthYear,
                  deathYear: $deathYear, birthPlace: $birthPlace, note: $note})
RETURN p {.*} AS person
;

// @name updatePerson
// @params $id, $name, $gender, $birthYear, $deathYear, $birthPlace, $note
// @returns person
MATCH (p:Person {id: $id})
SET p.name = $name, p.gender = $gender, p.birthYear = $birthYear,
    p.deathYear = $deathYear, p.birthPlace = $birthPlace, p.note = $note
RETURN p {.*} AS person
;

// @name deletePerson
// @params $id
// @returns
MATCH (p:Person {id: $id}) DETACH DELETE p
;

// @name addParentOf
// @params $parentId, $childId
// @returns
MATCH (parent:Person {id: $parentId}), (child:Person {id: $childId})
MERGE (parent)-[:PARENT_OF]->(child)
;

// @name addSpouseOf
// @params $a, $b, $marriageYear
// @returns
MATCH (a:Person {id: $a}), (b:Person {id: $b})
MERGE (a)-[r:SPOUSE_OF]-(b)
SET r.marriageYear = $marriageYear
;

// @name removeParentOf
// @params $parentId, $childId
// @returns
MATCH (:Person {id: $parentId})-[r:PARENT_OF]->(:Person {id: $childId}) DELETE r
;

// @name removeSpouseOf
// @params $a, $b
// @returns
MATCH (:Person {id: $a})-[r:SPOUSE_OF]-(:Person {id: $b}) DELETE r
;
