<?php
// dashboard.php
session_start();
include 'db.php'; // db.php đã tự set múi giờ VN, không cần lặp lại ở đây nữa

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 1. TỔNG SỐ CUỘC HỌP (Tất cả lịch đã đặt trong hệ thống)
$stmt_total = $conn->query("SELECT COUNT(*) as total FROM meetings");
$total_meetings = $stmt_total->fetch()['total'];

// 2. CUỘC HỌP SẮP DIỄN RA GẦN NHẤT (Lớn hơn giờ hiện tại)
$stmt_next = $conn->query("SELECT title, start_time, end_time FROM meetings WHERE start_time >= NOW() ORDER BY start_time ASC LIMIT 1");
$next_meeting = $stmt_next->fetch(PDO::FETCH_ASSOC);

$next_meeting_html = "Hiện chưa có lịch họp";
if ($next_meeting) {
    $time_start = strtotime($next_meeting['start_time']);
    $time_end = strtotime($next_meeting['end_time']);
    $day_map = ['Sunday'=>'Chủ Nhật', 'Monday'=>'Thứ 2', 'Tuesday'=>'Thứ 3', 'Wednesday'=>'Thứ 4', 'Thursday'=>'Thứ 5', 'Friday'=>'Thứ 6', 'Saturday'=>'Thứ 7'];
    $day_name = $day_map[date('l', $time_start)];
    
    // Format: "Thứ 2 : 9:00 - 10:30 (10/07/2026)"
    $time_str = $day_name . " : " . date('H:i', $time_start) . " - " . date('H:i', $time_end) . "<br>(" . date('d/m/Y', $time_start) . ")";
    $next_meeting_html = "<strong>{$next_meeting['title']}</strong><br><span style='font-size: 13px; line-height: 1.5; display: inline-block; margin-top: 5px;'>$time_str</span>";
}

// 3. TÍNH PHÒNG TRỐNG/ĐANG BẬN NGAY LÚC NÀY (real-time)
// Sửa lại: cách tính cũ chỉ cần phòng có 1 cuộc họp bất kỳ trong ngày là tính "bận"
// cả ngày, dù cuộc họp chỉ diễn ra 30 phút buổi sáng. Giờ đổi qua tính đúng theo
// thời điểm hiện tại (NOW() nằm giữa start_time và end_time) - cùng công thức đang
// dùng ở rooms.php - để 2 trang cho ra con số nhất quán với nhau.
$stmt_busy = $conn->query("SELECT COUNT(DISTINCT room_id) as busy_count FROM meetings WHERE start_time <= NOW() AND end_time > NOW()");
$busy_rooms = $stmt_busy->fetch()['busy_count'];

$stmt_rooms = $conn->query("SELECT COUNT(*) as total_rooms FROM rooms");
$total_rooms = $stmt_rooms->fetch()['total_rooms'];

// Phòng trống = Tổng phòng - Phòng đang có người họp ngay lúc này
$empty_rooms = $total_rooms - $busy_rooms;

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tổng Quan - XYZ Meetings</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: Arial, sans-serif; }
        body { background-color: #0f1319; color: #ffffff; display: flex; height: 100vh; }
        .sidebar { width: 240px; background-color: #161b22; padding: 30px 20px; border-right: 1px solid #21262d; }
        .logo { color: #d4af37; font-size: 20px; font-weight: bold; margin-bottom: 50px; }
        .menu-item { padding: 15px 10px; color: #8b949e; text-decoration: none; display: block; border-radius: 6px; margin-bottom: 10px; }
        .menu-item.active { color: #ffffff; background-color: #21262d; }
        .main-content { flex: 1; padding: 40px; overflow-y: auto; }
        .page-title { color: #8b949e; font-size: 24px; font-weight: normal; margin-bottom: 40px; letter-spacing: 1px; text-transform: uppercase; }
        
        .dashboard-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 40px; max-width: 900px; }
        
        /* Chuyển thẻ thành dạng bấm được */
        .stat-card {
            background-color: #c0392b; 
            padding: 20px; border-radius: 4px; text-align: center; min-height: 200px; 
            display: flex; flex-direction: column; align-items: center;
            text-decoration: none; /* Bỏ gạch chân của thẻ a */
            transition: 0.3s;
            cursor: pointer;
        }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.5); }
        
        .stat-title { color: white; font-size: 16px; margin-bottom: 20px; text-align: left; width: 100%; text-transform: lowercase; }
        .stat-value-box { background-color: #2f80ed; color: white; width: 80%; padding: 25px 10px; border-radius: 4px; font-size: 20px; font-weight: bold; display: flex; flex-direction: column; justify-content: center; align-items: center; line-height: 1.2; flex: 1; }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="logo">XYZ MEETINGS</div>
        <a href="dashboard.php" class="menu-item active">Dashboard</a>
        <a href="index.php" class="menu-item">Lịch của tôi</a>
        <a href="rooms.php" class="menu-item">Danh sách phòng</a>
        <a href="profile.php" class="menu-item">Hồ sơ cá nhân</a>
        <a href="logout.php" class="menu-item" style="color: #e57373; margin-top: 50px; border: 1px solid #30363d;">Đăng xuất </a>
    </div>

    <div class="main-content">
        <h2 class="page-title">Tổng quan cuộc họp</h2>
        
        <div class="dashboard-grid">
            <div class="stat-card">
                <div class="stat-title">cuộc họp sắp diễn ra</div>
                <div class="stat-value-box"><?php echo $next_meeting_html; ?></div>
            </div>

            <!-- Click vào đây nhảy sang Lịch -->
            <a href="index.php" class="stat-card">
                <div class="stat-title">tổng số cuộc họp</div>
                <div class="stat-value-box"><?php echo $total_meetings; ?></div>
            </a>

            <!-- Click vào đây nhảy sang danh sách phòng -->
            <a href="rooms.php" class="stat-card">
                <div class="stat-title">số phòng trống (ngay bây giờ)</div>
                <div class="stat-value-box"><?php echo $empty_rooms; ?></div>
            </a>

            <!-- Click vào đây nhảy sang danh sách phòng -->
            <a href="rooms.php" class="stat-card">
                <div class="stat-title">số phòng đang họp (ngay bây giờ)</div>
                <div class="stat-value-box"><?php echo $busy_rooms; ?></div>
            </a>
        </div>
    </div>

</body>
</html>