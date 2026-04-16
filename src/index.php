<?php
session_start();
require_once 'config/db.php';

// ดึงข้อมูลสาขาวิชา (Group) ทั้งหมดที่มีในระบบมาทำ Dropdown
$majorQuery = "SELECT DISTINCT `group` AS major FROM users WHERE `group` IS NOT NULL AND `group` != '' ORDER BY `group`";
$majorStmt = $conn->query($majorQuery);
$majors_list = $majorStmt->fetchAll(PDO::FETCH_ASSOC);

// ตรวจสอบว่ามีการกดปุ่มค้นหาหรือไม่
$is_submitted = isset($_GET['major']);
$selected_major = $_GET['major'] ?? '';

$schedules = [];
if ($is_submitted) {
    // สร้าง Query แบบยืดหยุ่น 
    // ถ้าไม่เลือกสาขา (ปล่อยว่าง) จะดึงตารางทั้งหมดออกมา
    $sql = "SELECT s.id, s.schedule_name, u.name AS creator_name, u.group AS major 
            FROM schedules s 
            LEFT JOIN users u ON s.user_id = u.id 
            WHERE 1=1";
    
    $params = [];

    // ถ้ามีการเลือกสาขา ให้กรองตามสาขานั้น
    if (!empty($selected_major)) {
        $sql .= " AND u.group = :major";
        $params['major'] = $selected_major;
    }

    $sql .= " ORDER BY s.id DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>หน้าแรก - Schedule Class System</title>
    <link rel="icon" type="image/png" href="img/FTE.png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --glass-white: rgba(255, 255, 255, 0.08);
            --glass-border: rgba(255, 255, 255, 0.2);
            --accent-blue: #3b82f6;
        }

        body {
            background: linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%);
            color: #f8f9fa;
            min-height: 100vh;
            font-family: 'Prompt', sans-serif;
            display: flex;
            flex-direction: column;
            margin: 0;
            padding: 0;
        }

        /* --- Navbar --- */
        .top-navbar {
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            position: absolute;
            top: 0;
            left: 0;
            z-index: 10;
        }

        .brand-logo {
            font-size: 1.2rem;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .brand-logo img { width: 30px; height: auto; }
        .auth-buttons { display: flex; gap: 15px; }

        .btn-outline-light-custom {
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.5);
            background: transparent;
            border-radius: 50px;
            padding: 8px 20px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        .btn-outline-light-custom:hover { background: rgba(255, 255, 255, 0.1); border-color: #fff; color: #fff; }

        .btn-primary-nav {
            background: #0d6efd;
            color: #fff;
            border: none;
            border-radius: 50px;
            padding: 8px 20px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            text-decoration: none;
            box-shadow: 0 4px 10px rgba(13, 110, 253, 0.3);
        }
        .btn-primary-nav:hover { background: #0b5ed7; color: #fff; transform: translateY(-2px); box-shadow: 0 6px 15px rgba(13, 110, 253, 0.4); }

        /* --- Main Content --- */
        .main-content {
            flex-grow: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            text-align: center;
            padding: 20px;
            margin-top: 80px;
        }

        h1 {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 15px;
            background: -webkit-linear-gradient(#fff, #a1c4fd);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .subtitle {
            font-size: 1.1rem;
            color: rgba(255, 255, 255, 0.7);
            max-width: 600px;
            margin-bottom: 40px;
            line-height: 1.6;
        }

        /* --- Search Components --- */
        .search-wrapper { 
            max-width: 600px; /* ปรับขนาดให้พอดีกับช่อง Dropdown */
            margin: 0 auto; 
            position: relative; 
            width: 100%;
        }

        .search-box {
            background: var(--glass-white) !important;
            border: 1.5px solid var(--glass-border) !important;
            color: white !important;
            height: 55px;
            transition: 0.3s;
        }
        
        .search-box:focus {
            background: rgba(255, 255, 255, 0.15) !important;
            border-color: var(--accent-blue) !important;
            box-shadow: 0 0 10px rgba(59, 130, 246, 0.2);
        }

        /* ปรับแต่ง Dropdown */
        .search-select {
            padding: 0 20px !important; 
            cursor: pointer;
            text-align: center;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: none !important;
        }

        .search-select option { 
            background-color: #0f2027; 
            color: white; 
            text-align: center; 
        }

        /* --- Table Glassmorphism --- */
        .glass-card {
            background: rgba(15, 32, 39, 0.4);
            border-radius: 20px;
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.5);
            overflow: hidden;
            width: 100%;
            max-width: 1000px;
        }

        .table-glass { color: #e0e0e0; margin-bottom: 0; background: transparent; }
        .table-glass th {
            background: rgba(59, 130, 246, 0.1); color: #3b82f6; font-weight: 500;
            border-bottom: 1px solid var(--glass-border); padding: 20px 15px; font-size: 0.9rem;
        }
        .table-glass td {
            padding: 20px 15px; border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            vertical-align: middle; background: transparent !important;
        }
        .major-badge { background: rgba(59, 130, 246, 0.15); color: #a1c4fd; border: 1px solid rgba(161, 196, 253, 0.3); }

        /* --- Footer --- */
        .footer { padding: 20px; text-align: center; font-size: 0.85rem; color: rgba(255, 255, 255, 0.4); }

        /* Mobile Responsive */
        @media (max-width: 576px) {
            .search-form-group { flex-direction: column; }
            .top-navbar { padding: 15px 20px; }
            .brand-logo span { display: none; }
            h1 { font-size: 2.2rem; }
            .search-wrapper { padding: 0 15px; }
        }
    </style>
</head>

<body>

    <div class="top-navbar">
        <a href="index.php" class="brand-logo">
            <img src="img/FTE2.png" alt="Logo" width="40" height="40">
            <span>Schedule Class</span>
        </a>
        <div class="auth-buttons">
            <a href="login.php" class="btn-outline-light-custom"><i class="fas fa-sign-in-alt me-1"></i> เข้าสู่ระบบ</a>
            <a href="register.php" class="btn-primary-nav"><i class="fas fa-user-plus me-1"></i> สมัครสมาชิก</a>
        </div>
    </div>

    <div class="main-content container">
        
        <?php if (!$is_submitted): ?>
            <img src="img/FTE2.png" alt="Logo" width="180" height="150" class="mb-3">
            <h1>ระบบจัดการตารางเรียน</h1>
            <p class="subtitle mx-auto">
                ยินดีต้อนรับสู่ระบบจัดตารางเรียน ภาควิชาคอมพิวเตอร์ศึกษา <br>
                สำหรับนักศึกษาและบุคคลทั่วไป สามารถค้นหาและดูตารางเรียนได้ทันทีโดยไม่ต้องเข้าสู่ระบบ
            </p>
        <?php else: ?>
            <h2 class="fw-bold mb-4" style="color: #a1c4fd;">
                <?= !empty($selected_major) ? "ผลการค้นหาตารางเรียน: สาขา " . htmlspecialchars($selected_major) : "ตารางเรียนทั้งหมด" ?>
            </h2>
        <?php endif; ?>

        <div class="search-wrapper mb-5 w-100">
            <form action="index.php" method="GET" class="d-flex gap-2 search-form-group">
                
                <div class="position-relative flex-grow-1">
                    <select name="major" class="form-select search-box search-select rounded-pill">
                        <option value="">-- แสดงตารางเรียนทุกสาขาวิชา --</option>
                        <?php foreach ($majors_list as $major): ?>
                            <option value="<?= htmlspecialchars($major['major']) ?>" <?= $selected_major === $major['major'] ? 'selected' : '' ?>>
                                สาขาวิชา <?= htmlspecialchars($major['major']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm">
                    ค้นหา
                </button>
                
                <?php if ($is_submitted): ?>
                    <a href="index.php" class="btn btn-outline-light rounded-pill px-4 shadow-sm d-flex align-items-center">
                        <i class="fas fa-times me-1"></i> ล้าง
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <?php if ($is_submitted): ?>
            <div class="glass-card animate__animated animate__fadeIn">
                <div class="table-responsive">
                    <table class="table table-glass text-center align-middle">
                        <thead>
                            <tr>
                                <th class="text-start ps-5">ข้อมูลตารางเรียน</th>
                                <th>สาขาวิชา</th>
                                <th class="pe-5 text-end">ดำเนินการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($schedules) > 0): ?>
                                <?php foreach ($schedules as $row): ?>
                                    <tr>
                                        <td class="text-start ps-5">
                                            <div class="fw-bold text-white fs-5"><?= htmlspecialchars($row['schedule_name']) ?></div>
                                            <small class="text-white-50">โดย <?= htmlspecialchars($row['creator_name']) ?></small>
                                        </td>
                                        <td>
                                            <span class="badge major-badge rounded-pill px-3 py-2 fw-light">
                                                <?= htmlspecialchars($row['major'] ?: 'ไม่ระบุสาขา') ?>
                                            </span>
                                        </td>
                                        <td class="pe-5 text-end">
                                            <a href="guest_view.php?id=<?= $row['id'] ?>" class="btn btn-success btn-sm px-4 rounded-pill shadow-sm">
                                                <i class="fas fa-eye me-1"></i> ดูตาราง
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="py-5 text-center">
                                        <div class="py-4">
                                            <i class="fas fa-search-minus fa-3x mb-3 text-white-50 opacity-25"></i>
                                            <h5 class="fw-light text-white-50">ไม่พบตารางเรียนในสาขานี้</h5>
                                            <p class="small text-white-50">โปรดลองเลือกสาขาวิชาอื่น</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <div class="footer mt-auto">
        พัฒนาระบบโดย: นายกษิด์เดช ปราบพาล & นายบรรณวัชร บำรุง | อาจารย์ที่ปรึกษา: ดร. จิรพันธ์ุ ศรีสมพันธุ์
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>