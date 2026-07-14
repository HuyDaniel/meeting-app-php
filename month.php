<?php
session_start();
include 'db.php';
date_default_timezone_set('Asia/Ho_Chi_Minh');

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

// Xử lý điều hướng Tháng
$ym = isset($_GET['ym']) ? $_GET['ym'] : date('Y-m');
$timestamp = strtotime($ym . '-01');
$prev_month = date('Y-m', strtotime('-1 month', $timestamp));
$next_month = date('Y-m', strtotime('+1 month', $timestamp));
$month_title = date('m/Y', $timestamp);

// Lấy danh sách lịch trong tháng
$sql = "SELECT id, title, start_time, room_id FROM meetings WHERE DATE_FORMAT(start_time, '%Y-%m') = :ym ORDER BY start_time ASC";
$stmt = $conn->prepare($sql);
$stmt->execute(['ym' => $ym]);
$meetings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Gom nhóm lịch theo Ngày (Y-m-d)
$events = [];
foreach($meetings as $m) {
    $date = date('Y-m-d', strtotime($m['start_time']));
    $events[$date][] = $m;
}

// Logic lịch
$day_count = date('t', $timestamp); // Tổng số ngày trong tháng
$str = date('N', $timestamp); // Ngày 1 rơi vào thứ mấy (1: T2, 7: CN)
$weeks = [];
$week = '';

// Thêm các ô trống đầu tháng
$week .= str_repeat('<td></td>', $str - 1);

for ($day = 1; $day <= $day_count; $day++, $str++) {
    $date_format = $ym . '-' . sprintf('%02d', $day);
    $today_class = ($date_format == date('Y-m-d')) ? 'today' : '';
    
    $cell = "<td class='$today_class'><div class='date-num'>$day</div>";
    
    // Đổ lịch vào ô
    if (isset($events[$date_format])) {
        foreach ($events[$date_format] as $e) {
            $css_class = ($e['room_id'] == 2) ? 'event dept' : 'event';
            $time = date('H:i', strtotime($e['start_time']));
            $cell .= "<div class='$css_class' title='{$e['title']}'>$time - {$e['title']}</div>";
        }
    }
    $cell .= "</td>";
    $week .= $cell;

    // Sang tuần mới
    if ($str % 7 == 0 || $day == $day_count) {
        if ($day == $day_count) $week .= str_repeat('<td></td>', 7 - ($str % 7)); // Điền nốt ô trống cuối tháng
        $weeks[] = "<tr>$week</tr>";
        $week = '';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Lịch Tháng - XYZ Meetings</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: Arial, sans-serif; }
        body { background-color: #0f1319; color: #ffffff; display: flex; height: 100vh; }
        .sidebar { width: 240px; background-color: #161b22; padding: 30px 20px; border-right: 1px solid #21262d; }
        .logo { color: #d4af37; font-size: 20px; font-weight: bold; margin-bottom: 50px; }
        .menu-item { padding: 15px 10px; color: #8b949e; text-decoration: none; display: block; border-radius: 6px; margin-bottom: 10px; }
        .menu-item.active { color: #ffffff; background-color: #21262d; }
        .main-content { flex: 1; display: flex; flex-direction: column; padding: 30px; overflow-y: auto; }
        
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .btn-nav { padding: 10px 15px; border-radius: 4px; font-weight: bold; background-color: #21262d; color: white; border: 1px solid #30363d; text-decoration: none;}
        .btn-nav:hover { background-color: #30363d; }
        .month-title { font-size: 24px; color: #d4f285; font-weight: bold; }
        
        /* Grid lịch tháng */
        .calendar-table { width: 100%; border-collapse: collapse; background-color: #161b22; table-layout: fixed;}
        .calendar-table th { background-color: #1f242c; padding: 15px; border: 1px solid #30363d; }
        .calendar-table td { border: 1px solid #30363d; height: 120px; vertical-align: top; padding: 5px; }
        .calendar-table td.today { background-color: rgba(212, 242, 133, 0.05); }
        .date-num { font-weight: bold; color: #8b949e; text-align: right; padding: 5px; margin-bottom: 5px;}
        .today .date-num { color: #d4f285; font-size: 18px;}
        
        .event { background-color: #2f80ed; color: white; padding: 4px 6px; border-radius: 3px; font-size: 11px; margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;}
        .event.dept { background-color: #bb6bd9; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo">XYZ MEETINGS</div>
        <a href="dashboard.php" class="menu-item">Dashboard</a>
        <a href="index.php" class="menu-item active">Lịch của tôi</a>
        <a href="rooms.php" class="menu-item">Danh sách phòng</a>
        <a href="profile.php" class="menu-item">Hồ sơ cá nhân</a>
    </div>

    <div class="main-content">
        <div class="top-bar">
            <div class="month-title">Tháng <?php echo $month_title; ?></div>
            <div>
                <a href="?ym=<?php echo $prev_month; ?>" class="btn-nav">◄ Tháng trước</a>
                <a href="?ym=<?php echo date('Y-m'); ?>" class="btn-nav">Tháng nay</a>
                <a href="?ym=<?php echo $next_month; ?>" class="btn-nav">Tháng sau ►</a>
                <a href="index.php" class="btn-nav" style="background-color: #d4f285; color: black; margin-left: 20px;">Dạng Tuần</a>
            </div>
        </div>

        <table class="calendar-table">
            <thead>
                <tr><th>Thứ 2</th><th>Thứ 3</th><th>Thứ 4</th><th>Thứ 5</th><th>Thứ 6</th><th>Thứ 7</th><th>Chủ Nhật</th></tr>
            </thead>
            <tbody>
                <?php foreach ($weeks as $w) echo $w; ?>
            </tbody>
        </table>
    </div>
</body>
</html>