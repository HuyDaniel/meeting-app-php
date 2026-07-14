<?php
session_start(); // Phải nằm chễm chệ ở trên cùng
include 'db.php';
$error_msg = '';
$reopen_book_modal = false; // true nếu lỗi đến từ form đặt phòng, để tự mở lại modal kèm lỗi

// Nếu chưa đăng nhập thì đá văng ra trang login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}


// --- 1. XỬ LÝ ĐẶT PHÒNG ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_book'])) {
    $title = $_POST['title'];
    $room_id = $_POST['room_id'];
    $date = $_POST['meet_date'];
    $start_time = $date . ' ' . $_POST['start_time'] . ':00';
    $end_time = $date . ' ' . $_POST['end_time'] . ':00';
    $created_by = $_SESSION['user_id'];

    // Kiểm tra giờ kết thúc phải sau giờ bắt đầu (tránh tạo họp 0 phút hoặc âm giờ)
    if (strtotime($end_time) <= strtotime($start_time)) {
        $error_msg = "Giờ kết thúc phải sau giờ bắt đầu, bạn chọn lại giúp mình nhé.";
        $reopen_book_modal = true;
    } else {
        $check_sql = "SELECT id FROM meetings WHERE room_id = :room_id AND start_time < :end_time AND end_time > :start_time";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->execute(['room_id' => $room_id, 'end_time' => $end_time, 'start_time' => $start_time]);

        if ($check_stmt->rowCount() > 0) {
            $error_msg = "Phòng này đã có người đặt trong khung giờ đó, bạn chọn giờ khác nhé.";
            $reopen_book_modal = true;
        } else {
            $insert_sql = "INSERT INTO meetings (title, room_id, created_by, start_time, end_time) VALUES (:title, :room_id, :created_by, :start_time, :end_time)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->execute(['title' => $title, 'room_id' => $room_id, 'created_by' => $created_by, 'start_time' => $start_time, 'end_time' => $end_time]);
            // Chuyển hướng lại trang để tránh submit form nhiều lần
            header("Location: index.php?date=" . $date);
            exit();
        }
    }
}

// --- 2. XỬ LÝ HỦY PHÒNG ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_delete'])) {
    $delete_id = $_POST['delete_meeting_id'];
    $current_user = $_SESSION['user_id'];

    $check_owner = $conn->prepare("SELECT created_by FROM meetings WHERE id = :id");
    $check_owner->execute(['id' => $delete_id]);
    $meeting = $check_owner->fetch(PDO::FETCH_ASSOC);

    if ($meeting && $meeting['created_by'] == $current_user) {
        $del_sql = "DELETE FROM meetings WHERE id = :id";
        $del_stmt = $conn->prepare($del_sql);
        $del_stmt->execute(['id' => $delete_id]);
        header("Location: index.php?date=" . (isset($_GET['date']) ? $_GET['date'] : ''));
        exit();
    } else {
        $error_msg = "Cảnh báo: Bạn không có quyền hủy cuộc họp này!";
    }
}

// --- 3. XỬ LÝ LOGIC NGÀY THÁNG (CHUYỂN TUẦN) ---
// Lấy ngày hiện tại từ URL, nếu không có thì lấy ngày hôm nay
$current_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Tính ra ngày Thứ 2 và Chủ Nhật của tuần chứa $current_date
$monday_of_week = date('Y-m-d', strtotime('monday this week', strtotime($current_date)));
$sunday_of_week = date('Y-m-d', strtotime('sunday this week', strtotime($current_date)));

// Tính ngày cho nút Prev / Next
$prev_week = date('Y-m-d', strtotime('-1 week', strtotime($monday_of_week)));
$next_week = date('Y-m-d', strtotime('+1 week', strtotime($monday_of_week)));

// --- 4. XỬ LÝ TÌM KIẾM & KÉO DATA LỊCH ---
$search_kw = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_condition = "";
if ($search_kw !== '') {
    $search_condition = " AND title LIKE :search ";
}

$sql = "SELECT id, title, start_time, room_id, created_by FROM meetings 
        WHERE start_time >= :start_week AND start_time <= :end_week" . $search_condition;
        
$stmt = $conn->prepare($sql);

$params = [
    'start_week' => $monday_of_week . ' 00:00:00',
    'end_week' => $sunday_of_week . ' 23:59:59'
];
if ($search_kw !== '') {
    $params['search'] = "%$search_kw%";
}

