# Gia phả Neo4j

Ứng dụng đồ án NoSQL dùng Laravel 13, PHP 8.3, Blade và Neo4j Community 5.
Dữ liệu thành viên/quan hệ chỉ lưu trong Neo4j; session/cache dùng tệp, queue đồng bộ.
Không dùng Eloquent, SQL hay migrations cho dữ liệu nghiệp vụ.

## Chức năng

CRUD thành viên; tìm tên và phân trang 10 người; hồ sơ với các nhóm họ hàng;
quản lý cha mẹ–con/vợ chồng có validation và transaction; cây gia phả vis-network;
đường đi quan hệ có diễn giải; Dashboard có biểu đồ và trang Admin.

Từ hồ sơ hoặc cây có thể thêm con, cha/mẹ, vợ/chồng và lưu ngay quan hệ trong cùng
một transaction. Thêm con có thể chọn thêm cha/mẹ thứ hai. Phả đồ xuất được PNG,
SVG và in/lưu PDF toàn cây, kể cả phần nằm ngoài khung nhìn.

## Yêu cầu

- PHP **8.3+**, Composer 2 và extensions theo `composer check-platform-reqs`.
- Neo4j Community 5: Docker Desktop/Compose hoặc bản native với Java tương thích.
- Internet cho Bootstrap 5.3.8, vis-network 9.1.9, Chart.js 4.4.9 qua CDN.
- Không cần npm/Vite cho giao diện Blade này.

## Cài từ bản clone sạch

Chạy tại gốc repo. Compose dùng mật khẩu demo mặc định, không cần xuất biến trước.
Nếu đang dùng Neo4j native trên cổng 7474/7687, chọn một runtime trước khi chạy Docker.

```powershell
git clone https://github.com/Charlesss30/Neo4j_Nosql.git
cd Neo4j_Nosql
docker compose up -d
docker compose ps
# Đợi healthy rồi nạp dữ liệu demo:
docker exec familytree-neo4j cypher-shell -u neo4j -p familytree123 -d neo4j -f /scripts/schema.cypher
docker exec familytree-neo4j cypher-shell -u neo4j -p familytree123 -d neo4j -f /scripts/seed.cypher
composer install
if (!(Test-Path .env)) { Copy-Item .env.example .env }
php artisan key:generate
php artisan config:clear
php artisan serve --host=127.0.0.1 --port=8000
```

Trên macOS/Linux dùng `cp .env.example .env` cho lần cài đầu.
Giữ cấu hình/key nếu đã cài ứng dụng; không chạy lại key:generate trên ứng dụng đang dùng.
Hướng dẫn DB/CSV/native: [neo4j/README.md](neo4j/README.md).
Seed chuẩn **30 người / 36 PARENT_OF / 11 SPOUSE_OF**. Không nạp lại seed nếu cần giữ thay đổi demo.
Ngoài lệnh cypher-shell, `php artisan db:seed` cũng nạp schema/seed Neo4j qua
`DatabaseSeeder`; không dùng seeder SQL và không xóa graph trước khi nạp.
Seed dùng MERGE nhưng có thể ghi lại thuộc tính fixture, nên chỉ nạp vào DB demo/kiểm thử.

Các giá trị cần có trong `.env` (đã có trong `.env.example`):

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

`neo4j` / `familytree123` là tài khoản demo công khai. Không commit `.env`.
Nếu đổi mật khẩu, cập nhật `.env` và biến `NEO4J_PASSWORD` cho Compose để khớp.
Thay NEO4J_AUTH không tự đổi mật khẩu của volume đã tồn tại.

## Các trang

Gốc minh họa: <http://127.0.0.1:8000>.

| URL | Chức năng |
| --- | --- |
| `/?root=P010` | Dashboard; thống kê toàn graph và thế hệ theo gốc đã chọn |
| `/persons` | Tìm tên, danh sách, phân trang |
| `/persons/create` | Thêm thành viên |
| `/persons/P014` | Hồ sơ; anh chị em mẫu P015 và P016 |
| `/persons/P014/edit` | Sửa |
| `/persons/P014/delete` | Xác nhận xóa, số quan hệ sẽ mất |
| `/relationships` | Thêm/xóa cha mẹ–con và vợ chồng |
| `/tree?root=P001` | Cây từ P001 |
| `/tree?all=1` | Toàn bộ đồ thị |
| `/api/tree?root=P010` | JSON nodes/edges của nhánh P010 |
| `/relation?a=P010&b=P012` | Đường đi ngắn nhất và diễn giải |
| `/admin` | Sao lưu/khôi phục JSON, schema, PROFILE, reset demo local |

