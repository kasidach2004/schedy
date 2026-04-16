<?php
session_start();

// 1. ตรวจสอบ Login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// 2. เรียกใช้การเชื่อมต่อฐานข้อมูล
require_once 'config/db.php'; 

// 3. ตรวจสอบสิทธิ์ Admin 
$sql_user = "SELECT name, email, `group`, role FROM users WHERE id = ?";
$stmt_user = $conn->prepare($sql_user);
$stmt_user->execute([$_SESSION['user_id']]);
$user_info = $stmt_user->fetch(PDO::FETCH_ASSOC);

if ($user_info['role'] !== 'admin') {
    echo '<div style="padding:20px; text-align:center; font-family:sans-serif;">
            <h2>❌ ไม่มีสิทธิ์เข้าถึง</h2>
            <p>เฉพาะ Admin เท่านั้นที่สามารถเข้าถึงหน้านี้ได้</p>
            <a href="home.php" style="padding:10px 20px; background:#3b82f6; color:#fff; text-decoration:none; border-radius:5px;">กลับไปหน้าหลัก</a>
          </div>';
    exit;
}

// === ส่วนจัดการ Action ล้างข้อมูล Log (Clear All) ===
if (isset($_GET['action']) && $_GET['action'] == 'clear_all') {
    try {
        // ล้างข้อมูลตาราง
        $conn->exec("TRUNCATE TABLE system_logs");
        
        // บันทึก Log ว่าใครเป็นคนล้างข้อมูล (จะเป็น Record แรกหลังล้างเสร็จ)
        if (function_exists('writeLog')) {
            writeLog($conn, $_SESSION['user_id'], $user_info['name'], 'Clear Logs', 'ผู้ดูแลระบบทำการล้างข้อมูลประวัติการใช้งานทั้งหมด');
        }
        
        header("Location: log_report.php?msg=cleared");
        exit;
    } catch (PDOException $e) {
        $error_msg = "ไม่สามารถล้างข้อมูลได้: " . $e->getMessage();
    }
}
// ==========================================

// 4. ตั้งค่าระบบค้นหาและแบ่งหน้า
$search = $_GET['search'] ?? '';
$page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$limit = 20; 
$offset = ($page - 1) * $limit;

// 5. ดึงข้อมูล Log
$sql = "SELECT * FROM system_logs 
        WHERE action LIKE :search OR user_name LIKE :search OR ip_address LIKE :search OR details LIKE :search
        ORDER BY created_at DESC 
        LIMIT :limit OFFSET :offset";
