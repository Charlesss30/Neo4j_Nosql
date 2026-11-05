# Rà soát và sửa lỗi theo bàn giao — 05/11/2026

## Nguồn và phạm vi

Thực hiện các mục bắt buộc P1/P2 trong tài liệu bàn giao ngày 05/11/2026.
Nhánh local `feature/app-review-fixes`, nền `daff0eb`; mã đã kiểm thử cuối tại
`273ee593a758b1bd64a3db0f9ed0919716f361cc`. Tài liệu/ảnh được bổ sung sau commit
mã này. Không push, merge hoặc deploy. P3 là đề xuất tùy chọn, chưa triển khai thêm.

Kết quả mới: [review-fixes-verification.json](review-fixes-verification.json).
Không sửa timestamp, SHA hoặc kết quả trong JSON bằng chứng lịch sử. Schema, seed,
34 truy vấn catalog và FamilyRepository giữ nguyên; không thêm dependency, SQL,
model hoặc thay `.env`.

## Các thay đổi

| Mục | Hành vi sau sửa | Mã và kiểm thử chính |
| --- | --- | --- |
| P1-01 | Chặn hôn nhân tổ tiên–hậu duệ và anh chị em chung một/hai cha mẹ. Chặn cả cạnh cha mẹ thêm sau hôn nhân, kể cả cạnh trung gian làm hai người khác trở thành họ hàng bị cấm. Không cấm anh em họ. | FamilyService, FamilyRelationshipPolicy; FamilyConsistencyTest |
| P1-02 | Năm kết hôn không sau năm mất của bất kỳ người nào; cùng năm mất hoặc null vẫn hợp lệ. Sửa hồ sơ hoặc tạo người kèm cạnh sai rollback toàn bộ. | FamilyRelationshipPolicy, FamilyService; FamilyConsistencyTest |
| P1-03 | Kiểm tra năm sinh con với năm mất cha/mẹ khi thêm cạnh, sửa năm/giới tính và tạo người kèm quan hệ. | FamilyRelationshipPolicy; FamilyConsistencyTest, AdminBackupInputTest |
| P2-01 | Dashboard chọn gốc bằng `?root=...`, ưu tiên P001 nếu chưa chọn rồi fallback roots; gốc yêu cầu không tồn tại trả 404, DB rỗng an toàn. Nhãn khớp dữ liệu, số tổng tách rõ với nhánh. | DashboardController/view; DashboardRootTest, FamilyConsistencyTest |
| P2-02 | Một khóa chung cho CRUD, seed, reset, PROFILE thay index, backup/restore. Reset dùng transaction helper tường minh; PROFILE phục hồi index trong finally. | GraphWriteLock, AppServiceProvider, FamilyService, AdminController, DatabaseSeeder; GraphWriteLockTest, GraphWriteCoordinationTest, AdminBackupRestoreTest |
| P2-03 | Đồng bộ README, báo cáo/Q&A app và các diễn giải lịch sử trong báo cáo/Q&A DB; phân biệt từng lượt đo. | Các tài liệu liên quan và JSON mới |
| Yêu cầu Admin | Backup/restore JSON tại `/admin#backup-restore`, có bản an toàn trước restore, kiểm tra đầu vào và rollback. | FamilyBackupService, RestoreBackupRequest, AdminController/view/routes; AdminBackupRestoreTest, AdminBackupInputTest |
| Lỗi db:seed | Laravel nhận đúng DatabaseSeeder, nạp schema/seed Neo4j mà không xóa graph hay dùng seeder SQL. | composer PSR-4, DatabaseSeeder, DatabaseSeederTest |
| Hiển thị mobile | Sửa nhãn cột lịch sử backup gây tràn ngang trang. Bảng rộng cuộn trong vùng bảng. | Admin view; kiểm tra trình duyệt ở ba kích thước |

Các câu Cypher kiểm tra huyết thống mới nằm ngoài catalog cố định, dùng tham số;
kiểm tra và ghi cùng transaction/khóa. Không nối input vào câu lệnh. Test cũ từng
cho cha–con thành phối ngẫu đã đổi fixture độc lập, giữ kiểm tra cạnh trùng.

## Chính sách dữ liệu được chọn