Bấm tên để mở hồ sơ. Trên cây, bấm node để chọn người trong biểu mẫu thêm người thân;
bấm đúp node để mở hồ sơ. Đồ thị đường quan hệ vẫn mở hồ sơ khi bấm node.
Tìm kiếm không phân biệt hoa/thường nhưng **không bỏ dấu tiếng Việt**: hãy gõ có dấu.
Nam xanh, nữ hồng, người đã mất có † và màu xám. Vợ/chồng nét đứt cùng tầng;
cha mẹ → con nối bằng nhánh gấp khúc. Anh chị em xếp theo năm sinh, tách đúng các nhóm con khi có nhiều cuộc hôn nhân. Dùng “Vừa khung”, “Cỡ chữ rõ” hoặc ＋/− để xem; trên điện thoại có thể kéo/chụm hai ngón tay. Có thể tồn tại nhiều đường đi ngắn nhất cùng độ dài.

Dashboard ưu tiên P001 khi chưa chọn gốc, sau đó lấy một người từ `roots`.
Gốc yêu cầu không tồn tại trả 404; DB rỗng có trạng thái trống. Giới tính, sống/mất,
top cha mẹ và tổng thành viên là toàn graph; biểu đồ thế hệ chỉ thuộc gốc đã chọn.
Ví dụ P010 có **2 / 3 / 2** người theo thế hệ, tổng 7, trong graph 30 người.

## Quy tắc quan hệ

- `PARENT_OF` được hiểu là cha mẹ sinh học trong phạm vi đồ án. Cha/mẹ sinh trước
  con; tối đa hai cha mẹ; không tự nhận, không trùng và không có chu trình.
- Nếu biết năm mất: con sinh không sau năm mất của mẹ, hoặc không sau năm mất của
  cha cộng 1. Đây là quy ước kỹ thuật cho dữ liệu chỉ có năm, không phải kết luận
  y khoa/pháp lý. Không tự gán năm mất khi thiếu; không đặt chênh lệch tuổi tối thiểu.
- Chặn hôn nhân giữa tổ tiên–hậu duệ và anh chị em chung ít nhất một cha/mẹ.
  Thêm cạnh cha mẹ sau hôn nhân cũng kiểm tra mâu thuẫn này, kể cả qua cạnh trung gian.
  Không mở rộng thành cấm anh em họ hoặc giới hạn số hôn nhân.
- Năm kết hôn có thể trống; nếu có phải không trước năm sinh hai người, không sau
  hiện tại hoặc năm mất đã biết của bất kỳ người nào. Cùng năm mất được chấp nhận.
- Thêm người kèm quan hệ và sửa năm sinh/mất/giới tính đều kiểm tra lại các quan hệ
  liên quan. Lỗi làm rollback toàn bộ người/cạnh trong transaction.

## Sao lưu và khôi phục tại Admin

Mở `/admin#backup-restore` trong môi trường `local`. Bấm **Tạo & tải bản sao lưu**
để lưu bản JSON trên máy chủ và tải về máy. Có thể tải lại 10 bản gần nhất.
Khôi phục bằng tệp JSON và ô xác nhận thay toàn bộ gia phả. App kiểm tra định dạng,
checksum, kiểu dữ liệu và quy tắc quan hệ trước khi ghi; tự lưu bản graph hiện tại
trước khi thay. Không lưu được bản an toàn thì dừng. Thao tác thay graph là một
transaction, lỗi giữa chừng sẽ rollback. Giới hạn tệp 10 MB, 20.000 người/100.000 cạnh;
đây là giới hạn đầu vào, chưa phải chứng minh UI xử lý tốt cây cỡ đó.

