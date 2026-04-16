<?php // courses.php - หน้าจัดการวิชา
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

// 🟢 บันทึก Log: ใครเปิดเข้ามาดูหน้าจัดการวิชาบ้าง
if (function_exists('writeLog')) {
    writeLog(
        $conn, 
        $_SESSION['user_id'], 
        $user_info['name'], 
        'View Page', 
        'เปิดดูหน้าจัดการวิชา (courses.php)'
    );
}

// 2. ดึงข้อมูลวิชา (กรองตาม user_id เสมอ ไม่ว่าจะเป็น role อะไร)
$sql_courses = "SELECT c.id, c.course_code, c.course_name, c.credits, c.teaching_hours, c.teacher_id, t.name AS teacher_name 
                FROM courses c 
                LEFT JOIN teachers t ON c.teacher_id = t.id 
                WHERE c.user_id = ? 
                ORDER BY c.id DESC";
$stmt_courses = $conn->prepare($sql_courses);
$stmt_courses->execute([$_SESSION['user_id']]);
$courses = $stmt_courses->fetchAll(PDO::FETCH_ASSOC);

// 3. ดึงข้อมูลอาจารย์ (เฉพาะของตัวเอง)
$sql_teachers = "SELECT id, name FROM teachers WHERE user_id = ?";
$stmt_teachers = $conn->prepare($sql_teachers);
$stmt_teachers->execute([$_SESSION['user_id']]);
$teachers = $stmt_teachers->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Courses</title>
    <link rel="icon" type="image/png" href="img/FTE.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(to right, #0f2027, #203a43, #2c5364); color: #f8f9fa; }
        .sidebar { background: #212529; height: auto; padding-top: 5px; width: 280px; flex-shrink: 0; }
        .sidebar .nav-link { color: #f8f9fa; padding: 15px; height: 50px; margin-bottom: 5px; border-radius: 8px; transition: background-color 0.3s; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background-color: #0d6efd; color: white; }
        .profile-card { background: rgba(255, 255, 255, 0.1); padding: 5px; border-radius: 20px; margin-bottom: 10px; text-align: center; }
        .content { padding: 5px; flex-grow: 1; overflow-y: auto; }
        .main-card { background: rgba(255, 255, 255, 0.1); border-radius: 10px; padding: 10px; }
        .modal-content { background-color: #fff; color: #333; }
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
                <span class="badge bg-danger mt-2"><i class="fas fa-crown me-1"></i> ADMIN</span>
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
            <li><a href="courses.php" class="nav-link active"><i class="fas fa-book me-2"></i> วิชา</a></li>
            <li><a href="rooms.php" class="nav-link"><i class="fas fa-school me-2"></i> หมายเลขห้องเรียน</a></li>
            <li><a href="classrooms.php" class="nav-link"><i class="fas fa-building me-2"></i> ห้องเรียน</a></li>
            <li><a href="curriculums.php" class="nav-link"><i class="fas fa-graduation-cap me-2"></i> หลักสูตร</a></li>
            <li><a href="logout.php" class="nav-link"><i class="fas fa-sign-out-alt me-2 text-danger"></i>ออกจากระบบ</a></li>
        </ul>
    </div>

    <div class="content flex-grow-1">
        <div class="main-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>วิชา</h1>
                <div>
                    <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#importCourseModal" onclick="logUserAction('Click Button', 'ผู้ใช้กดปุ่ม [นำเข้า CSV วิชา]')">
                        <i class="fas fa-download me-2"></i> นำเข้า CSV
                    </button>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCourseModal" onclick="logUserAction('Click Button', 'ผู้ใช้กดปุ่ม [เพิ่มวิชา]')">
                        <i class="fas fa-plus me-2"></i> เพิ่มวิชา
                    </button>
                </div>
            </div>

            <div class="mb-4">
                <input type="text" id="courseSearch" class="form-control form-control-dark" placeholder="ค้นหา รหัสวิชา หรือ ชื่อวิชา..." style="background: rgba(255, 255, 255, 0.1); color: #f8f9fa; border-color: rgba(255, 255, 255, 0.3);">
            </div>

            <div class="table-responsive">
                <table class="table table-dark table-striped table-hover" id="coursesTable">
                    <thead>
                        <tr>
                            <th>รหัสวิชา</th>
                            <th>ชื่อวิชา</th>
                            <th>หน่วยกิต</th>
                            <th>ชั่วโมงเรียน</th>
                            <th>อาจารย์ผู้สอน</th>
                            <th>การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($courses) > 0): ?>
                            <?php foreach ($courses as $course): ?>
                                <tr>
                                    <td><?= htmlspecialchars($course['course_code']) ?></td>
                                    <td><?= htmlspecialchars($course['course_name']) ?></td>
                                    <td><?= htmlspecialchars($course['credits']) ?></td>
                                    <td><?= htmlspecialchars($course['teaching_hours']) ?></td>
                                    <td><?= htmlspecialchars($course['teacher_name'] ?? 'ยังไม่ได้กำหนด') ?></td>
                                    <td>
                                        <button class="btn btn-warning btn-sm" onclick="openEditModal(<?= $course['id'] ?>)">
                                            <i class="fas fa-pen me-1"></i> แก้ไข
                                        </button>
                                        <button class="btn btn-danger btn-sm" onclick="confirmDelete(<?= $course['id'] ?>, '<?= htmlspecialchars($course['course_code']) ?>')">
                                            <i class="fas fa-trash me-1"></i> ลบ
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr id="noCourseRow">
                                <td colspan="6" class="text-center py-4 text-muted">ยังไม่มีข้อมูลวิชา</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addCourseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header border-bottom">
                <h5 class="modal-title text-dark">เพิ่มวิชาใหม่</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="add_course_main_process.php" method="POST">
                <div class="modal-body text-dark">
                    <div class="mb-3">
                        <label class="form-label">รหัสวิชา</label>
                        <input type="text" class="form-control" name="course_code" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">ชื่อวิชา</label>
                        <input type="text" class="form-control" name="course_name" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">หน่วยกิต</label>
                            <input type="number" class="form-control" name="credits" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ชั่วโมง/สัปดาห์</label>
                            <input type="number" class="form-control" name="teaching_hours" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">อาจารย์ผู้สอน</label>
                        <select class="form-select" name="teacher_id">
                            <option value="">เลือกอาจารย์ผู้สอน</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?= $teacher['id'] ?>"><?= htmlspecialchars($teacher['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary">บันทึก</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editCourseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header border-bottom">
                <h5 class="modal-title text-dark">แก้ไขข้อมูลวิชา</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editCourseForm" action="edit_course_process.php" method="POST">
                <input type="hidden" name="course_id" id="editCourseId">
                <div class="modal-body text-dark">
                    <div class="mb-3">
                        <label class="form-label">รหัสวิชา</label>
                        <input type="text" class="form-control" name="course_code" id="editCourseCode" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">ชื่อวิชา</label>
                        <input type="text" class="form-control" name="course_name" id="editCourseName" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">หน่วยกิต</label>
                            <input type="number" class="form-control" name="credits" id="editCredits" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ชั่วโมง/สัปดาห์</label>
                            <input type="number" class="form-control" name="teaching_hours" id="editTeachingHours" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">อาจารย์ผู้สอน</label>
                        <select class="form-select" name="teacher_id" id="editTeacherId">
                            <option value="">เลือกอาจารย์ผู้สอน</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?= $teacher['id'] ?>"><?= htmlspecialchars($teacher['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary">บันทึก</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="importCourseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header border-bottom">
                <h5 class="modal-title text-dark">นำเข้าข้อมูลวิชา (CSV)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-dark">
                <div class="alert alert-info">
                    <strong>รูปแบบ CSV:</strong><br>
                    รหัสวิชา, ชื่อวิชา, หน่วยกิต, ชั่วโมงเรียน<br>
                    <small>ตัวอย่าง: CS101, โปรแกรมเบื้องต้น, 3, 4</small>
                </div>
                <form id="importCourseForm" enctype="multipart/form-data">
                    <input type="file" class="form-control" id="csvFile" name="csvFile" accept=".csv" required>
                    <div id="importMessage" class="mt-3"></div>
                </form>
            </div>
            <div class="modal-footer border-top">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="button" class="btn btn-success" onclick="importCourses()">นำเข้า</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// 🟢 ฟังก์ชันส่ง Log พฤติกรรมผ่าน AJAX ไปยัง Path ใหม่
function logUserAction(action, details) {
    fetch('log/ajax_log_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=${encodeURIComponent(action)}&details=${encodeURIComponent(details)}`
    });
}

const coursesData = <?php echo json_encode($courses); ?>;

// 🟢 บันทึก Log การลบ (ระบุรหัสวิชา)
function confirmDelete(id, code) {
    logUserAction('Click Button', `ผู้ใช้กดปุ่ม [ลบ] เตรียมลบวิชารหัส: ${code}`);

    Swal.fire({
        title: 'ยืนยันการลบ',
        text: `คุณต้องการลบวิชา ${code} ใช่หรือไม่?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'ยืนยันลบ',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `delete_course.php?id=${id}`;
        } else {
            logUserAction('Cancel Action', `ผู้ใช้กดยกเลิกการลบวิชา: ${code}`);
        }
    });
}

// 🟢 บันทึก Log การเปิดแก้ไข (ระบุวิชา)
function openEditModal(courseId) {
    const course = coursesData.find(c => c.id == courseId);
    if (!course) return;

    logUserAction('Open Modal', `ผู้ใช้เปิดหน้าต่างแก้ไขข้อมูลวิชา: ${course.course_code} (${course.course_name})`);

    document.getElementById('editCourseId').value = course.id;
    document.getElementById('editCourseCode').value = course.course_code;
    document.getElementById('editCourseName').value = course.course_name;
    document.getElementById('editCredits').value = course.credits;
    document.getElementById('editTeachingHours').value = course.teaching_hours;
    document.getElementById('editTeacherId').value = course.teacher_id || '';
    
    var editModal = new bootstrap.Modal(document.getElementById('editCourseModal'));
    editModal.show();
}

// 🟢 บันทึก Log การนำเข้า CSV (ระบุชื่อไฟล์และสถานะ)
function importCourses() {
    const fileInput = document.getElementById('csvFile');
    if (!fileInput.files.length) {
        Swal.fire('แจ้งเตือน', 'กรุณาเลือกไฟล์ CSV ก่อน', 'warning');
        return;
    }

    const fileName = fileInput.files[0].name;
    logUserAction('Submit Form', `ผู้ใช้กดยืนยันนำเข้าไฟล์ CSV: ${fileName}`);

    const formData = new FormData();
    formData.append('csvFile', fileInput.files[0]);

    const msg = document.getElementById('importMessage');
    msg.className = 'alert alert-info';
    msg.innerHTML = 'กำลังนำเข้า...';

    fetch('import_courses.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            logUserAction('Process Success', `นำเข้าข้อมูลวิชาจากไฟล์ ${fileName} สำเร็จ (${data.imported} รายการ)`);
            Swal.fire({ icon: 'success', title: 'สำเร็จ', timer: 2000 }).then(() => location.reload());
        } else {
            logUserAction('Process Failed', `นำเข้าไฟล์ ${fileName} ไม่สำเร็จ: ${data.message || 'Error'}`);
            msg.className = 'alert alert-danger';
            msg.innerHTML = data.message || data.errors.join('<br>');
        }
    })
    .catch(err => {
        logUserAction('System Error', `เกิดข้อผิดพลาดในการเชื่อมต่อขณะนำเข้า CSV: ${err}`);
        msg.className = 'alert alert-danger';
        msg.innerHTML = 'เกิดข้อผิดพลาดในการเชื่อมต่อ';
    });
}

// 🟢 บันทึก Log การค้นหา (หน่วงเวลา 1 วิ)
let searchTimeout;
document.getElementById('courseSearch').addEventListener('keyup', function() {
    const filter = this.value.trim();
    
    clearTimeout(searchTimeout);
    if(filter.length > 0) {
        searchTimeout = setTimeout(() => {
            logUserAction('Search Data', `ผู้ใช้ค้นหาคำว่า: "${filter}" ในหน้าจัดการวิชา`);
        }, 1000);
    }

    const rows = document.querySelectorAll('#coursesTable tbody tr');
    let found = false;
    const noCourseRow = document.getElementById('noCourseRow');

    rows.forEach(row => {
        if (row.id === 'noCourseRow' || row.id === 'noResultRow') return;
        const codeText = row.cells[0]?.textContent.toLowerCase() || '';
        const nameText = row.cells[1]?.textContent.toLowerCase() || '';
        if (codeText.includes(filter.toLowerCase()) || nameText.includes(filter.toLowerCase())) {
            row.style.display = '';
            found = true;
        } else {
            row.style.display = 'none';
        }
    });

    let noResultRow = document.getElementById('noResultRow');
    if (!found && filter !== "") {
        if (!noResultRow) {
            const tbody = document.querySelector('#coursesTable tbody');
            noResultRow = tbody.insertRow();
            noResultRow.id = 'noResultRow';
            const cell = noResultRow.insertCell(0);
            cell.colSpan = 6;
            cell.className = 'text-center py-3 text-muted';
            cell.textContent = 'ไม่พบวิชาที่ค้นหา';
        }
        noResultRow.style.display = '';
        if(noCourseRow) noCourseRow.style.display = 'none';
    } else {
        if (noResultRow) noResultRow.style.display = 'none';
        if (noCourseRow && filter === "") noCourseRow.style.display = '';
    }
});
</script>
</body>
</html>