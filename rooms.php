<?php
// rooms.php
session_start();
include 'db.php'; // db.php đã tự set múi giờ VN, không cần lặp lại ở đây nữa

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 1. LOGIC ĐIỀU HƯỚNG NGÀY THÁNG (Giống index.php nhưng chuyển theo Từng Ngày)
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$prev_date = date('Y-m-d', strtotime('-1 day', strtotime($selected_date)));
$next_date = date('Y-m-d', strtotime('+1 day', strtotime($selected_date)));
$display_date = date('d/m/Y', strtotime($selected_date)); // VD: 10/07/2026

// 2. LẤY DANH SÁCH TẤT CẢ CÁC PHÒNG
$stmt_rooms = $conn->query("SELECT * FROM rooms");
$rooms = $stmt_rooms->fetchAll(PDO::FETCH_ASSOC);

// 3. LẤY LỊCH HỌP CỦA RIÊNG NGÀY ĐƯỢC CHỌN
$stmt_meetings = $conn->prepare("SELECT room_id, title, start_time, end_time FROM meetings WHERE DATE(start_time) = :sel_date ORDER BY start_time ASC");
$stmt_meetings->execute(['sel_date' => $selected_date]);
$meetings = $stmt_meetings->fetchAll(PDO::FETCH_ASSOC);

// Gom nhóm lịch theo phòng
$room_schedules = [];
foreach ($meetings as $m) {
    $room_schedules[$m['room_id']][] = $m;
}