JSON chứa người và hai loại cạnh nghiệp vụ; không chứa tài khoản, cấu hình hay
custom schema. App khôi phục schema chuẩn của dự án. Tệp nằm ở
`storage/app/private/family-backups/`; không tự xóa các bản cũ, danh sách chỉ hiện 10 bản.
Graph có label/type/thuộc tính ngoài hợp đồng hoặc dữ liệu không hợp lệ sẽ bị từ chối,
không tự sửa/xóa dữ liệu cũ. Đây là export logic, khác dump nhị phân offline của Neo4j.
Dump database gia phả cũng không chứa user/role của database `system`.

## Kiểm thử

**Chuẩn bị Neo4j kiểm thử riêng, có thể bỏ đi, và nạp seed vào đó trước.**
Không trỏ integration tests vào database người dùng. Community có thể chạy một
instance riêng với thư mục data và cổng Bolt khác; kiểm tra URI trước khi bật cờ.

```powershell
$env:NEO4J_URI='<DEDICATED_TEST_BOLT_URI>'
$env:NEO4J_TEST_DATABASE='1'
$env:NEO4J_RESTORE_TESTS='1' # Chỉ bật trên DB riêng: có test thay/xóa toàn graph.
php vendor/bin/phpunit --do-not-cache-result --fail-on-warning --fail-on-risky
# Chỉ định dạng/kiểm tra các tệp mình sửa; có thể dùng toàn bộ khi nhóm đã thống nhất:
php vendor/bin/pint --test
node --test tests/js/pedigree-export.test.mjs
php artisan route:list --except-vendor
composer validate --strict
composer check-platform-reqs
```

`FamilyRepositoryContractTest` đối chiếu nguyên văn đủ 34 truy vấn và tham số với `neo4j/queries.cypher`.
Các test CRUD/quy tắc thông thường tạo người **T_**, dọn fixture rồi so toàn bộ graph gốc.
`AdminBackupRestoreTest` thực sự thay/xóa graph trên DB cô lập và hoàn trả graph gốc;
chỉ chạy khi `NEO4J_RESTORE_TESTS=1`. Thiếu cả hai cờ thì integration tests skip
trước khi kết nối DB. Cờ là xác nhận lựa chọn DB của người chạy, không tự xác minh
URI có an toàn hay không. Không chạy integration song song chung DB.
Test cấp P031 dùng mock. Có test hai tiến trình tranh khóa, rollback reset/restore,
phục hồi index sau PROFILE lỗi và request tiếp theo hoạt động.

Kiểm tra lại sau test trong Neo4j Browser:

```cypher
MATCH (p:Person) RETURN count(p) AS persons;
MATCH ()-[r:PARENT_OF]->() RETURN count(r) AS parentOf;
MATCH ()-[r:SPOUSE_OF]->() RETURN count(r) AS spouseOf;
```

Kết quả seed chuẩn **30 / 36 / 11**. Kết quả chạy thực tế và ảnh: [báo cáo app](docs/report-app.md).
Lượt sửa theo bàn giao 05/11/2026: [review-fixes.md](docs/review-fixes.md),
kèm [bằng chứng mới](docs/review-fixes-verification.json); không thay số liệu lịch sử.

## Cấu trúc

| Vị trí | Vai trò |
| --- | --- |
| `app/Services/Neo4jService.php` | Singleton, chuẩn hóa kết quả, transaction, lỗi |
| `app/Services/FamilyRepository.php` | 34 phương thức Cypher theo catalog |
| `app/Services/FamilyService.php` | Quy tắc và thao tác ghi có khóa/transaction |
| `app/Services/FamilyRelationshipPolicy.php` | Quy tắc năm và huyết thống dùng chung |
| `app/Services/GraphWriteLock.php` | Khóa OS dùng chung cho ghi, seed và bảo trì |
| `app/Services/FamilyBackupService.php` | JSON backup/restore, bản an toàn và validation |
| `app/Services/FamilyGraphService.php` | Dữ liệu vis-network, tầng, diễn giải |
| `app/Services/FamilyTreeLayout.php` | Bố trí cố định từng gia đình/nhánh và sắp theo năm sinh |
| `app/Http/Requests/` | Validation và ép kiểu input |
| `app/Http/Controllers/`, `resources/views/` | HTTP và Blade |
| `routes/web.php` | Routes app, nạp `routes/admin.php` |
| `neo4j/` | Schema, seed, catalog và tài liệu DB |
| `docs/report-app.md`, `docs/qa-app.md` | Báo cáo và 10 câu hỏi bảo vệ app |

