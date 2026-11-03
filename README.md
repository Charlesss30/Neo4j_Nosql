# Ứng dụng gia phả Neo4j — Member B

Laravel 13 (PHP 8.3+), Blade, Bootstrap 5 CDN và `laudis/neo4j-php-client`.
Ứng dụng lưu dữ liệu nghiệp vụ trong Neo4j; session/cache dùng tệp, queue chạy đồng bộ.
Không chạy migration, không dùng MySQL/SQLite hay Eloquent cho ứng dụng.

## Trạng thái Milestone 1

- Đã có Neo4jService singleton: `run()`, `client()`, `ping()`; kết quả chuyển đệ quy về mảng PHP.
- Dashboard `/`: trạng thái kết nối, giới tính, còn sống/đã mất, người nhiều con nhất và người sinh sớm nhất.
- FamilyRepository có đủ 34 phương thức, sao chép nguyên văn truy vấn của Member A, truyền tham số riêng.
- Đã khai báo route thành viên, quan hệ, cây gia phả và tra cứu; các trang này hiển thị thông báo chờ Milestone 2.
- Endpoint ghi và `/api/tree` hiện trả HTTP 501 để không báo thành công khi tính năng chưa được triển khai.
- `/console` và `/admin` thuộc Member A. Navbar đã liên kết; route sẽ được nạp khi A cung cấp `routes/admin_console.php`.

## Yêu cầu

- PHP **8.3 trở lên** (Laravel 13 đã có trong bản dự án cần PHP 8.3, không chạy bằng PHP 8.2).
- Composer 2, PHP extensions theo `composer check-platform-reqs`.
- Docker Desktop đang chạy và Neo4j Community 5.x; cổng 7474, 7687 không bị chiếm.
- Trình duyệt có Internet để tải Bootstrap CDN. Milestone 1 không cần Node/npm hay Vite.

Trên máy hiện tại, nếu `php`/`composer` chưa nằm trong PATH, mở PowerShell:

```powershell
$env:Path = 'D:\S\laragon\bin\php\php-8.3.30-Win32-vs16-x64;D:\S\laragon\bin\composer;' + $env:Path
cd D:\S\laragon\www\projectNoSQL
php -v
composer -V
```

## Cài đặt Laravel

```powershell
composer install
# Chỉ sao chép nếu chưa có .env; giữ nguyên cấu hình cá nhân nếu tệp đã tồn tại.
if (!(Test-Path .env)) { Copy-Item .env.example .env }
php artisan key:generate
php artisan config:clear
```

Giữ các giá trị sau trong `.env`:

```dotenv
SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
NEO4J_URI=bolt://localhost:7687
NEO4J_USER=neo4j
NEO4J_PASSWORD=familytree123
NEO4J_DATABASE=neo4j
```

`familytree123` là mật khẩu demo công khai theo hợp đồng nhóm. `.env` bị Git bỏ qua.
Nếu cơ sở dữ liệu đã có mật khẩu khác, cấu hình theo mật khẩu thực tế của nó.

## Khởi động và nạp Neo4j

Trong repository đã hợp nhất, `docker-compose.yml` và `neo4j/` nằm ở gốc Laravel.
Chạy các lệnh sau tại gốc repository:

```powershell
$env:NEO4J_PASSWORD = 'familytree123'
docker compose up -d
docker compose ps
# Chờ container healthy rồi nạp schema và seed:
docker exec familytree-neo4j cypher-shell -u neo4j -p familytree123 -d neo4j -f /scripts/schema.cypher
docker exec familytree-neo4j cypher-shell -u neo4j -p familytree123 -d neo4j -f /scripts/seed.cypher
```

Hướng dẫn chính thức của A: [neo4j/README.md](neo4j/README.md).
Seed dự kiến: 30 người, 36 quan hệ cha mẹ–con, 11 quan hệ vợ chồng.
Neo4j Browser: <http://localhost:7474>, kết nối `bolt://localhost:7687`.
Nếu đang dùng bản ZIP cũ trên máy với thư mục lồng `Neo4j_Nosql-main/`,
dùng `docker compose -f .\Neo4j_Nosql-main\docker-compose.yml up -d` thay thế.

