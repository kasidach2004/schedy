<?php
// admin_users_report.php
session_start();

// 1. ตรวจสอบ Login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/db.php';

// 2. ดึงข้อมูลผู้ใช้ปัจจุบัน
$sql_user = "SELECT name, email, `group`, role FROM users WHERE id = ?";
$stmt_user = $conn->prepare($sql_user);
$stmt_user->execute([$_SESSION['user_id']]);
$user_info = $stmt_user->fetch(PDO::FETCH_ASSOC);

// 3. ตรวจสอบสิทธิ์ Admin
if ($user_info['role'] !== 'admin') {
    echo '<div class="alert alert-danger m-4">
            <h4>❌ ไม่มีสิทธิ์เข้าถึง</h4>
            <p>เฉพาะ Admin เท่านั้นที่สามารถเข้าถึงหน้านี้ได้</p>
            <a href="home.php" class="btn btn-primary">กลับไปหน้าหลัก</a>
          </div>';
    exit;
}

// === ส่วนจัดการ Action (ลบ) ===

// === ส่วนจัดการ Action (ลบ) ===

// ลบ User
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $delete_id = $_GET['id'];
    
    if ($delete_id != $_SESSION['user_id']) { // ป้องกันไม่ให้ลบตัวเอง
        try {
            // ดึงชื่อคนที่จะโดนลบมาก่อน เพื่อเก็บลง Log ให้รู้ว่าลบใคร
            $checkStmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
            $checkStmt->execute([$delete_id]);
            $deleted_user = $checkStmt->fetchColumn();

            if ($deleted_user) {
                // 🟢 เริ่ม Transaction: เพื่อป้องกันข้อมูลเสียหายเวลาลบพลาด
                $conn->beginTransaction();

                // 1. ลบตารางที่ไม่มี FK ผูกแบบ Cascade ไว้ (ต้องลบก่อนเพื่อไม่ให้เกิด Error)
                $conn->prepare("DELETE FROM curriculum_course_teachers WHERE curriculum_id IN (SELECT id FROM curriculums WHERE user_id = ?)")->execute([$delete_id]);
                $conn->prepare("DELETE FROM curriculum_courses WHERE curriculum_id IN (SELECT id FROM curriculums WHERE user_id = ?)")->execute([$delete_id]);
                $conn->prepare("DELETE FROM schedule_items WHERE schedule_id IN (SELECT id FROM schedules WHERE user_id = ?)")->execute([$delete_id]);
                
                // 2. ลบข้อมูลหลักที่ User คนนี้สร้างขึ้น
                $conn->prepare("DELETE FROM curriculums WHERE user_id = ?")->execute([$delete_id]);
                $conn->prepare("DELETE FROM schedules WHERE user_id = ?")->execute([$delete_id]);
                $conn->prepare("DELETE FROM courses WHERE user_id = ?")->execute([$delete_id]);
                $conn->prepare("DELETE FROM teachers WHERE user_id = ?")->execute([$delete_id]);
                $conn->prepare("DELETE FROM classrooms WHERE user_id = ?")->execute([$delete_id]);
                $conn->prepare("DELETE FROM rooms WHERE user_id = ?")->execute([$delete_id]);
                
                // (ทางเลือก) ลบประวัติ Log ของ User คนนี้ด้วย
                $conn->prepare("DELETE FROM system_logs WHERE user_id = ?")->execute([$delete_id]);

                // 3. ลบข้อมูลผู้ใช้เป็นลำดับสุดท้าย
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$delete_id]);

                // บันทึก LOG ทันทีที่ลบสำเร็จ!
                $admin_name = $user_info['name']; // ดึงชื่อแอดมินที่กำลัง login อยู่
                writeLog($conn, $_SESSION['user_id'], $admin_name, 'Delete User', "ลบผู้ใช้งานและข้อมูลทั้งหมดของ: $deleted_user (ID: $delete_id)");

                // 🟢 ยืนยันการเปลี่ยนแปลงทั้งหมดลงฐานข้อมูล (Commit)
                $conn->commit();

                header("Location: admin_users_report.php?msg=deleted");
                exit;
            }
        } catch (PDOException $e) {
            // 🔴 ถ้ามี Error เกิดขึ้นระหว่างลบ ให้ยกเลิกการลบที่ผ่านมาทั้งหมด (Rollback)
            $conn->rollBack();
            $error_msg = "เกิดข้อผิดพลาดในการลบข้อมูล: " . $e->getMessage();
        }
    }
}
// ==========================================
// ==========================================

