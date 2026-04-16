<?php // classrooms.php - หน้าจัดการห้องเรียน
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

// 🟢 บันทึก Log: ใครเปิดเข้ามาดูหน้าจัดการห้องเรียนบ้าง
if (function_exists('writeLog')) {
    writeLog(
        $conn, 
        $_SESSION['user_id'], 
        $user_info['name'], 
        'View Page', 
        'เปิดดูหน้าจัดการห้องเรียน (classrooms.php)'
    );
}

// 2. ดึงข้อมูลห้องเรียน
// เงื่อนไข: ทุกคน (รวม Admin) เห็นเฉพาะของตัวเอง (WHERE user_id = ?)
$sql_classrooms = "SELECT id, room_name, notes, user_id FROM classrooms WHERE user_id = ? ORDER BY id DESC";
$stmt_classrooms = $conn->prepare($sql_classrooms);
$stmt_classrooms->execute([$_SESSION['user_id']]);
$classrooms = $stmt_classrooms->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Student Groups</title>
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
        }
        .modal-header { border-bottom: 1px solid #dee2e6; }
        .modal-footer { border-top: 1px solid #dee2e6; }
        /* ปรับสี label ใน form modal ให้เป็นสีดำ */
        .modal-body label { color: #333; font-weight: 500; }
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
            <li><a href="classrooms.php" class="nav-link active"><i class="fas fa-building me-2"></i> ห้องเรียน</a></li>
            <li><a href="curriculums.php" class="nav-link"><i class="fas fa-graduation-cap me-2"></i> หลักสูตร</a></li>
            <li><a href="logout.php" class="nav-link"><i class="fas fa-sign-out-alt me-2 text-danger"></i>ออกจากระบบ</a></li>
        </ul>
    </div>

    <div class="content flex-grow-1">
        <div class="main-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>ห้องเรียน</h1>
                <span style="font-size: 10px;">*ชื่อห้องจะต้องตรงกับชื่อหลักสูตรที่กำหนดไว้</span>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addClassroomModal" onclick="logUserAction('Click Button', 'ผู้ใช้กดปุ่ม [เพิ่มห้องเรียน]')">
                    <i class="fas fa-plus me-2"></i> เพิ่มห้องเรียน
                </button>
            </div>
            
            <div class="table-responsive">
                <table class="table table-dark table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ชื่อห้องเรียน</th>
                            <th>หมายเหตุ</th>
                            <th style="width: 200px;">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($classrooms) > 0): ?>
                            <?php foreach ($classrooms as $classroom): ?>
                                <tr>
                                    <td><?= htmlspecialchars($classroom['room_name']) ?></td>
                                    <td><?= htmlspecialchars($classroom['notes']) ?></td>
                                    <td>
                                        <button class="btn btn-warning btn-sm" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editClassroomModal" 
                                                data-id="<?= $classroom['id'] ?>" 
                                                data-room_name="<?= htmlspecialchars($classroom['room_name']) ?>" 
                                                data-notes="<?= htmlspecialchars($classroom['notes']) ?>">
                                            <i class="fas fa-pen me-1"></i> แก้ไข
                                        </button>
                                        
                                        <button class="btn btn-danger btn-sm" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#deleteClassroomModal"
                                                data-id="<?= $classroom['id'] ?>"
                                                data-room_name="<?= htmlspecialchars($classroom['room_name']) ?>">
                                            <i class="fas fa-trash me-1"></i> ลบ
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center py-4 text-muted">
                                    ยังไม่มีข้อมูลห้องเรียน
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addClassroomModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus-circle text-primary me-2"></i>เพิ่มห้องเรียนใหม่</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="add_classroom_process.php" method="POST">
                    <div class="mb-3">
                        <label for="roomName" class="form-label">ชื่อห้องเรียน</label>
                        <input type="text" class="form-control" id="roomName" name="room_name" required placeholder="เช่น CED-DE-RA,TCT-DE-RB">
                    </div>
                    <div class="mb-3">
                        <label for="notes" class="form-label">หมายเหตุ</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
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

<div class="modal fade" id="editClassroomModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit text-warning me-2"></i>แก้ไขห้องเรียน</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editClassroomForm" action="edit_classroom_process.php" method="POST">
                    <input type="hidden" id="editClassroomId" name="id">
                    <div class="mb-3">
                        <label for="editRoomName" class="form-label">ชื่อห้องเรียน</label>
                        <input type="text" class="form-control" id="editRoomName" name="room_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="editNotes" class="form-label">หมายเหตุ</label>
                        <textarea class="form-control" id="editNotes" name="notes" rows="3"></textarea>
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

<div class="modal fade" id="deleteClassroomModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-white text-dark border-bottom">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle text-danger me-2"></i>ยืนยันการลบ
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-white text-dark">
                <p>คุณต้องการลบห้องเรียน <strong id="deleteRoomName" class="text-danger"></strong> ใช่หรือไม่?</p>
                <small class="text-muted">การกระทำนี้ไม่สามารถย้อนกลับได้</small>
            </div>
            <div class="modal-footer bg-white border-top">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> ยกเลิก
                </button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger">
                    <i class="fas fa-check me-1"></i> ยืนยันลบ
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

    // Script จัดการ Modal แก้ไข
    const editClassroomModal = document.getElementById('editClassroomModal');
    editClassroomModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const id = button.getAttribute('data-id');
        const roomName = button.getAttribute('data-room_name');
        const notes = button.getAttribute('data-notes');

        document.getElementById('editClassroomId').value = id;
        document.getElementById('editRoomName').value = roomName;
        document.getElementById('editNotes').value = notes;

        // 🟢 ส่ง Log ว่าเตรียมแก้ไข
        logUserAction('Click Button', `ผู้ใช้กดปุ่ม [แก้ไข] เตรียมแก้ไขห้องเรียน: ${roomName}`);
    });

    // Script จัดการ Modal ลบ
    const deleteClassroomModal = document.getElementById('deleteClassroomModal');
    deleteClassroomModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const id = button.getAttribute('data-id');
        const roomName = button.getAttribute('data-room_name');

        // แสดงชื่อห้องที่จะลบใน Modal
        document.getElementById('deleteRoomName').textContent = roomName;

        // กำหนด Link ให้ปุ่มยืนยัน
        const confirmBtn = document.getElementById('confirmDeleteBtn');
        confirmBtn.href = 'delete_classroom.php?id=' + id;

        // 🟢 ส่ง Log ว่าเตรียมลบ
        logUserAction('Click Button', `ผู้ใช้กดปุ่ม [ลบ] เตรียมลบห้องเรียน: ${roomName}`);
    });
</script>
</body>
</html>