$stmt->execute($params);
$meetings_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$calendar_events = [];
foreach ($meetings_data as $row) {
    $start_timestamp = strtotime($row['start_time']);
    $day_of_week = date('N', $start_timestamp) + 1; // 2 -> 8
    $hour_format = date('H:00', $start_timestamp); 
    
    // Dùng [] để lưu thành danh sách nhiều cuộc họp trong cùng 1 ô ngày/giờ.
    // Trước đây gán trực tiếp (không có []) nên nếu 2 phòng khác nhau cùng có
    // cuộc họp chung khung giờ, cuộc xử lý sau sẽ đè mất cuộc xử lý trước,
    // khiến nó biến mất khỏi lịch dù vẫn còn nguyên trong CSDL.
    $calendar_events[$day_of_week][$hour_format][] = [
        'id' => $row['id'],
        'title' => $row['title'],
        'room_id' => $row['room_id'],
        'created_by' => $row['created_by']
    ];
}
// --- KÉO DANH SÁCH PHÒNG CHO FORM ĐẶT LỊCH ---
$stmt_all_rooms = $conn->query("SELECT id, room_name, capacity FROM rooms");
$all_rooms_list = $stmt_all_rooms->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>XYZ Meetings - Lịch của tôi</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: Arial, sans-serif; }
        body { background-color: #0f1319; color: #ffffff; display: flex; height: 100vh; }
        .sidebar { width: 240px; background-color: #161b22; padding: 30px 20px; border-right: 1px solid #21262d; }
        .logo { color: #d4af37; font-size: 20px; font-weight: bold; margin-bottom: 50px; }
        .menu-item { padding: 15px 10px; color: #8b949e; text-decoration: none; display: block; border-radius: 6px; margin-bottom: 10px; }
        .menu-item.active { color: #ffffff; background-color: #21262d; }
        .main-content { flex: 1; display: flex; flex-direction: column; padding: 30px; overflow-y: auto; }
        
        /* Cập nhật UI Top Bar cho phần Search và Date Navigate */
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .search-form { display: flex; width: 50%; }
        .search-box { width: 100%; padding: 12px 20px; background-color: #161b22; border: 1px solid #30363d; border-radius: 6px 0 0 6px; color: white; outline: none;}
        .btn-search { padding: 12px 20px; background-color: #30363d; border: 1px solid #30363d; border-radius: 0 6px 6px 0; color: white; cursor: pointer; font-weight: bold;}
        .btn-search:hover { background-color: #8b949e; }
        
        .btn-group button, .btn-group a.btn-nav { padding: 10px 20px; margin-left: 10px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; background-color: #d4f285; color: #0f1319; text-decoration: none; display: inline-block;}
        .btn-group a.btn-nav { background-color: #21262d; color: white; border: 1px solid #30363d;}
        .btn-group a.btn-nav:hover { background-color: #30363d; }
        
        .week-label { margin-bottom: 20px; font-size: 18px; font-weight: bold; color: #d4f285; text-align: center; }

        .calendar-table { width: 100%; border-collapse: collapse; background-color: #161b22; border: 1px solid #30363d; }
        .calendar-table th, .calendar-table td { border: 1px solid #30363d; padding: 15px; text-align: center; width: 12%; }
        .calendar-table th { background-color: #1f242c; font-weight: bold; }
        .calendar-table td.time-col { background-color: #1f242c; font-weight: bold; width: 8%; }
        .event-block { background-color: #2f80ed; color: white; padding: 8px; border-radius: 4px; font-size: 13px; font-weight: bold; cursor: pointer; transition: 0.2s; margin-bottom: 6px; }
        .event-block:last-child { margin-bottom: 0; }
        .event-block:hover { transform: scale(1.05); }
        .event-block.dept { background-color: #bb6bd9; }

        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.7); justify-content: center; align-items: center; z-index: 1000; }
        .modal-content { background-color: #161b22; padding: 30px; border-radius: 8px; width: 400px; border: 1px solid #30363d; }
        .modal-content h3 { margin-bottom: 20px; color: #d4f285; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; color: #8b949e; }
        .form-group input, .form-group select { width: 100%; padding: 10px; background-color: #0f1319; border: 1px solid #30363d; color: white; border-radius: 4px; }
        .form-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
        .form-actions button { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn-submit { background-color: #d4f285; color: #0f1319; }
        .btn-close { background-color: #30363d; color: white; }
        .btn-cancel { background-color: #e57373; color: white; }
        .error-alert { background-color: #e57373; color: white; padding: 10px; border-radius: 4px; margin-bottom: 15px; text-align: center; font-weight: bold; }
        .detail-text { margin-bottom: 10px; font-size: 16px; color: #c9d1d9; }
    </style>
</head>
<body>

        <div class="sidebar">
        <div class="logo">XYZ MEETINGS</div>
        <a href="dashboard.php" class="menu-item">Dashboard</a>
        <a href="index.php" class="menu-item active">Lịch của tôi</a>
        <a href="rooms.php" class="menu-item">Danh sách phòng</a>
        <a href="profile.php" class="menu-item">Hồ sơ cá nhân</a>
        
        <a href="logout.php" class="menu-item" style="color: #e57373; margin-top: 50px; border: 1px solid #30363d;">
            Đăng xuất
        </a>
    </div>

    <div class="main-content">
        <div class="top-bar">
            <form class="search-form" method="GET" action="index.php">
                <input type="hidden" name="date" value="<?php echo $current_date; ?>">
                <input type="text" name="search" class="search-box" placeholder="Nhập tên cuộc họp để tìm..." value="<?php echo htmlspecialchars($search_kw); ?>">
                <button type="submit" class="btn-search">Tìm kiếm</button>
            </form>
            
            <div class="btn-group">
                <a href="?date=<?php echo $prev_week; ?>&search=<?php echo urlencode($search_kw); ?>" class="btn-nav">◄ Tuần trước</a>
                <a href="?date=<?php echo date('Y-m-d'); ?>" class="btn-nav">Hôm nay</a>
                <a href="?date=<?php echo $next_week; ?>&search=<?php echo urlencode($search_kw); ?>" class="btn-nav">Tuần sau ►</a>
                
                <button onclick="document.getElementById('bookModal').style.display='flex'">+ Đặt phòng họp</button>
            </div>
        </div>

        <?php if($error_msg && !$reopen_book_modal): ?>
            <div class="error-alert"><?php echo $error_msg; ?></div>
        <?php endif; ?>

        <div class="week-label">
            Lịch tuần: <?php echo date('d/m/Y', strtotime($monday_of_week)); ?> - <?php echo date('d/m/Y', strtotime($sunday_of_week)); ?>
        </div>

        <table class="calendar-table">
            <thead>
                <tr>
                    <th></th><th>Thứ 2</th><th>Thứ 3</th><th>Thứ 4</th><th>Thứ 5</th><th>Thứ 6</th><th>Thứ 7</th><th>Chủ Nhật</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $hours = ['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00'];
                foreach ($hours as $hour) {
                    echo "<tr>";
                    echo "<td class='time-col'>$hour</td>";
                    for ($day = 2; $day <= 8; $day++) {
                        echo "<td>";
                        if (isset($calendar_events[$day][$hour])) {
                            // 1 ô có thể có nhiều cuộc họp (khác phòng, cùng khung giờ) nên duyệt qua từng cái
                            foreach ($calendar_events[$day][$hour] as $event) {
                                $css_class = ($event['room_id'] == 2) ? 'event-block dept' : 'event-block';
                                echo "<div class='{$css_class}' onclick='openDetailModal({$event['id']}, \"{$event['title']}\", {$event['created_by']}, \"{$hour}\")'>{$event['title']}</div>";
                            }
                        }
                        echo "</td>";
                    }
                    echo "</tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

    <div id="bookModal" class="modal">
        <div class="modal-content">
            <h3>Tạo Lịch Họp Mới</h3>
            <?php if ($reopen_book_modal): ?>
                <div class="error-alert"><?php echo $error_msg; ?></div>
            <?php endif; ?>
            <form method="POST" action="" id="bookForm">
                <div class="form-group"><label>Tên cuộc họp:</label><input type="text" name="title" required placeholder="VD: Họp Sprint"></div>
                <div class="form-group">
    <label>Chọn phòng:</label>
    <select name="room_id">
        <?php foreach($all_rooms_list as $rm): ?>
            <option value="<?php echo $rm['id']; ?>">
                <?php echo $rm['room_name']; ?> (Sức chứa: <?php echo $rm['capacity']; ?>)
            </option>
        <?php endforeach; ?>
    </select>
</div>
                
                <div class="form-group">
                    <label>Ngày họp:</label>
                    <select name="meet_date" required>
                        <?php
                        // Danh sách 7 ngày của tuần đang xem, hiển thị kèm Thứ + ngày/tháng/năm
                        // cho user dễ chọn, thay vì lịch ngày mặc định của trình duyệt
                        // (vốn cho phép bấm lung tung rồi mới báo lỗi/không hiểu vì sao bị khoá).
                        $weekday_labels = ['Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7', 'Chủ Nhật'];
                        for ($i = 0; $i <= 6; $i++) {
                            $option_date = date('Y-m-d', strtotime("+{$i} day", strtotime($monday_of_week)));
                            $option_label = $weekday_labels[$i] . ' - ' . date('d/m/Y', strtotime($option_date));
                            $is_selected = ($option_date === $current_date) ? ' selected' : '';
                            echo "<option value=\"{$option_date}\"{$is_selected}>{$option_label}</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <div class="form-group"><label>Giờ bắt đầu:</label><select name="start_time" id="selStartTime" onchange="updateEndTimeOptions()"><?php foreach($hours as $h) echo "<option value='".substr($h,0,2)."'>$h</option>"; ?></select></div>
                <div class="form-group"><label>Giờ kết thúc:</label><select name="end_time" id="selEndTime"></select></div>
                <div class="form-actions">
                    <button type="button" class="btn-close" onclick="document.getElementById('bookModal').style.display='none'">Đóng</button>
                    <button type="submit" name="btn_book" class="btn-submit" id="btnSubmitBook">Xác nhận</button>
                </div>
            </form>
        </div>
    </div>

    <div id="detailModal" class="modal">
        <div class="modal-content">
            <h3>Chi tiết cuộc họp</h3>
            <div class="detail-text"><strong>Tên cuộc họp:</strong> <span id="txtTitle"></span></div>
            <div class="detail-text"><strong>Khung giờ:</strong> <span id="txtTime"></span></div>
            
            <form method="POST" action="">
                <input type="hidden" name="delete_meeting_id" id="hidMeetingId">
                <div class="form-actions" id="popupActions"></div>
            </form>
        </div>
    </div>

    <script>
        // Danh sách mốc giờ hợp lệ để chọn "Giờ kết thúc", đồng bộ với $hours bên PHP
        // cộng thêm mốc 17:00 để có thể kết thúc cuối ngày.
        const ALL_HOURS = ['08:00','09:00','10:00','11:00','12:00','13:00','14:00','15:00','16:00','17:00'];

        function updateEndTimeOptions() {
            const startSelect = document.getElementById('selStartTime');
            const endSelect = document.getElementById('selEndTime');
            const startHour = startSelect.value; // vd '08', '09'...
            const currentEndValue = endSelect.value; // giữ lựa chọn cũ nếu vẫn còn hợp lệ

            endSelect.innerHTML = '';
            ALL_HOURS.forEach(h => {
                const hourValue = h.substring(0, 2);
                if (hourValue > startHour) {
                    const opt = document.createElement('option');
                    opt.value = hourValue;
                    opt.textContent = h;
                    endSelect.appendChild(opt);
                }
            });

            // Nếu giá trị cũ vẫn còn hợp lệ trong danh sách mới thì giữ nguyên
            const stillValid = Array.from(endSelect.options).some(o => o.value === currentEndValue);
            if (stillValid) {
                endSelect.value = currentEndValue;
            }
        }
        // Chạy 1 lần lúc tải trang để giờ kết thúc có sẵn dữ liệu ngay từ đầu
        updateEndTimeOptions();

        // Tránh bấm đúp nút "Xác nhận" khi đặt phòng (đỡ tạo lịch trùng do click nhiều lần)
        document.getElementById('bookForm').addEventListener('submit', function() {
            const btn = document.getElementById('btnSubmitBook');
            btn.disabled = true;
            btn.textContent = 'Đang xử lý...';
        });

        <?php if ($reopen_book_modal): ?>
        // Có lỗi khi đặt phòng (giờ không hợp lệ hoặc trùng lịch) -> tự mở lại modal để user thấy lỗi và sửa ngay
        document.getElementById('bookModal').style.display = 'flex';
        <?php endif; ?>

        function openDetailModal(id, title, createdBy, hour) {
            document.getElementById('txtTitle').innerText = title;
            document.getElementById('txtTime').innerText = hour;
            document.getElementById('hidMeetingId').value = id;

            const currentUserId = <?php echo $_SESSION['user_id']; ?>;
            const actionsDiv = document.getElementById('popupActions');
            let htmlButtons = '<button type="button" class="btn-close" onclick="closeDetailModal()">Đóng</button>';

            if (createdBy === currentUserId) {
                htmlButtons += '<button type="submit" name="btn_delete" class="btn-cancel" onclick="return confirm(\'Bạn có chắc chắn muốn hủy cuộc họp này không?\')">Hủy Lịch Họp</button>';
            }

            actionsDiv.innerHTML = htmlButtons;
            document.getElementById('detailModal').style.display = 'flex';
        }

        function closeDetailModal() {
            document.getElementById('detailModal').style.display = 'none';
        }
    </script>
</body>
</html>