// ดึงข้อมูล user ทั้งหมด
$sql_users = "SELECT 
                u.id, u.name, u.email, u.created_at, u.`group`, u.password, u.role, u.status,
                COUNT(s.id) as schedule_count
              FROM users u
              LEFT JOIN schedules s ON u.id = s.user_id
              GROUP BY u.id
              ORDER BY u.created_at DESC";

$users = $conn->query($sql_users)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link rel="icon" type="image/png" href="img/FTE.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --sidebar-bg: #0f172a;
            --sidebar-hover: rgba(255, 255, 255, 0.08);
            --primary-gradient: linear-gradient(135deg, #6366f1 0%, #3b82f6 100%);
            --text-secondary: #94a3b8;
            --sidebar-width: 260px;
        }

        body { 
            background-color: #f1f5f9; 
            font-family: 'Kanit', sans-serif;
            overflow-x: hidden;
        }

        /* === Loading Overlay Style === */
        #loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.85);
            z-index: 9999;
            display: none;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            backdrop-filter: blur(5px);
        }

        /* === Modern Luxury Sidebar === */
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--sidebar-bg);
            color: #fff;
            position: fixed;
            transition: all 0.3s;
            z-index: 1050; /* เพิ่ม z-index ให้อยู่เหนือ content เวลาเปิดบนมือถือ */
            box-shadow: 10px 0 30px rgba(0,0,0,0.1);
            border-right: 1px solid rgba(255,255,255,0.05);
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            padding: 20px 20px;
            background: linear-gradient(to bottom, rgba(255,255,255,0.03), transparent);
            flex-shrink: 0;
        }

        .brand-text {
            font-weight: 600;
            font-size: 1.1rem;
            background: linear-gradient(90deg, #fff, #94a3b8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: 0.5px;
        }

        .profile-wrapper {
            margin: 0 15px 15px 15px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            text-align: center;
            flex-shrink: 0;
        }

        .profile-avatar {
            width: 50px;
            height: 50px;
            border: 2px solid rgba(255,255,255,0.1);
        }

        .admin-badge {
            background: rgba(239, 68, 68, 0.15);
            color: #f87171;
            font-size: 0.7rem;
            padding: 2px 10px;
            border-radius: 15px;
            letter-spacing: 1px;
            border: 1px solid rgba(239, 68, 68, 0.3);
            display: inline-block;
            margin-top: 5px;
        }

        .nav-scrollable {
            flex-grow: 1;
            overflow-y: auto;
            overflow-x: hidden;
        }
        
        .sidebar-heading {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-secondary);
            margin: 20px 10px 10px 20px;
            font-weight: 600;
            opacity: 0.8;
        }

        .nav-link {
            color: var(--text-secondary);
            padding: 10px 20px;
            margin: 4px 10px;
            border-radius: 10px;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            font-weight: 400;
            font-size: 0.9rem;
            position: relative;
        }

        .nav-link i {
            width: 24px;
            font-size: 1.1rem;
            margin-right: 10px;
            text-align: center;
            transition: transform 0.3s ease;
        }

        .nav-link:hover {
            color: #fff;
            background: var(--sidebar-hover);
            transform: translateX(3px);
        }
        
        .nav-link:hover i {
            color: #6366f1;
        }

        .nav-link.active {
            background: var(--primary-gradient);
            color: #fff;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
            font-weight: 500;
        }

        .sidebar-footer {
            padding: 15px;
            border-top: 1px solid rgba(255,255,255,0.05);
            flex-shrink: 0;
        }

        .nav-link.logout-btn {
            background: rgba(239, 68, 68, 0.05);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.1);
            justify-content: center;
        }
        
        .nav-link.logout-btn:hover {
            background: rgba(239, 68, 68, 0.15);
            color: #ff5c5c;
        }

        /* === Content Area === */
        .content {
            margin-left: var(--sidebar-width);
            padding: 30px;
            min-height: 100vh;
            transition: all 0.3s;
        }

        .main-card {
            background: #fff;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 5px 30px rgba(0,0,0,0.03);
            border: 1px solid rgba(0,0,0,0.02);
        }

        /* === Table Styling (Responsive Adaptation) === */
        .table-custom th {
            background-color: #f8fafc;
            color: #475569;
            font-weight: 600;
            padding: 14px;
            border-bottom: 2px solid #e2e8f0;
            font-size: 0.85rem;
            text-transform: uppercase;
            white-space: nowrap; /* ป้องกันไม่ให้หัวตารางตัดคำ */
        }
        
        .table-custom td {
            vertical-align: middle;
            padding: 14px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            font-size: 0.95rem;
        }

        .table-hover tbody tr:hover { background-color: #f8fafc; }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
        }
        
        .btn-action-icon {
            width: 32px; height: 32px;
            display: inline-flex; align-items: center; justify-content: center;
            border-radius: 8px;
            transition: all 0.2s;
            border: none;
            background: #f1f5f9;
            color: #64748b;
        }
        
        .btn-action-icon.delete:hover {
            background-color: #fee2e2;
            color: #ef4444;
            transform: translateY(-2px);
        }

        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: #0f172a; }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 3px; }

        /* ฉากหลังโปร่งแสงสำหรับมือถือเวลาเปิดเมนู */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1040;
            backdrop-filter: blur(2px);
        }

        /* ================= RESPONSIVE QUERIES ================= */
        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-100%); /* ซ่อน Sidebar ไปทางซ้าย */
            }
            .sidebar.show {
                transform: translateX(0); /* เลื่อน Sidebar ออกมา */
            }
            .sidebar-overlay.show {
                display: block; /* แสดงฉากหลัง */
            }
            .content {
                margin-left: 0; /* เอา margin ด้านซ้ายออกบนมือถือ */
                padding: 15px;
            }
            .main-card {
                padding: 20px;
            }
        }

        @media (max-width: 575.98px) {
            .header-info-container {
                flex-direction: column;
                align-items: flex-start !important;
                gap: 15px;
            }
            .main-card {
                padding: 15px;
            }
        }
    </style>
