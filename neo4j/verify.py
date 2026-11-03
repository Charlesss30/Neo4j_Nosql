"""Integration checks against a real, dedicated Neo4j demo database (stdlib only)."""

import argparse
import base64
import csv
import json
import os
import re
import time
import urllib.error
import urllib.request
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parent
CATALOG_NAMES = {
    "getPerson", "searchPersons", "countPersons", "parents", "children", "siblings",
    "uncles", "cousins", "ancestors", "descendants", "spouses", "relationPath",
    "generations", "statsByGender", "statsByGeneration", "topParents", "livingVsDeceased",
    "oldestPerson", "roots", "graphAll", "subtree", "parentCount", "wouldCreateCycle",
    "parentExists", "spouseExists", "maxPersonNumber", "relationshipCount", "createPerson",
    "updatePerson", "deletePerson", "addParentOf", "addSpouseOf", "removeParentOf", "removeSpouseOf",
}


def statements(text):
    """Split statements, preserving semicolons in strings and removing comments."""
    parts, buffer, quote = [], [], None
    index = 0
    while index < len(text):
        char = text[index]
        pair = text[index:index + 2]
        if quote:
            buffer.append(char)
            if char == "\\" and index + 1 < len(text):
                index += 1
                buffer.append(text[index])
            elif char == quote:
                if index + 1 < len(text) and text[index + 1] == quote:
                    index += 1
                    buffer.append(text[index])
                else:
                    quote = None
        elif pair == "//":
            end = text.find("\n", index)
            index = len(text) if end < 0 else end
            buffer.append("\n")
            continue
        elif pair == "/*":
            end = text.find("*/", index + 2)
            if end < 0:
                raise ValueError("Unclosed block comment")
            index = end + 2
            buffer.append(" ")
            continue
        elif char in "'\"`":
            quote = char
            buffer.append(char)
        elif char == ";":
            if "".join(buffer).strip():
                parts.append("".join(buffer).strip())
            buffer = []
        else:
            buffer.append(char)
        index += 1
    if quote:
        raise ValueError("Unclosed string or identifier")
    if "".join(buffer).strip():
        parts.append("".join(buffer).strip())
    return parts


