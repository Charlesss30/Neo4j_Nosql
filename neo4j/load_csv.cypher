// Run schema.cypher first. The import directory must contain both CSV files.
LOAD CSV WITH HEADERS FROM 'file:///persons.csv' AS row
MERGE (p:Person {id: row.id})
SET p.name = row.name, p.gender = row.gender,
    p.birthYear = toInteger(row.birthYear), p.deathYear = toInteger(row.deathYear),
    p.birthPlace = CASE WHEN trim(row.birthPlace) = '' THEN null ELSE row.birthPlace END,
    p.note = CASE WHEN trim(row.note) = '' THEN null ELSE row.note END;

LOAD CSV WITH HEADERS FROM 'file:///relationships.csv' AS row
WITH row WHERE row.type = 'PARENT_OF'
MATCH (parent:Person {id: row.from}), (child:Person {id: row.to})
MERGE (parent)-[:PARENT_OF]->(child);

LOAD CSV WITH HEADERS FROM 'file:///relationships.csv' AS row
WITH row WHERE row.type = 'SPOUSE_OF'
MATCH (a:Person {id: row.from}), (b:Person {id: row.to})
MERGE (a)-[r:SPOUSE_OF]-(b)
SET r.marriageYear = toInteger(row.marriageYear);

MATCH (p:Person) RETURN count(p) AS persons;
MATCH ()-[r:PARENT_OF]->() RETURN count(r) AS parentOf;
MATCH ()-[r:SPOUSE_OF]->() RETURN count(r) AS spouseOf;
