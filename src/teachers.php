<?php //หน้ารายชื่ออาจารย์
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}


require_once 'config/db.php';

// ดึงข้อมูลผู้ใช้ที่กำลังล็อกอิน
$sql_user = "SELECT name, email, `group`, role FROM users WHERE id = ?";
$stmt_user = $conn->prepare($sql_user);
$stmt_user->execute([$_SESSION['user_id']]);
$user_info = $stmt_user->fetch(PDO::FETCH_ASSOC);

// 🟢 เพิ่มเช็คตรงนี้: ถ้าไม่พบข้อมูล User (เช่น ไอดีนี้โดนลบไปแล้ว)
if (!$user_info) {
    session_destroy(); // ล้าง Session เก่าทิ้ง
    header("Location: login.php"); // เด้งกลับไปหน้าล็อกอิน
    exit;
}

// 🟢 บันทึก Log: เปิดเข้ามาดูหน้ารายชื่ออาจารย์

// --- ส่วนที่แก้ไข (ยืนยัน Logic): ทุกคนเห็นเฉพาะของตัวเอง ---
$sql_teachers = "SELECT id, name, initials, max_load, notes, user_id FROM teachers WHERE user_id = ? ORDER BY id DESC";
$stmt_teachers = $conn->prepare($sql_teachers);
$stmt_teachers->execute([$_SESSION['user_id']]);
$teachers = $stmt_teachers->fetchAll(PDO::FETCH_ASSOC);
// ------------------

// ==========================================
// 🟢 เพิ่มใหม่: คำนวณภาระโหลดที่ใช้ไปแล้วแบบ Real-time จาก schedule_items
// ==========================================
$teacher_used_loads = [];

// ดึงวิชาทั้งหมดที่ถูกจัดลงตารางสอนแล้ว ของ User คนนี้
$sql_items = "
    SELECT si.credits, si.teacher_ids 
    FROM schedule_items si
    JOIN schedules s ON si.schedule_id = s.id
    WHERE s.user_id = ?
";
$stmt_items = $conn->prepare($sql_items);
$stmt_items->execute([$_SESSION['user_id']]);
$schedule_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

