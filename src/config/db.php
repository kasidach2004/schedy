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

    error_log("Database Connection Error: " . $e->getMessage());
    
    die("System Error: ไม่สามารถเชื่อมต่อฐานข้อมูลได้ กรุณาติดต่อผู้ดูแลระบบ");
}
if (!function_exists('writeLog')) {
    function writeLog($conn, $userId, $userName, $action, $details = null) {

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }


        $agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';

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
        }
    }
}
?>