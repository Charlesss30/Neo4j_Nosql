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
Không áp chênh lệch tuổi tối thiểu; quy tắc hiện tại chỉ yêu cầu năm sinh nhỏ hơn nghiêm ngặt.

## 5. Transaction đã đủ chống hai người ghi đồng thời chưa?

Transaction bảo đảm commit/rollback nhóm thao tác, không tự tuần tự hóa mọi phép đọc điều kiện.
App thêm khóa tệp family-write trên cùng máy; constraint ID Neo4j chống ID trùng.
Nhiều máy chủ/client ghi trực tiếp không được khóa này bảo vệ; chưa phải giải pháp production phân tán.

## 6. Vì sao kiểm tra vợ chồng theo cả hai chiều?

Chỉ lưu một cạnh nhưng ý nghĩa đối xứng. spouseExists/spouses dùng mẫu vô hướng theo catalog,
nên thêm B–A sau A–B bị chặn. Năm kết hôn nếu có không trước năm sinh cả hai hay sau hiện tại.

## 7. Vì sao cần relFrom để diễn giải path?

shortestPath đi qua cạnh hai chiều; thứ tự node không cho biết ai là cha mẹ.
relFrom là ID đầu cạnh được lưu: trùng node hiện tại thì người đó là cha/mẹ, ngược lại là con.
Node path chỉ có ID/tên; getPerson bổ sung giới tính để dùng cha/mẹ, con trai/con gái, chồng/vợ.

## 8. Vì sao cùng cặp người có thể có câu diễn giải khác?

Có nhiều shortest path cùng độ dài. App diễn giải path Neo4j thực sự trả về,
không cố định tổ tiên hay đặt nhãn họ hàng mới. Tests không giả định một cách phá hòa.

## 9. Tests có xóa seed không? Biết bằng cách nào?

Chỉ tạo/sửa/xóa T_. tearDown chỉ DETACH DELETE Person có ID STARTS WITH T_, rồi so toàn bộ đồ thị gốc.
Test cấp P031 dùng mock, không ghi vào seed. Cuối lượt xác nhận 30 người / 36 PARENT_OF / 11 SPOUSE_OF.
Không chạy integration song song chung DB.

## 10. Những giới hạn phải nói rõ khi demo?

Tìm tên chưa bỏ dấu; CDN cần Internet; chưa có đăng nhập/phân quyền app; Community không có RBAC.
Console lọc từ khóa không phải phân quyền. Khóa ghi chỉ trên một máy, client trực tiếp có thể bỏ qua quy tắc.
Docker chưa chạy thử trên máy kiểm chứng; số liệu từ Neo4j native và seed 30 người.