// 4. CHECK TRẠNG THÁI "ĐANG HỌP" REAL-TIME (Chỉ có tác dụng nếu đang xem ngày hôm nay)
$busy_now_rooms = [];
if ($selected_date === date('Y-m-d')) {
    $stmt_current = $conn->query("SELECT DISTINCT room_id FROM meetings WHERE start_time <= NOW() AND end_time > NOW()");
    $busy_now_rooms = $stmt_current->fetchAll(PDO::FETCH_COLUMN);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh Sách Phòng - XYZ Meetings</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: Arial, sans-serif; }
        body { background-color: #0f1319; color: #ffffff; display: flex; height: 100vh; }
        .sidebar { width: 240px; background-color: #161b22; padding: 30px 20px; border-right: 1px solid #21262d; }
        .logo { color: #d4af37; font-size: 20px; font-weight: bold; margin-bottom: 50px; }
        .menu-item { padding: 15px 10px; color: #8b949e; text-decoration: none; display: block; border-radius: 6px; margin-bottom: 10px; transition: 0.2s;}
        .menu-item.active { color: #ffffff; background-color: #21262d; }
        .menu-item:hover { color: #ffffff; background-color: #21262d; }
        
        .main-content { flex: 1; padding: 40px; overflow-y: auto; }
        
        /* Cập nhật Top Bar điều hướng */
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; padding-bottom: 20px; border-bottom: 1px solid #30363d;}
        .page-title { color: #8b949e; font-size: 24px; font-weight: normal; letter-spacing: 1px; text-transform: uppercase; margin: 0;}
        .date-navigator { display: flex; align-items: center; gap: 15px; }
        .btn-nav { padding: 10px 20px; border-radius: 4px; cursor: pointer; font-weight: bold; text-decoration: none; background-color: #21262d; color: white; border: 1px solid #30363d; transition: 0.2s;}
        .btn-nav:hover { background-color: #30363d; }
        .date-label { font-size: 20px; font-weight: bold; color: #d4f285; }

        .room-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 25px; }

        .room-card {
            background-color: #161b22; border: 1px solid #30363d; border-radius: 8px; padding: 25px;
            display: flex; flex-direction: column; align-items: center;
            transition: 0.3s; cursor: pointer;
        }
        .room-card:hover { transform: translateY(-5px); border-color: #d4f285; box-shadow: 0 5px 15px rgba(0,0,0,0.5); }
        
        .room-icon { font-size: 40px; margin-bottom: 15px; }
        .room-name { font-size: 20px; font-weight: bold; color: #d4f285; margin-bottom: 10px; text-align: center; }
        .room-capacity { color: #8b949e; font-size: 14px; margin-bottom: 20px; }
        
        .status-badge { padding: 8px 15px; border-radius: 20px; font-size: 12px; font-weight: bold; text-transform: uppercase; margin-bottom: 15px;}
        .status-free { background-color: rgba(46, 204, 113, 0.2); color: #2ecc71; border: 1px solid #2ecc71; }
        .status-busy { background-color: rgba(231, 76, 60, 0.2); color: #e74c3c; border: 1px solid #e74c3c; }
        
        .upcoming-info { font-size: 13px; color: #8b949e; font-style: italic; text-align: center;}
        .highlight-text { color: #ffffff; font-weight: bold; font-style: normal; }

        /* Modal CSS */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.7); justify-content: center; align-items: center; z-index: 1000; }
        .modal-content { background-color: #161b22; padding: 30px; border-radius: 8px; width: 500px; border: 1px solid #30363d; max-height: 80vh; overflow-y: auto; }
        .modal-content h3 { margin-bottom: 5px; color: #d4f285; }
        .modal-date-subtitle { color: #8b949e; margin-bottom: 20px; border-bottom: 1px solid #30363d; padding-bottom: 10px; font-size: 14px;}
        .schedule-list { list-style: none; padding: 0; }
        .schedule-item { background-color: #21262d; padding: 15px; border-radius: 6px; margin-bottom: 10px; border-left: 4px solid #2f80ed; }
        .schedule-title { font-weight: bold; font-size: 16px; margin-bottom: 5px; }
        .schedule-time { color: #c9d1d9; font-size: 14px; }
        .no-schedule { text-align: center; color: #8b949e; padding: 20px; font-style: italic; }
        .btn-close { margin-top: 20px; padding: 10px 20px; background-color: #30363d; color: white; border: none; border-radius: 4px; cursor: pointer; width: 100%; font-weight: bold;}
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="logo">XYZ MEETINGS</div>
        <a href="dashboard.php" class="menu-item">Dashboard</a>
        <a href="index.php" class="menu-item">Lịch của tôi</a>
        <a href="rooms.php" class="menu-item active">Danh sách phòng</a>
        <a href="profile.php" class="menu-item">Hồ sơ cá nhân</a>
        <a href="logout.php" class="menu-item" style="color: #e57373; margin-top: 50px; border: 1px solid #30363d;">Đăng xuất </a>
    </div>

    <div class="main-content">
        <div class="top-bar">
            <h2 class="page-title">Danh sách phòng họp</h2>
            <div class="date-navigator">
                <a href="?date=<?php echo $prev_date; ?>" class="btn-nav">◄ Ngày trước</a>
                <a href="?date=<?php echo date('Y-m-d'); ?>" class="btn-nav">Hôm nay</a>
                <a href="?date=<?php echo $next_date; ?>" class="btn-nav">Ngày sau ►</a>
                <div class="date-label">Ngày: <?php echo $display_date; ?></div>
            </div>
        </div>
        
        <div class="room-grid">
            <?php foreach ($rooms as $room): 
                $r_id = $room['id'];
                $is_busy_now = in_array($r_id, $busy_now_rooms);
                $meetings_count = isset($room_schedules[$r_id]) ? count($room_schedules[$r_id]) : 0;
            ?>
                <div class="room-card" onclick="showSchedule(<?php echo $r_id; ?>, '<?php echo $room['room_name']; ?>')">
                    <div class="room-icon">🚪</div>
                    <div class="room-name"><?php echo htmlspecialchars($room['room_name']); ?></div>
                    <div class="room-capacity">Sức chứa: <?php echo $room['capacity']; ?> người</div>
                    
                    <?php if ($selected_date === date('Y-m-d') && $is_busy_now): ?>
                        <div class="status-badge status-busy">Đang Họp</div>
                    <?php else: ?>
                        <div class="status-badge status-free">Đang trống</div>
                    <?php endif; ?>
                    
                    <div class="upcoming-info">
                        <?php if ($meetings_count > 0): ?>
                            Có <span class="highlight-text"><?php echo $meetings_count; ?></span> lịch đặt
                        <?php else: ?>
                            Chưa có lịch đặt
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div id="scheduleModal" class="modal">
        <div class="modal-content">
            <h3 id="modalRoomName">Lịch đặt phòng</h3>
            <div class="modal-date-subtitle">Ngày: <?php echo $display_date; ?></div>
            
            <ul class="schedule-list" id="modalScheduleList">
                </ul>
            <button class="btn-close" onclick="document.getElementById('scheduleModal').style.display='none'">Đóng</button>
        </div>
    </div>

    <script>
        const roomSchedules = <?php echo json_encode($room_schedules); ?>;

        function showSchedule(roomId, roomName) {
            document.getElementById('modalRoomName').innerText = "Chi tiết lịch: " + roomName;
            const listContainer = document.getElementById('modalScheduleList');
            listContainer.innerHTML = ''; 

            const schedules = roomSchedules[roomId];

            if (!schedules || schedules.length === 0) {
                listContainer.innerHTML = '<li class="no-schedule">Hiện chưa có lịch đặt nào trong ngày này.</li>';
            } else {
                schedules.forEach(item => {
                    const startDate = new Date(item.start_time);
                    const endDate = new Date(item.end_time);
                    
                    // Vì đã lọc theo ngày ở PHP rồi, JS chỉ cần hiển thị Giờ thôi cho gọn gàng
                    const timeString = startDate.toLocaleTimeString('vi-VN', {hour: '2-digit', minute:'2-digit'}) + ' - ' + endDate.toLocaleTimeString('vi-VN', {hour: '2-digit', minute:'2-digit'});

                    const li = document.createElement('li');
                    li.className = 'schedule-item';
                    li.innerHTML = `
                        <div class="schedule-title">${item.title}</div>
                        <div class="schedule-time">⏰ Thời gian: ${timeString}</div>
                    `;
                    listContainer.appendChild(li);
                });
            }

            document.getElementById('scheduleModal').style.display = 'flex';
        }
    </script>
</body>
</html>