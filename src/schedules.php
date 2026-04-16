<?php //หน้าตารางสอน
session_start();
// ตรวจสอบว่าผู้ใช้ล็อกอินหรือไม่
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once 'auth_check.php';
require_once 'config/db.php';

// ดึงข้อมูลผู้ใช้ที่กำลังล็อกอิน (เพิ่ม role เพื่อใช้เช็คสิทธิ์แอดมิน)
$sql_user = "SELECT name, email, `group`, role FROM users WHERE id = ?";
$stmt_user = $conn->prepare($sql_user);
$stmt_user->execute([$_SESSION['user_id']]);
$user_info = $stmt_user->fetch(PDO::FETCH_ASSOC);

// ดึงรายการตารางสอนที่ผู้ใช้สร้างไว้
$sql_schedules = "SELECT id, schedule_name FROM schedules WHERE user_id = ?";
$stmt_schedules = $conn->prepare($sql_schedules);
$stmt_schedules->execute([$_SESSION['user_id']]);
$schedules = $stmt_schedules->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Teaching schedule</title>
    <link rel="icon" type="image/png" href="img/FTE.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        body { background: linear-gradient(to right, #0f2027, #203a43, #2c5364); color: #f8f9fa; min-height: 100vh; }
               /* สไตล์เดิมสำหรับ Sidebar */
        .sidebar { background: #212529; height: auto; padding-top: 5px; width: 280px; flex-shrink: 0; }
        .sidebar .nav-link { color: #f8f9fa; padding: 15px; height: 50px; margin-bottom: 5px; border-radius: 8px; transition: background-color 0.3s; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background-color: #0d6efd; color: white; }
        .profile-card { background: rgba(255, 255, 255, 0.1); padding: 5px; border-radius: 20px; margin-bottom: 10px; text-align: center; }
        .content { padding: 5px; flex-grow: 1; overflow-y: auto; }
        .main-card { background: rgba(255, 255, 255, 0.1); border-radius: 10px; padding: 10px; }
        .table { color: #f8f9fa; }
        
        /* ปรับแต่งปุ่มใน SweetAlert เล็กน้อยให้ดูทันสมัย */
        .swal2-popup { border-radius: 15px !important; font-family: 'Sarabun', sans-serif; }
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
            <small class="d-block mb-2"><?= htmlspecialchars($user_info['email']) ?></small>
            
            <?php if (($user_info['role'] ?? '') === 'admin'): ?>
                <span class="badge bg-danger"><i class="fas fa-crown me-1"></i> ADMIN</span>
            <?php else: ?>
                <span class="badge bg-primary"><?= htmlspecialchars($user_info['group']) ?></span>
            <?php endif; ?>
            </div>
        <ul class="nav nav-pills flex-column mb-auto">
            <li class="nav-item"><a href="home.php" class="nav-link"><i class="fas fa-home me-2"></i> หน้าหลัก</a></li>
            <li class="nav-item"><a href="schedules.php" class="nav-link active"><i class="fas fa-chalkboard me-2"></i> ตารางสอน</a></li>
            <div class="d-flex align-items-center px-1 mt-1 mb-1" style="line-height: 1.2;">
                <div class="flex-grow-1 border-top border-light" style="opacity: 0.4;"></div>
                    <span class="text-white px-2" style="font-size: 0.7rem; font-weight: bold; letter-spacing: 0.5px; text-transform: uppercase;"> เพิ่มข้อมูลพื้นฐาน</span>
                 <div class="flex-grow-1 border-top border-light" style="opacity: 0.4;"></div>
            </div>
            <li><a href="teachers.php" class="nav-link"><i class="fas fa-users me-2"></i> รายชื่ออาจารย์</a></li>
            <li><a href="courses.php" class="nav-link"><i class="fas fa-book me-2"></i> วิชา</a></li>
            <li><a href="rooms.php" class="nav-link"><i class="fas fa-school me-2"></i> หมายเลขห้องเรียน</a></li>
            <li><a href="classrooms.php" class="nav-link"><i class="fas fa-building me-2"></i> ห้องเรียน</a></li>
            <li><a href="curriculums.php" class="nav-link"><i class="fas fa-graduation-cap me-2"></i> หลักสูตร</a></li>
            <li><a href="logout.php" class="nav-link"><i class="fas fa-sign-out-alt me-2 text-danger"></i>ออกจากระบบ</a></li>
        </ul>
    </div>

    <div class="content flex-grow-1">
        <div class="main-card shadow">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><i class="fas fa-calendar-alt me-2"></i>ตารางสอน</h1>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
                    <i class="fas fa-plus me-2"></i> เพิ่มตารางสอน
                </button>
            </div>
            
            <div class="mb-4">
                <div class="input-group">
                    <span class="input-group-text bg-transparent border-secondary text-white"><i class="fas fa-search"></i></span>
                    <input type="text" id="scheduleSearch" class="form-control" placeholder="ค้นหา ชื่อตารางสอน..." style="background: rgba(255, 255, 255, 0.1); color: #f8f9fa; border-left: none; border-color: rgba(255, 255, 255, 0.3);">
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-dark table-hover" id="schedulesTable">
                    <thead>
                        <tr>
                            <th width="10%">ลำดับที่</th>
                            <th width="60%">ชื่อตารางสอน</th>
                            <th width="30%" class="text-center">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($schedules) > 0): ?>
                            <?php foreach ($schedules as $index => $schedule): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><?= htmlspecialchars($schedule['schedule_name']) ?></td>
                                    <td class="text-center">
                                        <a href="schedule_builder.php?id=<?= $schedule['id'] ?>" class="btn btn-info btn-sm me-1">
                                            <i class="fas fa-edit me-1"></i> จัดการ
                                        </a>
                                        <button type="button" 
                                                class="btn btn-danger btn-sm btn-delete" 
                                                data-id="<?= $schedule['id'] ?>" 
                                                data-name="<?= htmlspecialchars($schedule['schedule_name']) ?>">
                                            <i class="fas fa-trash me-1"></i> ลบ
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr id="noScheduleRow">
                                <td colspan="3" class="text-center py-4 text-muted">ยังไม่มีตารางสอนในระบบ</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addScheduleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content text-dark">
            <div class="modal-header border-bottom-0">
                <h5 class="modal-title font-weight-bold">เพิ่มตารางสอนใหม่</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="add_schedule_process.php" method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="scheduleName" class="form-label font-weight-bold">ชื่อตารางสอน</label>
                        <input type="text" class="form-control" id="scheduleName" name="schedule_name" placeholder="ระบุชื่อตารางสอน เช่น ภาคเรียนที่ 1/2567" required>
                    </div>
                </div>
                <div class="modal-footer border-top-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary px-4">บันทึก</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// ** ระบบยืนยันการลบ (SweetAlert2 - โทนสีขาว) **
document.querySelectorAll('.btn-delete').forEach(button => {
    button.addEventListener('click', function() {
        const scheduleId = this.getAttribute('data-id');
        const scheduleName = this.getAttribute('data-name');

        Swal.fire({
            title: 'ยืนยันการลบตารางสอน',
            html: `คุณกำลังจะลบตาราง: <b>${scheduleName}</b><br><small class="text-muted">ข้อมูลในตารางนี้จะหายไปทั้งหมดและไม่สามารถกู้คืนได้</small>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',     // สีแดงสำหรับลบ
            cancelButtonColor: '#6c757d',   // สีเทาสำหรับยกเลิก
            confirmButtonText: 'ยืนยันการลบ',
            cancelButtonText: 'ยกเลิก',
            // ปรับแต่งสี Popup เป็นโทนขาว
            background: '#ffffff',
            color: '#212529',
            iconColor: '#f8bb86'
        }).then((result) => {
            if (result.isConfirmed) {
                // ส่งไปยังไฟล์ลบข้อมูล
                window.location.href = `delete_schedule.php?id=${scheduleId}`;
            }
        });
    });
});

// ** ฟังก์ชันค้นหาตารางสอน **
document.getElementById('scheduleSearch').addEventListener('keyup', function() {
    const filter = this.value.toLowerCase();
    const tableBody = document.querySelector('#schedulesTable tbody');
    const rows = tableBody.getElementsByTagName('tr');
    let found = false;
    const noScheduleRow = document.getElementById('noScheduleRow');

    for (let i = 0; i < rows.length; i++) {
        if (rows[i].id === 'noScheduleRow' || rows[i].id === 'noResultRow') continue;

        const nameCell = rows[i].getElementsByTagName('td')[1];
        if (nameCell) {
            const nameText = nameCell.textContent || nameCell.innerText;
            if (nameText.toLowerCase().indexOf(filter) > -1) {
                rows[i].style.display = "";
                found = true;
            } else {
                rows[i].style.display = "none";
            }
        }       
    }

    let noResultRow = document.getElementById('noResultRow');
    if (!found && filter !== "") {
        if (!noResultRow) {
            noResultRow = tableBody.insertRow();
            noResultRow.id = 'noResultRow';
            const cell = noResultRow.insertCell(0);
            cell.colSpan = 3;
            cell.className = 'text-center py-4 text-muted';
            cell.innerHTML = 'ไม่พบตารางสอนที่ค้นหา';
        }
        noResultRow.style.display = "";
        if (noScheduleRow) noScheduleRow.style.display = "none";
    } else {
        if (noResultRow) noResultRow.style.display = "none";
        if (noScheduleRow && filter === "") noScheduleRow.style.display = "";
    }
});
</script>
</body>
</html>