</head>
<body>

<div id="loading-overlay">
    <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
    <h5 class="mt-3 text-secondary">กำลังโหลดข้อมูล...</h5>
</div>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center">
            <div style="width: 35px; height: 35px; background: var(--primary-gradient); border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-right: 12px; box-shadow: 0 4px 10px rgba(99, 102, 241, 0.4);">
                <i class="fas fa-layer-group text-white" style="font-size: 1rem;"></i>
            </div>
            <div>
                <div class="brand-text">Admin Panel</div>
                <div style="font-size: 0.7rem; color: #64748b;">System Management</div>
            </div>
        </div>
        <button class="btn btn-link text-white d-lg-none p-0" id="closeSidebarBtn">
            <i class="fas fa-times fs-5"></i>
        </button>
    </div>
    
    <div class="profile-wrapper">
        <div class="position-relative d-inline-block mb-1">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($user_info['name']) ?>&background=3b82f6&color=fff&size=128" 
                 class="rounded-circle profile-avatar">
            <span class="position-absolute bottom-0 end-0 p-1 bg-success border border-dark rounded-circle" style="width: 10px; height: 10px;"></span>
        </div>
        <h6 class="mb-0 fw-bold text-white text-truncate" style="font-size: 0.95rem;"><?= htmlspecialchars($user_info['name']) ?></h6>
        <div class="admin-badge"><i class="fas fa-shield-alt me-1"></i> ADMIN</div>
    </div>
    
    <div class="nav-scrollable">
        <div class="sidebar-heading">Admin Zone</div>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a href="admin_users_report.php" class="nav-link active">
                    <i class="fas fa-users-cog"></i> <span>จัดการผู้ใช้งาน</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="log_report.php" class="nav-link">
                    <i class="fas fa-file-alt"></i> <span>Log Report</span>
                </a>
            </li>
        </ul>

        <div class="sidebar-heading">Academic Data</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a href="home.php" class="nav-link">
                    <i class="fas fa-home"></i> <span>หน้าหลัก</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="schedules.php" class="nav-link">
                    <i class="fas fa-calendar-alt"></i> <span>ตารางสอน</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="teachers.php" class="nav-link">
                    <i class="fas fa-chalkboard-teacher"></i> <span>รายชื่ออาจารย์</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="courses.php" class="nav-link">
                    <i class="fas fa-book"></i> <span>รายวิชา</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="curriculums.php" class="nav-link">
                    <i class="fas fa-graduation-cap"></i> <span>หลักสูตร</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="classrooms.php" class="nav-link">
                    <i class="fas fa-building"></i> <span>ห้องเรียน</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="rooms.php" class="nav-link">
                    <i class="fas fa-door-open"></i> <span>หมายเลขห้อง</span>
                </a>
            </li>
        </ul>
    </div>
    
    <div class="sidebar-footer">
        <a href="logout.php" class="nav-link logout-btn">
            <i class="fas fa-sign-out-alt"></i> <span>ออกจากระบบ</span>
        </a>
    </div>
