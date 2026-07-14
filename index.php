<?php
// index.php
session_start();
include 'db.php';
$error_msg = '';
$reopen_book_modal = false;
$reopen_edit_modal = false;

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$current_user_id = $_SESSION['user_id'];

// --- 1. PHẢN HỒI LỜI MỜI HỌP (US-11) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_respond'])) {
    $respond_mid = $_POST['respond_meeting_id'];
    $new_status = $_POST['response_status']; // 'accepted' hoặc 'declined'
    $stmt = $conn->prepare("UPDATE meeting_participants SET status = :status WHERE meeting_id = :mid AND user_id = :uid");
    $stmt->execute(['status' => $new_status, 'mid' => $respond_mid, 'uid' => $current_user_id]);
    header("Location: index.php?date=" . urlencode($_GET['date'] ?? date('Y-m-d')));
    exit();
}

// --- 2. XỬ LÝ ĐẶT PHÒNG (US-06 & US-07) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_book'])) {
    $title = trim($_POST['title']);
    $room_id = $_POST['room_id'];
    $date = $_POST['meet_date'];
    $start_time = $date . ' ' . $_POST['start_time'] . ':00';
    $end_time = $date . ' ' . $_POST['end_time'] . ':00';
    $participants = isset($_POST['participants']) ? $_POST['participants'] : [];

    if (strtotime($end_time) <= strtotime($start_time)) {
        $error_msg = "Giờ kết thúc phải sau giờ bắt đầu!";
        $reopen_book_modal = true;
    } else {
        // A. Check trùng phòng
        $check_room = $conn->prepare("SELECT id FROM meetings WHERE room_id = :room_id AND start_time < :end_time AND end_time > :start_time");
        $check_room->execute(['room_id' => $room_id, 'end_time' => $end_time, 'start_time' => $start_time]);

        if ($check_room->rowCount() > 0) {
            $error_msg = "Phòng này đã kẹt lịch trong khung giờ đó!";
            $reopen_book_modal = true;
        } else {
            // B. Check trùng lịch người tham gia (US-07)
            $busy_names = [];
            if (!empty($participants)) {
                $in_users = implode(',', array_map('intval', $participants));
                $busy_sql = "
                    SELECT full_name FROM users WHERE id IN ($in_users)
                    AND EXISTS (
                        SELECT 1 FROM meetings m
                        LEFT JOIN meeting_participants mp ON m.id = mp.meeting_id
                        WHERE (m.created_by = users.id OR (mp.user_id = users.id AND mp.status != 'declined'))
                        AND m.start_time < :end_time AND m.end_time > :start_time
                    )";
                $busy_stmt = $conn->prepare($busy_sql);
                $busy_stmt->execute(['end_time' => $end_time, 'start_time' => $start_time]);
                $busy_names = $busy_stmt->fetchAll(PDO::FETCH_COLUMN);
            }

            if (count($busy_names) > 0) {
                $error_msg = "Thành viên: " . implode(', ', $busy_names) . " bị kẹt lịch giờ này!";
                $reopen_book_modal = true;
            } else {
                // C. Insert Thành công
                $insert_sql = "INSERT INTO meetings (title, room_id, created_by, start_time, end_time) VALUES (:title, :room_id, :created_by, :start_time, :end_time)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->execute(['title' => $title, 'room_id' => $room_id, 'created_by' => $current_user_id, 'start_time' => $start_time, 'end_time' => $end_time]);
                
                $new_meeting_id = $conn->lastInsertId();

                // Lưu danh sách người tham gia
                if (!empty($participants)) {
                    $insert_part = $conn->prepare("INSERT INTO meeting_participants (meeting_id, user_id, status) VALUES (:mid, :uid, 'pending')");
                    foreach ($participants as $uid) {
                        $insert_part->execute(['mid' => $new_meeting_id, 'uid' => $uid]);
                    }
                }
                header("Location: index.php?date=" . urlencode($date));
                exit();
            }
        }
    }
}

