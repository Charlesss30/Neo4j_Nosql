// Fictional demo fixture. Re-running this file does not create duplicates.
UNWIND [
  {id: "P001", name: "Nguyễn Văn An", gender: "M", birthYear: 1920, deathYear: 2000, birthPlace: "Hà Nội", note: "Thủy tổ dòng họ Nguyễn; dữ liệu hư cấu."},
  {id: "P002", name: "Trần Thị Bình", gender: "F", birthYear: 1923, deathYear: 2008, birthPlace: "Hà Nội", note: "Phối ngẫu của thủy tổ."},
  {id: "P003", name: "Nguyễn Văn Cường", gender: "M", birthYear: 1945, deathYear: 2018, birthPlace: "Hà Nội", note: null},
  {id: "P004", name: "Nguyễn Thị Đào", gender: "F", birthYear: 1948, deathYear: null, birthPlace: "Hà Nội", note: null},
  {id: "P005", name: "Nguyễn Văn Đức", gender: "M", birthYear: 1951, deathYear: null, birthPlace: "Hà Nội", note: "Có hai cuộc hôn nhân; không suy diễn thời điểm ly hôn."},
  {id: "P006", name: "Lê Thị Dung", gender: "F", birthYear: 1947, deathYear: null, birthPlace: "Hải Phòng", note: "Kết hôn vào gia phả; không có cha mẹ trong cây."},
  {id: "P007", name: "Phạm Văn Hải", gender: "M", birthYear: 1946, deathYear: 2021, birthPlace: "Nam Định", note: null},
  {id: "P008", name: "Võ Thị Hoa", gender: "F", birthYear: 1953, deathYear: 1999, birthPlace: "Huế", note: "Người vợ thứ nhất của Nguyễn Văn Đức."},
  {id: "P009", name: "Bùi Thị Lan", gender: "F", birthYear: 1958, deathYear: null, birthPlace: "Ninh Bình", note: "Người vợ thứ hai của Nguyễn Văn Đức."},
  {id: "P010", name: "Nguyễn Minh Khang", gender: "M", birthYear: 1968, deathYear: null, birthPlace: "Hà Nội", note: null},
  {id: "P011", name: "Nguyễn Thị Linh", gender: "F", birthYear: 1971, deathYear: null, birthPlace: "Hà Nội", note: null},
  {id: "P012", name: "Phạm Minh Nam", gender: "M", birthYear: 1969, deathYear: null, birthPlace: "Nam Định", note: null},
  {id: "P013", name: "Phạm Thị Ngọc", gender: "F", birthYear: 1973, deathYear: null, birthPlace: "Nam Định", note: null},
  {id: "P014", name: "Nguyễn Quốc Phúc", gender: "M", birthYear: 1974, deathYear: null, birthPlace: "Hà Nội", note: null},
  {id: "P015", name: "Nguyễn Thị Quỳnh", gender: "F", birthYear: 1978, deathYear: null, birthPlace: "Hà Nội", note: null},
  {id: "P016", name: "Nguyễn Văn Sơn", gender: "M", birthYear: 2002, deathYear: null, birthPlace: "Hà Nội", note: "Em cùng cha khác mẹ của Phúc và Quỳnh."},
  {id: "P017", name: "Đỗ Thị Mai", gender: "F", birthYear: 1970, deathYear: null, birthPlace: "Bắc Ninh", note: null},
  {id: "P018", name: "Hoàng Văn Long", gender: "M", birthYear: 1969, deathYear: null, birthPlace: "Hà Nam", note: null},
  {id: "P019", name: "Đặng Thị Oanh", gender: "F", birthYear: 1972, deathYear: null, birthPlace: "Thái Bình", note: null},
  {id: "P020", name: "Trần Thị Yến", gender: "F", birthYear: 1976, deathYear: null, birthPlace: "Huế", note: null},
  {id: "P021", name: "Nguyễn Gia Bảo", gender: "M", birthYear: 1992, deathYear: null, birthPlace: "Hà Nội", note: null},
  {id: "P022", name: "Nguyễn Thảo Chi", gender: "F", birthYear: 1995, deathYear: null, birthPlace: "Hà Nội", note: null},
  {id: "P023", name: "Hoàng Minh Đạt", gender: "M", birthYear: 1994, deathYear: null, birthPlace: "Hà Nội", note: null},
  {id: "P024", name: "Phạm Ngọc Hà", gender: "F", birthYear: 1993, deathYear: null, birthPlace: "Nam Định", note: null},
  {id: "P025", name: "Nguyễn Gia Huy", gender: "M", birthYear: 1996, deathYear: null, birthPlace: "Hà Nội", note: null},
  {id: "P026", name: "Lê Thị Ánh", gender: "F", birthYear: 1993, deathYear: null, birthPlace: "Hải Dương", note: null},
  {id: "P027", name: "Vũ Văn Bình", gender: "M", birthYear: 1992, deathYear: null, birthPlace: "Nam Định", note: null},
  {id: "P028", name: "Nguyễn An Nhiên", gender: "F", birthYear: 2015, deathYear: null, birthPlace: "Hà Nội", note: null},
  {id: "P029", name: "Nguyễn Minh Quân", gender: "M", birthYear: 2018, deathYear: null, birthPlace: "Hà Nội", note: null},
  {id: "P030", name: "Vũ Phương Thảo", gender: "F", birthYear: 2014, deathYear: null, birthPlace: "Nam Định", note: null}
] AS row
MERGE (p:Person {id: row.id})
SET p += row;

