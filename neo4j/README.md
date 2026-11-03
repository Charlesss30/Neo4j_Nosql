# Family-tree database — Member A, milestone 1

This directory is the database contract for Member B. Use the queries in
`queries.cypher` verbatim and pass parameter arrays through `Neo4jService::run()`.
Milestone 1 contains no Laravel Console/Admin implementation.

## Files

| File | Purpose |
| --- | --- |
| `schema.cypher` | Unique `Person.id` constraint and `Person.name` index |
| `seed.cypher` | Idempotent fictional Nguyễn family: 30 people, 5 generations |
| `queries.cypher` | All 34 agreed queries, named blocks with parameters/returns |
| `import/persons.csv`, `import/relationships.csv` | The same fixture for CSV import |
| `load_csv.cypher` | Idempotent `LOAD CSV WITH HEADERS` import |
| `verify.md`, `verification-results.json` | Expected results and real execution evidence |
| `verify.py` | Repeatable real-database integration checks, Python standard library |
| `build_seed.py` | Regenerate seed and CSV from one fixture definition |
| `local_runtime.py` | Optional isolated native Windows runtime using existing binaries |

## Start with Docker

Run from the repository root with Docker Desktop running:

```powershell
# This is the public classroom demo credential, not a production secret.
$env:NEO4J_PASSWORD = 'familytree123'
docker compose up -d
docker compose ps
docker exec familytree-neo4j cypher-shell -u neo4j -p familytree123 -d neo4j -f /scripts/schema.cypher
docker exec familytree-neo4j cypher-shell -u neo4j -p familytree123 -d neo4j -f /scripts/seed.cypher
```

Expected seed verification: **persons = 30, parentOf = 36, spouseOf = 11**.
Open <http://localhost:7474> and connect to `bolt://localhost:7687`, database `neo4j`.
The graph is fictional. Both host ports bind only to loopback. Data is persisted in
the Compose volume `neo4j_data`; Cypher and CSV files are mounted read-only.

Compose requires `NEO4J_PASSWORD` explicitly, so no private password is stored in
the repository. The documented value is the shared, public demo fixture credential.
On Bash, use `export NEO4J_PASSWORD=familytree123` in place of the PowerShell assignment.

The application environment contract is:

```dotenv
NEO4J_URI=bolt://localhost:7687
NEO4J_USER=neo4j
NEO4J_PASSWORD=familytree123
NEO4J_DATABASE=neo4j
```

Member B owns `.env.example`, application configuration and `Neo4jService`.

## CSV import demonstration

Apply the schema, then import the CSV files already mounted under the Neo4j import directory:

```powershell
docker exec familytree-neo4j cypher-shell -u neo4j -p familytree123 -d neo4j -f /scripts/schema.cypher
docker exec familytree-neo4j cypher-shell -u neo4j -p familytree123 -d neo4j -f /scripts/load_csv.cypher
```

`file:///persons.csv` and `file:///relationships.csv` resolve on the **server**,
inside `/var/lib/neo4j/import`. Both files use UTF-8 and a header row. Blank optional
fields become null; Neo4j omits null properties. Relationships are imported after
all persons. Repeating either loader leaves the same 30 persons and 47 edges.

To demonstrate importing into an empty graph, first reset **only the dedicated demo
database**, then run schema + CSV import:

```powershell
docker exec familytree-neo4j cypher-shell -u neo4j -p familytree123 -d neo4j 'MATCH (n) DETACH DELETE n;'
docker exec familytree-neo4j cypher-shell -u neo4j -p familytree123 -d neo4j -f /scripts/schema.cypher
docker exec familytree-neo4j cypher-shell -u neo4j -p familytree123 -d neo4j -f /scripts/load_csv.cypher
```

Reset deletes all nodes and relationships in the targeted database; constraints and
indexes remain. To reset and seed instead, use `seed.cypher` for the last command.
`docker compose down` stops containers while retaining the named data volume.

## Native Windows — tested on this machine

Java **17 or 21** is required for Neo4j 5.26; the machine's default Java 26 should
not be used. The helper selects Java explicitly and uses the supplied Neo4j binaries,
with a separate data/log/config store under ignored `neo4j/.runtime/`. It does not
modify the existing Neo4j installation's `conf`, `data`, `import`, or password.
It refuses to start when ports 7474 or 7687 are already occupied.

Run these commands from the repository root:

```powershell
$python = 'D:\hoc-tap\LocalWP\laragon\bin\python\python-3.13\python.exe'
$neo4jHome = 'D:\hoc-tap\NoSQL\neo4j-community-5.26.31-windows\neo4j-community-5.26.31'
$javaHome = 'C:\Program Files\Java\jdk-17'
$env:NEO4J_PASSWORD = 'familytree123'
& $python neo4j/local_runtime.py start --neo4j-home $neo4jHome --java-home $javaHome
& $python neo4j/local_runtime.py shell --neo4j-home $neo4jHome --java-home $javaHome --file neo4j/schema.cypher
& $python neo4j/local_runtime.py shell --neo4j-home $neo4jHome --java-home $javaHome --file neo4j/seed.cypher
& $python neo4j/verify.py
```