`PARENT_OF` biểu diễn **cha mẹ sinh học** trong phạm vi đồ án; mô hình chưa phân
biệt cha mẹ nuôi. Cha/mẹ phải sinh trước con, không đặt chênh tuổi tối thiểu.
Nếu biết năm mất:

- Mẹ: `child.birthYear <= mother.deathYear`.
- Cha: `child.birthYear <= father.deathYear + 1`.

Dung sai một năm cho cha là quy ước kỹ thuật của mô hình chỉ có năm; **không phải
khẳng định y khoa/pháp lý**. Thiếu năm mất thì không tự suy diễn. Ví dụ mẹ mất
1980/con sinh 2000 bị chặn; cha mất 2000/con sinh 2001 được, sinh 2002 bị chặn.
Đổi giới tính hoặc năm sinh/mất cũng kiểm tra lại các cạnh liên quan.

Năm kết hôn tùy chọn, nếu có phải nằm từ năm sinh của cả hai đến hiện tại và
không sau năm mất đã biết. Không cấm cùng năm mất hoặc cùng năm sinh bằng suy
đoán ngày tháng; không thêm tuổi kết hôn tối thiểu hay giới hạn số phối ngẫu.

## Khóa và transaction

`GraphWriteLock` là singleton dùng OS `flock` tại
`storage/framework/cache/graph-write.lock`, ngoài thư mục data của file cache.
Mặc định chờ tối đa 5 giây (`neo4j.write_lock_wait_seconds`), giữ khóa đến khi
callback kết thúc/lỗi hoặc process kết thúc, không có TTL hết hạn giữa bảo trì.
Lời gọi lồng cùng singleton dùng quyền giữ khóa hiện có, không lấy khóa lần nữa.
Không xóa/đổi tệp khóa khi app đang chạy. Khóa chỉ có hiệu lực cho các process app
dùng chung tệp trên một máy; client Bolt trực tiếp và nhiều host nằm ngoài phạm vi.

Helper Neo4j dùng beginTransaction/commit/rollback tường minh và trả runner về
client trong finally. Reset gộp DELETE và nạp seed trong cùng transaction. Schema
IF NOT EXISTS chạy riêng. So sánh equality tên tạm bỏ `person_name`, phục hồi trong
finally và đợi ONLINE cả khi PROFILE lỗi; không dùng CONTAINS làm demo index seek.

## Backup/restore tại Admin

Mở `/admin#backup-restore` trong môi trường local:

1. **Tạo & tải bản sao lưu**: lưu JSON private trên server, đồng thời tải về máy.
2. Chọn JSON, đánh dấu xác nhận và **Khôi phục từ tệp** để thay toàn bộ gia phả.
3. App kiểm tra dữ liệu, lưu bản an toàn trước thay; lỗi lưu bản an toàn thì dừng.
   DELETE/CREATE/đối chiếu snapshot cùng transaction, lỗi giữa chừng rollback.
4. Tải lại các bản thủ công hoặc trước-restore ở danh sách 10 bản gần nhất.

JSON chứa thuộc tính người và PARENT_OF/SPOUSE_OF; không có tài khoản, cấu hình
hoặc custom schema. Restore áp dụng schema chuẩn. Giới hạn nhận 10 MB,
20.000 người/100.000 cạnh chưa phải kết luận về hiệu năng/UI ở mức đó. Tệp ở
`storage/app/private/family-backups/`, hiện chưa có retention tự xóa. UUID tải
được validate; không nhận đường dẫn tệp tùy ý. Graph có label/type/thuộc tính ngoài
hợp đồng hoặc dữ liệu không hợp lệ bị từ chối, không tự sửa dữ liệu cũ.

Checksum kiểm tra toàn vẹn nội dung, không phải chữ ký xác thực nguồn gửi.
Các route bảo trì kiểm tra môi trường local ở server; APP_ENV không phải auth.
JSON là export logic, khác backup nhị phân Neo4j offline.

## Lệnh thực sự đã chạy và kết quả

PHP 8.3.30, Laravel 13.39.0, Laudis 3.7.1, PHPUnit 12.5.43, Node 24.16.0;
Neo4j Community 5.26.31 native với Java 17. Full suite chạy trên **instance riêng**
Bolt 17687 / HTTP 17474, data/config riêng. DB người dùng Bolt 7687 chỉ được đọc
đối chiếu và tạo backup qua UI, không reset/restore để thử lỗi.