// --- 3. XỬ LÝ SỬA PHÒNG & KHÁCH MỜI (US-09) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_edit'])) {
    $edit_id = $_POST['edit_meeting_id'];
    $title = trim($_POST['title']);
    $room_id = $_POST['room_id'];
    $date = $_POST['meet_date'];
    $start_time = $date . ' ' . $_POST['start_time'] . ':00';
    $end_time = $date . ' ' . $_POST['end_time'] . ':00';
    $participants = isset($_POST['participants']) ? $_POST['participants'] : [];

    $check_owner = $conn->prepare("SELECT created_by FROM meetings WHERE id = :id");
    $check_owner->execute(['id' => $edit_id]);
    $meeting = $check_owner->fetch(PDO::FETCH_ASSOC);

    if ($meeting && $meeting['created_by'] == $current_user_id) {
        if (strtotime($end_time) <= strtotime($start_time)) {
            $error_msg = "Sửa thất bại: Giờ kết thúc phải sau giờ bắt đầu!";
            $reopen_edit_modal = true;
        } else {
            // A. Check trùng phòng (bỏ qua id đang sửa)
            $check_sql = "SELECT id FROM meetings WHERE room_id = :room_id AND start_time < :end_time AND end_time > :start_time AND id != :edit_id";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->execute(['room_id' => $room_id, 'end_time' => $end_time, 'start_time' => $start_time, 'edit_id' => $edit_id]);

            if ($check_stmt->rowCount() > 0) {
                $error_msg = "Sửa thất bại: Phòng này đã kẹt lịch!";
                $reopen_edit_modal = true;
            } else {
                // B. Check trùng lịch người tham gia (bỏ qua id đang sửa)
                $busy_names = [];
                if (!empty($participants)) {
                    $in_users = implode(',', array_map('intval', $participants));
                    $busy_sql = "
                        SELECT full_name FROM users WHERE id IN ($in_users)
                        AND EXISTS (
                            SELECT 1 FROM meetings m
                            LEFT JOIN meeting_participants mp ON m.id = mp.meeting_id
                            WHERE (m.created_by = users.id OR (mp.user_id = users.id AND mp.status != 'declined'))
                            AND m.start_time < :end_time AND m.end_time > :start_time
                            AND m.id != :edit_id
                        )";
                    $busy_stmt = $conn->prepare($busy_sql);
                    $busy_stmt->execute(['end_time' => $end_time, 'start_time' => $start_time, 'edit_id' => $edit_id]);
                    $busy_names = $busy_stmt->fetchAll(PDO::FETCH_COLUMN);
                }

                if (count($busy_names) > 0) {
                    $error_msg = "Thành viên: " . implode(', ', $busy_names) . " bị kẹt lịch giờ này!";
                    $reopen_edit_modal = true;
                } else {
                    $update_sql = "UPDATE meetings SET title = :title, room_id = :room_id, start_time = :start_time, end_time = :end_time WHERE id = :edit_id";
                    $conn->prepare($update_sql)->execute(['title' => $title, 'room_id' => $room_id, 'start_time' => $start_time, 'end_time' => $end_time, 'edit_id' => $edit_id]);
                    
                    $conn->prepare("DELETE FROM meeting_participants WHERE meeting_id = :id")->execute(['id' => $edit_id]);
                    if (!empty($participants)) {
                        $insert_part = $conn->prepare("INSERT INTO meeting_participants (meeting_id, user_id, status) VALUES (:mid, :uid, 'pending')");
                        foreach ($participants as $uid) {
                            $insert_part->execute(['mid' => $edit_id, 'uid' => $uid]);
                        }
                    }
                    header("Location: index.php?date=" . urlencode($date));
                    exit();
                }
            }
        }
    }
}

