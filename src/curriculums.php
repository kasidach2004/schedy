<?php // curriculums.php - หน้าจัดการหลักสูตร
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/db.php';

// 1. ดึงข้อมูลผู้ใช้
$sql_user = "SELECT name, email, `group`, role FROM users WHERE id = ?";
$stmt_user = $conn->prepare($sql_user);
$stmt_user->execute([$_SESSION['user_id']]);
$user_info = $stmt_user->fetch(PDO::FETCH_ASSOC);

// 2. จัดการการค้นหา และดึงข้อมูลหลักสูตร
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$params = [$_SESSION['user_id']];

// 🟢 บันทึก Log: ใครเปิดเข้ามาดู หรือค้นหาข้อมูลหลักสูตรบ้าง
if (function_exists('writeLog')) {
    $logAction = 'View Page';
    $logDetails = 'เปิดดูหน้าจัดการหลักสูตร (curriculums.php)';
    
    // ถ้ามีการค้นหา ให้เปลี่ยน Log เป็นการค้นหา
    if (!empty($search)) {
        $logAction = 'Search Data';
        $logDetails = "ผู้ใช้ค้นหาหลักสูตรคำว่า: '" . $search . "'";
    }
    
    writeLog($conn, $_SESSION['user_id'], $user_info['name'], $logAction, $logDetails);
}

// Query พื้นฐาน: เห็นเฉพาะของตัวเอง
$sql_curriculums = "SELECT id, curriculum_name, semester, academic_year, user_id 
                    FROM curriculums 
                    WHERE user_id = ?";

