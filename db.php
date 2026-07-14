<?php
// db.php
date_default_timezone_set('Asia/Ho_Chi_Minh');

$host = 'localhost';
$dbname = 'xyz_meetings';
$username = 'root';
$password = '';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Set múi giờ cho MySQL
    $conn->exec("SET time_zone = '+07:00'");
} catch(PDOException $e) {
    echo "Lỗi kết nối: " . $e->getMessage();
}
// db.php
$host = 'localhost';
$dbname = 'xyz_meetings'; 
$username = 'root'; 
$password = '';

try {
    // Thiết lập kết nối
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    
    // Set chế độ báo lỗi để dễ debug nếu tạch
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // echo "Kết nối thành công rồi nè!"; // Mở comment ra để test thử nha
} catch(PDOException $e) {
    echo "Toang kết nối: " . $e->getMessage();
}
?>