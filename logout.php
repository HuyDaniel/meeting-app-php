<?php
session_start();
session_unset();    // Xóa hết các biến session đang lưu
session_destroy();  // Hủy luôn phiên session hiện tại
header("Location: login.php"); // Đá thẳng về trang đăng nhập
exit();
?>