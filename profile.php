<?php
// profile.php
session_start();
include 'db.php';
date_default_timezone_set('Asia/Ho_Chi_Minh');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$msg = ''; $msg_type = ''; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Cập nhật SĐT & Avatar (US-03)
    if (isset($_POST['btn_update_profile'])) {
        $phone = trim($_POST['phone']);
        
        // Xử lý upload ảnh
        $avatar_query = "";
        $params = ['phone' => $phone, 'id' => $user_id];

        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['avatar']['name'];
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            
            if (in_array(strtolower($ext), $allowed)) {
                $new_filename = 'avatar_' . $user_id . '_' . time() . '.' . $ext;
                if (!is_dir('uploads')) mkdir('uploads', 0777, true);
                move_uploaded_file($_FILES['avatar']['tmp_name'], 'uploads/' . $new_filename);
                
                $avatar_query = ", avatar = :avatar";
                $params['avatar'] = $new_filename;
            } else {
                $msg = "Chỉ chấp nhận file ảnh (JPG, PNG, GIF)!";
                $msg_type = "error";
            }
        }

        if (empty($msg_type)) {
            $stmt = $conn->prepare("UPDATE users SET phone = :phone $avatar_query WHERE id = :id");
            if ($stmt->execute($params)) {
                $msg = "Cập nhật thông tin thành công!";
                $msg_type = "success";
            }
        }
    }
    
    // 2. Đổi mật khẩu (US-02) (Giữ nguyên)
    if (isset($_POST['btn_change_pass'])) {
        $old_pass = $_POST['old_password']; $new_pass = $_POST['new_password']; $confirm_pass = $_POST['confirm_password'];
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = :id");
        $stmt->execute(['id' => $user_id]);
        $user_db = $stmt->fetch();
        
        if ($old_pass !== $user_db['password']) { $msg = "Mật khẩu cũ sai!"; $msg_type = "error"; } 
        elseif (strlen($new_pass) < 8) { $msg = "Mật khẩu mới phải ≥ 8 ký tự!"; $msg_type = "error"; } 
        elseif ($new_pass !== $confirm_pass) { $msg = "Mật khẩu xác nhận không khớp!"; $msg_type = "error"; } 
        else {
            $conn->prepare("UPDATE users SET password = :new_pass WHERE id = :id")->execute(['new_pass' => $new_pass, 'id' => $user_id]);
            $msg = "Đổi mật khẩu thành công!"; $msg_type = "success";
        }
    }
}

// Kéo data hiển thị
$stmt = $conn->prepare("SELECT u.*, d.dept_name FROM users u LEFT JOIN departments d ON u.department_id = d.id WHERE u.id = :id");
$stmt->execute(['id' => $user_id]);
$user_info = $stmt->fetch(PDO::FETCH_ASSOC);

$dob_formatted = $user_info['dob'] ? date('d/m/Y', strtotime($user_info['dob'])) : 'Chưa cập nhật';
$join_formatted = $user_info['join_date'] ? date('d/m/Y', strtotime($user_info['join_date'])) : 'Chưa cập nhật';
$phone_display = $user_info['phone'] ? htmlspecialchars($user_info['phone']) : '';

