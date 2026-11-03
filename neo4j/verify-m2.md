# Member A — milestone 2 verification

## Delivered scope

- `GET /console`, `POST /console/run`: read-query editor, seven clickable examples,
  automatic result columns, JSON for nested values, timing and a 500-row display cap.
- `CypherGuard`: rejects writes, non-allowlisted `CALL`, multiple statements, empty
  queries and input longer than 2000 characters. Strings, quoted identifiers and
  comments are scanned before keywords are checked. `createdAt` is not mistaken
  for `CREATE`. `SHOW` commands stay in the Admin page.
- `GET /admin`: complete `SHOW CONSTRAINTS` and `SHOW INDEXES` output, total nodes
  and relationships, counts by node label and relationship type.
- `POST /admin/profile`: four fixed queries read from `queries.cypher`, plus the
  equality lookup comparison with and without `person_name`.
- `POST /admin/reset`: explicit confirmation and `APP_ENV=local` are required.
  Schema and seed are parsed before deletion; deletion and seed loading share one
  write transaction. A failed seed therefore rolls back the graph changes.

The implementation uses B's existing singleton `Neo4jService`; B's services,
layout, `routes/web.php`, Composer files and application controllers were not edited.
The existing conditional route include loads A's `routes/admin_console.php`.

## Automated validation

Environment: PHP 8.3.30, Laravel 13.39.0, Laudis Neo4j client 3.7.1,
Neo4j 5.26.31 Community with the complete milestone 1 fixture.

| Check | Result |
| --- | --- |
| Full Laravel suite | **72 tests passed, 318 assertions** |
| Existing B query contract | All 34 repository methods match the catalog verbatim and pass their named parameters |
| PHP formatting | Laravel Pint passed on A's PHP files |
| Blade compilation | `php artisan view:cache` succeeded |
| Guard coverage | Read queries; every required forbidden token; comments/literals; quoted identifiers; Unicode length; multiple statements; allowed/forbidden procedures |
| Console rendering | HTML escaping, nested JSON, no rows, backend errors and 501 returned / 500 displayed |
| Maintenance restrictions | Reset and index removal rejected outside `local`; local reset requires confirmation |
| Maintenance failure | Index recreation and `db.awaitIndexes(30)` still called after an injected failure |

The CLI suite uses mocks for database-dependent page branches. The separate HTTP
checks below use the actual running Laravel server and real Neo4j, including CSRF.

## Real HTTP verification

Raw results and execution timestamp are in
[`milestone2-results.json`](milestone2-results.json).

Verified through the local server at `http://127.0.0.1:8001`:

1. Loaded `/console`; ran all seven example queries successfully.
2. Accepted keywords inside a string and the property name `p.createdAt`.
3. Rejected a write, multiple statements and a non-allowlisted procedure with HTTP 422.
4. Returned 501 rows from `UNWIND range(1, 501)` and displayed only 500.
5. Loaded constraints, online indexes, 30 nodes, 47 relationships and per-type counts.
6. Ran all four catalog PROFILE samples through `Neo4jService::client()`.
7. Compared the identical parameterized equality lookup before and after index creation.
8. Checked that a reset without confirmation left the graph unchanged.
9. Confirmed reset and compared every person property and every edge with the original fixture.

The reset test first checked the complete live graph against both fixture CSV files.
It refused to reset a graph that differed from the fictional demo data.

### PROFILE measurements

| Sample | Actual result rows | Total db hits |
| --- | ---: | ---: |
| `getPerson('P010')` | 1 | 8 |
| `ancestors('P028')` | 8 | 107 |
| `cousins('P010')` | 5 | 324 |
| `topParents` | 5 | 263 |

The UI reads actual rows from `ProfiledQueryPlan::arguments->rows` first. Neo4j 5
supplies this metadata even when Laudis 3.7's legacy `records` property remains zero.
HTTP verification compares the displayed root row count with the actual query result.
No vendor or B service change was needed.

### Equality index comparison

```cypher
MATCH (p:Person)
WHERE p.name = $name
RETURN p {.*} AS person
```

Parameter: `name = Nguyễn Minh Khang`.

| State | Access operator | Rows at access operator | Result rows | Total db hits |
| --- | --- | ---: | ---: | ---: |
| Without `person_name` | `NodeByLabelScan` | 30 | 1 | 67 |
| With `person_name` | `NodeIndexSeek` | 1 | 1 | 8 |

The two runs returned the same person. The index was restored and verified `ONLINE`.
The before/after operation and reset share a maintenance lock. Index restoration
runs in `finally`. Both maintenance actions are limited to `APP_ENV=local`.
These numbers describe this 30-person fixture, not a general performance benchmark.

The client exposed real PROFILE data in this environment. If it does not provide a
profile, the page requests EXPLAIN and explicitly labels estimated rows and absent
actual db hits; it also shows the textual plan when available.

## Reproduce and review

Start and seed Neo4j using [`README.md`](README.md). From the repository root:

```powershell
composer install
php artisan config:clear
php artisan test --compact
php artisan serve --host=127.0.0.1 --port=8001
```

For a fresh clone, follow the application's root README to create `.env` and generate
an application key first. Local values must include:

```dotenv
APP_ENV=local
SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
NEO4J_URI=bolt://localhost:7687
NEO4J_USER=neo4j
NEO4J_PASSWORD=familytree123
NEO4J_DATABASE=neo4j
```

Open `/console` and `/admin` on the chosen port. Run an example, then try
`CREATE (:Person)` in Console and observe the Vietnamese rejection. In Admin,
run each PROFILE option and the equality index comparison. Only confirm reset when
the selected database contains disposable demo data.

The console displays the required note: “Bộ lọc từ khóa, không phải phân quyền thật.
Neo4j Community không hỗ trợ RBAC.” Its keyword filter is an application safeguard;
the connection's database credentials still determine the actual permissions.

Docker execution remains unverified on this machine because Docker is unavailable.
Milestone 2 was verified against the isolated native Neo4j store used for milestone 1.
