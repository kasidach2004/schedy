<?php
// rooms.php (หมายเลขห้องเรียน)
session_start();
// 1. ตรวจสอบการล็อกอิน
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/db.php';

// ดึงข้อมูลผู้ใช้สำหรับ Profile Card
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
        'เปิดดูหน้าหมายเลขห้องเรียน (rooms.php)'
    );
}

// 2. จัดการการลบห้องเรียน
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    
    // ดึงชื่อห้องก่อนลบเพื่อเก็บ Log
    $stmt_check = $conn->prepare("SELECT room_number FROM rooms WHERE room_id = ? AND user_id = ?");
    $stmt_check->execute([$delete_id, $_SESSION['user_id']]);
    $deleted_room = $stmt_check->fetchColumn();

    // ลบได้เฉพาะห้องที่ user_id ตรงกับคนล็อกอินเท่านั้น
    try {
        $sql_delete = "DELETE FROM rooms WHERE room_id = ? AND user_id = ?";
        $stmt_delete = $conn->prepare($sql_delete);
        
        if ($stmt_delete->execute([$delete_id, $_SESSION['user_id']]) && $deleted_room) {
            // 🟢 บันทึก Log: ทำการลบห้องเรียนสำเร็จ
            if (function_exists('writeLog')) {
                writeLog($conn, $_SESSION['user_id'], $user_info['name'], 'Delete Room', 'ลบหมายเลขห้องเรียน: ' . $deleted_room);
            }
        }
    } catch (PDOException $e) {
        // หากเกิดข้อผิดพลาด
    }
    
    header("Location: rooms.php");
    exit;
}

// 3. จัดการการเพิ่มห้องเรียนใหม่
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['room_number'])) {
    $room_number = trim($_POST['room_number']);
    $notes = trim($_POST['notes']); // อาจเป็นค่าว่างได้
    
    if (!empty($room_number)) {
        try {
            $sql_insert = "INSERT INTO rooms (room_number, notes, user_id) VALUES (?, ?, ?)";
            $stmt_insert = $conn->prepare($sql_insert);
            
            if ($stmt_insert->execute([$room_number, $notes, $_SESSION['user_id']])) {
                // 🟢 บันทึก Log: ทำการเพิ่มห้องเรียนสำเร็จ
                if (function_exists('writeLog')) {
                    writeLog($conn, $_SESSION['user_id'], $user_info['name'], 'Add Room', 'เพิ่มหมายเลขห้องเรียนใหม่: ' . $room_number);
                }
            }
        } catch (PDOException $e) {
            // เกิดข้อผิดพลาด
        }
        
        header("Location: rooms.php");
        exit;
    }
}

// 4. ดึงรายการห้องเรียน - ให้เห็นเฉพาะของตัวเองเท่านั้น (รวมถึง Admin)
$sql_rooms = "SELECT room_id, room_number, notes FROM rooms WHERE user_id = ? ORDER BY room_number";
$stmt_rooms = $conn->prepare($sql_rooms);
$stmt_rooms->execute([$_SESSION['user_id']]);
$rooms = $stmt_rooms->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Classroom number</title>
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
        
        /* Modal White Tone Styles */
        .modal-content {
            background-color: #ffffff;
            color: #333333; /* บังคับตัวหนังสือสีเข้ม เพราะ Body เป็นสีขาว */
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.5);
        }
        .modal-header { border-bottom: 1px solid #dee2e6; }
        .modal-footer { border-top: 1px solid #dee2e6; }
        .modal-body label { color: #333; font-weight: 500; }
        
        /* Fix input colors in modal */
        .form-control {
            background-color: #fff;
            color: #212529;
            border: 1px solid #ced4da;
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
            <li><a href="rooms.php" class="nav-link active"><i class="fas fa-school me-2"></i> หมายเลขห้องเรียน</a></li>
            <li><a href="classrooms.php" class="nav-link"><i class="fas fa-building me-2"></i> ห้องเรียน</a></li>
            <li><a href="curriculums.php" class="nav-link"><i class="fas fa-graduation-cap me-2"></i> หลักสูตร</a></li>
            <li><a href="logout.php" class="nav-link"><i class="fas fa-sign-out-alt me-2 text-danger"></i>ออกจากระบบ</a></li>
        </ul>
    </div>
    
    <div class="content flex-grow-1">
        <div class="main-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>หมายเลขห้องเรียน</h1>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRoomModal" onclick="logUserAction('Click Button', 'ผู้ใช้กดปุ่ม [เพิ่มหมายเลขห้องเรียน]')">
                    <i class="fas fa-plus me-2"></i> เพิ่มห้องเรียน
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-dark table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>หมายเลขห้อง</th>
                            <th>หมายเหตุ</th>
                            <th>การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($rooms) > 0): ?>
                            <?php foreach ($rooms as $index => $room): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><?= htmlspecialchars($room['room_number']) ?></td>
                                    <td><?= htmlspecialchars($room['notes']) ?></td>
                                    <td>
                                        <button class="btn btn-danger btn-sm" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#deleteRoomModal"
                                                data-id="<?= $room['room_id'] ?>"
                                                data-number="<?= htmlspecialchars($room['room_number']) ?>">
                                            <i class="fas fa-trash"></i> ลบ
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center">ยังไม่มีหมายเลขห้องเรียน</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addRoomModal" tabindex="-1" aria-labelledby="addRoomModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-dark" id="addRoomModalLabel">เพิ่มหมายเลขห้องเรียนใหม่</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="rooms.php" method="POST">
                    <div class="mb-3">
                        <label for="roomNumber" class="form-label text-dark">หมายเลขห้อง (เช่น 1101, 2205)</label>
                        <input type="text" class="form-control" id="roomNumber" name="room_number" required>
                    </div>
                    <div class="mb-3">
                        <label for="notes" class="form-label text-dark">หมายเหตุ (เช่น ห้องปฏิบัติการคอมพิวเตอร์)</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary">บันทึก</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteRoomModal" tabindex="-1" aria-labelledby="deleteRoomModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger" id="deleteRoomModalLabel">
                    <i class="fas fa-exclamation-triangle me-2"></i>ยืนยันการลบ
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>คุณต้องการลบห้อง <strong id="deleteRoomName" class="text-primary"></strong> ใช่หรือไม่?</p>
                <small class="text-muted">การกระทำนี้ไม่สามารถย้อนกลับได้</small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger">ยืนยันลบ</a>
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

    // สคริปต์สำหรับส่งข้อมูลไปที่ Modal ลบ
    const deleteRoomModal = document.getElementById('deleteRoomModal')
    deleteRoomModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget
        const roomId = button.getAttribute('data-id')
        const roomNumber = button.getAttribute('data-number')
        
        // อัปเดตข้อความใน Modal
        const modalTitle = deleteRoomModal.querySelector('#deleteRoomName')
        modalTitle.textContent = roomNumber
        
        // อัปเดต Link ปุ่มยืนยัน
        const confirmBtn = deleteRoomModal.querySelector('#confirmDeleteBtn')
        confirmBtn.href = 'rooms.php?delete_id=' + roomId

        // 🟢 ส่ง Log ว่าเตรียมลบ
        logUserAction('Click Button', `ผู้ใช้กดปุ่ม [ลบ] เตรียมลบหมายเลขห้องเรียน: ${roomNumber}`);
    })
</script>
</body>
</html>