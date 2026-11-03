"""Generate both import formats from the same fictional, public demo fixture."""

import csv
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parent
FIELDS = ["id", "name", "gender", "birthYear", "deathYear", "birthPlace", "note"]
PEOPLE = [
    ("P001", "Nguyễn Văn An", "M", 1920, 2000, "Hà Nội", "Thủy tổ dòng họ Nguyễn; dữ liệu hư cấu."),
    ("P002", "Trần Thị Bình", "F", 1923, 2008, "Hà Nội", "Phối ngẫu của thủy tổ."),
    ("P003", "Nguyễn Văn Cường", "M", 1945, 2018, "Hà Nội", None),
    ("P004", "Nguyễn Thị Đào", "F", 1948, None, "Hà Nội", None),
    ("P005", "Nguyễn Văn Đức", "M", 1951, None, "Hà Nội", "Có hai cuộc hôn nhân; không suy diễn thời điểm ly hôn."),
    ("P006", "Lê Thị Dung", "F", 1947, None, "Hải Phòng", "Kết hôn vào gia phả; không có cha mẹ trong cây."),
    ("P007", "Phạm Văn Hải", "M", 1946, 2021, "Nam Định", None),
    ("P008", "Võ Thị Hoa", "F", 1953, 1999, "Huế", "Người vợ thứ nhất của Nguyễn Văn Đức."),
    ("P009", "Bùi Thị Lan", "F", 1958, None, "Ninh Bình", "Người vợ thứ hai của Nguyễn Văn Đức."),
    ("P010", "Nguyễn Minh Khang", "M", 1968, None, "Hà Nội", None),
    ("P011", "Nguyễn Thị Linh", "F", 1971, None, "Hà Nội", None),
    ("P012", "Phạm Minh Nam", "M", 1969, None, "Nam Định", None),
    ("P013", "Phạm Thị Ngọc", "F", 1973, None, "Nam Định", None),
    ("P014", "Nguyễn Quốc Phúc", "M", 1974, None, "Hà Nội", None),
    ("P015", "Nguyễn Thị Quỳnh", "F", 1978, None, "Hà Nội", None),
    ("P016", "Nguyễn Văn Sơn", "M", 2002, None, "Hà Nội", "Em cùng cha khác mẹ của Phúc và Quỳnh."),
    ("P017", "Đỗ Thị Mai", "F", 1970, None, "Bắc Ninh", None),
    ("P018", "Hoàng Văn Long", "M", 1969, None, "Hà Nam", None),
    ("P019", "Đặng Thị Oanh", "F", 1972, None, "Thái Bình", None),
    ("P020", "Trần Thị Yến", "F", 1976, None, "Huế", None),
    ("P021", "Nguyễn Gia Bảo", "M", 1992, None, "Hà Nội", None),
    ("P022", "Nguyễn Thảo Chi", "F", 1995, None, "Hà Nội", None),
    ("P023", "Hoàng Minh Đạt", "M", 1994, None, "Hà Nội", None),
    ("P024", "Phạm Ngọc Hà", "F", 1993, None, "Nam Định", None),
    ("P025", "Nguyễn Gia Huy", "M", 1996, None, "Hà Nội", None),
    ("P026", "Lê Thị Ánh", "F", 1993, None, "Hải Dương", None),
    ("P027", "Vũ Văn Bình", "M", 1992, None, "Nam Định", None),
    ("P028", "Nguyễn An Nhiên", "F", 2015, None, "Hà Nội", None),
    ("P029", "Nguyễn Minh Quân", "M", 2018, None, "Hà Nội", None),
    ("P030", "Vũ Phương Thảo", "F", 2014, None, "Nam Định", None),
]
FAMILIES = [
    ("P001", "P002", ["P003", "P004", "P005"]),
    ("P003", "P006", ["P010", "P011"]),
    ("P004", "P007", ["P012", "P013"]),
    ("P005", "P008", ["P014", "P015"]),
    ("P005", "P009", ["P016"]),
    ("P010", "P017", ["P021", "P022"]),
    ("P011", "P018", ["P023"]),
    ("P012", "P019", ["P024"]),
    ("P014", "P020", ["P025"]),
    ("P021", "P026", ["P028", "P029"]),
    ("P024", "P027", ["P030"]),
]
MARRIAGES = [
    ("P001", "P002", 1942), ("P003", "P006", 1967), ("P004", "P007", 1968),
    ("P005", "P008", 1973), ("P005", "P009", 2001), ("P010", "P017", 1990),
    ("P011", "P018", 1992), ("P012", "P019", 1991), ("P014", "P020", 1995),
    ("P021", "P026", 2014), ("P024", "P027", 2013),
]


def cypher_literal(value):
    if isinstance(value, dict):
        return "{" + ", ".join(key + ": " + cypher_literal(item) for key, item in value.items()) + "}"
    if isinstance(value, list):
        return "[\n" + ",\n".join("  " + cypher_literal(item) for item in value) + "\n]"
    return json.dumps(value, ensure_ascii=False)


def main():
    persons = [dict(zip(FIELDS, person)) for person in PEOPLE]
    parents = [{"from": parent, "to": child} for a, b, children in FAMILIES
               for parent in (a, b) for child in children]
    spouses = [{"from": a, "to": b, "marriageYear": year} for a, b, year in MARRIAGES]
    (ROOT / "import").mkdir(exist_ok=True)
    with (ROOT / "import/persons.csv").open("w", encoding="utf-8", newline="") as stream:
        writer = csv.DictWriter(stream, fieldnames=FIELDS)
        writer.writeheader()
        writer.writerows(persons)
    with (ROOT / "import/relationships.csv").open("w", encoding="utf-8", newline="") as stream:
        writer = csv.DictWriter(stream, fieldnames=["from", "to", "type", "marriageYear"])
        writer.writeheader()
        writer.writerows(dict(edge, type="PARENT_OF") for edge in parents)
        writer.writerows(dict(edge, type="SPOUSE_OF") for edge in spouses)
    seed = "// Fictional demo fixture. Re-running this file does not create duplicates.\n"
    seed += "UNWIND " + cypher_literal(persons) + " AS row\n"
    seed += "MERGE (p:Person {id: row.id})\nSET p += row;\n\n"
    seed += "UNWIND " + cypher_literal(parents) + " AS row\n"
    seed += "MATCH (parent:Person {id: row.from}), (child:Person {id: row.to})\n"
    seed += "MERGE (parent)-[:PARENT_OF]->(child);\n\n"
    seed += "UNWIND " + cypher_literal(spouses) + " AS row\n"
    seed += "MATCH (a:Person {id: row.from}), (b:Person {id: row.to})\n"
    seed += "MERGE (a)-[r:SPOUSE_OF]-(b)\nSET r.marriageYear = row.marriageYear;\n\n"
    seed += "// Verification counts on an otherwise empty demo database: 30, 36, 11.\n"
    seed += "MATCH (p:Person) RETURN count(p) AS persons;\n"
    seed += "MATCH ()-[r:PARENT_OF]->() RETURN count(r) AS parentOf;\n"
    seed += "MATCH ()-[r:SPOUSE_OF]->() RETURN count(r) AS spouseOf;\n"
    (ROOT / "seed.cypher").write_text(seed, encoding="utf-8")
    print(f"Generated {len(persons)} persons, {len(parents)} PARENT_OF, {len(spouses)} SPOUSE_OF.")


if __name__ == "__main__":
    main()
