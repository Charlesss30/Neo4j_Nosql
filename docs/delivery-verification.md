# Kiểm chứng bốn hạng mục bổ sung

## 1. Thêm người kèm quan hệ từ hồ sơ/cây

Hồ sơ có nút **Thêm con**, **Thêm cha/mẹ**, **Thêm vợ/chồng**. Trên cây,
bấm node để chọn người trong biểu mẫu rồi chọn vai trò của người mới. Bấm đúp
node vẫn mở hồ sơ; danh sách chọn vẫn dùng được khi không tải được vis-network.

Biểu mẫu xác nhận hướng quan hệ: “người mới là con/cha mẹ/vợ chồng của” mã đã có.
Thêm con cho phép chọn cha/mẹ thứ hai, nhưng không suy diễn hai cha mẹ đã kết hôn.
Năm kết hôn chỉ dùng cho quan hệ vợ/chồng. Các trường không áp dụng được vô hiệu hóa
trên giao diện và vẫn bị kiểm tra lại phía server.

`FamilyService::createPerson` cấp ID, tạo người, kiểm tra và tạo quan hệ trong một
transaction và một lần lấy khóa ghi. Các hàm thêm quan hệ dùng chung các quy tắc cũ:
hai người phải tồn tại, không trùng, không tự làm cha mẹ, tối đa hai cha mẹ, không chu trình,
cha mẹ sinh trước con; năm kết hôn không trước năm sinh hai người và không sau hiện tại.
34 truy vấn trong catalog của A không bị chỉnh sửa.

Nếu bất kỳ quan hệ nào lỗi, người mới và mọi cạnh mới đều rollback. Kiểm thử thực tế
đã phát hiện đường xử lý exception trong transaction managed có thể giữ khóa lâu;
`Neo4jService` hiện dùng API `beginTransaction`, `commit`, `rollback` tường minh.
Không lồng thêm khóa tệp khi nối các quan hệ trong cùng thao tác. Sau rollback,
runner được khôi phục và yêu cầu tiếp theo vẫn chạy. Không tự retry thao tác ghi
khi gặp lỗi kết nối; người dùng nhận lỗi thân thiện và có thể tải lại để kiểm tra.

Khi thêm cha/mẹ từ cây, người mới trở thành gốc nhánh sau khi lưu để không bị ẩn
ngoài subtree của người con cũ. Chuyển hướng chỉ dùng các route nội bộ đã định nghĩa.

## 2. Xuất và in toàn bộ phả đồ

Các nút **Tải ảnh PNG**, **Tải SVG**, **In / Lưu PDF** nằm cạnh điều khiển cây.
Đầu ra dựng từ toàn bộ dữ liệu và tọa độ cố định của cây, không chụp phần canvas
đang nhìn thấy. Xuất được node ngoài màn hình, dấu †, màu giới tính, các đời,
nét vợ/chồng và các nhóm con theo đúng cha mẹ; không thêm cạnh hôn nhân do suy diễn.

SVG là vector và giữ nét khi phóng to. PNG giới hạn mỗi chiều 8.192 px và tổng
32 triệu pixel để tránh vượt bộ nhớ trình duyệt. Bản in dùng trang A3 ngang,
co toàn bộ cây vào trang; trình duyệt cho phép chọn **Lưu PDF**. Nếu chặn cửa sổ
mới, ứng dụng báo cách cho phép in. Tên/thông tin người được escape trước khi đưa vào SVG.

Đầu ra được kiểm tra bằng Edge headless trên seed 30 người: đủ 30 node trong SVG/PDF,
PNG toàn cây 5.960 × 2.220 px. Luồng tạo người từ hồ sơ và cây được thao tác bằng
trình duyệt thật, sau đó xóa người thử và đối chiếu đồ thị gốc. Màn hình 390 px
không có tràn ngang trang.

## 3. Clone sạch, Docker, backup/restore thật

`scripts/verify_delivery.py` dùng Python standard library, Docker và Git:

1. Clone sạch nhánh đã commit từ repository cục bộ (`git clone --no-local`), ghi SHA;
   kiểm tra clone không chứa `.env` hoặc `vendor` trước khi cài.
2. Dựng image PHP 8.3, chạy Composer từ lockfile, tạo `.env` demo trong container,
   kiểm tra Composer/platform và chạy toàn bộ PHPUnit với `--fail-on-warning --fail-on-risky`.
3. Neo4j 5.26.31 Community dùng project, volume và cổng loopback ngẫu nhiên riêng.
   Cấu hình overlay chỉ cô lập runtime và bổ sung app kiểm chứng; dùng Compose gốc cho DB.
4. Nạp schema/seed 30 người, 47 quan hệ. Tests chỉ dùng mã T_ và phải trả lại đồ thị gốc.
5. Thêm `T_BACKUP` và một cạnh PARENT_OF sau khi seed. Restart DB và kiểm tra dữ liệu còn.
6. Dừng DB riêng, chạy `neo4j-admin database dump neo4j --to-path=/backups`.
   Đây là file dump nhị phân thật, không phải seed hoặc file tham chiếu.