UNWIND [
  {from: "P001", to: "P003"},
  {from: "P001", to: "P004"},
  {from: "P001", to: "P005"},
  {from: "P002", to: "P003"},
  {from: "P002", to: "P004"},
  {from: "P002", to: "P005"},
  {from: "P003", to: "P010"},
  {from: "P003", to: "P011"},
  {from: "P006", to: "P010"},
  {from: "P006", to: "P011"},
  {from: "P004", to: "P012"},
  {from: "P004", to: "P013"},
  {from: "P007", to: "P012"},
  {from: "P007", to: "P013"},
  {from: "P005", to: "P014"},
  {from: "P005", to: "P015"},
  {from: "P008", to: "P014"},
  {from: "P008", to: "P015"},
  {from: "P005", to: "P016"},
  {from: "P009", to: "P016"},
  {from: "P010", to: "P021"},
  {from: "P010", to: "P022"},
  {from: "P017", to: "P021"},
  {from: "P017", to: "P022"},
  {from: "P011", to: "P023"},
  {from: "P018", to: "P023"},
  {from: "P012", to: "P024"},
  {from: "P019", to: "P024"},
  {from: "P014", to: "P025"},
  {from: "P020", to: "P025"},
  {from: "P021", to: "P028"},
  {from: "P021", to: "P029"},
  {from: "P026", to: "P028"},
  {from: "P026", to: "P029"},
  {from: "P024", to: "P030"},
  {from: "P027", to: "P030"}
] AS row
MATCH (parent:Person {id: row.from}), (child:Person {id: row.to})
MERGE (parent)-[:PARENT_OF]->(child);

UNWIND [
  {from: "P001", to: "P002", marriageYear: 1942},
  {from: "P003", to: "P006", marriageYear: 1967},
  {from: "P004", to: "P007", marriageYear: 1968},
  {from: "P005", to: "P008", marriageYear: 1973},
  {from: "P005", to: "P009", marriageYear: 2001},
  {from: "P010", to: "P017", marriageYear: 1990},
  {from: "P011", to: "P018", marriageYear: 1992},
  {from: "P012", to: "P019", marriageYear: 1991},
  {from: "P014", to: "P020", marriageYear: 1995},
  {from: "P021", to: "P026", marriageYear: 2014},
  {from: "P024", to: "P027", marriageYear: 2013}
] AS row
MATCH (a:Person {id: row.from}), (b:Person {id: row.to})
MERGE (a)-[r:SPOUSE_OF]-(b)
SET r.marriageYear = row.marriageYear;

// Verification counts on an otherwise empty demo database: 30, 36, 11.
MATCH (p:Person) RETURN count(p) AS persons;
MATCH ()-[r:PARENT_OF]->() RETURN count(r) AS parentOf;
MATCH ()-[r:SPOUSE_OF]->() RETURN count(r) AS spouseOf;
