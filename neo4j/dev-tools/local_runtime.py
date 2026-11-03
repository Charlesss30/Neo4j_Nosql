"""Run an isolated Windows demo store using an existing Neo4j 5 installation."""

import argparse
import ctypes
import json
import os
import socket
import subprocess
import time
import urllib.request
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
RUNTIME = ROOT / ".runtime"


def process_creation_time(pid):
    """Identify the exact Windows process, guarding against PID reuse."""
    from ctypes import wintypes

    kernel = ctypes.WinDLL("kernel32", use_last_error=True)
    kernel.OpenProcess.restype = wintypes.HANDLE
    kernel.OpenProcess.argtypes = [wintypes.DWORD, wintypes.BOOL, wintypes.DWORD]
    kernel.GetProcessTimes.argtypes = [wintypes.HANDLE] + [ctypes.POINTER(wintypes.FILETIME)] * 4
    kernel.CloseHandle.argtypes = [wintypes.HANDLE]
    handle = kernel.OpenProcess(0x1000, False, pid)
    if not handle:
        raise SystemExit("The recorded demo process is no longer running; no process was stopped.")
    try:
        times = [wintypes.FILETIME() for _ in range(4)]
        if not kernel.GetProcessTimes(handle, *(ctypes.byref(value) for value in times)):
            raise OSError(ctypes.get_last_error(), "Cannot identify demo process")
        return (times[0].dwHighDateTime << 32) | times[0].dwLowDateTime
    finally:
        kernel.CloseHandle(handle)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("action", choices=["inspect", "start", "shell", "stop"])
    parser.add_argument("--neo4j-home", required=True, type=Path)
    parser.add_argument("--java-home", required=True, type=Path)
    parser.add_argument("--file", type=Path)
    args = parser.parse_args()
    home = args.neo4j_home.resolve()
    java = str(args.java_home.resolve() / "bin/java.exe")
    flags = getattr(subprocess, "CREATE_NO_WINDOW", 0)
    classpath = str(home / "lib/*")
    env = dict(os.environ, JAVA_HOME=str(args.java_home), NEO4J_HOME=str(home),
               NEO4J_CONF=str(RUNTIME / "conf"))
    if args.action == "inspect":
        for jar in home.rglob("*.jar"):
            if "shell" in jar.name or "server" in jar.name:
                with zipfile.ZipFile(jar) as archive:
                    classes = [name for name in archive.namelist() if name.endswith(
                        ("CypherShellBoot.class", "shell/Main.class", "Neo4jBoot.class", "Neo4jAdminBoot.class"))]
                if classes:
                    print(jar.relative_to(home), classes)
        return
    if args.action == "shell":
        password = os.environ.get("NEO4J_PASSWORD")
        if not password:
            raise SystemExit("Set NEO4J_PASSWORD first.")
        command = [java, "-cp", classpath, "-Dapp.home=" + str(home),
                   "-Dapp.repo=" + str(home / "lib"), "-Dbasedir=" + str(home),
                   "org.neo4j.shell.Main", "-a", "bolt://localhost:7687", "-u", "neo4j",
                   "--database", "neo4j", "--format", "plain"]
        if args.file:
            command += ["-f", str(args.file.resolve())]
        else:
            command += ["RETURN 1 AS connection;"]
        result = subprocess.run(command, env=env, creationflags=flags)
        raise SystemExit(result.returncode)
    if args.action == "stop":
        state = json.loads((RUNTIME / "process.json").read_text())
        if state.get("creationTime") != process_creation_time(state["pid"]):
            raise SystemExit("The recorded PID has been reused; no process was stopped.")
        # Only terminate the process tree created and recorded by this launcher.
        subprocess.run(["taskkill", "/PID", str(state["pid"]), "/T", "/F"], check=True,
                       creationflags=flags)
        return
    password = os.environ.get("NEO4J_PASSWORD")
    if not password:
        raise SystemExit("Set NEO4J_PASSWORD first.")
    for port in (7474, 7687):
        with socket.socket() as connection:
            if connection.connect_ex(("127.0.0.1", port)) == 0:
                raise SystemExit(f"Port {port} is already occupied; no existing server was changed.")
    conf = RUNTIME / "conf"
    conf.mkdir(parents=True, exist_ok=True)
    for name in ("data", "logs", "run", "transactions"):
        (RUNTIME / name).mkdir(exist_ok=True)
    settings = {
        "server.directories.data": (RUNTIME / "data").as_posix(),
        "server.directories.logs": (RUNTIME / "logs").as_posix(),
        "server.directories.run": (RUNTIME / "run").as_posix(),
        "server.directories.transaction.logs.root": (RUNTIME / "transactions").as_posix(),
        "server.directories.import": (ROOT / "import").as_posix(),
        "server.default_listen_address": "127.0.0.1",
        "server.bolt.enabled": "true", "server.http.enabled": "true",
        "server.memory.heap.initial_size": "256m", "server.memory.heap.max_size": "512m",
        "server.memory.pagecache.size": "128m",
        "dbms.usage_report.enabled": "false",
    }
    (conf / "neo4j.conf").write_text("\n".join(f"{key}={value}" for key, value in settings.items()) + "\n")
    admin = [java, "-cp", classpath, "org.neo4j.server.startup.Neo4jAdminBoot",
             "dbms", "set-initial-password", password]
    if not (RUNTIME / "data/databases/system").exists():
        subprocess.run(admin, env=env, check=True, creationflags=flags)
    command = [java, "-cp", classpath, "org.neo4j.server.startup.Neo4jBoot", "console"]
    with (RUNTIME / "console.log").open("ab") as log:
        process = subprocess.Popen(command, env=env, cwd=home, stdout=log, stderr=subprocess.STDOUT,
                                   creationflags=flags)
    (RUNTIME / "process.json").write_text(json.dumps({"pid": process.pid,
                                                     "creationTime": process_creation_time(process.pid)}))
    for _ in range(50):
        if process.poll() is not None:
            raise SystemExit((RUNTIME / "console.log").read_text(errors="replace"))
        try:
            with urllib.request.urlopen("http://127.0.0.1:7474/", timeout=1) as response:
                if response.status == 200:
                    print(f"Neo4j ready at http://localhost:7474/; isolated data: {RUNTIME / 'data'}")
                    return
        except (OSError, urllib.error.URLError):
            time.sleep(1)
    raise SystemExit("Startup timed out. Inspect neo4j/.runtime/console.log.")


if __name__ == "__main__":
    main()
