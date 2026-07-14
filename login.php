<?php
// login.php
session_start();
include 'db.php'; // Gọi file kết nối database

$error_msg = '';

// Nếu user đã đăng nhập rồi thì đá thẳng vào trang lịch luôn, không cho ở lại trang login
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

// Xử lý khi bạn bấm nút "Đăng Nhập"
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Tìm user có email khớp trong database
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Chú ý: Vì ở Bước 1 tụi mình tạo data mẫu pass là '123456' (dạng text thường)
    // nên ở đây so sánh bằng (===) luôn. Làm thực tế sau này bạn nhớ dùng mã hóa mật khẩu nha.
    if ($user && $password === $user['password']) {
        // Đăng nhập thành công -> Lưu session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role']; // Để mốt làm phân quyền Admin/Manager nếu cần
        
        // Chuyển hướng vô trang lịch
        header("Location: index.php");
        exit();
    } else {
        $error_msg = "Tài khoản hoặc mật khẩu không đúng!";
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Nhập - XYZ Meetings</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: Arial, sans-serif; }
        body { 
            background-color: #0f1319; /* Màu nền dark giống bản thiết kế */
            color: #ffffff; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            height: 100vh; 
        }
        .login-container {
            width: 100%;
            max-width: 400px;
            text-align: center;
        }
        .login-container h2 {
            margin-bottom: 40px;
            font-size: 24px;
            font-weight: normal;
            letter-spacing: 1px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            background-color: transparent;
            border: 1px solid #30363d;
            border-radius: 6px;
            color: white;
            text-align: center;
            font-size: 14px;
            outline: none;
            transition: 0.3s;
        }
        .form-group input:focus {
            border-color: #d4f285;
        }
        .btn-login {
            width: 100%;
            padding: 12px;
            background-color: #d4f285;
            color: #0f1319;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
            margin-top: 10px;
        }
        .btn-login:hover {
            background-color: #b5d65f;
        }
        .error-text {
            color: #e57373;
            margin-bottom: 20px;
            font-size: 14px;
        }
    </style>
</head>
<body>

    <div class="login-container">
        <h2>ĐĂNG NHẬP HỆ THỐNG</h2>
        
        <?php if($error_msg): ?>
            <div class="error-text"><?php echo $error_msg; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <input type="email" name="email" placeholder="Email" required>
            </div>
            <div class="form-group">
                <input type="password" name="password" placeholder="Mật Khẩu" required>
            </div>
            <button type="submit" class="btn-login">Đăng Nhập</button>
        </form>
    </div>

</body>
</html>