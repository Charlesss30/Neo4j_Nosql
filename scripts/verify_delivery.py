"""Reproduce a clean-clone Docker install, offline dump/load, and isolated PROFILE.

Python 3.10+ standard library only. All writes target newly created test volumes.
Run after committing the feature branch; uncommitted files are deliberately absent
from the clone. --keep retains the isolated application for browser verification.
"""

import argparse
import base64
import hashlib
import json
import os
from pathlib import Path
import statistics
import subprocess
import time
import urllib.error
import urllib.request
import uuid

IMAGE = "neo4j:5.26.31-community"
PASSWORD = "familytree123"  # Public, isolated classroom fixture credential.


def command(args, cwd=None, env=None):
    result = subprocess.run([str(arg) for arg in args], cwd=cwd, env=env,
                            capture_output=True, text=True, encoding="utf-8", errors="replace")
    if result.returncode:
        raise RuntimeError(f"Command failed: {' '.join(map(str, args))}\n{result.stdout}\n{result.stderr}")
    return result.stdout.strip()


class Graph:
    def __init__(self, url):
        self.url = url + "/db/neo4j/tx/commit"

    def run(self, query, params=None):
        payload = {"statements": [{"statement": query, "parameters": params or {},
                                  "resultDataContents": ["row"]}]}
        token = base64.b64encode(("neo4j:" + PASSWORD).encode()).decode()
        request = urllib.request.Request(self.url, json.dumps(payload).encode(),
                                         {"Content-Type": "application/json", "Authorization": "Basic " + token})
        with urllib.request.urlopen(request, timeout=180) as response:
            data = json.load(response)
        if data["errors"]:
            raise RuntimeError(json.dumps(data["errors"], ensure_ascii=False))
        result = data["results"][0]
        result["rows"] = [dict(zip(result["columns"], row["row"])) for row in result["data"]]
        return result

    def ready(self):
        for _ in range(90):
            try:
                if self.run("RETURN 1 AS ok")["rows"] == [{"ok": 1}]:
                    return
            except (OSError, RuntimeError):
                pass
            time.sleep(2)
        raise RuntimeError("Neo4j did not become ready within 180 seconds.")

    def snapshot(self):
        nodes = self.run("MATCH (n) RETURN labels(n) AS labels, properties(n) AS properties ORDER BY properties.id")["rows"]
        edges = self.run("MATCH (a)-[r]->(b) RETURN a.id AS source, type(r) AS type, b.id AS target, properties(r) AS properties")["rows"]
        edges.sort(key=lambda row: json.dumps(row, sort_keys=True, ensure_ascii=False))
        schema = self.run("SHOW CONSTRAINTS YIELD name, type, labelsOrTypes, properties RETURN name, type, labelsOrTypes, properties ORDER BY name")["rows"]
        indexes = self.run("SHOW INDEXES YIELD name, type, labelsOrTypes, properties RETURN name, type, labelsOrTypes, properties ORDER BY name")["rows"]
        content = {"nodes": nodes, "edges": edges, "constraints": schema, "indexes": indexes}
        digest = hashlib.sha256(json.dumps(content, ensure_ascii=False, sort_keys=True).encode()).hexdigest()
        return {"persons": len(nodes), "relationships": len(edges), "sha256": digest}


def profile(graph, statement, parameters, repetitions=5):
    for _ in range(2):
        graph.run(statement, parameters)
    samples = []
    expected = None
    plan = None
    for _ in range(repetitions):
        start = time.perf_counter()
        result = graph.run("PROFILE " + statement, parameters)
        samples.append(round((time.perf_counter() - start) * 1000, 3))
        if expected is None:
            expected = result["rows"]
        assert result["rows"] == expected, "Repeated PROFILE results differ"
        plan = result["plan"]["root"]
    operators = []

    def visit(node):
        operators.append({"operator": node["operatorType"], "rows": node.get("rows"),
                          "dbHits": node.get("dbHits", 0), "estimatedRows": node.get("args", {}).get("EstimatedRows")})
        for child in node.get("children", []):
            visit(child)
    visit(plan)
    return {"statement": statement, "parameters": parameters, "result": expected,
            "operators": operators, "dbHits": sum(row["dbHits"] for row in operators),
            "httpProfileMs": samples, "medianHttpProfileMs": statistics.median(samples), "plan": plan}


