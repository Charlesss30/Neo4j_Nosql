# Milestone 1 — actual Neo4j verification

Verified on **Neo4j 5.26.31 Community**, Java 17, against a dedicated local store.
The machine's existing Neo4j installation and original `data` directory were not changed.
Schema and seed were loaded through Cypher Shell. The integration runner executes the
catalog with parameters against Neo4j's transactional HTTP endpoint, not a mock.

Run timestamp, server version and every assertion are recorded in
[`verification-results.json`](verification-results.json). Reproduce with the commands
in [`README.md`](README.md).

## Fixture totals

| Check | Expected and observed |
| --- | --- |
| Persons | 30 |
| `PARENT_OF` (parent → child) | 36 |
| `SPOUSE_OF` (one stored direction) | 11 |
| Generations from `P001` | 1: 2; 2: 7; 3: 11; 4: 7; 5: 3 |
| Gender | F: 16; M: 14 |
| Living / deceased | 25 / 5 |
| Catalog coverage | **34/34 queries executed** |
| Assertions in initial successful run | **245 passed** |

All parents are at least 15 years older than their children. Each child has at
most two parents. No ancestry cycles, duplicate parent edges, duplicate spouse pairs,
invalid genders or children born after their parent's death were found.

## Three chosen people

IDs below are complete expected results, in catalog order. They were compared with
actual rows; map properties were checked separately against the CSV fixture.
`uncles` includes blood-related uncles and aunts; spouses of those relatives are excluded.
`siblings` includes half-siblings. `cousins` means first cousins and excludes siblings.

| Query | P010 — Nguyễn Minh Khang | P012 — Phạm Minh Nam | P014 — Nguyễn Quốc Phúc |
| --- | --- | --- | --- |
| `siblings($id)` | P011 | P013 | P015, P016 |
| `uncles($id)` | P004, P005 | P003, P005 | P003, P004 |
| `cousins($id)` | P012, P013, P014, P015, P016 | P010, P011, P014, P015, P016 | P010, P011, P012, P013 |
| `ancestors($id)` | P001, P002, P003, P006 | P001, P002, P004, P007 | P001, P002, P005, P008 |
| `generations('P001')` for this person | 3 | 3 | 3 |

P016 is a half-sibling of P014 and P015: they share P005, but P016's mother is
P009 while the other two have mother P008. Both marriages of P005 are returned:
P008 / 1973 and P009 / 2001.

## Paths and generations

| Query | Expected and verified |
| --- | --- |
| `relationPath('P010','P012')` | 5 node maps, 4 `PARENT_OF` relationships; endpoints P010/P012; common grandparent P001 **or** P002 |
| `relationPath('P010','P014')` | 5 node maps, 4 `PARENT_OF` relationships; endpoints P010/P014; common grandparent P001 **or** P002 |
| `relationPath('P010','P010')` | One node; empty `relTypes` and `relFrom` |
| Path to a missing or disconnected person | No row |
| `generations('P001')` generation 1 | P001, P002 |
| generation 2 | P003–P009 |
| generation 3 | P010–P020 |
| generation 4 | P021–P027 |
| generation 5 | P028, P029, P030 |
| `subtree('P010')` | P010, P017, P021, P022, P026, P028, P029; 10 internal edges |
| `subtree('P001')` | Exactly the same nodes and edges as `graphAll` |

There are equally short paths through P001 and P002. Tests accept either instead
of depending on Neo4j's unspecified tie selection. For a path
`[P010, P003, grandparent, P004, P012]`, `relFrom` must be
`[P003, grandparent, grandparent, P004]`. This verifies the stored direction.

## Write-rule helpers

| Query / action | Expected and observed |
| --- | --- |
| `wouldCreateCycle('P010','P010')` | true — self-parenting |
| `wouldCreateCycle('P021','P001')` | true — adding descendant → ancestor |
| `wouldCreateCycle('P001','P028')` | false |
| `wouldCreateCycle('P017','P001')` | false |
| `parentCount('P014')` / `parentCount('P006')` | 2 / 0 |
| `parentExists('P001','P003')` / reversed | true / false |
| `spouseExists('P005','P008')` / reversed | true / true |
| `spouseExists('P005','P009')` | true |
| `spouseExists('P010','P012')` | false |
| Duplicate `createPerson` with P001 | Rejected by `person_id` uniqueness constraint |
| `createPerson`, `updatePerson` | Correct property maps; nullable properties removed when set to null |
| Repeating `addParentOf` | One edge, not two |
| Repeating `addSpouseOf` with reversed endpoints | One stored edge, not two |
| `removeSpouseOf` with reversed endpoints | Relationship removed |
| `removeParentOf` | Relationship removed |
| `deletePerson` with attached edges | Person and incident edges removed; other person retained |

Catalog write queries are low-level operations. Member B must check person
existence, gender/year inputs, parent count and cycles before accepting writes,
and coordinate checks and writes in a transaction. Neo4j Community does not enforce
these family rules through the two schema declarations. The catalog deliberately
does not change the agreed write interfaces.

## Repeated loading and regression checks

- Schema was applied twice; the index was verified `ONLINE`.
- Seed was applied twice; every person property and every edge remained identical.
- In a transaction, the demo graph was emptied, loaded with `LOAD CSV`, and compared
  with the complete seed snapshot. CSV was loaded twice to verify idempotence.
- All write/import experiments were rolled back; the original demo graph was
  compared again after rollback.
- Empty `graphAll` returns one row with `nodes: []`, `edges: []`;
  empty `maxPersonNumber` returns `0`.
- Search covered empty input, ordering/pagination, mixed case and an injection-like
  string supplied as a parameter.
- All 34 catalog entries had their parameter names and returned column names checked.

Docker configuration is supplied but was **not executed on this machine**, because
Docker is unavailable. Runtime verification used the supplied native Neo4j 5.26.31.