```powershell
$env:NEO4J_URI='bolt://127.0.0.1:17687'
$env:NEO4J_USER='neo4j'
$env:NEO4J_PASSWORD='familytree123'
$env:NEO4J_DATABASE='neo4j'
$env:NEO4J_TEST_DATABASE='1'
$env:NEO4J_RESTORE_TESTS='1'
php vendor/bin/phpunit --do-not-cache-result --fail-on-warning --fail-on-risky --log-junit neo4j/.runtime/review-tests.xml
php vendor/bin/pint --test
node --test tests/js/pedigree-export.test.mjs
composer validate --strict
composer check-platform-reqs
composer show --direct
php artisan route:list --except-vendor
```

Các executable được gọi bằng đường dẫn PHP/Composer/Node đã cài trên máy; trên đây
là cách viết tương đương khi đã có PATH. Kết quả:

- **92 tests / 1190 assertions**, không failure/error/warning/risky/skip.
- **3 JavaScript tests** đạt; Pint toàn dự án, Composer validate/platform đạt.
- 23 routes, gồm 3 route backup/download/restore mới. Script delivery được kiểm
  tra cú pháp sau khi thêm cờ DB riêng, chưa chạy lại Docker.
- Đặt URI không hoạt động `bolt://127.0.0.1:1`, bỏ cả hai cờ và chạy
  `php vendor/bin/phpunit --filter test_ancestor --do-not-cache-result`:
  **1 skipped / 0 assertions**, không thử kết nối DB. Cờ xác nhận vẫn do người
  chạy chịu trách nhiệm trỏ đúng DB riêng, không tự xác minh URI là disposable.

### Kiểm thử có ý nghĩa

| Nhóm | Bằng chứng |
| --- | --- |
| Huyết thống | Neo4j thật: cha/con, tổ tiên xa, anh chị em đủ/nửa huyết thống bị chặn cả hai chiều; cạnh trung gian sau hôn nhân bị chặn; anh em họ vẫn được phép |
| Năm | Biên năm mất mẹ/cha, năm kết hôn sau năm mất của mỗi bên, cùng năm/null, cập nhật năm/giới tính, rollback tạo người kèm quan hệ sai |
| Luật cũ | Tự cha mẹ, cha mẹ thứ ba, chu trình, cạnh trùng, đảo chiều hôn nhân; fixture và 34-query contract vẫn đúng |
| Đồng thời | Hai process đồng bộ stdout/stdin để giữ/nhả khóa; timeout, nhả khi exception, callback lồng; các đường ghi/bảo trì dùng cùng singleton |
| Bảo trì | Lỗi thật sau DELETE trong reset/restore hoàn tác graph; PROFILE lỗi sau DROP phục hồi index ONLINE; request tiếp theo hoạt động |
| Backup | Round-trip toàn thuộc tính/kiểu/cạnh, safety copy có dữ liệu sau bản backup, lỗi lưu tệp không xóa graph, graph rỗng, từ chối label ngoài hợp đồng và JSON sai |
| Dashboard | Chọn gốc, ưu tiên P001, thiếu P001, gốc sai trả 404, input dạng mảng, graph rỗng; P010 thật có 7 người theo thế hệ dù tổng graph là 30 |

### Bảo toàn dữ liệu

Trước lượt cuối: so từng label/thuộc tính người và cạnh/thuộc tính cạnh với CSV.
Sau lượt cuối: so snapshot toàn graph/schema và kiểm tra index, T_. Cả DB người
dùng và DB kiểm thử đều giữ **30 Person / 36 PARENT_OF / 11 SPOUSE_OF**, **0 T_**,
các index ONLINE, hash trước/sau giống nhau:

```text
73142ef6d15ba301e827027f8c782515ddda11b03387679cf474675dc46671fe
```

Hash dùng `Graph.snapshot` có sẵn trong scripts/verify_delivery.py: gồm labels,
properties, cạnh, constraint/index definitions, bỏ schema ID/counter thay đổi theo
runtime. Tests thường dọn T_; test thay toàn graph chỉ chạy DB riêng và hoàn trả
snapshot. Không dọn theo tiền tố trên DB người dùng.