foreach ($schedule_items as $item) {
    // แปลงข้อมูล teacher_ids จาก JSON เป็น Array
    $t_ids = json_decode($item['teacher_ids'], true);
    
    if (is_array($t_ids) && count($t_ids) > 0) {
        // 🟢 แก้ไขใหม่: ภาระงานไม่ต้องหารเฉลี่ยแล้ว ทุกคนที่ถูกเลือกจะได้ภาระงานเต็มๆ
        $load_per_head = floatval($item['credits']);
        
        foreach ($t_ids as $tid) {
            if (!isset($teacher_used_loads[$tid])) {
                $teacher_used_loads[$tid] = 0;
            }
            $teacher_used_loads[$tid] += $load_per_head;
        }
    }
}
// ==========================================
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Lecturers</title>
    <link rel="icon" type="image/png" href="img/FTE.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { background: linear-gradient(to right, #0f2027, #203a43, #2c5364); color: #f8f9fa; }
        .sidebar { background: #212529; height: auto; padding-top: 5px; width: 280px; flex-shrink: 0; }
        .sidebar .nav-link { color: #f8f9fa; padding: 15px; height: 50px; margin-bottom: 5px; border-radius: 8px; transition: background-color 0.3s; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background-color: #0d6efd; color: white; }
        .profile-card { background: rgba(255, 255, 255, 0.1); padding: 5px; border-radius: 20px; margin-bottom: 10px; text-align: center; }
        .content { padding: 5px; flex-grow: 1; overflow-y: auto; }
        .main-card { background: rgba(255, 255, 255, 0.1); border-radius: 10px; padding: 10px; }
        
        .modal-content.white-theme {
            background-color: #ffffff;
            color: #333333;
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }
        .modal-header.white-theme { border-bottom: 1px solid #dee2e6; }
        .modal-footer.white-theme { border-top: 1px solid #dee2e6; }
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
            <li><a href="teachers.php" class="nav-link active"><i class="fas fa-users me-2"></i> รายชื่ออาจารย์</a></li>
            <li><a href="courses.php" class="nav-link"><i class="fas fa-book me-2"></i> วิชา</a></li>
            <li><a href="rooms.php" class="nav-link"><i class="fas fa-school me-2"></i> หมายเลขห้องเรียน</a></li>
            <li><a href="classrooms.php" class="nav-link"><i class="fas fa-building me-2"></i> ห้องเรียน</a></li>
            <li><a href="curriculums.php" class="nav-link"><i class="fas fa-graduation-cap me-2"></i> หลักสูตร</a></li>
            <li><a href="logout.php" class="nav-link"><i class="fas fa-sign-out-alt me-2 text-danger"></i>ออกจากระบบ</a></li>
        </ul>
    </div>
    <div class="content flex-grow-1">
        <div class="main-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>รายชื่ออาจารย์</h1>
                <div>
                    <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#importTeacherModal" onclick="logUserAction('Click Button', 'ผู้ใช้กดปุ่ม [นำเข้า CSV]')">
                        <i class="fas fa-download me-2"></i> นำเข้า CSV
                    </button>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTeacherModal" onclick="logUserAction('Click Button', 'ผู้ใช้กดปุ่ม [เพิ่มอาจารย์]')">
                        <i class="fas fa-plus me-2"></i> เพิ่มอาจารย์
                    </button>
                </div>
            </div>
            
            <div class="mb-4">
                <input type="text" id="teacherSearch" class="form-control form-control-dark" placeholder="ค้นหา ชื่ออาจารย์ หรือ ชื่อย่อ..." style="background: rgba(255, 255, 255, 0.1); color: #f8f9fa; border-color: rgba(255, 255, 255, 0.3);">
            </div>
            <table class="table table-dark table-striped" id="teachersTable">
                <thead>
                    <tr>
                        <th>ชื่ออาจารย์</th>
                        <th>ชื่อย่อ</th>
                        <th>Max โหลด</th>
                        <th>ใช้ไปแล้ว</th>
                        <th>คงเหลือ</th>
                        <th>เงื่อนไข</th>
                        <th>การจัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($teachers) > 0): ?>
                        <?php foreach ($teachers as $teacher): ?>
                            <?php 
                                // ดึงค่าโหลดที่ใช้ไปแล้วมาคำนวณ
                                $used_load = isset($teacher_used_loads[$teacher['id']]) ? $teacher_used_loads[$teacher['id']] : 0;
                                $max_load = floatval($teacher['max_load']);
                                $remaining = $max_load - $used_load;
                                
                                // ตกแต่งสีข้อความ กรณีติดลบ (เกินภาระงาน) ให้เป็นสีแดง
                                $remaining_class = ($remaining < 0) ? 'text-danger fw-bold' : 'text-success fw-bold';
                                $status_text = ($remaining < 0) ? ' (เกิน!)' : '';
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($teacher['name']) ?></td>
                                <td><?= htmlspecialchars($teacher['initials']) ?></td>
                                <td><?= $max_load ?></td>
                                <td class="text-warning"><?= number_format($used_load, 2) ?></td>
                                <td class="<?= $remaining_class ?>">
                                    <?= number_format($remaining, 2) ?><?= $status_text ?>
                                </td>
                                <td><?= htmlspecialchars($teacher['notes']) ?></td>
                                <td>
                                    <a href="#" onclick="openEditTeacherModal(<?= $teacher['id'] ?>); return false;" class="btn btn-warning btn-sm"><i class="fas fa-pen me-1"></i> แก้ไข</a>
                                    <button type="button" class="btn btn-danger btn-sm" onclick="confirmDelete(<?= $teacher['id'] ?>, '<?= htmlspecialchars($teacher['name']) ?>')">
                                        <i class="fas fa-trash me-1"></i> ลบ
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr id="noTeacherRow">
                            <td colspan="7" class="text-center">ยังไม่มีข้อมูลอาจารย์</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="importTeacherModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content white-theme">
            <div class="modal-header white-theme">
                <h5 class="modal-title">นำเข้าข้อมูลอาจารย์จาก CSV</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="importMessage"></div>
                <form id="importForm" action="import_teachers.php" method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">เลือกไฟล์ CSV</label>
                        <input type="file" class="form-control" name="csv_file" accept=".csv" required>
                        <small class="text-muted">รูปแบบ: ชื่อ,ชื่อย่อ,ภาระโหลด,เงื่อนไข</small>
                    </div>
                    <button type="submit" class="btn btn-success">นำเข้า</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addTeacherModal" tabindex="-1" aria-labelledby="addTeacherModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content white-theme">
            <div class="modal-header white-theme">
                <h5 class="modal-title" id="addTeacherModalLabel">เพิ่มอาจารย์ใหม่</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="add_teacher_process.php" method="POST">
                    <div class="mb-3">
                        <label for="teacherName" class="form-label">ชื่ออาจารย์</label>
                        <input type="text" class="form-control" id="teacherName" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="teacherInitials" class="form-label">ชื่อย่ออาจารย์</label>
                        <input type="text" class="form-control" id="teacherInitials" name="initials" required>
                    </div>
                    <div class="mb-3">
                        <label for="maxLoad" class="form-label">ภาระโหลดสูงสุด (ชม.)</label>
                        <input type="number" class="form-control" id="maxLoad" name="max_load" required>
                    </div>
                    <div class="mb-3">
                        <label for="teacherNotes" class="form-label">เงื่อนไขพิเศษ</label>
                        <textarea class="form-control" id="teacherNotes" name="notes" rows="3"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">บันทึก</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editTeacherModal" tabindex="-1" aria-labelledby="editTeacherModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content white-theme">
      <div class="modal-header white-theme">
        <h5 class="modal-title" id="editTeacherModalLabel">แก้ไขข้อมูลอาจารย์</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="editTeacherForm" action="edit_teacher_process.php" method="POST">
        <input type="hidden" name="teacher_id" id="editTeacherId">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">ชื่ออาจารย์</label>
            <input type="text" class="form-control" name="name" id="editTeacherName" required>
          </div>
          <div class="mb-3">
            <label class="form-label">ชื่อย่อ</label>
            <input type="text" class="form-control" name="initials" id="editTeacherInitials" required>
          </div>
          <div class="mb-3">
            <label class="form-label">ภาระโหลด (ชม.)</label>
            <input type="number" class="form-control" name="max_load" id="editTeacherMaxLoad" required>
          </div>
          <div class="mb-3">
            <label class="form-label">เงื่อนไข</label>
            <textarea class="form-control" name="notes" id="editTeacherNotes" rows="3"></textarea>
          </div>
        </div>
        <div class="modal-footer white-theme">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn btn-primary">บันทึก</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content white-theme">
            <div class="modal-header white-theme">
                <h5 class="modal-title fw-bold text-danger" id="deleteConfirmModalLabel">
                    <i class="fas fa-exclamation-triangle me-2"></i>ยืนยันการลบ
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>คุณต้องการลบข้อมูลอาจารย์ <strong id="deleteTeacherName"></strong> ใช่หรือไม่?</p>
                <p class="text-muted small">การกระทำนี้ไม่สามารถเรียกคืนได้</p>
            </div>
            <div class="modal-footer white-theme">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> ยกเลิก
                </button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger">
                    <i class="fas fa-check me-1"></i> ยืนยัน
                </a>
            </div>
        </div>
    </div>
</div>

<script>
function logUserAction(action, details) {
    fetch('log/ajax_log_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=${encodeURIComponent(action)}&details=${encodeURIComponent(details)}`
    });
}

const teachersData = <?php echo json_encode($teachers); ?>;

function openEditTeacherModal(teacherId) {
    const teacher = teachersData.find(t => t.id == teacherId);
    if (!teacher) return;
    document.getElementById('editTeacherId').value = teacher.id;
    document.getElementById('editTeacherName').value = teacher.name;
    document.getElementById('editTeacherInitials').value = teacher.initials;
    document.getElementById('editTeacherMaxLoad').value = teacher.max_load;
    document.getElementById('editTeacherNotes').value = teacher.notes;
    
    logUserAction('Click Button', `ผู้ใช้กดปุ่ม [แก้ไข] ข้อมูลอาจารย์: ${teacher.name}`);
    
    var editModal = new bootstrap.Modal(document.getElementById('editTeacherModal'));
    editModal.show();
}

function confirmDelete(id, name) {
    document.getElementById('deleteTeacherName').innerText = name;
    document.getElementById('confirmDeleteBtn').setAttribute('href', 'delete_teacher.php?id=' + id);
    
    logUserAction('Click Button', `ผู้ใช้กดปุ่ม [ลบ] เตรียมลบข้อมูลอาจารย์: ${name}`);
    
    var deleteModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
    deleteModal.show();
}

let searchTimeout;
document.getElementById('teacherSearch').addEventListener('keyup', function() {
    const filter = this.value.toLowerCase();
    
    clearTimeout(searchTimeout);
    if(filter.length > 0) {
        searchTimeout = setTimeout(() => {
            logUserAction('Search Data', `ผู้ใช้ค้นหาคำว่า: "${filter}" ในหน้ารายชื่ออาจารย์`);
        }, 1000);
    }

    const rows = document.querySelectorAll('#teachersTable tbody tr');
    let found = false;
    rows.forEach(row => {
        if (row.id === 'noTeacherRow') return;
        const name = row.cells[0]?.textContent.toLowerCase() || '';
        const initials = row.cells[1]?.textContent.toLowerCase() || '';
        if (name.includes(filter) || initials.includes(filter)) {
            row.style.display = '';
            found = true;
        } else {
            row.style.display = 'none';
        }
    });
});

document.getElementById('importForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const msg = document.getElementById('importMessage');
    msg.innerHTML = 'กำลังนำเข้า...';
    msg.className = 'alert alert-info';

    logUserAction('Submit Form', 'ผู้ใช้กดยืนยันการนำเข้าไฟล์ CSV อาจารย์');

    fetch('import_teachers.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            msg.className = 'alert alert-success';
            msg.innerHTML = data.message + '<br>กำลังรีเฟรชข้อมูล...';
            setTimeout(() => location.reload(), 1500);
        } else {
            msg.className = 'alert alert-danger';
            msg.innerHTML = data.errors.join('<br>');
        }
    })
    .catch(() => {
        msg.className = 'alert alert-danger';
        msg.innerHTML = 'เกิดข้อผิดพลาดในการเชื่อมต่อ';
    });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>