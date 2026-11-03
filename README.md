# Gia phả Neo4j

Ứng dụng đồ án NoSQL dùng Laravel 13, PHP 8.3, Blade và Neo4j Community 5.
Dữ liệu thành viên/quan hệ chỉ lưu trong Neo4j; session/cache dùng tệp, queue đồng bộ.
Không dùng Eloquent, SQL hay migrations cho dữ liệu nghiệp vụ.

## Chức năng

CRUD thành viên; tìm tên và phân trang 10 người; hồ sơ với các nhóm họ hàng;
quản lý cha mẹ–con/vợ chồng có validation và transaction; cây gia phả vis-network;
đường đi quan hệ có diễn giải; Dashboard có biểu đồ; Cypher Console và Admin.

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
| `/` | Dashboard, thống kê và biểu đồ |
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
| `/console` | Cypher Console |
| `/admin` | Schema, PROFILE, reset demo local |

Bấm tên/node để mở hồ sơ. Tìm kiếm không phân biệt hoa/thường nhưng **không bỏ dấu tiếng Việt**: hãy gõ có dấu.
Nam xanh, nữ hồng, người đã mất có † và màu xám. Vợ/chồng nét đứt cùng tầng;
cha mẹ → con có mũi tên. Có thể tồn tại nhiều đường đi ngắn nhất cùng độ dài.

## Kiểm thử

**Bật Neo4j và nạp seed trước** khi chạy toàn bộ tests. Dùng cơ sở dữ liệu demo.

```powershell
php artisan test --compact
# Chỉ định dạng/kiểm tra các tệp mình sửa; có thể dùng toàn bộ khi nhóm đã thống nhất:
php vendor/bin/pint --test
php artisan route:list --except-vendor
composer validate --strict
composer check-platform-reqs
```

`FamilyRepositoryContractTest` đối chiếu nguyên văn đủ 34 truy vấn và tham số với `neo4j/queries.cypher`.
`FamilyRulesTest`, `PersonTest`, `FamilyGraphTest` dùng Neo4j thật: chỉ tạo/sửa/xóa người **T_**;
tearDown chỉ dọn T_ và so sánh toàn bộ đồ thị gốc, không xóa/sửa P001–P030.
Test HTTP cấp P031 dùng mock, không ghi mã P vào seed. Không chạy integration song song chung DB.

Kiểm tra lại sau test trong Neo4j Browser:

```cypher
MATCH (p:Person) RETURN count(p) AS persons;
MATCH ()-[r:PARENT_OF]->() RETURN count(r) AS parentOf;
MATCH ()-[r:SPOUSE_OF]->() RETURN count(r) AS spouseOf;
```

Kết quả seed chuẩn **30 / 36 / 11**. Kết quả chạy thực tế và ảnh: [báo cáo app](docs/report-app.md).

## Cấu trúc

| Vị trí | Vai trò |
| --- | --- |
| `app/Services/Neo4jService.php` | Singleton, chuẩn hóa kết quả, transaction, lỗi |
| `app/Services/FamilyRepository.php` | 34 phương thức Cypher theo catalog |
| `app/Services/FamilyService.php` | Quy tắc và thao tác ghi có khóa/transaction |
| `app/Services/FamilyGraphService.php` | Dữ liệu vis-network, tầng, diễn giải |
| `app/Http/Requests/` | Validation và ép kiểu input |
| `app/Http/Controllers/`, `resources/views/` | HTTP và Blade |
| `routes/web.php` | Routes app, nạp route Console/Admin |
| `neo4j/` | Schema, seed, catalog và tài liệu DB |
| `docs/report-app.md`, `docs/qa-app.md` | Báo cáo và 10 câu hỏi bảo vệ app |

## Giới hạn và khắc phục lỗi

- Chưa có đăng nhập/phân quyền app. Community không có RBAC; Console lọc từ khóa không phải phân quyền thật.
- Quy tắc FamilyService chỉ bảo vệ thao tác qua app. Không reset Admin hoặc ghi trực tiếp bằng Browser trong lúc nhập liệu.
- Khóa tệp chỉ tuần tự hóa ghi app trên một máy; nhiều máy chủ/client trực tiếp cần giải pháp khác.
- Cấp ID bằng max+1 có thể dùng lại mã cao nhất đã xóa; constraint ID vẫn do Neo4j thực thi.
- Người ngoài nhánh thế hệ đã chọn trong cây toàn bộ có thể nằm ở tầng gốc; tầng là bố cục, không tạo quan hệ mới.
- CDN cần Internet; mất thư viện vẫn có bảng/danh sách link và thông báo.
- Neo4j lỗi: Dashboard báo tiếng Việt; trang cần dữ liệu trả 503 thân thiện. Kiểm tra cổng/tài khoản rồi `php artisan config:clear`.
- PHP/Composer chưa trong PATH: thêm `<PHP_HOME>`, `<COMPOSER_HOME>`, kiểm tra `php -v`, `composer -V`.
- Thiếu extension: xem `php --ini`, chạy `composer check-platform-reqs`.
- Cổng 8000 bận: `php artisan serve --port=8001`.
- **Docker chưa được kiểm thử trên máy kiểm chứng bản app này**; tests thực tế dùng Neo4j native 5.26.31.

Phát triển trên nhánh riêng, mở PR vào main để người còn lại review; không push trực tiếp main.