7. Chạy `neo4j-admin database load neo4j --from-path=/backups` vào volume hoàn toàn mới,
   khởi động server mới, đối chiếu SHA-256 của toàn bộ thuộc tính node, cạnh, constraints, indexes.
8. Xóa bản ghi chứng cứ khỏi hai DB riêng và xác nhận seed trở lại nguyên trạng.

Lỗi cài Docker thật được sửa: image root cố `chown` thư mục CSV mount read-only.
Compose hiện chạy `user: neo4j` và ghim image `neo4j:5.26.31-community`.
Các volume dump/load mới được cấp quyền UID/GID 7474 trước khi dùng.
Script đọc lại cổng công bố sau restart/start vì cổng ngẫu nhiên có thể đổi.

Dump chỉ chứa dữ liệu/schema của database `neo4j`, không chứa user/role của database
`system`. Server restore khởi tạo tài khoản demo của nó. Community dùng dump/load
offline nên cần dừng database đang dump; không tuyên bố backup online hoặc không gián đoạn.

Các bài thử không dùng container/volume đang có. Project có tên ngẫu nhiên và cleanup
kiểm tra nhãn sở hữu trước khi xóa. File dump và JSON bằng chứng vẫn được giữ lại.
Không dùng `docker system prune` hay xóa volume chung.

## 4. PROFILE trên dataset riêng lớn hơn

Script tạo **20.000 Person / 29.998 quan hệ** trong server/volume benchmark riêng,
không chèn vào seed demo. Dataset có 10.000 cặp vợ chồng, hai cha mẹ cho mỗi người con
ở nhánh huyết thống, hướng cha mẹ → con, năm sinh tăng 25 năm mỗi đời và không có chu trình.
Người kết hôn từ ngoài dòng họ không bị gán cha mẹ giả.

| Phép đo trên cùng dữ liệu | DB hits thực tế |
| --- | ---: |
| Equality theo tên, chưa có index tên | 40.006 |
| Cùng equality và tham số, có index tên | 7 |
| `toLower(name) CONTAINS ...`, đã có index tên | 40.001 |

Kết quả equality hai lần giống nhau; có `NodeIndexSeek` sau khi tạo index và đợi ONLINE.
Giảm khoảng 5.715 lần **DB hits**, không có nghĩa giảm tương ứng thời gian phản hồi.
`CONTAINS` không được ghi nhận như equality seek. Có thêm PROFILE đếm hậu duệ từ
`B000001`, kết quả 9.999 người huyết thống, tách khỏi 10.000 người kết hôn ngoài họ.

Mỗi trường hợp warm-up 2 lần, ghi 5 lần PROFILE; lưu kế hoạch thật, rows, DB hits,
thời gian HTTP và trung vị. Thời gian HTTP bao gồm mạng, serialize và overhead PROFILE,
chỉ là số tham khảo trên máy đang chạy các container khác. DB hits cộng theo các operator;
không cộng rows ở các operator thành tổng người trả về. Index được phục hồi trong `finally`.
Sau benchmark, đồ thị seed của ứng dụng được so sánh lại để chứng minh tách dữ liệu.

## Chạy lại

```powershell
# Docker Desktop đang chạy; Python 3.10+; commit thay đổi trước để clone chứa đúng mã.
python scripts/verify_delivery.py --output workbench/run-01 --people 20000 --keep
# Trên máy dùng context Docker Desktop có thể thêm --docker-context desktop-linux.
node --test tests/js/pedigree-export.test.mjs
```

Kiểm chứng browser dùng Playwright ngoài dependency của app:

```powershell
npm install --prefix workbench/browser playwright --ignore-scripts
$env:PLAYWRIGHT_MODULE = (Resolve-Path workbench/browser/node_modules/playwright/index.mjs).Path
$env:PLAYWRIGHT_EXECUTABLE = 'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe'
node scripts/verify_browser.mjs workbench/run-01/verification.json workbench/browser-evidence
python scripts/cleanup_verification.py workbench/run-01/verification.json
```

Nếu không dùng Edge có sẵn, cài Chromium cho Playwright và bỏ biến executable.
Các thao tác ghi browser chỉ chấp nhận báo cáo của môi trường kiểm chứng riêng trên loopback.
`.env` thật của người dùng không được sao chép vào image hoặc thay đổi trong quá trình kiểm chứng.

Kết quả chạy được lưu trong `docs/delivery-verification.json`; file dump, ảnh và PDF
được bàn giao riêng. Chưa có đăng nhập/phân quyền app; khóa tệp vẫn chỉ tuần tự hóa
ghi trên một máy chủ. Đây là dataset tổng hợp, không phải phép đo trên dữ liệu thực tế của một dòng họ lớn.

Nguồn chính thức: [Neo4j Docker dump/load](https://neo4j.com/docs/operations-manual/current/docker/dump-load/),
[khôi phục dump](https://neo4j.com/docs/operations-manual/current/backup-restore/restore-dump/),
[ý nghĩa EXPLAIN/PROFILE và DB hits](https://neo4j.com/docs/cypher-manual/current/planning-and-tuning/execution-plans/).