## Chạy ứng dụng

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

Mở <http://127.0.0.1:8000>. Khi Neo4j đã nạp seed, Dashboard hiển thị 30 thành viên,
các bảng thống kê và trạng thái xanh “Đã kết nối”. Khi Neo4j chưa chạy, trang vẫn mở
với thông báo tiếng Việt, trạng thái “Chưa kết nối” và dấu “—” cho các chỉ số chưa có.
Các mục thuộc Milestone 2 hiện báo “Chức năng đang phát triển”.

## Kiểm thử

```powershell
php artisan test
php artisan route:list --except-vendor
composer validate --strict
composer check-platform-reqs
```

Milestone 1 có unit/feature tests cho chuyển đổi CypherMap/CypherList, truyền tham số,
lỗi xác thực/kết nối, kết quả lazy, phục hồi kết nối, Dashboard và đối chiếu đủ 34 truy vấn.
Các test này dùng mock, không cần Neo4j. Chúng chưa chứng minh truy vấn chạy trên Neo4j thật.
Các test `FamilyRulesTest` của Milestone 2 sẽ cần Neo4j thật, tạo người có tiền tố `T_`
và dọn dữ liệu thử theo hợp đồng; chúng chưa thuộc bản Milestone 1 này.

## Cấu trúc chính

| Tệp | Vai trò |
| --- | --- |
| `config/neo4j.php` | URI, tài khoản, database, timeout |
| `app/Services/Neo4jService.php` | Giao tiếp client, chuẩn hóa kết quả, ping |
| `app/Services/FamilyRepository.php` | 34 truy vấn Cypher nguyên văn |
| `app/Exceptions/Neo4jQueryException.php` | Thông báo lỗi an toàn bằng tiếng Việt |
| `app/Http/Controllers/DashboardController.php` | Điều phối thống kê |
| `resources/views/layouts/app.blade.php` | Bootstrap, menu, flash, script stack |
| `routes/web.php` | Route của B và điểm tích hợp route của A |

## Git và bàn giao

Phần cơ sở dữ liệu của A nằm trong `neo4j/` và `docker-compose.yml`; phần Laravel của B
nằm ở gốc repository. Các truy vấn PHP được kiểm thử đối chiếu với `neo4j/queries.cypher`.
Khi phát triển tiếp, clone repository để giữ lịch sử commit thay vì khởi tạo Git từ ZIP:

```powershell
git clone --branch Khang https://github.com/Charlesss30/Neo4j_Nosql.git
cd Neo4j_Nosql
git switch -c feature/app-persons
```

Đồng bộ thay đổi qua pull request vào `main`. Không ghi đè các tệp thuộc Member A.

## Khắc phục lỗi

- `php` không được nhận diện: thêm thư mục PHP Laragon vào PATH như ví dụ trên.
- Thiếu extension: kiểm tra `php --ini`, bật extension trong đúng PHP CLI rồi chạy lại Composer.
- Docker Engine không kết nối được: mở Docker Desktop, chờ engine sẵn sàng, chạy `docker info`.
- Lỗi xác thực: kiểm tra `.env`; đổi `NEO4J_AUTH` không đổi mật khẩu của volume đã tồn tại.
- Đổi `.env` chưa có hiệu lực: chạy `php artisan config:clear`.
- Thống kê rỗng: nạp schema/seed đúng database `neo4j` theo hướng dẫn của A.
- Cổng 8000 bị chiếm: dùng `php artisan serve --port=8001`.
- Lỗi quyền tệp: bảo đảm tài khoản chạy PHP có quyền đọc dự án và ghi `bootstrap/cache`, `storage`.
- `/console` hoặc `/admin` trả 404: A chưa cung cấp route/controller/view tương ứng ở Milestone 1.
