<?php
$envFilePath = dirname(__DIR__) . '/.env';

if (file_exists($envFilePath)) {
    $env = parse_ini_file($envFilePath);
    $servername = $env['DB_SERVER'];
    $username   = $env['DB_USER'];
    $password   = $env['DB_PASS'];
    $dbname     = $env['DB_NAME'];
} else {
    die("System Error: ไม่พบไฟล์ .env หรือตั้งค่า Path ไม่ถูกต้อง");
}

try {
  $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  // echo "Connected successfully";
} catch(PDOException $e) {
    // บันทึก Error เชิงลึกลงไฟล์ error_log ของ Server (ผู้ใช้จะไม่เห็น)
    error_log("Database Connection Error: " . $e->getMessage());
    
    // แสดงข้อความทั่วไปให้ผู้ใช้งานเห็น
    die("System Error: ไม่สามารถเชื่อมต่อฐานข้อมูลได้ กรุณาติดต่อผู้ดูแลระบบ");
}
if (!function_exists('writeLog')) {
    function writeLog($conn, $userId, $userName, $action, $details = null) {
        // 1. ดักจับ IP Address จริงของผู้ใช้งาน
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }

        // 2. ดักจับข้อมูลอุปกรณ์ (Browser/OS), URL ที่เข้าถึง และประเภท Request (GET/POST)
        $agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';

        // 3. บันทึกข้อมูลลงตาราง system_logs
        try {
            $sql = "INSERT INTO system_logs (user_id, user_name, action, details, ip_address, user_agent, request_uri, request_method) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $userId, 
                $userName, 
                $action, 
                $details, 
                $ip, 
                $agent, 
                $uri, 
                $method
            ]);
        } catch (PDOException $e) {
            // ปล่อยผ่าน (Silently fail) หากเกิด Error ในการบันทึก Log 
            // เพื่อไม่ให้ระบบหลัก (เช่น การ Login หรือแก้ไขข้อมูล) พังไปด้วย
        }
    }
}
?>