</div>

<div class="content">
    
    <button class="btn btn-dark d-lg-none mb-4 shadow-sm" id="openSidebarBtn" style="border-radius: 8px;">
        <i class="fas fa-bars me-2"></i> เมนูจัดการ
    </button>

    <div class="main-card">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap header-info-container">
            <div>
                <h3 class="fw-bold mb-1 text-dark">จัดการผู้ใช้งาน (Users)</h3>
                <p class="text-secondary mb-0 small">ตรวจสอบสถานะ และอนุมัติบัญชีผู้ใช้งานในระบบ</p>
            </div>
            <div class="d-flex gap-3">
                <div class="bg-primary bg-opacity-10 px-3 py-2 rounded-3 border border-primary border-opacity-10 text-center">
                    <span class="d-block text-primary small fw-bold text-uppercase" style="font-size: 0.7rem;">Total Users</span>
                    <span class="fs-5 fw-bold text-dark"><?= count($users) ?></span>
                </div>
            </div>
        </div>

        <?php if(isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    Swal.fire({
                        icon: 'success',
                        title: 'ลบเรียบร้อย',
                        text: 'ข้อมูลผู้ใช้ถูกลบออกจากระบบแล้ว',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    
                    if (window.history.replaceState) {
                        const url = new URL(window.location);
                        url.searchParams.delete('msg');
                        window.history.replaceState(null, '', url.toString());
                    }
                });
            </script>
        <?php endif; ?>

        <?php if(isset($error_msg)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i> <?= $error_msg ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-custom table-hover align-middle">
                <thead>
                    <tr>
                        <th class="text-center" style="min-width: 70px;">#ID</th>
                        <th style="min-width: 250px;">ข้อมูลผู้ใช้</th>
                        <th style="min-width: 120px;">กลุ่มเรียน</th>
                        <th class="text-center" style="min-width: 100px;">สิทธิ์</th>
                        <th class="text-center" style="min-width: 180px;">สถานะ / การอนุมัติ</th>
                        <th class="text-center" style="min-width: 100px;">ตารางสอน</th>
                        <th class="text-center" style="min-width: 80px;">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td class="text-center text-secondary fw-bold">#<?= $user['id'] ?></td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="bg-light rounded-circle p-2 me-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 35px; height: 35px;">
                                    <i class="fas fa-user text-secondary"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark" style="font-size: 0.9rem;"><?= htmlspecialchars($user['name']) ?></div>
                                    <small class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($user['email']) ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border fw-normal px-2 py-1">
                                <?= htmlspecialchars($user['group']) ?>
                            </span>
                        </td>
                        
                        <td class="text-center">
                            <?php if($user['role'] === 'admin'): ?>
                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-3 py-1 rounded-pill">
                                    ADMIN
                                </span>
                            <?php else: ?>
                                <span class="badge bg-info bg-opacity-10 text-primary border border-info border-opacity-25 px-3 py-1 rounded-pill">
                                    USER
                                </span>
                            <?php endif; ?>
                        </td>

                        <td class="text-center">
                            <?php if ($user['status'] == 'pending'): ?>
                                <div class="btn-group shadow-sm rounded-3">
                                    <button onclick="confirmStatus('<?= $user['id'] ?>', 'approved')" class="btn btn-sm btn-success px-2 py-1" style="font-size: 0.8rem;">
                                        <i class="fas fa-check me-1"></i> อนุมัติ
                                    </button>
                                    <button onclick="confirmStatus('<?= $user['id'] ?>', 'rejected')" class="btn btn-sm btn-outline-danger px-2 py-1" style="font-size: 0.8rem;">
                                        <i class="fas fa-times me-1"></i> ไม่
                                    </button>
                                </div>
                            <?php elseif ($user['status'] == 'approved'): ?>
                                <span class="status-badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">
                                    <i class="fas fa-check-circle"></i> ปกติ
                                </span>
                                <button onclick="confirmStatus('<?= $user['id'] ?>', 'pending', 'รีเซ็ตสถานะ')" class="btn btn-link btn-sm text-secondary text-decoration-none ms-2 p-0" title="รีเซ็ต">
                                    <i class="fas fa-undo" style="font-size: 0.8rem;"></i>
                                </button>
                            <?php else: ?>
                                <span class="status-badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">
                                    <i class="fas fa-ban"></i> ระงับ
                                </span>
                                <button onclick="confirmStatus('<?= $user['id'] ?>', 'pending', 'รีเซ็ตสถานะ')" class="btn btn-link btn-sm text-secondary text-decoration-none ms-2 p-0" title="รีเซ็ต">
                                    <i class="fas fa-undo" style="font-size: 0.8rem;"></i>
                                </button>
                            <?php endif; ?>
                        </td>

                        <td class="text-center">
                            <span class="badge bg-secondary bg-opacity-10 text-dark"><?= $user['schedule_count'] ?></span>
                        </td>

                        <td class="text-center">
                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                <button onclick="confirmDelete('<?= $user['id'] ?>', '<?= htmlspecialchars($user['name']) ?>')" class="btn-action-icon delete" title="ลบผู้ใช้">
                                    <i class="far fa-trash-alt"></i>
                                </button>
                            <?php else: ?>
                                <span class="text-muted opacity-25" style="font-size: 0.8rem;">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    // สคริปต์สำหรับเปิด-ปิด Sidebar บนหน้าจอมือถือ
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const openSidebarBtn = document.getElementById('openSidebarBtn');
    const closeSidebarBtn = document.getElementById('closeSidebarBtn');

    function toggleSidebar() {
        sidebar.classList.toggle('show');
        sidebarOverlay.classList.toggle('show');
    }

    openSidebarBtn.addEventListener('click', toggleSidebar);
    closeSidebarBtn.addEventListener('click', toggleSidebar);
    sidebarOverlay.addEventListener('click', toggleSidebar);

    // Loading overlay
    window.addEventListener('beforeunload', function (e) {
        document.getElementById('loading-overlay').style.display = 'flex';
    });

    function confirmDelete(id, name) {
        Swal.fire({
            title: 'ยืนยันการลบ?',
            html: "คุณต้องการลบผู้ใช้ <b class='text-danger'>" + name + "</b> หรือไม่?<br>การกระทำนี้ไม่สามารถย้อนกลับได้",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: '<i class="fas fa-trash-alt me-1"></i> ลบข้อมูล',
            cancelButtonText: 'ยกเลิก',
            reverseButtons: true,
            focusCancel: true
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'admin_users_report.php?action=delete&id=' + id;
            }
        })
    }

    function confirmStatus(id, newStatus, label = null) {
        let actionText = '';
        let confirmBtnColor = '';
        let icon = '';
        
        if (newStatus === 'approved') {
            actionText = 'อนุมัติการใช้งาน';
            confirmBtnColor = '#10b981'; 
            icon = 'question';
        } else if (newStatus === 'rejected') {
            actionText = 'ปฏิเสธ/ระงับการใช้งาน';
            confirmBtnColor = '#ef4444'; 
            icon = 'warning';
        } else {
            actionText = label || 'รีเซ็ตสถานะเป็นรออนุมัติ';
            confirmBtnColor = '#64748b'; 
            icon = 'info';
        }

        Swal.fire({
            title: 'ยืนยันการทำรายการ',
            text: "คุณต้องการ " + actionText + " สำหรับผู้ใช้นี้ใช่หรือไม่?",
            icon: icon,
            showCancelButton: true,
            confirmButtonColor: confirmBtnColor,
            cancelButtonColor: '#cbd5e1',
            confirmButtonText: 'ยืนยัน',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'update_status.php?id=' + id + '&status=' + newStatus;
            }
        })
    }
</script>

</body>
</html>