## Giới hạn và khắc phục lỗi

- Chưa có đăng nhập/phân quyền app. Community không có RBAC.
- Quy tắc FamilyService bảo vệ thao tác qua app; ghi trực tiếp bằng Browser/Bolt có thể bỏ qua.
- CRUD, seed, backup/restore, reset và so sánh index cùng dùng `GraphWriteLock` tại
  `storage/framework/cache/graph-write.lock`: OS `flock`, chờ tối đa 5 giây mặc định
  (`neo4j.write_lock_wait_seconds`), giữ đến khi xong/lỗi/process kết thúc, không hết hạn TTL.
  Lời gọi lồng cùng singleton không lấy lại khóa. Chỉ bảo vệ các process dùng chung
  tệp trên một máy; không phải khóa phân tán. Không xóa/đổi tệp khóa khi app đang chạy.
- `APP_ENV=local` là điều kiện môi trường, không phải đăng nhập hay phân quyền người dùng.
- Validation mới bảo vệ thao tác mới; chưa có công cụ audit toàn bộ dữ liệu cũ.
- Danh sách chọn người vẫn tải toàn bộ; chưa load test render/export UI 20.000 người.
- Cấp ID bằng max+1 có thể dùng lại mã cao nhất đã xóa; constraint ID vẫn do Neo4j thực thi.
- Cây xếp theo từng nhánh và năm sinh; các gia đình rời được đặt riêng. Với hôn nhân nối hai nhánh, vẫn hiển thị đủ cha mẹ dù có thể có đường nối dài.
- CDN cần Internet; mất thư viện vẫn có bảng/danh sách link và thông báo.
- Neo4j lỗi: Dashboard báo tiếng Việt; trang cần dữ liệu trả 503 thân thiện. Kiểm tra cổng/tài khoản rồi `php artisan config:clear`.
- PHP/Composer chưa trong PATH: thêm `<PHP_HOME>`, `<COMPOSER_HOME>`, kiểm tra `php -v`, `composer -V`.
- Thiếu extension: xem `php --ini`, chạy `composer check-platform-reqs`.
- Cổng 8000 bận: `php artisan serve --port=8001`.
- Bằng chứng delivery tại commit `d448211` ghi nhận clone sạch từ repo Git cục bộ,
  Composer trong container, restart và dump/load thật vào volume mới; xem
  [kiểm chứng bốn hạng mục](docs/delivery-verification.md). Đây không phải bằng chứng
  clone trực tiếp GitHub hay kiểm chứng lại mã mới ngày 05/11. Lượt mới dùng native;
  máy hiện không có Docker CLI.

Phát triển trên nhánh riêng, mở PR vào main để người còn lại review; không push trực tiếp main.

## Kiểm chứng clone sạch, backup/restore và PROFILE lớn

Cần Docker Desktop/Compose và Python 3.10+. Commit nhánh đang thử trước khi chạy;
script clone nhánh cục bộ bằng Git, xác nhận không có `.env`/`vendor`, rồi cài Composer
trong image PHP riêng. Không sử dụng `.env` hoặc volume dữ liệu của ứng dụng đang dùng.

```powershell
python scripts/verify_delivery.py --output workbench/run-01 --people 20000
# Nếu máy có nhiều Docker context, thêm --docker-context desktop-linux.
# Thêm --keep để giữ app/container riêng cho bước kiểm tra trình duyệt.
```

Đầu ra gồm `verification.json`, log tests, `neo4j.dump` thật và dataset PROFILE riêng.
Mặc định script dọn container/volume thử sau khi xong, giữ các file bằng chứng.
Nếu dùng `--keep`, dọn đúng môi trường đó bằng:

```powershell
python scripts/cleanup_verification.py workbench/run-01/verification.json
```

Thông tin backup, cách đo, kết quả và kiểm thử trình duyệt: [docs/delivery-verification.md](docs/delivery-verification.md).
