# 10 câu hỏi bảo vệ phần ứng dụng

## 1. Vì sao không dùng Eloquent, MySQL hay migrations?

Dữ liệu là đồ thị Person/PARENT_OF/SPOUSE_OF trong Neo4j. FamilyRepository gửi Cypher qua Neo4jService và Bolt.
Session/cache dùng tệp, queue đồng bộ; không có bảng nghiệp vụ SQL để migrate.

## 2. Vì sao tách Controller, FamilyService và FamilyRepository?

Controller xử lý HTTP; FormRequest kiểm tra input; FamilyService giữ quy tắc và transaction;
FamilyRepository giữ 34 query catalog. Việc tách giúp kiểm thử và tránh tự sửa Cypher ở nhiều nơi.

## 3. Vì sao ép năm và skip/limit sang int?

Form gửi chuỗi. Giữ chuỗi có thể làm so sánh năm sai kiểu hoặc SKIP/LIMIT lỗi.
PersonRequest và RelationshipController ép kiểu trước query; phân trang cũng truyền int.
Ô tùy chọn trống thành null; tests đọc lại năm từ Neo4j thật và kiểm tra đúng kiểu int.

## 4. Chặn vòng lặp và cha mẹ thứ ba thế nào?

FamilyService kiểm tra khác ID, chưa có cạnh, parentCount < 2, wouldCreateCycle=false và cha mẹ sinh trước con.
Check + write cùng transaction; lỗi rollback và hiển thị tiếng Việt.
Không áp chênh lệch tuổi tối thiểu. PARENT_OF được hiểu là cha mẹ sinh học:
nếu biết năm mất thì con sinh không sau năm mẹ mất, hoặc năm cha mất cộng 1.
Đây là quy ước dữ liệu theo năm, không phải kết luận y khoa/pháp luật; thiếu năm mất thì không suy diễn.
Thêm cạnh không được làm cặp phối ngẫu hiện có trở thành tổ tiên–hậu duệ/anh chị em.

## 5. Transaction đã đủ chống hai người ghi đồng thời chưa?

Transaction bảo đảm commit/rollback nhóm thao tác, không tự tuần tự hóa mọi phép đọc điều kiện.
App dùng chung GraphWriteLock cho CRUD, seed, backup/restore, reset và PROFILE thay index.
OS flock giữ đến khi xong/lỗi/process kết thúc, không có TTL; mặc định chờ 5 giây.
Callback lồng cùng singleton không lấy lại khóa. Transaction dùng begin/commit/rollback
tường minh, finally trả runner về client; reset cũng dùng helper này.
Đã thử tranh khóa bằng hai process đồng bộ và request tiếp theo sau lỗi bảo trì.
Constraint ID Neo4j chống ID trùng.
Nhiều máy chủ/client ghi trực tiếp không được khóa này bảo vệ; chưa phải giải pháp production phân tán.

## 6. Vì sao kiểm tra vợ chồng theo cả hai chiều?

Chỉ lưu một cạnh nhưng ý nghĩa đối xứng. spouseExists/spouses dùng mẫu vô hướng theo catalog,
nên thêm B–A sau A–B bị chặn. Chặn cả tổ tiên–hậu duệ và anh chị em chung ít nhất
một cha/mẹ; không tự cấm anh em họ. Năm kết hôn nếu có không trước năm sinh cả hai,
không sau hiện tại hay năm mất đã biết của một trong hai; cùng năm mất/null vẫn được.
Sửa hồ sơ gây mâu thuẫn hoặc tạo người kèm cạnh sai phải rollback toàn bộ.

## 7. Vì sao cần relFrom để diễn giải path?

shortestPath đi qua cạnh hai chiều; thứ tự node không cho biết ai là cha mẹ.
relFrom là ID đầu cạnh được lưu: trùng node hiện tại thì người đó là cha/mẹ, ngược lại là con.
Node path chỉ có ID/tên; getPerson bổ sung giới tính để dùng cha/mẹ, con trai/con gái, chồng/vợ.

## 8. Vì sao cùng cặp người có thể có câu diễn giải khác?

Có nhiều shortest path cùng độ dài. App diễn giải path Neo4j thực sự trả về,
không cố định tổ tiên hay đặt nhãn họ hàng mới. Tests không giả định một cách phá hòa.

## 9. Tests có xóa seed không? Biết bằng cách nào?

Integration chỉ chạy khi bật cờ xác nhận DB riêng; thiếu cờ thì skip trước kết nối.
Tests thường dùng T_, tearDown dọn fixture và đối chiếu toàn bộ graph gốc.
Test backup/restore/reset thực sự thay hoặc xóa graph, nên chỉ bật NEO4J_RESTORE_TESTS=1
trên instance có thể bỏ đi. Test trống graph hoàn trả snapshot trong finally; lỗi
giữa reset/restore chứng minh rollback. Không chạy integration trên DB người dùng
hoặc song song chung DB. Test cấp P031 dùng mock.
Lượt 05/11: 92 tests/1190 assertions; graph trước/sau khớp CSV 30/36/11, 0 T_,
hash graph/schema không đổi. Nguồn: [review-fixes.md](review-fixes.md).

## 10. Những giới hạn phải nói rõ khi demo?

Tìm tên chưa bỏ dấu; CDN cần Internet; chưa có đăng nhập/phân quyền app; Community không có RBAC.
Cypher Console đã được gỡ. Khóa ghi chỉ trên một máy, client trực tiếp có thể bỏ qua quy tắc.
APP_ENV=local không phải xác thực người dùng. Dashboard chọn gốc chỉ đổi thống kê
thế hệ; các số tổng là toàn graph. JSON restore trong Admin cần xác nhận và lưu
bản an toàn trước khi thay dữ liệu; không gồm tài khoản/cấu hình/custom schema.
Delivery tại d448211 đã thử Docker, clone từ repo Git cục bộ và dump/load offline;
dump không chứa user/role của database system. Lượt sửa mới dùng native vì không
có Docker CLI, không khẳng định đã kiểm chứng lại Docker trên mã mới.
Benchmark 20.000 người chỉ đo query tổng hợp, không chứng minh UI/export cỡ đó;
choices vẫn tải toàn bộ người. Giảm DB hits không phải tỷ lệ tăng tốc thời gian.