$stmt_m = $conn->prepare("SELECT m.*, r.room_name FROM meetings m JOIN rooms r ON m.room_id = r.id WHERE m.created_by = :user_id ORDER BY m.start_time DESC");
$stmt_m->execute(['user_id' => $user_id]);
$my_meetings = $stmt_m->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Hồ Sơ Của Tôi - XYZ Meetings</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: Arial, sans-serif; }
        body { background-color: #0f1319; color: #ffffff; display: flex; height: 100vh; }
        .sidebar { width: 240px; background-color: #161b22; padding: 30px 20px; border-right: 1px solid #21262d; }
        .logo { color: #d4af37; font-size: 20px; font-weight: bold; margin-bottom: 50px; }
        .menu-item { padding: 15px 10px; color: #8b949e; text-decoration: none; display: block; border-radius: 6px; margin-bottom: 10px; }
        .menu-item.active, .menu-item:hover { color: #ffffff; background-color: #21262d; }
        .main-content { flex: 1; padding: 40px; overflow-y: auto; display: flex; gap: 30px; }
        .left-col { flex: 1; max-width: 450px; } .right-col { flex: 2; }
        .card { background-color: #161b22; border: 1px solid #30363d; border-radius: 12px; padding: 30px; margin-bottom: 25px; }
        .card h3 { color: #d4f285; margin-bottom: 20px; border-bottom: 1px solid #30363d; padding-bottom: 10px; }
        .profile-header { display: flex; align-items: center; margin-bottom: 20px; }
        .avatar { width: 70px; height: 70px; background-color: #2f80ed; color: white; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 28px; font-weight: bold; margin-right: 20px; object-fit: cover;}
        .form-group { margin-bottom: 15px; } .form-group label { display: block; margin-bottom: 5px; color: #8b949e; font-size: 13px; }
        .form-group input { width: 100%; padding: 10px; background-color: #0f1319; border: 1px solid #30363d; color: white; border-radius: 4px; outline: none; }
        .btn-submit { background-color: #d4f285; color: #0f1319; border: none; padding: 10px 20px; border-radius: 4px; font-weight: bold; cursor: pointer; width: 100%; margin-top: 5px;}
        .alert { padding: 10px; border-radius: 4px; margin-bottom: 20px; font-weight: bold; text-align: center; }
        .alert.success { background-color: rgba(46, 204, 113, 0.2); color: #2ecc71; border: 1px solid #2ecc71; }
        .alert.error { background-color: rgba(231, 76, 60, 0.2); color: #e74c3c; border: 1px solid #e74c3c; }
        .meeting-table { width: 100%; border-collapse: collapse; }
        .meeting-table th, .meeting-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #30363d; }
        .meeting-table th { color: #8b949e; font-size: 13px; text-transform: uppercase; }
        .status-tag { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: bold; }
        .status-past { background-color: #30363d; color: #8b949e; } .status-upcoming { background-color: rgba(46, 204, 113, 0.2); color: #2ecc71; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo">XYZ MEETINGS</div>
        <a href="dashboard.php" class="menu-item">Dashboard</a>
        <a href="index.php" class="menu-item">Lịch của tôi</a>
        <a href="rooms.php" class="menu-item">Danh sách phòng</a>
        <a href="profile.php" class="menu-item active">Hồ sơ cá nhân</a>
        <a href="logout.php" class="menu-item" style="color: #e57373; margin-top: 50px; border: 1px solid #30363d;">Đăng xuất </a>
    </div>

    <div class="main-content">
        <div class="left-col">
            <?php if ($msg): ?><div class="alert <?php echo $msg_type; ?>"><?php echo $msg; ?></div><?php endif; ?>
            <div class="card">
                <div class="profile-header">
                    <!-- Check nếu có avatar thì in ra thẻ img, không thì in chữ cái -->
                    <?php if (!empty($user_info['avatar']) && file_exists('uploads/' . $user_info['avatar'])): ?>
                        <img src="uploads/<?php echo $user_info['avatar']; ?>" class="avatar" alt="Avatar">
                    <?php else: ?>
                        <div class="avatar"><?php echo mb_substr(htmlspecialchars($user_info['full_name']), 0, 1, "UTF-8"); ?></div>
                    <?php endif; ?>
                    
                    <div class="profile-title">
                        <h2><?php echo htmlspecialchars($user_info['full_name']); ?></h2>
                        <p style="color:#8b949e; font-size:14px;"><?php echo htmlspecialchars($user_info['email']); ?></p>
                    </div>
                </div>
                
                <!-- Nhớ thêm enctype để form truyền được file -->
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Đổi ảnh đại diện:</label>
                        <input type="file" name="avatar" accept="image/png, image/jpeg, image/jpg" style="padding: 6px;">
                    </div>
                    <div class="form-group">
                        <label>Số điện thoại liên hệ:</label>
                        <input type="text" name="phone" value="<?php echo $phone_display; ?>" placeholder="Nhập SĐT...">
                    </div>
                    <button type="submit" name="btn_update_profile" class="btn-submit">Cập Nhật Thông Tin</button>
                </form>
            </div>

            <div class="card">
                <h3>Đổi Mật Khẩu</h3>
                <form method="POST">
                    <div class="form-group"><label>Mật khẩu cũ:</label><input type="password" name="old_password" required></div>
                    <div class="form-group"><label>Mật khẩu mới (Tối thiểu 8 ký tự):</label><input type="password" name="new_password" required minlength="8"></div>
                    <div class="form-group"><label>Xác nhận mật khẩu mới:</label><input type="password" name="confirm_password" required minlength="8"></div>
                    <button type="submit" name="btn_change_pass" class="btn-submit" style="background-color: #2f80ed; color: white;">Xác Nhận Đổi</button>
                </form>
            </div>
        </div>

        <div class="right-col">
            <div class="card" style="min-height: 90%;">
                <h3>Lịch Họp Tôi Đã Đặt</h3>
                <?php if (count($my_meetings) > 0): ?>
                    <table class="meeting-table">
                        <thead><tr><th>Tên cuộc họp</th><th>Phòng</th><th>Thời gian</th><th>Trạng thái</th></tr></thead>
                        <tbody>
                            <?php foreach ($my_meetings as $m): 
                                $start_time = strtotime($m['start_time']); $end_time = strtotime($m['end_time']); $now = time();
                                $status_class = ($end_time < $now) ? 'status-past' : 'status-upcoming';
                                $status_text = ($end_time < $now) ? 'Đã kết thúc' : 'Sắp diễn ra';
                            ?>
                                <tr>
                                    <td style="font-weight: bold; color: #d4f285;"><?php echo htmlspecialchars($m['title']); ?></td>
                                    <td><?php echo htmlspecialchars($m['room_name']); ?></td>
                                    <td><?php echo date('d/m/Y', $start_time); ?><br><span style="color: #8b949e; font-size: 12px;"><?php echo date('H:i', $start_time) . ' - ' . date('H:i', $end_time); ?></span></td>
                                    <td><span class="status-tag <?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="color: #8b949e; text-align: center; margin-top: 50px; font-style: italic;">Bạn chưa tạo lịch họp nào cả.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>