// --- 4. XỬ LÝ HỦY PHÒNG ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_delete'])) {
    $delete_id = $_POST['delete_meeting_id'];
    $check_owner = $conn->prepare("SELECT created_by FROM meetings WHERE id = :id");
    $check_owner->execute(['id' => $delete_id]);
    $meeting = $check_owner->fetch(PDO::FETCH_ASSOC);

    if ($meeting && $meeting['created_by'] == $current_user_id) {
        $conn->prepare("DELETE FROM meetings WHERE id = :id")->execute(['id' => $delete_id]);
        header("Location: index.php?date=" . (isset($_GET['date']) ? urlencode($_GET['date']) : ''));
        exit();
    }
}

// --- LOGIC NGÀY THÁNG ---
$current_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$monday_of_week = date('Y-m-d', strtotime('monday this week', strtotime($current_date)));
$sunday_of_week = date('Y-m-d', strtotime('sunday this week', strtotime($current_date)));
$prev_week = date('Y-m-d', strtotime('-1 week', strtotime($monday_of_week)));
$next_week = date('Y-m-d', strtotime('+1 week', strtotime($monday_of_week)));

// --- KÉO DATA PHỤ ---
$stmt_all_rooms = $conn->query("SELECT id, room_name, capacity FROM rooms");
$all_rooms_list = $stmt_all_rooms->fetchAll(PDO::FETCH_ASSOC);

$stmt_all_users = $conn->query("SELECT id, full_name FROM users");
$all_users_list = $stmt_all_users->fetchAll(PDO::FETCH_ASSOC);

$stmt_other_users = $conn->prepare("SELECT id, full_name, email FROM users WHERE id != :id");
$stmt_other_users->execute(['id' => $current_user_id]);
$other_users_list = $stmt_other_users->fetchAll(PDO::FETCH_ASSOC);

// --- TÌM KIẾM & KÉO DATA LỊCH ---
$search_kw = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_room = isset($_GET['filter_room']) ? $_GET['filter_room'] : '';
$filter_user = isset($_GET['filter_user']) ? $_GET['filter_user'] : '';

$search_condition = "";
$params = ['start_week' => $monday_of_week . ' 00:00:00', 'end_week' => $sunday_of_week . ' 23:59:59'];

if ($search_kw !== '') { $search_condition .= " AND m.title LIKE :search "; $params['search'] = "%$search_kw%"; }
if ($filter_room !== '') { $search_condition .= " AND m.room_id = :room_id "; $params['room_id'] = $filter_room; }
if ($filter_user !== '') { $search_condition .= " AND m.created_by = :created_by "; $params['created_by'] = $filter_user; }

$sql = "SELECT m.id, m.title, m.start_time, m.end_time, m.room_id, m.created_by, u.full_name as creator_name
        FROM meetings m 
        JOIN users u ON m.created_by = u.id
        WHERE m.start_time >= :start_week AND m.start_time <= :end_week" . $search_condition;
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$meetings_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$participants_by_meeting = [];
$meeting_ids = array_column($meetings_data, 'id');
if (!empty($meeting_ids)) {
    $in_mids = implode(',', $meeting_ids);
    $part_sql = "SELECT mp.meeting_id, mp.user_id, u.full_name, mp.status 
                 FROM meeting_participants mp JOIN users u ON mp.user_id = u.id 
                 WHERE mp.meeting_id IN ($in_mids)";
    $part_stmt = $conn->query($part_sql);
    while ($r = $part_stmt->fetch(PDO::FETCH_ASSOC)) {
        $participants_by_meeting[$r['meeting_id']][] = $r;
    }
}

