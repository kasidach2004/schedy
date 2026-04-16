<?php
// home.php
session_start();

// ตรวจสอบว่าผู้ใช้ล็อกอินหรือไม่
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'auth_check.php';
require_once 'config/db.php';

// ดึงข้อมูลผู้ใช้
$sql_user = "SELECT name, email, `group`, role FROM users WHERE id = ?";
$stmt_user = $conn->prepare($sql_user);
$stmt_user->execute([$_SESSION['user_id']]);
$user_info = $stmt_user->fetch(PDO::FETCH_ASSOC);

// ตรวจสอบถ้าหาข้อมูลผู้ใช้ไม่เจอ ให้ส่งกลับไป Login
if (!$user_info) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// 🟢 บันทึก Log: ผู้ใช้เปิดหน้า Dashboard หลัก
if (function_exists('writeLog')) {
    writeLog(
        $conn, 
        $_SESSION['user_id'], 
        $user_info['name'], 
        'View Dashboard', 
        'เข้าดูหน้าหลัก (home.php) สิทธิ์การใช้งาน: ' . strtoupper($user_info['role'])
    );
}

// ดึงสถิติตารางสอนของผู้ใช้
$sql_count = "SELECT COUNT(*) as cnt FROM schedules WHERE user_id = ?";
$stmt_count = $conn->prepare($sql_count);
$stmt_count->execute([$_SESSION['user_id']]);
$count_row = $stmt_count->fetch(PDO::FETCH_ASSOC);
$schedule_count = ($count_row) ? ($count_row['cnt'] ?? 0) : 0;