$stmt = $conn->prepare($sql);
$stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 6. นับจำนวนทั้งหมด
$countStmt = $conn->prepare("SELECT COUNT(*) FROM system_logs WHERE action LIKE :search OR user_name LIKE :search OR ip_address LIKE :search OR details LIKE :search");
$countStmt->execute([':search' => "%$search%"]);
$totalLogs = $countStmt->fetchColumn();
$totalPages = ceil($totalLogs / $limit);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Log Report - Admin Panel</title>
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

        /* === Sidebar Styles === */
        .sidebar {
            width: var(--sidebar-width); height: 100vh; background: var(--sidebar-bg);
            color: #fff; position: fixed; transition: all 0.3s ease; z-index: 1050; /* อัปเดต z-index ให้อยู่เหนือ overlay */
            box-shadow: 10px 0 30px rgba(0,0,0,0.1); border-right: 1px solid rgba(255,255,255,0.05);
            display: flex; flex-direction: column; left: 0; top: 0;
        }
        .sidebar.collapsed { left: calc(var(--sidebar-width) * -1); }
        
        .sidebar-header { padding: 20px; background: linear-gradient(to bottom, rgba(255,255,255,0.03), transparent); flex-shrink: 0; }
        .brand-text { font-weight: 600; font-size: 1.1rem; background: linear-gradient(90deg, #fff, #94a3b8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; letter-spacing: 0.5px; }
        .profile-wrapper { margin: 0 15px 15px 15px; padding: 15px; background: rgba(255, 255, 255, 0.03); border-radius: 12px; border: 1px solid rgba(255, 255, 255, 0.05); backdrop-filter: blur(10px); text-align: center; flex-shrink: 0; }
        .profile-avatar { width: 50px; height: 50px; border: 2px solid rgba(255,255,255,0.1); }
        .admin-badge { background: rgba(239, 68, 68, 0.15); color: #f87171; font-size: 0.7rem; padding: 2px 10px; border-radius: 15px; border: 1px solid rgba(239, 68, 68, 0.3); display: inline-block; margin-top: 5px; }
        .nav-scrollable { flex-grow: 1; overflow-y: auto; overflow-x: hidden; }
        .sidebar-heading { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; color: var(--text-secondary); margin: 20px 10px 10px 20px; font-weight: 600; opacity: 0.8; }
        .nav-link { color: var(--text-secondary); padding: 10px 20px; margin: 4px 10px; border-radius: 10px; transition: all 0.2s; display: flex; align-items: center; font-weight: 400; font-size: 0.9rem; }
        .nav-link i { width: 24px; font-size: 1.1rem; margin-right: 10px; text-align: center; }
        .nav-link:hover { color: #fff; background: var(--sidebar-hover); transform: translateX(3px); }
        .nav-link.active { background: var(--primary-gradient); color: #fff; box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3); font-weight: 500; }
        .sidebar-footer { padding: 15px; border-top: 1px solid rgba(255,255,255,0.05); flex-shrink: 0; }
        .nav-link.logout-btn { background: rgba(239, 68, 68, 0.05); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.1); justify-content: center; }
        
        /* === Content Area === */
        .content { margin-left: var(--sidebar-width); padding: 30px; min-height: 100vh; transition: all 0.3s ease; }
        .content.expanded { margin-left: 0; }
        .main-card { background: #fff; border-radius: 16px; padding: 30px; box-shadow: 0 5px 30px rgba(0,0,0,0.03); border: 1px solid rgba(0,0,0,0.02); }
        
        /* === Toggle Button === */
        .toggle-btn { background: #fff; border: 1px solid #e2e8f0; color: #475569; padding: 8px 12px; border-radius: 8px; cursor: pointer; transition: 0.2s; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .toggle-btn:hover { background: #f8fafc; color: #0f172a; }

        /* === Table Styling (Responsive Adaptation) === */
        .table-custom th { background-color: #f8fafc; color: #475569; font-weight: 600; padding: 14px; border-bottom: 2px solid #e2e8f0; font-size: 0.85rem; text-transform: uppercase; white-space: nowrap; }
        .table-custom td { vertical-align: middle; padding: 14px; border-bottom: 1px solid #f1f5f9; color: #334155; font-size: 0.9rem; }
        .details-col { min-width: 250px; }
        
        /* สไตล์พิเศษสำหรับ Action */
        .action-badge { padding: 6px 12px; border-radius: 6px; font-weight: 500; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; }
        
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
                transform: translateX(-100%);
                left: 0;
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .sidebar-overlay.show {
                display: block;
            }
            .content {
                margin-left: 0 !important;
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
            .header-actions {
                width: 100%;
                justify-content: space-between;
                flex-wrap: wrap;
            }
            .main-card {
                padding: 15px;
            }
        }
    </style>
</head>
<body>

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
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($user_info['name']) ?>&background=3b82f6&color=fff&size=128" class="rounded-circle profile-avatar">
            <span class="position-absolute bottom-0 end-0 p-1 bg-success border border-dark rounded-circle" style="width: 10px; height: 10px;"></span>
        </div>
        <h6 class="mb-0 fw-bold text-white text-truncate" style="font-size: 0.95rem;"><?= htmlspecialchars($user_info['name']) ?></h6>
        <div class="admin-badge"><i class="fas fa-shield-alt me-1"></i> ADMIN</div>
    </div>
    
    <div class="nav-scrollable">
        <div class="sidebar-heading">Admin Zone</div>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a href="admin_users_report.php" class="nav-link">
                    <i class="fas fa-users-cog"></i> <span>จัดการผู้ใช้งาน</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="log_report.php" class="nav-link active">
                    <i class="fas fa-file-alt"></i> <span>Log Report</span>
                </a>
            </li>
        </ul>

        <div class="sidebar-heading">Academic Data</div>
        <ul class="nav flex-column">
            <li class="nav-item"><a href="home.php" class="nav-link"><i class="fas fa-home"></i> <span>หน้าหลัก</span></a></li>
            <li class="nav-item"><a href="schedules.php" class="nav-link"><i class="fas fa-calendar-alt"></i> <span>ตารางสอน</span></a></li>
            <li class="nav-item"><a href="teachers.php" class="nav-link"><i class="fas fa-chalkboard-teacher"></i> <span>รายชื่ออาจารย์</span></a></li>
            <li class="nav-item"><a href="courses.php" class="nav-link"><i class="fas fa-book"></i> <span>รายวิชา</span></a></li>
            <li class="nav-item"><a href="curriculums.php" class="nav-link"><i class="fas fa-graduation-cap"></i> <span>หลักสูตร</span></a></li>
            <li class="nav-item"><a href="classrooms.php" class="nav-link"><i class="fas fa-building"></i> <span>ห้องเรียน</span></a></li>
            <li class="nav-item"><a href="rooms.php" class="nav-link"><i class="fas fa-door-open"></i> <span>หมายเลขห้อง</span></a></li>
        </ul>
    </div>
    
    <div class="sidebar-footer">
        <a href="logout.php" class="nav-link logout-btn">
            <i class="fas fa-sign-out-alt"></i> <span>ออกจากระบบ</span>
        </a>
    </div>
</div>

<div class="content" id="main-content">
    <div class="main-card">
        
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap header-info-container">
            <div class="d-flex align-items-center gap-3">
                <button onclick="toggleSidebar()" class="toggle-btn" title="เมนู">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <h3 class="fw-bold mb-0 text-dark">ประวัติการใช้งาน (System Logs)</h3>
                    <p class="text-secondary mb-0 small">ตรวจสอบและจัดการกิจกรรมทั้งหมดในระบบ</p>
                </div>
            </div>
            
            <div class="d-flex gap-2 align-items-center header-actions mt-3 mt-md-0">
                <a href="admin_users_report.php" class="btn btn-light border text-secondary shadow-sm">
                    <i class="fas fa-arrow-left me-1"></i> กลับหน้าผู้ใช้งาน
                </a>
                <button onclick="confirmClearLogs()" class="btn btn-danger shadow-sm d-flex align-items-center gap-2">
                    <i class="fas fa-trash-alt"></i> ล้าง Log
                </button>
                
                <div class="bg-primary bg-opacity-10 px-3 py-2 rounded-3 border border-primary border-opacity-10 text-center ms-md-2">
                    <span class="d-block text-primary small fw-bold text-uppercase" style="font-size: 0.7rem;">Total Logs</span>
                    <span class="fs-5 fw-bold text-dark"><?php echo number_format($totalLogs); ?></span>
                </div>
            </div>
        </div>

        <form method="GET" class="row g-2 mb-4 bg-light p-3 rounded-3 border">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="ค้นหาชื่อ, กิจกรรม, IP หรือรายละเอียด..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-md-2 col-6">
                <button type="submit" class="btn btn-primary w-100">ค้นหาข้อมูล</button>
            </div>
            <div class="col-md-2 col-6">
                <a href="log_report.php" class="btn btn-outline-secondary w-100">รีเซ็ต</a>
            </div>
        </form>

        <?php if(isset($_GET['msg']) && $_GET['msg'] == 'cleared'): ?>
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    Swal.fire({
                        icon: 'success', title: 'ล้างข้อมูลสำเร็จ!', text: 'ประวัติการใช้งานทั้งหมดถูกลบออกจากระบบแล้ว',
                        timer: 2000, showConfirmButton: false
                    });
                    if (window.history.replaceState) {
                        const url = new URL(window.location); url.searchParams.delete('msg');
                        window.history.replaceState(null, '', url.toString());
                    }
                });
            </script>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-custom table-hover align-middle">
                <thead>
                    <tr>
                        <th style="min-width: 150px;">วัน-เวลา</th>
                        <th style="min-width: 180px;">ผู้ใช้งาน</th>
                        <th class="text-center" style="min-width: 120px;">IP Address</th>
                        <th style="min-width: 200px;">การดำเนินการ (Action)</th>
                        <th style="min-width: 250px;">รายละเอียด</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($logs) > 0): ?>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td class="text-secondary" style="font-size: 0.85rem;">
                                <i class="far fa-clock me-1"></i> <?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?>
                            </td>
                            
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="bg-light rounded-circle d-flex justify-content-center align-items-center text-secondary flex-shrink-0" style="width:30px; height:30px;"><i class="fas fa-user"></i></div>
                                    <div>
                                        <strong class="text-dark"><?php echo htmlspecialchars($log['user_name'] ?? 'System'); ?></strong><br>
                                        <span class="text-muted" style="font-size: 0.7rem;">ID: <?php echo $log['user_id'] ?? 'SYS'; ?></span>
                                    </div>
                                </div>
                            </td>
                            
                            <td class="text-center text-primary fw-bold" style="font-size:0.85rem;">
                                <?php echo htmlspecialchars($log['ip_address']); ?>
                            </td>
                            
                            <td>
                                <?php 
                                    // ตกแต่ง Action ให้ชัดเจนตามคำค้นหา
                                    $action_text = htmlspecialchars($log['action']);
                                    $action_lower = strtolower($action_text);
                                    
                                    $bg_class = 'bg-secondary bg-opacity-10 text-secondary border-secondary';
                                    $icon = 'fas fa-cog';

                                    if (strpos($action_lower, 'login') !== false || strpos($action_lower, 'approve') !== false) { 
                                        $bg_class = 'bg-success bg-opacity-10 text-success border-success'; $icon = 'fas fa-check-circle'; 
                                    } elseif (strpos($action_lower, 'delete') !== false || strpos($action_lower, 'remove') !== false || strpos($action_lower, 'clear') !== false) { 
                                        $bg_class = 'bg-danger bg-opacity-10 text-danger border-danger'; $icon = 'fas fa-trash-alt'; 
                                    } elseif (strpos($action_lower, 'update') !== false || strpos($action_lower, 'edit') !== false) { 
                                        $bg_class = 'bg-warning bg-opacity-10 text-warning border-warning'; $icon = 'fas fa-edit'; 
                                    } elseif (strpos($action_lower, 'add') !== false || strpos($action_lower, 'insert') !== false) { 
                                        $bg_class = 'bg-primary bg-opacity-10 text-primary border-primary'; $icon = 'fas fa-plus-circle'; 
                                    }
                                ?>
                                <div class="action-badge border border-opacity-25 <?php echo $bg_class; ?>">
                                    <i class="<?php echo $icon; ?>"></i> <?php echo $action_text; ?>
                                </div>
                            </td>
                            
                            <td>
                                <div class="details-col text-dark" style="font-size: 0.9rem;">
                                    <?php echo htmlspecialchars($log['details'] ?? '-'); ?>
                                </div>
                                <div class="text-muted mt-1" style="font-size: 0.7rem;">
                                    <i class="fas fa-link"></i> <?php echo htmlspecialchars($log['request_uri']); ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="fas fa-clipboard-list fs-1 mb-3 opacity-50"></i><br>
                                    <h5>ยังไม่มีข้อมูล Log ในระบบ</h5>
                                    <p class="small">ระบบจะเริ่มบันทึกข้อมูลเมื่อมีการทำรายการต่างๆ</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center flex-wrap">
                <?php if ($page > 1): ?>
                    <li class="page-item"><a class="page-link" href="?p=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>">ก่อนหน้า</a></li>
                <?php endif; ?>
                
                <?php 
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    for ($i = $startPage; $i <= $endPage; $i++): 
                ?>
                    <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                        <a class="page-link" href="?p=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <li class="page-item"><a class="page-link" href="?p=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>">ถัดไป</a></li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    // สคริปต์จัดการ Sidebar (รองรับทั้ง Desktop และ Mobile)
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const mainContent = document.getElementById('main-content');
    const closeSidebarBtn = document.getElementById('closeSidebarBtn');

    function toggleSidebar() {
        if (window.innerWidth <= 991.98) {
            // โหมดมือถือ/แท็บเล็ต
            sidebar.classList.toggle('show');
            sidebarOverlay.classList.toggle('show');
        } else {
            // โหมด Desktop
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
        }
    }

    // ปิดเมนูเมื่อกดปุ่ม X หรือกดพื้นที่ว่าง (Overlay)
    if (closeSidebarBtn) closeSidebarBtn.addEventListener('click', toggleSidebar);
    if (sidebarOverlay) sidebarOverlay.addEventListener('click', toggleSidebar);

    // รีเซ็ตคลาสเมื่อมีการหมุนจอหรือขยายหน้าต่าง
    window.addEventListener('resize', () => {
        if (window.innerWidth > 991.98) {
            sidebar.classList.remove('show');
            sidebarOverlay.classList.remove('show');
        }
    });

    // ฟังก์ชันยืนยันการล้างข้อมูล Log
    function confirmClearLogs() {
        Swal.fire({
            title: 'ยืนยันการล้างข้อมูล?',
            text: "คุณต้องการลบประวัติการใช้งานทั้งหมดใช่หรือไม่? (การกระทำนี้ไม่สามารถกู้คืนได้)",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-trash-alt me-1"></i> ยืนยันล้างข้อมูล',
            cancelButtonText: 'ยกเลิก',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                // ส่งคำสั่งไปเคลียร์ข้อมูลที่ด้านบนของไฟล์
                window.location.href = "log_report.php?action=clear_all";
            }
        });
    }
</script>

</body>
</html>