// ถ้ามีการค้นหา ให้เพิ่มเงื่อนไข SQL
if (!empty($search)) {
    $sql_curriculums .= " AND (curriculum_name LIKE ? OR academic_year LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql_curriculums .= " ORDER BY id DESC"; // เรียงลำดับล่าสุดขึ้นก่อน

$stmt_curriculums = $conn->prepare($sql_curriculums);
$stmt_curriculums->execute($params);
$curriculums = $stmt_curriculums->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Curriculums</title>
    <link rel="icon" type="image/png" href="img/FTE.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { background: linear-gradient(to right, #0f2027, #203a43, #2c5364); color: #f8f9fa; }
        /* สไตล์เดิมสำหรับ Sidebar */
        .sidebar { background: #212529; height: auto; padding-top: 5px; width: 280px; flex-shrink: 0; }
        .sidebar .nav-link { color: #f8f9fa; padding: 15px; height: 50px; margin-bottom: 5px; border-radius: 8px; transition: background-color 0.3s; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background-color: #0d6efd; color: white; }
        .profile-card { background: rgba(255, 255, 255, 0.1); padding: 5px; border-radius: 20px; margin-bottom: 10px; text-align: center; }
        .content { padding: 5px; flex-grow: 1; overflow-y: auto; }
        .main-card { background: rgba(255, 255, 255, 0.1); border-radius: 10px; padding: 10px; }
        
        /* สไตล์ Modal โทนขาว */
        .modal-content {
            background-color: #ffffff;
            color: #333333;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.5);
            border: none;
        }
        .modal-header { border-bottom: 1px solid #dee2e6; }
        .modal-footer { border-top: 1px solid #dee2e6; }
        .modal-body label { color: #333; font-weight: 500; }
        .modal-title { color: #212529; font-weight: bold; }
        
        /* ปรับ Input ใน Modal ให้ชัดเจน */
        .form-control, .form-select {
            border: 1px solid #ced4da;
            background-color: #fff;
            color: #212529;
        }
    </style>
</head>
<body>
<div class="d-flex" style="height: 100vh;">
    <div class="sidebar d-flex flex-column p-3 text-white">
        <a href="#" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none">
            <i class="fas fa-university me-2"></i>
            <span class="fs-4">schedules class</span>
        </a>
        <hr>
        <div class="profile-card">
            <i class="fas fa-user-circle fa-3x mb-2"></i>
            <h5><?= htmlspecialchars($user_info['name']) ?></h5>
            <small><?= htmlspecialchars($user_info['email']) ?></small>
            <br>
            <?php if ($user_info['role'] === 'admin'): ?>
                <span class="badge bg-danger mt-2">
                    <i class="fas fa-crown me-1"></i> ADMIN
                </span>
            <?php else: ?>
                <span class="badge bg-primary mt-2"><?= htmlspecialchars($user_info['group']) ?></span>
            <?php endif; ?>
        </div>
        <ul class="nav nav-pills flex-column mb-auto">
            <li class="nav-item"><a href="home.php" class="nav-link"><i class="fas fa-home me-2"></i> หน้าหลัก</a></li>
            <li class="nav-item"><a href="schedules.php" class="nav-link"><i class="fas fa-chalkboard me-2"></i> ตารางสอน</a></li>
            <div class="d-flex align-items-center px-1 mt-1 mb-1" style="line-height: 1.2;">
                <div class="flex-grow-1 border-top border-light" style="opacity: 0.4;"></div>
                    <span class="text-white px-2" style="font-size: 0.7rem; font-weight: bold; letter-spacing: 0.5px; text-transform: uppercase;"> เพิ่มข้อมูลพื้นฐาน</span>
                 <div class="flex-grow-1 border-top border-light" style="opacity: 0.4;"></div>
            </div>
            <li><a href="teachers.php" class="nav-link"><i class="fas fa-users me-2"></i> รายชื่ออาจารย์</a></li>
            <li><a href="courses.php" class="nav-link"><i class="fas fa-book me-2"></i> วิชา</a></li>
            <li><a href="rooms.php" class="nav-link"><i class="fas fa-school me-2"></i> หมายเลขห้องเรียน</a></li>
            <li><a href="classrooms.php" class="nav-link"><i class="fas fa-building me-2"></i> ห้องเรียน</a></li>
            <li><a href="curriculums.php" class="nav-link active"><i class="fas fa-graduation-cap me-2"></i> หลักสูตร</a></li>
            <li><a href="logout.php" class="nav-link"><i class="fas fa-sign-out-alt me-2 text-danger"></i>ออกจากระบบ</a></li>
        </ul>
    </div>

    <div class="content flex-grow-1">
        <div class="main-card">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <h1>หลักสูตร</h1>
                
                <div class="d-flex gap-2">
                    <form method="GET" action="curriculums.php" class="d-flex">
                        <input type="text" name="search" class="form-control me-2" placeholder="ค้นหาชื่อหลักสูตร..." value="<?= htmlspecialchars($search) ?>" style="width: 250px;">
                        <button type="submit" class="btn btn-outline-light"><i class="fas fa-search"></i></button>
                        <?php if(!empty($search)): ?>
                            <a href="curriculums.php" class="btn btn-outline-secondary ms-1" title="ล้างค่าค้นหา"><i class="fas fa-times"></i></a>
                        <?php endif; ?>
                    </form>

                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCurriculumModal" onclick="logUserAction('Click Button', 'ผู้ใช้กดปุ่ม [เพิ่มหลักสูตร]')">
                        <i class="fas fa-plus me-2"></i> เพิ่มหลักสูตร
                    </button>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-dark table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ชื่อหลักสูตร</th>
                            <th>ภาคเรียน</th>
                            <th>ปีการศึกษา</th>
                            <th style="width: 250px;">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($curriculums) > 0): ?>
                            <?php foreach ($curriculums as $curriculum): ?>
                                <tr>
                                    <td><?= htmlspecialchars($curriculum['curriculum_name']) ?></td>
                                    <td><?= htmlspecialchars($curriculum['semester']) ?></td>
                                    <td><?= htmlspecialchars($curriculum['academic_year']) ?></td>
                                    <td>
                                        <a href="manage_curriculum.php?id=<?= $curriculum['id'] ?>" class="btn btn-info btn-sm" title="จัดการวิชา" onclick="logUserAction('Click Link', 'ผู้ใช้กดปุ่ม [จัดการวิชา] หลักสูตร: <?= htmlspecialchars($curriculum['curriculum_name']) ?>')">
                                            <i class="fas fa-list"></i>
                                        </a>
                                        
                                        <a href="edit_curriculum.php?id=<?= $curriculum['id'] ?>" class="btn btn-warning btn-sm" title="แก้ไข" onclick="logUserAction('Click Link', 'ผู้ใช้กดปุ่ม [แก้ไข] หลักสูตร: <?= htmlspecialchars($curriculum['curriculum_name']) ?>')">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                        
                                        <button class="btn btn-secondary btn-sm" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#copyCurriculumModal"
                                                data-id="<?= $curriculum['id'] ?>"
                                                data-name="<?= htmlspecialchars($curriculum['curriculum_name']) ?>"
                                                title="ทำสำเนา">
                                            <i class="fas fa-copy"></i>
                                        </button>

                                        <button class="btn btn-danger btn-sm" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#deleteCurriculumModal"
                                                data-id="<?= $curriculum['id'] ?>"
                                                data-name="<?= htmlspecialchars($curriculum['curriculum_name']) ?>"
                                                title="ลบ">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center py-5 text-muted">
                                    ไม่พบข้อมูลหลักสูตร
                                    <?php if(!empty($search)) echo "สำหรับคำค้นหา '$search'"; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addCurriculumModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus-circle text-primary me-2"></i>เพิ่มหลักสูตรใหม่</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="add_curriculum_process.php" method="POST">
                    <div class="mb-3">
                        <label for="curriculumName" class="form-label">ชื่อหลักสูตร</label>
                        <input type="text" class="form-control" id="curriculumName" name="curriculum_name" placeholder="เช่น CED,TCT.TCT-RA" required>
                    </div>
                    <div class="mb-3">
                        <label for="semester" class="form-label">ภาคเรียน</label>
                        <select class="form-select" id="semester" name="semester" required>
                            <option value="1">1</option>
                            <option value="2">2</option>
                            <option value="ฤดูร้อน">ฤดูร้อน</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="academicYear" class="form-label">ปีการศึกษา (พ.ศ.)</label>
                        <input type="number" class="form-control" id="academicYear" name="academic_year" min="2500" max="2599" value="<?= date('Y') + 543 ?>" required>
                    </div>
                    <div class="text-end">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary">บันทึก</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="copyCurriculumModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-copy text-secondary me-2"></i>ยืนยันการทำสำเนา
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>คุณต้องการทำสำเนาหลักสูตร <strong id="copyName" class="text-primary"></strong> พร้อมรายวิชาภายในใช่หรือไม่?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <a href="#" id="confirmCopyBtn" class="btn btn-primary">
                    <i class="fas fa-check me-1"></i> ยืนยัน
                </a>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteCurriculumModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle text-danger me-2"></i>ยืนยันการลบ
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>คุณต้องการลบหลักสูตร <strong id="deleteName" class="text-danger"></strong> ใช่หรือไม่?</p>
                <small class="text-muted">ข้อมูลรายวิชาในหลักสูตรนี้จะถูกลบไปด้วย และไม่สามารถกู้คืนได้</small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger">
                    <i class="fas fa-trash me-1"></i> ยืนยันลบ
                </a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // 🟢 ฟังก์ชันส่ง Log พฤติกรรมผ่าน AJAX
    function logUserAction(action, details) {
        fetch('log/ajax_log_action.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=${encodeURIComponent(action)}&details=${encodeURIComponent(details)}`
        });
    }

    // จัดการ Modal ทำสำเนา
    const copyCurriculumModal = document.getElementById('copyCurriculumModal');
    copyCurriculumModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const id = button.getAttribute('data-id');
        const name = button.getAttribute('data-name');
        
        document.getElementById('copyName').textContent = name;
        document.getElementById('confirmCopyBtn').href = 'copy_curriculum_process.php?id=' + id;

        // 🟢 ส่ง Log ว่าเตรียมทำสำเนา
        logUserAction('Click Button', `ผู้ใช้กดปุ่ม [ทำสำเนา] เตรียมทำสำเนาหลักสูตร: ${name}`);
    });

    // จัดการ Modal ลบ
    const deleteCurriculumModal = document.getElementById('deleteCurriculumModal');
    deleteCurriculumModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const id = button.getAttribute('data-id');
        const name = button.getAttribute('data-name');
        
        document.getElementById('deleteName').textContent = name;
        document.getElementById('confirmDeleteBtn').href = 'delete_curriculum.php?id=' + id;

        // 🟢 ส่ง Log ว่าเตรียมลบ
        logUserAction('Click Button', `ผู้ใช้กดปุ่ม [ลบ] เตรียมลบหลักสูตร: ${name}`);
    });
</script>
</body>
</html>