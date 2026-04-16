<?php
session_start();

// ลบตัวแปร session ทั้งหมด
session_unset();

// ทำลาย session
session_destroy();

// เปลี่ยนเส้นทางกลับไปที่หน้าล็อกอิน
header("Location: login.php");
exit;
?>