// --- [เพิ่ม] ส่วนเช็คยอดคนรออนุมัติ (เฉพาะ Admin) ---
$pending_count = 0;
if (($user_info['role'] ?? '') === 'admin') {
    $stmt_pending = $conn->query("SELECT COUNT(*) FROM users WHERE status = 'pending'");
    $pending_count = $stmt_pending->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Home - Schedule Class</title>
    <link rel="icon" type="image/png" href="img/FTE.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
    body {
        background: linear-gradient(to right, #0f2027, #203a43, #2c5364);
        color: #f8f9fa;
    }

    /* สไตล์เดิมสำหรับ Sidebar และเพิ่มการรองรับเมนู Mobile (Offcanvas) */
    .sidebar {
        background: #212529;
        height: auto;
        padding-top: 5px;
        width: 280px;
        flex-shrink: 0;
    }

    .sidebar .nav-link,
    .offcanvas-body .nav-link {
        color: #f8f9fa;
        padding: 15px;
        height: 50px;
        margin-bottom: 5px;
        border-radius: 8px;
        transition: background-color 0.3s;
    }

    .sidebar .nav-link:hover,
    .sidebar .nav-link.active,
    .offcanvas-body .nav-link:hover,
    .offcanvas-body .nav-link.active {
        background-color: #0d6efd;
        color: white;
    }

    /* สไตล์พิเศษสำหรับปุ่ม Logout ใน Mobile เมื่อ Hover */
    .offcanvas-body .nav-link.text-danger:hover {
        background-color: #dc3545 !important;
        color: white !important;
    }

    /* ปรับสีพื้นหลัง Offcanvas ให้เข้ากับ Sidebar Desktop */
    .offcanvas.bg-dark {
        background-color: #212529 !important;
    }

    .profile-card {
        background: rgba(255, 255, 255, 0.1);
        padding: 5px;
        border-radius: 20px;
        margin-bottom: 10px;
        text-align: center;
    }

    .content {
        padding: 5px;
        flex-grow: 1;
        overflow-y: auto;
    }

    .main-card {
        background: rgba(255, 255, 255, 0.1);
        border-radius: 10px;
        padding: 10px;
    }

    /* สไตล์พิเศษสำหรับ Admin/Quick Guide */
    .admin-action-card {
        background: rgba(253, 126, 20, 0.15);
        border: 1px solid #ffc107;
        text-align: center;
        position: relative;
    }

    .admin-action-card .btn-report {
        background-color: #ffc107;
        color: #212529;
        font-weight: bold;
        transition: background-color 0.3s;
        position: relative;
    }

    .admin-action-card .btn-report:hover {
        background-color: #e0a800;
    }

    .quick-guide-card {
        background: rgba(13, 110, 253, 0.1);
        border-left: 5px solid #0d6efd;
    }

    .step-item {
        padding: 10px 0;
        border-bottom: 1px dashed rgba(255, 255, 255, 0.1);
    }

    .step-item:last-child {
        border-bottom: none;
    }

    /* Animation สำหรับการแจ้งเตือน */
    @keyframes pulse-red {
        0% {
            box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7);
        }

        70% {
            box-shadow: 0 0 0 10px rgba(220, 53, 69, 0);
        }

        100% {
            box-shadow: 0 0 0 0 rgba(220, 53, 69, 0);
        }
    }

    .pulse-badge {
        animation: pulse-red 2s infinite;
    }


    .step-item {
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 20px;
    }

    .video-frame {
        width: 80%;
        /* ปรับขนาดตามต้องการ เช่น 70-90% */
        aspect-ratio: 16 / 9;
        /* ทำให้ได้อัตราส่วนเหมือน YouTube */
        border-radius: 16px;
        /* ทำให้มุมโค้งมน */
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        /* ใส่เงาเบา ๆ ให้ดูเด่น */
    }


    /* Responsive */
    @media (max-width: 767.98px) {
        .sidebar {
            position: fixed;
            z-index: 1050;
            height: auto;
            width: 100%;
            padding: 10px;
            display: none !important;
        }

        .content {
            padding: 20px;
            margin-left: 0 !important;
        }

        .profile-card h5 {
            font-size: 1.25rem;
        }

        .d-flex {
            flex-direction: column;
        }

        .col-md-4 {
            width: 100%;
            margin-bottom: 15px !important;
        }

        .menu-toggle {
            display: block !important;
            margin-bottom: 15px;
        }
    }
    </style>
</head>

<body>
    <div class="d-flex" style="height: 100vh;">
        <button class="btn btn-primary d-block d-lg-none menu-toggle" type="button" data-bs-toggle="offcanvas"
            data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas"
            style="position: fixed; top: 10px; left: 10px; z-index: 1060;">
            <i class="fas fa-bars"></i> เมนู
        </button>

        <div class="sidebar d-none d-lg-flex flex-column p-3 text-white">
            <a href="#" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none">
                <i class="fas fa-university me-2"></i>
                <span class="fs-4">schedules class</span>
            </a>
            <hr>
            <div class="profile-card">
                <i class="fas fa-user-circle fa-3x mb-2"></i>
                <h5><?= htmlspecialchars($user_info['name'] ?? '') ?></h5>
                <small><?= htmlspecialchars($user_info['email'] ?? '') ?></small>
                <br>
                <?php if (($user_info['role'] ?? '') === 'admin'): ?>
                <span class="badge bg-danger mt-2">
                    <i class="fas fa-crown me-1"></i> ADMIN
                </span>
                <?php else: ?>
                <span class="badge bg-primary mt-2"><?= htmlspecialchars($user_info['group'] ?? '') ?></span>
                <?php endif; ?>
            </div>
            <ul class="nav nav-pills flex-column mb-auto">
                <li class="nav-item"><a href="home.php" class="nav-link active"><i class="fas fa-home me-2"></i> หน้าหลัก</a>
                </li>
                <li class="nav-item"><a href="schedules.php" class="nav-link"><i
                            class="fas fa-chalkboard me-2"></i> ตารางสอน</a></li>
                <div class="d-flex align-items-center px-1 mt-1 mb-1" style="line-height: 1.2;">
                    <div class="flex-grow-1 border-top border-light" style="opacity: 0.4;"></div>
                    <span class="text-white px-2"
                        style="font-size: 0.7rem; font-weight: bold; letter-spacing: 0.5px; text-transform: uppercase;">
                        เพิ่มข้อมูลพื้นฐาน</span>
                    <div class="flex-grow-1 border-top border-light" style="opacity: 0.4;"></div>
                </div>
                <li><a href="teachers.php" class="nav-link"><i class="fas fa-users me-2"></i> รายชื่ออาจารย์</a></li>
                <li><a href="courses.php" class="nav-link"><i class="fas fa-book me-2"></i> วิชา</a></li>
                <li><a href="rooms.php" class="nav-link"><i class="fas fa-school me-2"></i> หมายเลขห้องเรียน</a></li>
                <li><a href="classrooms.php" class="nav-link"><i class="fas fa-building me-2"></i> ห้องเรียน</a></li>
                <li><a href="curriculums.php" class="nav-link"><i class="fas fa-graduation-cap me-2"></i> หลักสูตร</a>
                </li>
                <li><a href="logout.php" class="nav-link"><i
                            class="fas fa-sign-out-alt me-2 text-danger"></i>ออกจากระบบ</a></li>
            </ul>
        </div>

        <div class="offcanvas offcanvas-start bg-dark text-white" tabindex="-1" id="sidebarOffcanvas"
            aria-labelledby="sidebarOffcanvasLabel">
            <div class="offcanvas-header border-bottom border-secondary mb-2 pb-3">
                <h5 class="offcanvas-title" id="sidebarOffcanvasLabel"><i class="fas fa-university me-2"></i> schedules
                    class</h5>
                <button type="button" class="btn-close text-reset btn-close-white" data-bs-dismiss="offcanvas"
                    aria-label="Close"></button>
            </div>
            <div class="offcanvas-body d-flex flex-column px-3">
                <div class="profile-card mb-3">
                    <i class="fas fa-user-circle fa-3x mb-2"></i>
                    <h5><?= htmlspecialchars($user_info['name'] ?? '') ?></h5>
                    <small><?= htmlspecialchars($user_info['email'] ?? '') ?></small>
                    <br>
                    <?php if (($user_info['role'] ?? '') === 'admin'): ?>
                    <span class="badge bg-danger mt-2"><i class="fas fa-crown me-1"></i> ADMIN</span>
                    <?php else: ?>
                    <span class="badge bg-primary mt-2"><?= htmlspecialchars($user_info['group'] ?? '') ?></span>
                    <?php endif; ?>
                </div>
                <ul class="nav nav-pills flex-column mb-auto">
                    <li class="nav-item"><a href="home.php" class="nav-link"><i class="fas fa-home me-2"></i>
                            หน้าหลัก</a></li>
                    <li class="nav-item"><a href="schedules.php" class="nav-link active"><i
                                class="fas fa-chalkboard me-2"></i> ตารางสอน</a></li>
                    <div class="d-flex align-items-center px-1 mt-1 mb-1" style="line-height: 1.2;">
                        <div class="flex-grow-1 border-top border-light" style="opacity: 0.4;"></div>
                        <span class="text-white px-2"
                            style="font-size: 0.7rem; font-weight: bold; letter-spacing: 0.5px; text-transform: uppercase;">
                            เพิ่มข้อมูลพื้นฐาน</span>
                        <div class="flex-grow-1 border-top border-light" style="opacity: 0.4;"></div>
                    </div>
                    <li><a href="teachers.php" class="nav-link"><i class="fas fa-users me-2"></i> รายชื่ออาจารย์</a>
                    </li>
                    <li><a href="courses.php" class="nav-link"><i class="fas fa-book me-2"></i> วิชา</a></li>
                    <li><a href="rooms.php" class="nav-link"><i class="fas fa-school me-2"></i> หมายเลขห้องเรียน</a>
                    </li>
                    <li><a href="classrooms.php" class="nav-link"><i class="fas fa-building me-2"></i> ห้องเรียน</a>
                    </li>
                    <li><a href="curriculums.php" class="nav-link"><i class="fas fa-graduation-cap me-2"></i>
                            หลักสูตร</a></li>
                    <li><a href="logout.php" class="nav-link"><i
                                class="fas fa-sign-out-alt me-2 text-danger"></i>ออกจากระบบ</a></li>
                </ul>
            </div>
        </div>

        <div class="content flex-grow-1">
            <div class="d-lg-none" style="height: 50px;"></div>

            <div class="main-card">

                <?php if (($user_info['role'] ?? '') === 'admin' && $pending_count > 0): ?>
                <div class="alert alert-warning d-flex align-items-center shadow-sm mb-4 border-warning" role="alert"
                    style="background: rgba(255, 193, 7, 0.2); color: #ffca2c;">
                    <div class="me-3 position-relative">
                        <i class="fas fa-bell fa-2x pulse-badge text-warning"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                            <?= $pending_count ?>
                        </span>
                    </div>
                    <div class="flex-grow-1">
                        <h5 class="alert-heading mb-1 text-white fw-bold">มีคำขอสมัครสมาชิกใหม่!</h5>
                        <p class="mb-0 text-white-50">มีผู้ใช้งาน <strong><?= $pending_count ?></strong> คน
                            รอการอนุมัติสิทธิ์เข้าใช้งานระบบ กรุณาตรวจสอบ</p>
                    </div>
                    <a href="admin_users_report.php" class="btn btn-warning text-dark fw-bold ms-3 px-4">
                        <i class="fas fa-check-circle me-1"></i> ไปอนุมัติ
                    </a>
                </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
                    <h1>หน้าหลัก</h1>
                    <a href="schedules.php" class="btn btn-light text-dark mt-2 mt-sm-0"><i
                            class="fas fa-chalkboard me-2"></i> ไปที่ตารางสอน</a>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="p-3 bg-white bg-opacity-10 rounded h-100">
                            <h5>ตารางสอนของคุณ</h5>
                            <p class="display-6 mb-0"><?= intval($schedule_count) ?></p>
                            <small>จำนวนตารางสอนที่คุณสร้าง</small>
                        </div>
                    </div>

                    <?php if (($user_info['role'] ?? '') === 'admin'): ?>
                    <div class="col-md-4 mb-3">
                        <div class="p-3 rounded admin-action-card h-100 d-flex flex-column justify-content-between">
                            <div>
                                <h5><i class="fas fa-chart-line me-2"></i> ข้อมูลผู้ดูแลระบบ</h5>
                                <p class="text-white-50">ตรวจสอบและจัดการข้อมูลผู้ใช้ทั้งหมด</p>
                            </div>
                            <a href="admin_users_report.php" class="btn btn-report w-100 position-relative">
                                <i class="fas fa-file-alt me-2"></i> ดูรายงาน User

                                <?php if ($pending_count > 0): ?>
                                <span
                                    class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-light">
                                    <?= $pending_count ?>
                                    <span class="visually-hidden">unread messages</span>
                                </span>
                                <?php endif; ?>
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>

                </div>

                <hr class="my-4 border-secondary">

                <div class="row">
                    <div class="col-12">
                        <div class="quick-guide-card p-4 rounded">
                            <h3 class="text-white"><i class="fas fa-rocket me-2"></i> คู่มือเริ่มต้นใช้งาน (Quick Start)
                            </h3>
                            <p class="text-white-50">
                                ระบบจัดการตารางสอนออกแบบมาเพื่อให้การจัดการตารางสอนของคุณเป็นเรื่องง่าย
                                โปรดทำตามขั้นตอนเหล่านี้เพื่อเริ่มต้นใช้งาน</p>

                            <div class="step-item">
                                <h5 class="text-white">วิดีโอแนะนำการใช้งาน</h5>
                            </div>



                            <div class="step-item">
                                <iframe class="video-frame"
                                    src="https://www.youtube.com/embed/Gy-MZjiFv2M?list=RDH3DVaW7T_l4"
                                    title="ดอกกระเจียวบาน - ก้อง ห้วยไร่ |Official MV|"
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                    referrerpolicy="strict-origin-when-cross-origin" allowfullscreen>

                                </iframe>
                            </div>


                            <div class="step-item border-0">
                                <h5 class="text-white">คู่มือการใช้งานแบบละเอียด</h5>
                                <ul class="list-unstyled mb-0 mt-2">
                                    <li>
                                        <a href="readme.html" class="btn btn-sm btn-outline-warning" target="_blank">
                                            <i class="fas fa-book-reader me-2"></i> อ่านคู่มือการใช้งาน
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>