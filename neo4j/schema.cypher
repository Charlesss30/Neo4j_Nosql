CREATE CONSTRAINT person_id IF NOT EXISTS FOR (p:Person) REQUIRE p.id IS UNIQUE;
CREATE INDEX person_name IF NOT EXISTS FOR (p:Person) ON (p.name);
