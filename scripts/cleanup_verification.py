"""Remove only labelled test resources belonging to one verification report."""
import argparse
import json
from pathlib import Path
import re
from verify_delivery import command

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument("report", type=Path)
parser.add_argument("--docker-context")
args = parser.parse_args()
report = json.loads(args.report.read_text(encoding="utf-8-sig"))
project = report["project"]
if not re.fullmatch(r"familytree-verify-[a-z0-9]+", project):
    parser.error("The report does not name an isolated verification project")
docker = ["docker"] + (["--context", args.docker_context] if args.docker_context else [])
containers = command(docker + ["ps", "-a", "--filter", "label=familytree.verification=" + project, "--format", "{{.Names}}"])
for name in containers.splitlines():
    labels = json.loads(command(docker + ["inspect", "--format", "{{json .Config.Labels}}", name]))
    if labels.get("familytree.verification") != project:
        raise RuntimeError("Container ownership label does not match")
    command(docker + ["rm", "-f", name])
volume_names = set()
for label in ["familytree.verification=", "com.docker.compose.project="]:
    volume_names.update(command(docker + ["volume", "ls", "--filter", "label=" + label + project, "-q"]).splitlines())
for name in volume_names:
    labels = json.loads(command(docker + ["volume", "inspect", "--format", "{{json .Labels}}", name]))
    if labels.get("familytree.verification") != project and labels.get("com.docker.compose.project") != project:
        raise RuntimeError("Volume ownership label does not match")
    command(docker + ["volume", "rm", name])
for name in command(docker + ["network", "ls", "--filter", "label=com.docker.compose.project=" + project, "--format", "{{.Name}}"]).splitlines():
    labels = json.loads(command(docker + ["network", "inspect", "--format", "{{json .Labels}}", name]))
    if labels.get("com.docker.compose.project") != project:
        raise RuntimeError("Network ownership label does not match")
    command(docker + ["network", "rm", name])
print(json.dumps({"project": project, "cleaned": True, "evidenceFilesPreserved": True}))