class Database:
    def __init__(self, url, password):
        self.base = url.rstrip("/") + "/db/neo4j/tx"
        token = base64.b64encode(("neo4j:" + password).encode()).decode()
        self.headers = {"Authorization": "Basic " + token, "Content-Type": "application/json"}

    def request(self, url, method="POST", payload=None):
        data = None if payload is None else json.dumps(payload, ensure_ascii=False).encode()
        request = urllib.request.Request(url, data=data, headers=self.headers, method=method)
        try:
            with urllib.request.urlopen(request, timeout=40) as response:
                result = json.load(response)
        except urllib.error.HTTPError as error:
            raise RuntimeError(error.read().decode()) from error
        if result.get("errors"):
            raise RuntimeError(json.dumps(result["errors"], ensure_ascii=False))
        return result

    def run(self, query, params=None, tx=None):
        result = self.request(tx or self.base + "/commit", payload={"statements": [
            {"statement": query, "parameters": params or {}, "resultDataContents": ["row"]}
        ]})["results"][0]
        return result["columns"], [dict(zip(result["columns"], row["row"])) for row in result["data"]]

    def begin(self):
        return self.request(self.base, payload={"statements": []})["commit"].rsplit("/commit", 1)[0]

    def rollback(self, tx):
        self.request(tx, method="DELETE")


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--url", default="http://127.0.0.1:7474")
    parser.add_argument("--prepare-demo", action="store_true",
                        help="Load schema/seed; refuses any non-fixture graph.")
    args = parser.parse_args()
    password = os.environ.get("NEO4J_PASSWORD")
    if not password:
        raise SystemExit("Set NEO4J_PASSWORD first.")
    database = Database(args.url, password)
    catalog = {}
    pattern = r"// @name (\w+)\s*\n// @params([^\n]*)\n// @returns([^\n]*)\n(.*?)(?=// @name|\Z)"
    for name, params, columns, body in re.findall(pattern, (ROOT / "queries.cypher").read_text("utf-8"), re.S):
        queries = statements(body)
        assert len(queries) == 1, name
        catalog[name] = {"query": queries[0], "params": re.findall(r"\$(\w+)", params),
                         "columns": [column.strip() for column in columns.split(",") if column.strip()]}
    assert set(catalog) == CATALOG_NAMES, (set(catalog) ^ CATALOG_NAMES)
    results, covered = [], set()
    started = time.monotonic()

    def check(label, actual, expected):
        if actual != expected:
            raise AssertionError(f"{label}\nEXPECTED {expected!r}\nACTUAL   {actual!r}")
        results.append({"check": label, "passed": True})

    def query(name, params=None, tx=None):
        entry = catalog[name]
        check(name + ": parameter contract", set(params or {}), set(entry["params"]))
        columns, rows = database.run(entry["query"], params, tx)
        check(name + ": return contract", columns, entry["columns"])
        covered.add(name)
        return rows

    def ids(name, person_id, tx=None):
        return [row["person"]["id"] for row in query(name, {"id": person_id}, tx)]

    def run_file(name, tx=None):
        return [database.run(statement, tx=tx)[1]
                for statement in statements((ROOT / name).read_text("utf-8"))]

    with (ROOT / "import/persons.csv").open(encoding="utf-8", newline="") as stream:
        people = [{key: (int(value) if key in ("birthYear", "deathYear") else value)
                   for key, value in row.items() if value != ""} for row in csv.DictReader(stream)]
    existing = database.run("MATCH (n) RETURN count(n) AS total, collect(n.id) AS ids")[1][0]
    if args.prepare_demo:
        if existing["total"] and (existing["total"] != 30 or set(existing["ids"]) != {p["id"] for p in people}):
            raise SystemExit("Refusing to seed a graph containing unrelated nodes. Use a dedicated database.")
        if existing["total"]:
            unknown = database.run("MATCH (n) WHERE labels(n) <> ['Person'] RETURN count(n) AS total")[1]
            check("dedicated fixture labels", unknown, [{"total": 0}])
            unknown = database.run("MATCH ()-[r]->() WHERE NOT type(r) IN ['PARENT_OF', 'SPOUSE_OF'] RETURN count(r) AS total")[1]
            check("dedicated fixture relationship types", unknown, [{"total": 0}])
        run_file("schema.cypher")
        run_file("schema.cypher")
        database.run("CALL db.awaitIndexes(30)")
        run_file("seed.cypher")
    baseline = query("graphAll")[0]
    check("all seed properties", baseline["nodes"], people)
    check("fixture node count", len(baseline["nodes"]), 30)
    check("fixture PARENT_OF count", sum(edge["type"] == "PARENT_OF" for edge in baseline["edges"]), 36)
    check("fixture SPOUSE_OF count", sum(edge["type"] == "SPOUSE_OF" for edge in baseline["edges"]), 11)
    run_file("seed.cypher")
    check("seed is idempotent", query("graphAll")[0], baseline)
    schema = database.run("SHOW CONSTRAINTS YIELD name, type RETURN name, type ORDER BY name")[1]
    check("unique id constraint", any(row["name"] == "person_id" and row["type"] == "UNIQUENESS" for row in schema), True)
    indexes = database.run("SHOW INDEXES YIELD name, state RETURN name, state ORDER BY name")[1]
    check("name index online", {row["name"]: row["state"] for row in indexes}.get("person_name"), "ONLINE")
    fixture_rules = [
        ("parent age gap >= 15", "MATCH (p)-[:PARENT_OF]->(c) WHERE c.birthYear-p.birthYear < 15 RETURN count(*) AS total"),
        ("maximum two parents", "MATCH (c:Person) OPTIONAL MATCH (p)-[:PARENT_OF]->(c) WITH c,count(p) AS total WHERE total > 2 RETURN count(*) AS total"),
        ("no ancestry cycles", "MATCH (p:Person) WHERE EXISTS { MATCH (p)-[:PARENT_OF*1..]->(p) } RETURN count(p) AS total"),
        ("valid gender", "MATCH (p:Person) WHERE p.gender IS NULL OR NOT p.gender IN ['M','F'] RETURN count(p) AS total"),
        ("no duplicate directed parents", "MATCH (a)-[r:PARENT_OF]->(b) WITH a,b,count(r) AS total WHERE total > 1 RETURN count(*) AS total"),
        ("one stored spouse relationship per pair", "MATCH (a)-[r:SPOUSE_OF]-(b) WHERE a.id < b.id WITH a,b,count(r) AS total WHERE total > 1 RETURN count(*) AS total"),
        ("no deceased parent before birth", "MATCH (p)-[:PARENT_OF]->(c) WHERE p.deathYear < c.birthYear RETURN count(*) AS total"),
    ]
    for label, cypher in fixture_rules:
        check(label, database.run(cypher)[1], [{"total": 0}])

    expected_kin = {
        "P010": {"siblings": ["P011"], "uncles": ["P004", "P005"],
                 "cousins": ["P012", "P013", "P014", "P015", "P016"],
                 "ancestors": ["P001", "P002", "P003", "P006"]},
        "P012": {"siblings": ["P013"], "uncles": ["P003", "P005"],
                 "cousins": ["P010", "P011", "P014", "P015", "P016"],
                 "ancestors": ["P001", "P002", "P004", "P007"]},
        "P014": {"siblings": ["P015", "P016"], "uncles": ["P003", "P004"],
                 "cousins": ["P010", "P011", "P012", "P013"],
                 "ancestors": ["P001", "P002", "P005", "P008"]},
    }
    for person_id, relations in expected_kin.items():
        for name, expected in relations.items():
            check(name + "(" + person_id + ")", ids(name, person_id), expected)
    check("getPerson", query("getPerson", {"id": "P010"}), [{"person": people[9]}])
    check("parents", ids("parents", "P010"), ["P003", "P006"])
    check("children", ids("children", "P005"), ["P014", "P015", "P016"])
    check("descendants", ids("descendants", "P010"), ["P021", "P022", "P028", "P029"])
    check("case insensitive search", [r["person"]["id"] for r in query("searchPersons", {"q": "mInH kHaNg", "skip": 0, "limit": 5})], ["P010"])
    ordered = sorted(people, key=lambda p: (p["name"], p["id"]))
    check("empty search and pagination", [r["person"] for r in query("searchPersons", {"q": "", "skip": 2, "limit": 3})], ordered[2:5])
    check("search treats injection as text", query("searchPersons", {"q": "' OR true //", "skip": 0, "limit": 10}), [])
    check("count all", query("countPersons", {"q": ""}), [{"total": 30}])
    check("count search", query("countPersons", {"q": "mInH kHaNg"}), [{"total": 1}])
    check("spouses and two marriages", [(r["person"]["id"], r["marriageYear"]) for r in query("spouses", {"id": "P005"})], [("P008", 1973), ("P009", 2001)])
    generation_rows = query("generations", {"root": "P001"})
    expected_generations = {person_id: generation for generation, person_ids in [
        (1, ["P001", "P002"]), (2, [f"P{i:03}" for i in range(3, 10)]),
        (3, [f"P{i:03}" for i in range(10, 21)]), (4, [f"P{i:03}" for i in range(21, 28)]),
        (5, ["P028", "P029", "P030"])] for person_id in person_ids}
    check("all five generations including married-in spouses", {r["person"]["id"]: r["generation"] for r in generation_rows}, expected_generations)
    check("generation totals", query("statsByGeneration", {"root": "P001"}), [{"generation": g, "total": n} for g, n in enumerate([2, 7, 11, 7, 3], 1)])
    check("gender totals", query("statsByGender"), [{"gender": "F", "total": 16}, {"gender": "M", "total": 14}])
    check("living and deceased", query("livingVsDeceased"), [{"living": 25, "deceased": 5}])
    check("oldest means earliest birth year", [r["person"]["id"] for r in query("oldestPerson")], ["P001"])
    check("roots include married-in people", [r["person"]["id"] for r in query("roots")], ["P001", "P002", "P006", "P007", "P008", "P009", "P017", "P018", "P019", "P020", "P026", "P027"])
    check("top parents", [(r["id"], r["childCount"]) for r in query("topParents")], [("P001", 3), ("P002", 3), ("P005", 3), ("P003", 2), ("P004", 2)])
    check("maximum person number", query("maxPersonNumber"), [{"maxNum": 30}])
    check("incident relationship count", query("relationshipCount", {"id": "P005"}), [{"total": 7}])
    subtree = query("subtree", {"root": "P010"})[0]
    check("subtree members", [p["id"] for p in subtree["nodes"]], ["P010", "P017", "P021", "P022", "P026", "P028", "P029"])
    check("subtree internal edges", len(subtree["edges"]), 10)
    check("whole clan subtree", query("subtree", {"root": "P001"})[0], baseline)
    check("two parents", query("parentCount", {"id": "P014"}), [{"total": 2}])
    check("married-in has no parents", query("parentCount", {"id": "P006"}), [{"total": 0}])
    for parent, child, expected in [("P010", "P010", True), ("P021", "P001", True), ("P001", "P028", False), ("P017", "P001", False)]:
        check(f"wouldCreateCycle({parent},{child})", query("wouldCreateCycle", {"parentId": parent, "childId": child}), [{"exists": expected}])
    for parent, child, expected in [("P001", "P003", True), ("P003", "P001", False)]:
        check("parentExists direction", query("parentExists", {"parentId": parent, "childId": child}), [{"exists": expected}])
    for a, b, expected in [("P005", "P008", True), ("P008", "P005", True), ("P005", "P009", True), ("P010", "P012", False)]:
        check("spouseExists either direction", query("spouseExists", {"a": a, "b": b}), [{"exists": expected}])

    for a, b, middle in [("P010", "P012", {"P001", "P002"}), ("P010", "P014", {"P001", "P002"})]:
        path = query("relationPath", {"a": a, "b": b})[0]
        path_ids = [p["id"] for p in path["nodes"]]
        check("cousin shortest path endpoints", (path_ids[0], path_ids[-1], len(path_ids)), (a, b, 5))
        check("cousin common grandparent", path_ids[2] in middle, True)
        check("path relationship types", path["relTypes"], ["PARENT_OF"] * 4)
        check("path stored edge directions", path["relFrom"], [path_ids[1], path_ids[2], path_ids[2], path_ids[3]])
    self_path = query("relationPath", {"a": "P010", "b": "P010"})[0]
    check("self path zero length", (len(self_path["nodes"]), self_path["relTypes"], self_path["relFrom"]), (1, [], []))
    check("missing person", query("getPerson", {"id": "missing"}), [])
    check("missing path", query("relationPath", {"a": "P010", "b": "missing"}), [])

    tx = database.begin()
    try:
        for person_id in ("P901", "P902", "P903"):
            person = dict(id=person_id, name="Nhân vật kiểm thử", gender="F", birthYear=2000,
                          deathYear=None, birthPlace=None, note="Parameter ; // CREATE is ordinary text")
            check("createPerson scalar/map output", query("createPerson", person, tx), [{"person": {k: v for k, v in person.items() if v is not None}}])
        check("disconnected path", query("relationPath", {"a": "P001", "b": "P903"}, tx), [])
        updated = dict(id="P901", name="Tên mới", gender="M", birthYear=1970,
                       deathYear=2020, birthPlace="Huế", note=None)
        check("updatePerson fields and nullable removal", query("updatePerson", updated, tx), [{"person": {k: v for k, v in updated.items() if v is not None}}])
        for _ in range(2):
            query("addParentOf", {"parentId": "P901", "childId": "P902"}, tx)
        check("addParentOf idempotent", query("parentCount", {"id": "P902"}, tx), [{"total": 1}])
        for a, b in [("P901", "P902"), ("P902", "P901")]:
            query("addSpouseOf", {"a": a, "b": b, "marriageYear": 2018}, tx)
        check("spouse merge reversed remains single", query("relationshipCount", {"id": "P901"}, tx), [{"total": 2}])
        query("removeSpouseOf", {"a": "P902", "b": "P901"}, tx)
        check("remove spouse reversed", query("spouseExists", {"a": "P901", "b": "P902"}, tx), [{"exists": False}])
        query("removeParentOf", {"parentId": "P901", "childId": "P902"}, tx)
        check("remove parent", query("parentExists", {"parentId": "P901", "childId": "P902"}, tx), [{"exists": False}])
        query("addParentOf", {"parentId": "P901", "childId": "P902"}, tx)
        query("deletePerson", {"id": "P901"}, tx)
        check("DETACH DELETE person", query("getPerson", {"id": "P901"}, tx), [])
        check("DETACH DELETE incident edges", query("relationshipCount", {"id": "P902"}, tx), [{"total": 0}])
    finally:
        database.rollback(tx)
    check("write tests rolled back", query("graphAll")[0], baseline)

    duplicate_rejected = False
    try:
        query("createPerson", dict(id="P001", name="Duplicate", gender="M", birthYear=1920,
                                   deathYear=None, birthPlace=None, note=None))
    except RuntimeError as error:
        duplicate_rejected = "ConstraintValidationFailed" in str(error)
    check("unique constraint rejects duplicate id", duplicate_rejected, True)

    tx = database.begin()
    try:
        database.run("MATCH (n) DETACH DELETE n", tx=tx)
        check("graphAll on empty database", query("graphAll", tx=tx), [{"nodes": [], "edges": []}])
        check("max number on empty database", query("maxPersonNumber", tx=tx), [{"maxNum": 0}])
        run_file("load_csv.cypher", tx)
        check("LOAD CSV from empty graph equals seed", query("graphAll", tx=tx)[0], baseline)
        run_file("load_csv.cypher", tx)
        check("LOAD CSV is idempotent", query("graphAll", tx=tx)[0], baseline)
    finally:
        database.rollback(tx)
    check("import test rolled back", query("graphAll")[0], baseline)
    check("every catalog query executed", covered, CATALOG_NAMES)
    version = database.run("CALL dbms.components() YIELD name, versions, edition RETURN name, versions, edition")[1]
    report = {"verifiedAt": datetime.now(timezone.utc).isoformat(), "server": version,
              "catalogQueries": len(catalog), "checksPassed": len(results),
              "durationSeconds": round(time.monotonic() - started, 3), "counts": {
                  "persons": 30, "PARENT_OF": 36, "SPOUSE_OF": 11,
                  "generations": [2, 7, 11, 7, 3], "living": 25, "deceased": 5},
              "chosenPersons": expected_kin, "checks": results}
    (ROOT / "verification-results.json").write_text(json.dumps(report, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(f"PASS: {len(catalog)}/{len(CATALOG_NAMES)} catalog queries, {len(results)} checks; 30 persons / 36 parents / 11 spouses.")
    print("Evidence: neo4j/verification-results.json")


if __name__ == "__main__":
    main()
