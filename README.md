# 📅 XYZ Meetings - Hệ Thống Quản Lý Lịch Họp Nội Bộ

**XYZ Meetings** là một ứng dụng web quản lý và đặt lịch phòng họp dành cho nhân viên công ty, được xây dựng trên nền tảng **PHP Thuần (PDO)** và **MySQL**. Hệ thống được thiết kế theo phong cách giao diện tối (Dark Mode) hiện đại, tập trung tối ưu hóa trải nghiệm người dùng (UI/UX) và giải quyết triệt để các bài toán logic phức tạp về đồng bộ thời gian thời gian thực.

---

## 🚀 Công Nghệ Sử Dụng

*   **Backend:** PHP (PDO Extension) - Đảm bảo an toàn trước lỗi SQL Injection.
*   **Database:** MySQL (Hỗ trợ ràng buộc khóa ngoại, cơ chế Cascade Delete).
*   **Frontend:** HTML5, CSS3 (Custom Dark Mode Layout, Responsive Grid), JavaScript ES6 (Xử lý DOM, Trạng thái Modal, Tối ưu hóa lựa chọn thời gian).

---

## ✨ Tính Năng Nổi Bật (Bám sát Đặc tả User Stories)

Hệ thống tập trung triển khai toàn diện phân hệ dành cho **Nhân viên (Employee)** bao gồm 11 User Stories cốt lõi:

1.  **Hệ thống Đăng nhập & Bảo mật (US-01):** Xác thực tài khoản doanh nghiệp hợp lệ, quản lý phiên làm việc thông qua Session, tự động chặn và đá văng các truy cập trái phép.
2.  **Quản lý Tài khoản nâng cao (US-02 & US-03):** 
    *   Cập nhật thông tin cá nhân (Họ tên, Số điện thoại, Phòng ban).
    *   Đổi mật khẩu bảo mật (Có cơ chế kiểm tra độ dài mật khẩu mới `>= 8` ký tự ở cả 2 lớp Frontend và Backend).
    *   Tải lên ảnh đại diện cá nhân (`Upload Avatar`) với thuật toán tự động đổi tên file theo thời gian thực để chống trùng lặp dữ liệu trên máy chủ.
3.  **Đa dạng Góc nhìn Lịch họp (US-04):** Hỗ trợ người dùng theo dõi lịch biểu linh hoạt theo 3 dạng: Dạng Ngày (Chi tiết phòng ban), Dạng Tuần (Thời gian biểu 7 ngày), và Dạng Tháng (Lưới Calendar toàn diện).
4.  **Bộ lọc Kết hợp Thông minh (US-05):** Tìm kiếm và lọc lịch họp chéo theo 3 điều kiện cùng lúc: Từ khóa chủ đề, Phòng họp cụ thể, và Người tổ chức.
5.  **Tạo Lịch họp & Mời thành viên (US-06):** Cho phép đặt phòng họp và tích chọn mời nhiều đồng nghiệp tham gia cùng lúc. Hệ thống tích hợp tính năng chống người dùng spam click (Double Submit) làm hỏng dữ liệu.
6.  **Thuật toán Kiểm tra Trùng lịch Nâng cao (US-07):** Chặn đứng xung đột thời gian ngay từ form nhập liệu:
    *   *Trùng Phòng:* Kiểm tra xem phòng đó có bị trùng khung giờ với cuộc họp khác không.
    *   *Trùng Người:* Sử dụng câu lệnh SQL nâng cao chứa `EXISTS` để quét toàn bộ danh sách khách mời, nếu có bất kỳ ai bận lịch ở một phòng họp khác trong khung giờ đó, hệ thống sẽ chặn và cảnh báo đích danh.
7.  **Quản lý & Chỉnh sửa Lịch họp (US-08 & US-09):** Theo dõi toàn bộ lịch họp do mình tổ chức trong Profile (Phân loại thẻ Quá khứ/Tương lai). Cho phép cập nhật thông tin lịch họp, thuật toán check trùng lịch thông minh tự động loại trừ chính ID cuộc họp đang sửa.
8.  **Hủy cuộc họp & Giải phóng Phòng (US-10):** Người tổ chức có quyền hủy lịch họp. Khi hủy, hệ thống tự động xóa sạch danh sách khách mời liên quan nhờ ràng buộc `ON DELETE CASCADE` trong database, không để lại dữ liệu rác.
9.  **Tương tác & Phản hồi Lời mời (US-11):** Sự kiện bị mời sẽ hiển thị **Màu Cam** nổi bật trên lịch biểu. Người dùng có quyền chọn **Đồng ý (Accepted)** hoặc **Từ chối (Declined)**. Nếu từ chối, quỹ thời gian đó sẽ được giải phóng để người khác có thể mời vào cuộc họp sau.

---

## 📁 Cấu Trúc Thư Mục Dự Án

```text
meeting_app/
├── uploads/               # Thư mục lưu trữ ảnh đại diện của nhân viên
├── db.php                 # Kết nối CSDL bằng PDO, chuẩn hóa múi giờ hệ thống
├── login.php              # Xử lý đăng nhập tài khoản nhân viên
├── logout.php             # Xử lý đăng xuất, hủy session
├── index.php              # Trang chủ: Giao diện lịch tuần, đặt phòng, sửa phòng, phản hồi
├── dashboard.php          # Trang tổng quan: Báo cáo số liệu, phòng trống/bận hôm nay
├── rooms.php              # Trang danh sách phòng: Xem chi tiết lịch bận theo từng ngày
├── profile.php            # Trang hồ sơ cá nhân: Đổi pass, up ảnh, quản lý lịch đã đặt
├── month.php              # Trang lịch tháng: Xem lưới sự kiện toàn bộ các ngày trong tháng
└── xyz_meetings.sql       # Cấu trúc CSDL và dữ liệu mẫu (Users, Rooms, Departments, Meetings...)

🛠️ Hướng Dẫn Cài Đặt (Localhost)
1. Cấu hình Cơ sở dữ liệu
Khởi động module Apache và MySQL trên XAMPP.

Truy cập vào đường dẫn http://localhost/phpmyadmin/.

Tạo một cơ sở dữ liệu mới tên là xyz_meetings.

Chọn cơ sở dữ liệu vừa tạo, bấm vào tab Import (Nhập), chọn tệp xyz_meetings.sql trong thư mục dự án và nhấn Go (Thực hiện).

2. Khởi chạy Ứng dụng
Tải mã nguồn về và giải nén vào thư mục htdocs của XAMPP (Đặt tên thư mục là meeting_app).

Mở thư mục meeting_app, tạo thủ công một thư mục trống tên là uploads (nếu chưa có) để làm phân hệ lưu trữ ảnh.

Mở trình duyệt web và truy cập theo đường dẫn: http://localhost:8080/meeting_app/login.php (hoặc cổng 80 tùy cấu hình máy bạn).

3. Tài khoản Thử nghiệm (Test Accounts)
Hệ thống đã chuẩn bị sẵn dữ liệu nhân viên chéo phòng ban để kiểm tra tính năng mời họp và check trùng lịch:

Tài khoản 1 (Người tổ chức):

Email: chuyenviensieucap@xyz.com

Mật khẩu: 123456

Tài khoản 2 (Người được mời):

Email: nhanvien2@xyz.com

Mật khẩu: 123456