$calendar_events = [];
foreach ($meetings_data as $row) {
    $start_timestamp = strtotime($row['start_time']);
    $day_of_week = date('N', $start_timestamp) + 1; 
    $hour_format = date('H:00', $start_timestamp); 
    
    $parts = isset($participants_by_meeting[$row['id']]) ? $participants_by_meeting[$row['id']] : [];
    
    $calendar_events[$day_of_week][$hour_format][] = [
        'id' => $row['id'],
        'title' => $row['title'],
        'room_id' => $row['room_id'],
        'created_by' => $row['created_by'],
        'creator_name' => $row['creator_name'],
        'date' => date('Y-m-d', $start_timestamp),
        'start_hour' => date('H', $start_timestamp),
        'end_hour' => date('H', strtotime($row['end_time'])),
        'participants' => $parts
    ];
}
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
        
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .search-form { display: flex; flex: 1; margin-right: 20px; }
        .search-box { width: 40%; padding: 12px 15px; background-color: #161b22; border: 1px solid #30363d; border-radius: 6px 0 0 6px; color: white; outline: none; }
        .filter-box { width: 25%; padding: 12px 10px; background-color: #161b22; border: 1px solid #30363d; border-left: none; color: white; outline: none; }
        .btn-search { padding: 12px 20px; background-color: #30363d; border: 1px solid #30363d; border-radius: 0 6px 6px 0; color: white; cursor: pointer; font-weight: bold; }
        .btn-search:hover { background-color: #8b949e; }
        
        .btn-group button, .btn-group a.btn-nav { padding: 10px 15px; margin-left: 5px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; background-color: #d4f285; color: #0f1319; text-decoration: none; display: inline-block;}
        .btn-group a.btn-nav { background-color: #21262d; color: white; border: 1px solid #30363d;}
        
        .week-label { margin-bottom: 20px; font-size: 18px; font-weight: bold; color: #d4f285; text-align: center; }

        .calendar-table { width: 100%; border-collapse: collapse; background-color: #161b22; border: 1px solid #30363d; }
        .calendar-table th, .calendar-table td { border: 1px solid #30363d; padding: 10px; text-align: center; width: 12%; vertical-align: top;}
        .calendar-table th { background-color: #1f242c; font-weight: bold; padding: 15px;}
        .calendar-table td.time-col { background-color: #1f242c; font-weight: bold; width: 8%; vertical-align: middle; }
        
        .event-block { background-color: #2f80ed; color: white; padding: 8px; border-radius: 4px; font-size: 13px; font-weight: bold; cursor: pointer; margin-bottom: 6px; transition: 0.2s; position: relative;}
        .event-block:hover { transform: scale(1.02); }
        .event-block.dept { background-color: #bb6bd9; }
        .event-block.invited { background-color: #e67e22; border-left: 4px solid #d35400;} 

        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.7); justify-content: center; align-items: center; z-index: 1000; }
        .modal-content { background-color: #161b22; padding: 30px; border-radius: 8px; width: 450px; border: 1px solid #30363d; max-height: 90vh; overflow-y: auto;}
        .modal-content h3 { margin-bottom: 20px; color: #d4f285; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; color: #8b949e; }
        .form-group input, .form-group select { width: 100%; padding: 10px; background-color: #0f1319; border: 1px solid #30363d; color: white; border-radius: 4px; outline: none;}
        
        .participant-box { background-color: #0f1319; padding: 10px; border: 1px solid #30363d; border-radius: 4px; max-height: 120px; overflow-y: auto; }
        .participant-item { display: block; margin-bottom: 8px; font-size: 14px; cursor: pointer; }
        
        .form-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
        .form-actions button { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn-submit { background-color: #d4f285; color: #0f1319; }
        .btn-edit-action { background-color: #f39c12; color: white; }
        .btn-accept { background-color: #2ecc71; color: white; }
        .btn-decline { background-color: #e74c3c; color: white; }
        .btn-close { background-color: #30363d; color: white; }
        .btn-cancel { background-color: #e57373; color: white; }
        .error-alert { background-color: #e57373; color: white; padding: 10px; border-radius: 4px; margin-bottom: 15px; text-align: center; font-weight: bold; font-size: 14px;}
        .detail-text { margin-bottom: 10px; font-size: 15px; color: #c9d1d9; }
        
        .invited-list { list-style: none; margin-top: 10px; padding: 10px; background-color: #0f1319; border-radius: 4px; border: 1px solid #30363d;}
        .invited-list li { margin-bottom: 5px; font-size: 14px; display: flex; justify-content: space-between; align-items: center;}
        .tag-status { padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: bold; text-transform: uppercase;}
        .st-pending { background-color: #f39c12; color: white; }
        .st-accepted { background-color: #2ecc71; color: white; }
        .st-declined { background-color: #e74c3c; color: white; }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="logo">XYZ MEETINGS</div>
        <a href="dashboard.php" class="menu-item">Dashboard</a>
        <a href="index.php" class="menu-item active">Lịch của tôi</a>
        <a href="rooms.php" class="menu-item">Danh sách phòng</a>
        <a href="profile.php" class="menu-item">Hồ sơ cá nhân</a>
        <a href="logout.php" class="menu-item" style="color: #e57373; margin-top: 50px; border: 1px solid #30363d;">Đăng xuất</a>
    </div>

    <div class="main-content">
        <div class="top-bar">
            <form class="search-form" method="GET" action="index.php">
                <input type="hidden" name="date" value="<?php echo htmlspecialchars($current_date); ?>">
                <input type="text" name="search" class="search-box" placeholder="Nhập tên cuộc họp..." value="<?php echo htmlspecialchars($search_kw); ?>">
                <select name="filter_room" class="filter-box">
                    <option value="">-- Tất cả phòng --</option>
                    <?php foreach($all_rooms_list as $rm): ?>
                        <option value="<?php echo $rm['id']; ?>" <?php if($filter_room == $rm['id']) echo 'selected'; ?>><?php echo htmlspecialchars($rm['room_name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="filter_user" class="filter-box">
                    <option value="">-- Người tổ chức --</option>
                    <?php foreach($all_users_list as $u): ?>
                        <option value="<?php echo $u['id']; ?>" <?php if($filter_user == $u['id']) echo 'selected'; ?>><?php echo htmlspecialchars($u['full_name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-search">Lọc</button>
            </form>
            
            <div class="btn-group">
                <a href="?date=<?php echo $prev_week; ?>" class="btn-nav">◄ Tuần trước</a>
                <a href="?date=<?php echo date('Y-m-d'); ?>" class="btn-nav">Hôm nay</a>
                <a href="?date=<?php echo $next_week; ?>" class="btn-nav">Tuần sau ►</a>
                <a href="month.php" class="btn-nav" style="border-color: #d4f285; color: #d4f285;">Dạng Tháng</a>
                <button onclick="document.getElementById('bookModal').style.display='flex'">+ Đặt phòng</button>
            </div>
        </div>

        <?php if($error_msg && !$reopen_book_modal && !$reopen_edit_modal): ?>
            <div class="error-alert"><?php echo $error_msg; ?></div>
        <?php endif; ?>

        <div class="week-label">
            Lịch tuần: <?php echo date('d/m/Y', strtotime($monday_of_week)); ?> - <?php echo date('d/m/Y', strtotime($sunday_of_week)); ?>
        </div>

        <table class="calendar-table">
            <thead>
                <tr><th></th><th>Thứ 2</th><th>Thứ 3</th><th>Thứ 4</th><th>Thứ 5</th><th>Thứ 6</th><th>Thứ 7</th><th>Chủ Nhật</th></tr>
            </thead>
            <tbody>
                <?php
                $hours = ['08:00','09:00','10:00','11:00','12:00','13:00','14:00','15:00','16:00'];
                foreach ($hours as $hour) {
                    echo "<tr><td class='time-col'>$hour</td>";
                    for ($day = 2; $day <= 8; $day++) {
                        echo "<td>";
                        if (isset($calendar_events[$day][$hour])) {
                            foreach ($calendar_events[$day][$hour] as $event) {
                                $is_invited = false;
                                foreach($event['participants'] as $p) {
                                    if ($p['user_id'] == $current_user_id) $is_invited = true;
                                }
                                
                                $css_class = 'event-block';
                                if ($event['room_id'] == 2) $css_class .= ' dept';
                                if ($is_invited) $css_class .= ' invited';

                                $event_json = htmlspecialchars(json_encode($event), ENT_QUOTES, 'UTF-8');
                                echo "<div class='{$css_class}' onclick='openDetailModal({$event_json}, \"{$hour}\")'>".htmlspecialchars($event['title'])."</div>";
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

    <!-- MODAL ĐẶT LỊCH -->
    <div id="bookModal" class="modal" <?php if($reopen_book_modal) echo 'style="display:flex;"'; ?>>
        <div class="modal-content">
            <h3>Tạo Lịch Họp Mới</h3>
            <?php if ($reopen_book_modal): ?><div class="error-alert"><?php echo $error_msg; ?></div><?php endif; ?>
            <form method="POST" action="" id="bookForm">
                <div class="form-group"><label>Tên cuộc họp:</label><input type="text" name="title" required></div>
                <div class="form-group"><label>Chọn phòng:</label>
                    <select name="room_id">
                        <?php foreach($all_rooms_list as $rm): ?>
                            <option value="<?php echo $rm['id']; ?>"><?php echo htmlspecialchars($rm['room_name']); ?> (<?php echo $rm['capacity']; ?> người)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Mời người tham gia (Tùy chọn):</label>
                    <div class="participant-box">
                        <?php foreach($other_users_list as $ou): ?>
                            <label class="participant-item">
                                <input type="checkbox" name="participants[]" value="<?php echo $ou['id']; ?>"> 
                                <?php echo htmlspecialchars($ou['full_name']); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group"><label>Ngày họp:</label>
                    <select name="meet_date" required>
                        <?php
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
                <div class="form-group"><label>Giờ bắt đầu:</label><select name="start_time" id="selStartTime" onchange="updateEndTimeOptions('selStartTime', 'selEndTime')"><?php foreach($hours as $h) echo "<option value='".substr($h,0,2)."'>$h</option>"; ?></select></div>
                <div class="form-group"><label>Giờ kết thúc:</label><select name="end_time" id="selEndTime"></select></div>
                <div class="form-actions">
                    <button type="button" class="btn-close" onclick="document.getElementById('bookModal').style.display='none'">Đóng</button>
                    <button type="submit" name="btn_book" class="btn-submit" id="btnSubmitBook">Xác nhận</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL SỬA LỊCH -->
    <div id="editModal" class="modal" <?php if($reopen_edit_modal) echo 'style="display:flex;"'; ?>>
        <div class="modal-content">
            <h3>Chỉnh Sửa Lịch Họp</h3>
            <?php if ($reopen_edit_modal): ?><div class="error-alert"><?php echo $error_msg; ?></div><?php endif; ?>
            <form method="POST" action="" id="editForm">
                <input type="hidden" name="edit_meeting_id" id="edit_meeting_id" value="<?php echo $_POST['edit_meeting_id'] ?? ''; ?>">
                <div class="form-group"><label>Tên cuộc họp:</label><input type="text" name="title" id="edit_title" required value="<?php echo $_POST['title'] ?? ''; ?>"></div>
                <div class="form-group"><label>Chọn phòng:</label>
                    <select name="room_id" id="edit_room_id">
                        <?php foreach($all_rooms_list as $rm): ?>
                            <option value="<?php echo $rm['id']; ?>"><?php echo htmlspecialchars($rm['room_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Mời lại người tham gia:</label>
                    <div class="participant-box" id="edit_participant_box"></div>
                </div>

                <div class="form-group"><label>Ngày họp (Trong tuần này):</label>
                    <select name="meet_date" id="edit_meet_date" required>
                        <?php
                        for ($i = 0; $i <= 6; $i++) {
                            $option_date = date('Y-m-d', strtotime("+{$i} day", strtotime($monday_of_week)));
                            $option_label = $weekday_labels[$i] . ' - ' . date('d/m/Y', strtotime($option_date));
                            echo "<option value=\"{$option_date}\">{$option_label}</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group"><label>Giờ bắt đầu:</label><select name="start_time" id="edit_start_time" onchange="updateEndTimeOptions('edit_start_time', 'edit_end_time')"><?php foreach($hours as $h) echo "<option value='".substr($h,0,2)."'>$h</option>"; ?></select></div>
                <div class="form-group"><label>Giờ kết thúc:</label><select name="end_time" id="edit_end_time"></select></div>
                <div class="form-actions">
                    <button type="button" class="btn-close" onclick="document.getElementById('editModal').style.display='none'">Đóng</button>
                    <button type="submit" name="btn_edit" class="btn-submit btn-edit-action" id="btnSubmitEdit">Lưu Thay Đổi</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL CHI TIẾT & PHẢN HỒI -->
    <div id="detailModal" class="modal">
        <div class="modal-content">
            <h3>Chi tiết cuộc họp</h3>
            <div class="detail-text"><strong>Chủ đề:</strong> <span id="txtTitle"></span></div>
            <div class="detail-text"><strong>Người tạo:</strong> <span id="txtCreator"></span></div>
            <div class="detail-text"><strong>Khung giờ:</strong> <span id="txtTime"></span></div>
            
            <div class="detail-text" style="margin-top: 15px; font-weight: bold;">Danh sách tham gia:</div>
            <ul class="invited-list" id="ulParticipants"></ul>
            
            <form method="POST" action="" id="respondForm" style="display: none; margin-top: 20px; border-top: 1px solid #30363d; padding-top: 15px;">
                <input type="hidden" name="respond_meeting_id" id="respondMeetingId">
                <div style="margin-bottom: 10px; color: #d4f285; font-weight: bold;">Bạn có tham gia được không?</div>
                <button type="submit" name="btn_respond" class="btn-submit btn-accept" onclick="document.getElementById('response_status').value='accepted'">Đồng ý</button>
                <button type="submit" name="btn_respond" class="btn-submit btn-decline" onclick="document.getElementById('response_status').value='declined'">Từ chối</button>
                <input type="hidden" name="response_status" id="response_status" value="">
            </form>

            <form method="POST" action="">
                <input type="hidden" name="delete_meeting_id" id="hidMeetingId">
                <div class="form-actions" id="popupActions"></div>
            </form>
        </div>
    </div>

    <script>
        const ALL_HOURS = ['08:00','09:00','10:00','11:00','12:00','13:00','14:00','15:00','16:00','17:00'];
        const ALL_OTHER_USERS = <?php echo json_encode($other_users_list); ?>;

        function updateEndTimeOptions(startId, endId) {
            const startSelect = document.getElementById(startId);
            const endSelect = document.getElementById(endId);
            const startHour = startSelect.value; 
            const currentEndValue = endSelect.value; 

            endSelect.innerHTML = '';
            ALL_HOURS.forEach(h => {
                const hourValue = h.substring(0, 2);
                if (hourValue > startHour) {
                    const opt = document.createElement('option');
                    opt.value = hourValue; opt.textContent = h;
                    endSelect.appendChild(opt);
                }
            });

            const stillValid = Array.from(endSelect.options).some(o => o.value === currentEndValue);
            if (stillValid) endSelect.value = currentEndValue;
        }
        updateEndTimeOptions('selStartTime', 'selEndTime');

        // Đã fix lỗi kẹt data khi submit form bằng setTimeout
        document.getElementById('bookForm').addEventListener('submit', function() {
            const btn = document.getElementById('btnSubmitBook');
            setTimeout(() => { btn.disabled = true; btn.textContent = 'Đang xử lý...'; }, 10);
        });
        document.getElementById('editForm').addEventListener('submit', function() {
            const btn = document.getElementById('btnSubmitEdit');
            setTimeout(() => { btn.disabled = true; btn.textContent = 'Đang lưu...'; }, 10);
        });

        function openDetailModal(eventObj, hour) {
            document.getElementById('txtTitle').innerText = eventObj.title;
            document.getElementById('txtTime').innerText = hour;
            document.getElementById('txtCreator').innerText = eventObj.creator_name;
            document.getElementById('hidMeetingId').value = eventObj.id;

            const currentUserId = <?php echo $_SESSION['user_id']; ?>;
            const actionsDiv = document.getElementById('popupActions');
            const respondForm = document.getElementById('respondForm');
            const ulParts = document.getElementById('ulParticipants');
            
            let htmlButtons = '<button type="button" class="btn-close" onclick="document.getElementById(\'detailModal\').style.display=\'none\'">Đóng</button>';
            ulParts.innerHTML = `<li><span>👑 ${eventObj.creator_name} (Tổ chức)</span> <span class="tag-status st-accepted">Đã xác nhận</span></li>`;
            
            let amIInvited = false;
            let myStatus = '';

            eventObj.participants.forEach(p => {
                let badge = '';
                if(p.status === 'pending') badge = '<span class="tag-status st-pending">Chờ xác nhận</span>';
                if(p.status === 'accepted') badge = '<span class="tag-status st-accepted">Tham gia</span>';
                if(p.status === 'declined') badge = '<span class="tag-status st-declined">Từ chối</span>';
                
                ulParts.innerHTML += `<li><span>👤 ${p.full_name}</span> ${badge}</li>`;
                
                if (p.user_id == currentUserId) {
                    amIInvited = true;
                    myStatus = p.status;
                }
            });

            if (eventObj.created_by === currentUserId) {
                const eventJson = JSON.stringify(eventObj).replace(/'/g, "\\'");
                htmlButtons += `<button type="button" class="btn-submit btn-edit-action" onclick='openEditModal(${eventJson})'>Sửa Lịch</button>`;
                htmlButtons += '<button type="submit" name="btn_delete" class="btn-cancel" onclick="return confirm(\'Hủy cuộc họp sẽ xóa toàn bộ danh sách khách mời. Bạn chắc chắn chứ?\')">Hủy Lịch</button>';
                respondForm.style.display = 'none';
            } else {
                if (amIInvited && myStatus === 'pending') {
                    document.getElementById('respondMeetingId').value = eventObj.id;
                    respondForm.style.display = 'block';
                } else {
                    respondForm.style.display = 'none';
                }
            }

            actionsDiv.innerHTML = htmlButtons;
            document.getElementById('detailModal').style.display = 'flex';
        }

        function openEditModal(eventObj) {
            document.getElementById('detailModal').style.display = 'none'; 
            
            document.getElementById('edit_meeting_id').value = eventObj.id;
            document.getElementById('edit_title').value = eventObj.title;
            document.getElementById('edit_room_id').value = eventObj.room_id;
            document.getElementById('edit_meet_date').value = eventObj.date;
            
            let pBox = document.getElementById('edit_participant_box');
            pBox.innerHTML = '';
            ALL_OTHER_USERS.forEach(u => {
                const isChecked = eventObj.participants.some(p => p.user_id == u.id) ? 'checked' : '';
                pBox.innerHTML += `
                    <label class="participant-item">
                        <input type="checkbox" name="participants[]" value="${u.id}" ${isChecked}> ${u.full_name}
                    </label>
                `;
            });

            document.getElementById('edit_start_time').value = eventObj.start_hour;
            updateEndTimeOptions('edit_start_time', 'edit_end_time'); 
            document.getElementById('edit_end_time').value = eventObj.end_hour;
            
            document.getElementById('editModal').style.display = 'flex';
        }
    </script>
</body>
</html>