Sau khi thu bằng chứng, đã dừng đúng instance kiểm thử do lượt này tạo, đối chiếu
PID và thời điểm tạo trước khi dừng. Cổng 17474/17687 đã đóng. Neo4j người dùng
7474/7687 và Laravel 8000 vẫn chạy; Admin và Dashboard trả HTTP 200.

### Kiểm tra UI mới

Trình duyệt đã tải JSON thật **30/36/11**, checksum hợp lệ; không bấm restore trên
DB người dùng. Dashboard chọn P010 qua form cho thế hệ **2/3/2**, số tổng vẫn 30.
Desktop/tablet/phone có document widths 1425/805/375 px, scrollWidth bằng width;
Admin controls nằm trong trang, bảng rộng cuộn bên trong. Viewport yêu cầu và
kích thước thực được ghi riêng trong JSON. Override viewport đã được trả về mặc định.

- [Admin sao lưu/khôi phục — ảnh điện thoại thật](screenshots/admin-backup-restore-phone.jpg).
- [Dashboard P010 — ảnh điện thoại thật](screenshots/dashboard-root-phone.jpg).

Ảnh desktop thử bị giới hạn khung capture nên đã loại bỏ; không dùng ảnh cắt để
tuyên bố bằng chứng desktop. Kiểm tra desktop/tablet dựa trên DOM đo trực tiếp.
SVG/PNG/PDF có test JavaScript hồi quy; chưa chạy lại luồng export trình duyệt ở lượt này.

## Phân biệt bằng chứng cũ và mới

| Nguồn | Kết luận được hỗ trợ |
| --- | --- |
| app-verification.json, 03/11 | Native app trước lượt sửa, 49 tests/752 assertions |
| neo4j/evidence và report-db, mã `993b129` | Mốc 3: 34 query mẫu, SQL CTE, PROFILE qua Admin trên seed 30; dump khi đó mới tham khảo |
| delivery-verification.json, mã `d448211` | 58 tests/820 assertions; clone Git cục bộ sạch, Docker, restart, dump/load nhị phân thật vào volume mới; query dataset riêng 20.000/29.998 |
| review-fixes-verification.json, mã `273ee59` | Mã sửa P1/P2 và JSON Admin trên native; 92 tests/1190 assertions, 3 JS tests, dữ liệu/khóa/rollback và UI Admin/Dashboard |

Delivery clone từ **repo Git cục bộ**, không phải chứng minh clone trực tiếp GitHub.
Dump database gia phả không gồm user/role của `system`. Artifact nhị phân trên máy
cũ không mặc nhiên tồn tại ở repo này. Lượt mới không chạy lại Docker/clone sạch/
dump-load/PROFILE 20.000 vì máy hiện không có Docker CLI. Không sửa bằng chứng cũ
để giả như kiểm chứng mã `273ee59`.

Số DB hits 40.006→7 trong delivery chỉ là phép đo equality query trên dữ liệu tổng
hợp; không suy ra tỷ lệ tăng tốc thời gian tương ứng. `toLower(name) CONTAINS` có
phép đo riêng và không phải equality index seek. Benchmark đó chưa chứng minh
render/export UI toàn bộ 20.000 người.

## Giới hạn còn lại

- Chưa có auth app; local là kiểm tra môi trường, không phân quyền người dùng.
- Khóa chỉ cho một host dùng chung tệp; client trực tiếp có thể bỏ qua luật.
- Chưa có audit toàn graph cũ hay sửa dữ liệu cũ tự động; validation chỉ bảo vệ
  các thao tác liên quan sau sửa. Backup có thể từ chối graph ngoài hợp đồng.
- Chỉ có năm, quy ước cha mẹ sinh học chưa biểu diễn ngày cụ thể/cha mẹ nuôi.
- Tìm kiếm chưa bỏ dấu, choices vẫn tải toàn bộ người; UI lớn chưa load test.
- CDN cần mạng; ID max+1 có thể dùng lại mã cao nhất đã xóa.
- JSON backup không thay dump toàn DB/schema/account, chưa tự dọn bản cũ.

P3 tùy chọn không được mở rộng trong lượt này. Các commit ở local để người dùng
kiểm tra trước khi quyết định push/PR.