The shell helper launches the installation's actual Cypher Shell Java entry point;
it avoids Windows `.bat` quoting problems and the default Java version. To demonstrate
CSV loading, pass `--file neo4j/load_csv.cypher` to the same shell command.

To stop only the process tree started by this helper:

```powershell
& $python neo4j/local_runtime.py stop --neo4j-home $neo4jHome --java-home $javaHome
```

The helper checks both the recorded PID and process creation time before stopping it.

## Reproduce verification

With a dedicated demo Neo4j database running and seeded:

```powershell
$env:NEO4J_PASSWORD = 'familytree123'
python neo4j/verify.py
```

Use the full Python path above if Python is not in PATH. To initialize a fresh
dedicated database, use `python neo4j/verify.py --prepare-demo`. This option refuses
unrelated node IDs, labels or relationship types. The runner contacts the real Neo4j
transactional endpoint at `http://127.0.0.1:7474`; pass `--url` to change the server.
It executes every catalog entry and validates the complete fixture, imports, write
helpers, uniqueness and idempotence. Temporary writes and the empty-graph import
test run inside transactions that are rolled back. The final graph is compared with
the original snapshot. It writes an evidence file without credentials.

To regenerate both fixture formats after editing `build_seed.py`:

```powershell
python neo4j/build_seed.py
```

## Query semantics and application handoff

- Only `Person`, `PARENT_OF` and `SPOUSE_OF` are stored. Parent edges always point
  from parent to child. Spouse lookups/removal/merge use an undirected pattern so
  reversed input cannot create a second stored edge for the same pair.
- `siblings` includes half-siblings; `uncles` includes blood uncles and aunts;
  `cousins` returns first cousins, excludes siblings and deduplicates shared ancestors.
- `generations` uses minimum directed parent-path length plus one. Married-in spouses
  get their spouse's generation. A person who is already a descendant keeps their
  own computed generation. `statsByGeneration` uses the same algorithm.
- `roots` includes every person without parents, including married-in spouses. Use
  `P001` as the clan's demo root instead of assuming the roots query has one result.
- `relationPath` searches both relationship types undirected. `relFrom` records each
  relationship's stored start ID. Equally short paths can vary; a self-path has length 0.
- `subtree` includes root, descendants and their direct spouses; edges are restricted
  to included endpoints. `graphAll` always returns one row, including for an empty graph.
- `oldestPerson` means the earliest birth year, including deceased persons.
- Every person result is a property map; graph/path results are lists of maps/scalars.
  Null properties are absent from `p {.*}` because Neo4j does not store null properties.
- Pagination inputs must be nonnegative integers. Text search is case insensitive
  `CONTAINS`; it does not remove Vietnamese accents. The name range index does not
  imply that `toLower(name) CONTAINS` will use an index seek.
- The application validates IDs, person fields, age gaps, the two-parent limit, cycle
  prevention and self-marriage. Check existence before calling the helpers. The
  low-level catalog writes retain exactly the agreed interfaces. Coordinate validation
  and writes transactionally; do not interpolate user input into query strings.

## Troubleshooting

| Symptom | Action |
| --- | --- |
| Docker command missing | Install/start Docker Desktop, or use the tested native helper |
| Port already occupied | Stop the intended old demo instance or explicitly choose other ports; do not start two servers on the same ports |
| Authentication failed after changing an environment variable | Existing stores retain their password; `NEO4J_AUTH` initializes a new store only |
| Java startup/class version errors | Select Java 17 or 21 explicitly; check `.runtime/console.log` |
| `Couldn't load the external resource` | Check the server import path and that both CSV files are present/mounted |
| CSV names are corrupted | Preserve UTF-8 when editing CSV; do not save with a legacy Windows code page |
| Duplicate IDs when creating the constraint | Inspect existing data; load this fixture into a dedicated empty database |
| Incorrect counts after seeding an existing graph | `MERGE` preserves unrelated data; inspect it or use the dedicated demo reset procedure |
| Native startup fails | Inspect `neo4j/.runtime/console.log` and `.runtime/logs`; original installation logs describe a different store |

Primary references: [Neo4j CSV import](https://neo4j.com/docs/getting-started/data-import/csv-import/),
[CSV file paths](https://neo4j.com/docs/data-ingestion-guide/ingestion-methods/load-csv/),
[Neo4j Java requirements](https://neo4j.com/docs/operations-manual/current/installation/requirements/).
