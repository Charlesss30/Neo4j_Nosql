# 10 câu hỏi bảo vệ phần Neo4j và Cypher

Các câu trả lời bám catalog và dữ liệu hư cấu 30 người, đồng bộ với bản ứng dụng
đã gỡ Console. Số liệu PROFILE được đo lại qua Admin trong mốc 3;
xem [báo cáo DB](report-db.md) và các JSON bằng chứng.

## 1. Vì sao chọn Neo4j cho gia phả? SQL có làm được không?

Gia phả có nhiều câu hỏi theo đường đi và nhiều thế hệ. Neo4j biểu diễn trực tiếp
người và liên kết; Cypher dùng `PARENT_OF*1..` để tìm tổ tiên. SQL vẫn làm được bằng
recursive CTE, đã chạy đối chiếu cùng dữ liệu và cho đúng tám tổ tiên của P028.
Chưa đo benchmark hai hệ nên không kết luận Neo4j luôn nhanh hơn SQL.

## 2. Tại sao chỉ lưu PARENT_OF và SPOUSE_OF?

Anh chị em, chú bác/cô dì, anh em họ và thế hệ suy ra từ các cạnh cơ sở. Lưu thêm
những quan hệ suy diễn làm tăng số nơi cần sửa khi đổi cha mẹ và dễ gây mâu thuẫn.
Ví dụ P014 có P015 và P016 là anh chị em, dù P016 chỉ cùng cha; đây là kết quả truy
vấn, không có cạnh SIBLING nào được lưu.

## 3. Chiều cạnh và hôn nhân được xử lý thế nào?

`PARENT_OF` luôn lưu cha/mẹ đến con, nên tổ tiên đi ngược mẫu từ người được chọn,
hậu duệ đi xuôi. `SPOUSE_OF` chỉ lưu một cạnh cho mỗi cặp nhưng tìm/kiểm tra/xóa bằng
mẫu vô hướng. Vì vậy đảo thứ tự hai người không tạo hôn nhân thứ hai. P005 có hai
phối ngẫu khác nhau: P008 năm 1973 và P009 năm 2001.

## 4. CREATE khác MERGE thế nào? MERGE có thay unique constraint không?

`CREATE` tạo mới; `MERGE` tìm mẫu phù hợp hoặc tạo khi chưa có, thích hợp nạp lại
seed và thêm cạnh idempotent. `MERGE` không thay constraint: `person_id` bảo đảm mã
không trùng và hỗ trợ kiểm soát ghi đồng thời. Cũng không dùng `MERGE` để thay các
kiểm tra tối đa hai cha mẹ, năm sinh hoặc chống chu trình.

## 5. *1.. và *0.. khác nhau ra sao? Chống vòng lặp bằng gì?

`*1..` yêu cầu ít nhất một cạnh, phù hợp tìm tổ tiên/hậu duệ mà không lấy chính mình.
`*0..` chấp nhận đường dài 0, dùng cho gốc ở thế hệ 1 và kiểm tra tự làm cha mẹ.
Trước cạnh parent→child, tìm đường child→parent qua `PARENT_OF*0..`; tồn tại đường
thì cạnh mới tạo vòng. `wouldCreateCycle(P010,P010)` và `(P021,P001)` trả true;
`(P001,P028)` trả false. Lớp ghi phải thực sự dùng kết quả helper, không chỉ gọi MERGE.

## 6. shortestPath trả gì? Có xác định duy nhất quan hệ giữa hai người không?

Catalog tìm một đường có số cạnh ít nhất qua `PARENT_OF` và `SPOUSE_OF`, tìm theo cả
hai hướng. Kết quả gồm node maps `{id,name}`, loại cạnh và `relFrom` để biết chiều
lưu. P010–P012 có đường bốn cạnh qua P001 hoặc P002; hai đường đồng hạng nên không
khẳng định chỉ có một đáp án. Đường đi ngắn không tự là toàn bộ diễn giải vai vế.

## 7. Index khác constraint thế nào? Tại sao có person_name vẫn không cấm trùng tên?

Constraint là quy tắc dữ liệu, ở đây `person_id` cấm các id hiện diện bị trùng.
Index là cấu trúc hỗ trợ truy vấn. `person_name` không có ràng buộc unique, nên nhiều
người vẫn có thể cùng tên. Unique constraint có index hỗ trợ; bản thân khai báo
unique cũng không thay kiểm tra id bắt buộc/đúng kiểu ở ứng dụng.

## 8. EXPLAIN khác PROFILE thế nào? Kết quả index đã đo là gì?

EXPLAIN không chạy truy vấn, chỉ cho kế hoạch và ước tính. PROFILE chạy và đo thực tế.
Equality lookup `WHERE p.name = $name`, tên Nguyễn Minh Khang, trả một người trong
cả hai lần: không index dùng `NodeByLabelScan`, tổng 67 DB hits; có index dùng
`NodeIndexSeek`, tổng 8. Đây là DB hits trên fixture 30 người, không phải phép đo
thời gian nhanh hơn 8,375 lần. Không lấy `toLower(name) CONTAINS` làm demo này.

## 9. Ứng dụng kiểm soát truy cập và tránh Cypher injection thế nào?

Community vẫn có xác thực nhưng không có RBAC theo role như Enterprise. Bản app
hiện tại chưa có đăng nhập/phân quyền riêng; APP_ENV=local chỉ giới hạn môi trường
cho thao tác bảo trì, không xác định quyền người dùng. Console đã được gỡ.
Các truy vấn nghiệp vụ truyền giá trị bằng tham số riêng, không nối chuỗi input.
`CypherStatements` phục vụ tách schema/seed cho Admin, không phải cơ chế phân quyền.
[Giới hạn Community](https://neo4j.com/docs/operations-manual/current/authentication-authorization/manage-users/)

## 10. Chuyển máy thì nạp dữ liệu và backup bằng cách nào?

Fixture có seed và LOAD CSV; phải nạp người trước cạnh, chuyển năm sang integer,
đọc CSV từ thư mục import của server. Cả hai cách đã cho cùng 30 người, 36 cạnh
cha mẹ và 11 cạnh hôn nhân trên Neo4j native. Seed không giữ thay đổi người dùng
phát sinh sau đó. Backup database Community dùng `neo4j-admin database dump` khi
offline, load sang instance/volume phù hợp; dump gia phả không chứa user/role của
database `system`. Đợt mốc 3 `993b129` mới kiểm tra help, chưa dump. Đợt delivery
`d448211` đã thử Docker, clone Git cục bộ và dump/load thật với hash graph/schema
khớp trước/sau; xem [delivery-verification.md](delivery-verification.md).

Hiện Admin còn có JSON backup/restore gia phả: tải tệp, xác nhận khôi phục, lưu
bản an toàn trước khi thay và rollback khi lỗi. JSON không chứa tài khoản/cấu hình/
custom schema. Lượt 05/11 kiểm thử JSON trên DB riêng và giữ nguyên seed; chưa chạy
lại Docker/dump nhị phân trên mã sửa mới vì máy không có Docker CLI. Nguồn riêng:
[review-fixes.md](review-fixes.md). Không trộn bằng chứng của các lượt này.