def benchmark(graph, count):
    assert count >= 1000 and count % 2 == 0
    assert graph.snapshot()["persons"] == 0, "Benchmark requires a new empty database"
    graph.run("CREATE CONSTRAINT person_id IF NOT EXISTS FOR (p:Person) REQUIRE p.id IS UNIQUE")
    households = count // 2
    for start in range(0, households, 500):
        people, spouses, parents = [], [], []
        for index in range(start, min(start + 500, households)):
            year = 1600 + ((index + 1).bit_length() - 1) * 25
            first, second = f"B{2 * index + 1:06d}", f"B{2 * index + 2:06d}"
            people.extend([{"id": first, "name": "Benchmark " + first, "gender": "M", "birthYear": year},
                           {"id": second, "name": "Benchmark " + second, "gender": "F", "birthYear": year + 1}])
            spouses.append({"a": first, "b": second, "year": year + 20})
            if index:
                previous = (index - 1) // 2
                parents.extend([{"a": f"B{2 * previous + 1:06d}", "b": first},
                                {"a": f"B{2 * previous + 2:06d}", "b": first}])
        graph.run("UNWIND $people AS row CREATE (p:Person) SET p = row", {"people": people})
        graph.run("UNWIND $edges AS row MATCH (a:Person {id:row.a}), (b:Person {id:row.b}) CREATE (a)-[:SPOUSE_OF {marriageYear:row.year}]->(b)", {"edges": spouses})
        graph.run("UNWIND $edges AS row MATCH (a:Person {id:row.a}), (b:Person {id:row.b}) CREATE (a)-[:PARENT_OF]->(b)", {"edges": parents})
    statement = "MATCH (p:Person) WHERE p.name = $name RETURN p {.*} AS person"
    target = {"name": f"Benchmark B{count - 1:06d}"}
    without = profile(graph, statement, target)
    try:
        graph.run("CREATE INDEX person_name IF NOT EXISTS FOR (p:Person) ON (p.name)")
        graph.run("CALL db.awaitIndexes(60)")
        indexed = profile(graph, statement, target)
        contains = profile(graph, "MATCH (p:Person) WHERE toLower(p.name) CONTAINS toLower($name) RETURN count(p) AS total", target)
        descendants = profile(graph, "MATCH (:Person {id:$root})-[:PARENT_OF*1..]->(p:Person) RETURN count(DISTINCT p) AS total", {"root": "B000001"})
        assert indexed["result"] == without["result"]
        assert any("IndexSeek" in row["operator"] for row in indexed["operators"])
        assert indexed["dbHits"] < without["dbHits"]
        snapshot = graph.snapshot()
        assert snapshot["persons"] == count
        assert snapshot["relationships"] == 3 * households - 2
        return {"dataset": snapshot, "seedUnchanged": True, "warmups": 2, "repetitions": 5,
                "withoutIndex": without, "withIndex": indexed, "containsWithIndex": contains,
                "descendants": descendants, "dbHitRatio": round(without["dbHits"] / max(1, indexed["dbHits"]), 2),
                "timingNote": "Client HTTP wall time includes PROFILE and serialization overhead; not a production latency benchmark."}
    finally:
        graph.run("CREATE INDEX person_name IF NOT EXISTS FOR (p:Person) ON (p.name)")
        graph.run("CALL db.awaitIndexes(60)")


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--output", type=Path, required=True, help="New directory for clone, logs, real dump, and JSON evidence")
    parser.add_argument("--people", type=int, default=20000)
    parser.add_argument("--docker-context")
    parser.add_argument("--keep", action="store_true")
    args = parser.parse_args()
    if args.people < 1000 or args.people % 2 or args.people > 50000:
        parser.error("--people must be an even number from 1000 to 50000")
    source = Path(__file__).resolve().parents[1]
    output = args.output.resolve()
    if output.exists() and any(output.iterdir()):
        parser.error("--output must be new or empty")
    output.mkdir(parents=True, exist_ok=True)
    project = "familytree-verify-" + uuid.uuid4().hex[:10]
    docker = ["docker"] + (["--context", args.docker_context] if args.docker_context else [])
    branch = command(["git", "-c", f"safe.directory={source.as_posix()}", "branch", "--show-current"], source)
    head = command(["git", "-c", f"safe.directory={source.as_posix()}", "rev-parse", "HEAD"], source)
    if command(["git", "-c", f"safe.directory={source.as_posix()}", "status", "--porcelain"], source):
        parser.error("Commit the branch first so the clean clone contains the exact reviewed source.")
    clone = output / "clean-clone"
    command(["git", "-c", f"safe.directory={source.as_posix()}", "clone", "--no-local", "--single-branch", "--branch", branch, source, clone])
    assert not (clone / ".env").exists() and not (clone / "vendor").exists()
    env = dict(os.environ, VERIFY_PROJECT=project, NEO4J_PASSWORD=PASSWORD)
    compose = docker + ["compose", "-p", project, "-f", "docker-compose.yml", "-f", "scripts/verification.compose.yml"]
    report = {"sourceCommit": head, "sourceBranch": branch, "project": project,
              "image": IMAGE, "cleanClone": {"noEnv": True, "noVendor": True, "method": "git clone --no-local of committed feature branch"},
              "passed": False}
    extras, volumes = [], []

    def dc(*arguments):
        return command(compose + list(arguments), clone, env)

    def resource_volume(suffix):
        name = project + "-" + suffix
        command(docker + ["volume", "create", "--label", "familytree.verification=" + project, name])
        volumes.append(name)
        return name

    def server(suffix, volume):
        name = project + "-" + suffix
        command(docker + ["run", "-d", "--name", name, "--label", "familytree.verification=" + project,
                          "-p", "127.0.0.1::7474", "-e", "NEO4J_AUTH=neo4j/" + PASSWORD,
                          "-e", "NEO4J_server_memory_heap_initial__size=256m", "-e", "NEO4J_server_memory_heap_max__size=512m",
                          "-e", "NEO4J_server_memory_pagecache_size=128m", "-v", volume + ":/data", IMAGE])
        extras.append(name)
        address = command(docker + ["port", name, "7474/tcp"]).splitlines()[0]
        graph = Graph("http://" + address)
        graph.ready()
        return graph

    try:
        print("Building application from a clean clone...", flush=True)
        (output / "build.log").write_text(dc("build", "app"), encoding="utf-8")
        dc("up", "-d", "neo4j")
        source_container = project + "-neo4j"
        address = dc("port", "neo4j", "7474").splitlines()[0]
        graph = Graph("http://" + address)
        graph.ready()
        for file in ["schema.cypher", "seed.cypher"]:
            command(docker + ["exec", source_container, "cypher-shell", "-u", "neo4j", "-p", PASSWORD, "-d", "neo4j", "-f", "/scripts/" + file])
        initial = graph.snapshot()
        assert initial["persons"] == 30 and initial["relationships"] == 47
        dc("up", "-d", "app")
        report["appUrl"] = "http://" + dc("port", "app", "8000").splitlines()[0]
        report["neo4jUrl"] = "http://" + address
        print("Running Laravel integration tests against Docker Neo4j...", flush=True)
        tests = dc("exec", "-T", "-e", "APP_ENV=testing", "-e", "SESSION_DRIVER=array", "-e", "CACHE_STORE=array", "app", "php", "artisan", "test", "--compact")
        (output / "tests.log").write_text(tests, encoding="utf-8")
        report["testsOutput"] = tests
        assert graph.snapshot() == initial, "Integration tests changed the seed"
        print("Checking persistence and an actual offline dump/load...", flush=True)
        graph.run("MATCH (parent:Person {id:'P010'}) CREATE (p:Person {id:'T_BACKUP',name:'Kiểm chứng sao lưu',gender:'F',birthYear:2001,note:'Created after seed'}) CREATE (parent)-[:PARENT_OF]->(p)")
        before = graph.snapshot()
        dc("restart", "neo4j")
        graph = Graph("http://" + dc("port", "neo4j", "7474").splitlines()[0])
        graph.ready()
        assert graph.snapshot() == before, "Volume did not preserve writes after restart"
        dc("stop", "neo4j")
        backup_volume = resource_volume("backups")
        mounts = json.loads(command(docker + ["inspect", "--format", "{{json .Mounts}}", source_container]))
        data_volume = next(mount["Name"] for mount in mounts if mount["Destination"] == "/data" and mount["Type"] == "volume")
        assert data_volume.startswith(project + "_"), "Only the isolated Compose volume may be dumped"
        dump_output = command(docker + ["run", "--rm", "--user", "root", "-e", "HEAP_SIZE=256m", "-v", data_volume + ":/data",
                                      "-v", backup_volume + ":/backups", IMAGE, "neo4j-admin", "database", "dump", "neo4j", "--to-path=/backups"])
        (output / "dump.log").write_text(dump_output, encoding="utf-8")
        helper = project + "-export"
        command(docker + ["create", "--name", helper, "--label", "familytree.verification=" + project, "-v", backup_volume + ":/backups:ro", IMAGE])
        extras.append(helper)
        command(docker + ["cp", helper + ":/backups/neo4j.dump", output / "neo4j.dump"])
        restore_volume = resource_volume("restored-data")
        load_output = command(docker + ["run", "--rm", "--user", "root", "-e", "HEAP_SIZE=256m", "-v", restore_volume + ":/data",
                                      "-v", backup_volume + ":/backups:ro", IMAGE, "neo4j-admin", "database", "load", "neo4j", "--from-path=/backups"])
        (output / "load.log").write_text(load_output, encoding="utf-8")
        restored = server("restored", restore_volume)
        after = restored.snapshot()
        assert before == after, "Restored properties, edges, or schema differ"
        report["backupRestore"] = {"before": before, "after": after, "equal": True, "restartPreserved": True,
                                   "dumpBytes": (output / "neo4j.dump").stat().st_size,
                                   "dumpSha256": hashlib.sha256((output / "neo4j.dump").read_bytes()).hexdigest(),
                                   "witness": "T_BACKUP and its PARENT_OF edge were added after seeding and recovered from the dump.",
                                   "authNote": "The graph dump does not include system users/roles; the fresh server initializes its own demo credentials."}
        dc("start", "neo4j")
        graph = Graph("http://" + dc("port", "neo4j", "7474").splitlines()[0])
        report["neo4jUrl"] = graph.url.removesuffix("/db/neo4j/tx/commit")
        graph.ready()
        for target in [graph, restored]:
            target.run("MATCH (p:Person {id:'T_BACKUP'}) DETACH DELETE p")
            assert target.snapshot() == initial
        print(f"Profiling a separate {args.people}-person dataset...", flush=True)
        bench_graph = server("benchmark", resource_volume("benchmark-data"))
        report["profile"] = benchmark(bench_graph, args.people)
        assert graph.snapshot() == initial, "Benchmark changed the application seed"
        report["seedAfterAllChecks"] = graph.snapshot()
        report["passed"] = True
        print(json.dumps({"passed": True, "appUrl": report["appUrl"], "dbHitRatio": report["profile"]["dbHitRatio"]}), flush=True)
    except Exception as failure:
        report["error"] = str(failure)
        raise
    finally:
        (output / "verification.json").write_text(json.dumps(report, ensure_ascii=False, indent=2), encoding="utf-8")
        if not args.keep:
            for name in extras:
                labels = json.loads(command(docker + ["inspect", "--format", "{{json .Config.Labels}}", name]))
                if labels.get("familytree.verification") == project:
                    command(docker + ["rm", "-f", name])
            dc("down", "--volumes")
            for name in volumes:
                labels = json.loads(command(docker + ["volume", "inspect", "--format", "{{json .Labels}}", name]))
                if labels.get("familytree.verification") == project:
                    command(docker + ["volume", "rm", name])


if __name__ == "__main__":
    main()
