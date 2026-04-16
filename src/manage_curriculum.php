<?php
// manage_curriculum.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once 'config/db.php';

if (!isset($_GET['id'])) {
    header("Location: curriculums.php");
    exit;
}

$curriculum_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

// 1. ตรวจสอบความเป็นเจ้าของหลักสูตร
$sql_curriculum = "SELECT * FROM curriculums WHERE id = ? AND user_id = ?";
$stmt_curriculum = $conn->prepare($sql_curriculum);
$stmt_curriculum->execute([$curriculum_id, $user_id]);
$curriculum = $stmt_curriculum->fetch(PDO::FETCH_ASSOC);

if (!$curriculum) {
    header("Location: curriculums.php");
    exit;
}

// 🟢 บันทึก Log: ใครเปิดเข้ามาดูหน้าจัดการวิชาในหลักสูตร
if (function_exists('writeLog')) {
    // ดึงชื่อ user เพื่อใช้บันทึก Log
    $stmt_user = $conn->prepare("SELECT name FROM users WHERE id = ?");
    $stmt_user->execute([$user_id]);
    $user_name = $stmt_user->fetchColumn() ?: 'Unknown';

    writeLog(
        $conn, 
        $user_id, 
        $user_name, 
        'View Page', 
        'เปิดดูวิชาในหลักสูตร: ' . $curriculum['curriculum_name'] . ' (ID: ' . $curriculum_id . ')'
    );
}

// 2. ดึงข้อมูลวิชาในหลักสูตร (ปรับให้รองรับห้องเรียนหลายห้อง)
$sql_courses = "SELECT c.*, 
                GROUP_CONCAT(DISTINCT CONCAT(t.initials, ' (', t.name, ')') SEPARATOR '; ') AS teacher_list,
                GROUP_CONCAT(DISTINCT t.id) AS teacher_ids,
                GROUP_CONCAT(DISTINCT r.room_number SEPARATOR ', ') as room_number,
                GROUP_CONCAT(DISTINCT r.room_id) as assigned_room_ids
            FROM courses c
            INNER JOIN curriculum_courses cc ON c.id = cc.course_id
            LEFT JOIN curriculum_course_teachers cct ON cc.curriculum_id = cct.curriculum_id AND cc.course_id = cct.course_id
            LEFT JOIN teachers t ON cct.teacher_id = t.id
            LEFT JOIN rooms r ON FIND_IN_SET(r.room_id, cc.classroom_id) > 0
            WHERE cc.curriculum_id = ?
            GROUP BY c.id";
$stmt_courses = $conn->prepare($sql_courses);
$stmt_courses->execute([$curriculum_id]);
$courses = $stmt_courses->fetchAll(PDO::FETCH_ASSOC);

$academic_year_be = $curriculum['academic_year']; 

// 3. เตรียมข้อมูล Dropdown
$stmt_teachers = $conn->prepare("SELECT id, name, initials FROM teachers WHERE user_id = ? ORDER BY name");
$stmt_teachers->execute([$user_id]);
$teachers_modal = $stmt_teachers->fetchAll(PDO::FETCH_ASSOC);

$stmt_rooms = $conn->prepare("SELECT room_id, room_number, notes FROM rooms WHERE user_id = ? ORDER BY room_number");
$stmt_rooms->execute([$user_id]);
$rooms_modal = $stmt_rooms->fetchAll(PDO::FETCH_ASSOC);

$stmt_all_courses = $conn->prepare("SELECT id, course_code, course_name, credits, teaching_hours FROM courses WHERE user_id = ? ORDER BY course_code");
$stmt_all_courses->execute([$user_id]);
$all_courses_modal = $stmt_all_courses->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Manage curriculum</title>
    <link rel="icon" type="image/png" href="img/FTE.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    
    <style>
        body { background: linear-gradient(to right, #0f2027, #203a43, #2c5364); color: #f8f9fa; min-height: 100vh; padding: 20px; }
        .card { background: rgba(255, 255, 255, 0.1); border: none; border-radius: 15px; backdrop-filter: blur(10px); }
        .table { color: #f8f9fa; }
        input[type="checkbox"] { width: 18px; height: 18px; cursor: pointer; accent-color: #0d6efd; border: 2px solid #000 !important; border-radius: 4px; }
        
        /* Modal White Theme */
        .modal-content.white-theme {
            background-color: #ffffff;
            color: #333333;
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            border-radius: 10px;
        }
        .modal-header.white-theme { border-bottom: 1px solid #dee2e6; background-color: #f8f9fa; border-top-left-radius: 10px; border-top-right-radius: 10px; }
        .modal-title.white-theme { color: #212529; font-weight: bold; }
        .modal-footer.white-theme { border-top: 1px solid #dee2e6; background-color: #f8f9fa; border-bottom-left-radius: 10px; border-bottom-right-radius: 10px; }
        .white-theme .btn-close { filter: none; }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="card p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="fas fa-book"></i> <span style="color:#fff;">จัดการวิชาในหลักสูตร: <?= htmlspecialchars($curriculum['curriculum_name']) ?></span></h2>
                <p class="text-light">ภาคเรียนที่ <?= htmlspecialchars($curriculum['semester']) ?> ปีการศึกษา <?= htmlspecialchars($academic_year_be) ?></p>
            </div>
            <div>
                <button type="button" class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#addCourseModal" onclick="logUserAction('Click Button', 'ผู้ใช้กดปุ่ม [เพิ่มวิชาในหลักสูตร] <?= htmlspecialchars($curriculum['curriculum_name']) ?>')">
                    <i class="fas fa-plus"></i> เพิ่มวิชา
                </button>
                <a href="curriculums.php" class="btn btn-outline-light"><i class="fas fa-arrow-left"></i> กลับ</a>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-dark table-striped">
                <thead>
                    <tr>
                        <th>รหัสวิชา</th>
                        <th>ชื่อวิชา</th>
                        <th>อาจารย์ผู้สอน</th>
                        <th>ชั่วโมง</th>
                        <th>หน่วยกิต</th>
                        <th>ห้องเรียน</th>
                        <th>การจัดการ</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (count($courses) > 0): ?>
                    <?php foreach ($courses as $course): ?>
                        <tr data-course-id="<?= $course['id'] ?>"
                            data-teacher-ids="<?= htmlspecialchars($course['teacher_ids'] ?? '') ?>"
                            data-classroom-ids="<?= htmlspecialchars($course['assigned_room_ids'] ?? '') ?>"
                            data-credits="<?= htmlspecialchars($course['credits']) ?>"
                            data-hours="<?= htmlspecialchars($course['teaching_hours']) ?>">
                            
                            <td><?= htmlspecialchars($course['course_code']) ?></td>
                            <td><?= htmlspecialchars($course['course_name']) ?></td>
                            <td>
                                <?php if (!empty($course['teacher_list'])): ?>
                                    <?php foreach (explode('; ', $course['teacher_list']) as $tname): ?>
                                        <div><?= htmlspecialchars($tname) ?></div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="text-warning">ยังไม่กำหนด</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($course['teaching_hours']) ?></td>
                            <td><?= htmlspecialchars($course['credits']) ?></td>
                            <td>
                                <?php if (!empty($course['room_number'])): ?>
                                    <?php foreach (explode(', ', $course['room_number']) as $rname): ?>
                                        <div><?= htmlspecialchars(trim($rname)) ?></div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="text-warning">ยังไม่กำหนด</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-warning btn-sm" onclick="editCourse(<?= $course['id'] ?>)">
                                    <i class="fas fa-pen"></i> แก้ไข
                                </button>
                                <button class="btn btn-danger btn-sm" 
                                        onclick="confirmDelete(<?= $course['id'] ?>, '<?= htmlspecialchars($course['course_name']) ?>')">
                                    <i class="fas fa-trash"></i> ลบ
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center">ยังไม่มีวิชาในหลักสูตรนี้</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addCourseModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content white-theme">
            <div class="modal-header white-theme">
                <h5 class="modal-title white-theme">เพิ่มวิชาในหลักสูตร</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form action="add_course_process.php" method="POST">
                    <input type="hidden" name="curriculum_id" value="<?= $curriculum_id ?>">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label text-dark">เลือกวิชา</label>
                            <select class="form-select" id="courseSelect" name="course_id" required>
                                <option value="">กรุณาเลือกวิชา</option>
                                <?php foreach ($all_courses_modal as $c): ?>
                                    <option value="<?= $c['id'] ?>" data-credits="<?= $c['credits'] ?>" data-hours="<?= $c['teaching_hours'] ?>">
                                        <?= htmlspecialchars($c['course_code']) ?> - <?= htmlspecialchars($c['course_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-dark">อาจารย์ผู้สอน</label>
                            <div class="form-control" style="max-height: 200px; overflow-y: auto;">
                                <?php foreach ($teachers_modal as $t): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="teacher_id[]" value="<?= $t['id'] ?>" id="add_t_<?= $t['id'] ?>">
                                        <label class="form-check-label text-dark" for="add_t_<?= $t['id'] ?>">
                                            <?= htmlspecialchars($t['name']) ?> (<?= htmlspecialchars($t['initials']) ?>)
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label text-dark">ห้องเรียน</label>
                            <div class="form-control" style="max-height: 200px; overflow-y: auto;">
                                <?php foreach ($rooms_modal as $r): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="classroom_id[]" value="<?= $r['room_id'] ?>" id="add_r_<?= $r['room_id'] ?>">
                                        <label class="form-check-label text-dark" for="add_r_<?= $r['room_id'] ?>">
                                            <?= htmlspecialchars($r['room_number']) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-dark">หน่วยกิต</label>
                            <input type="text" class="form-control bg-light" id="credits" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-dark">ชั่วโมง/สัปดาห์</label>
                            <input type="text" class="form-control bg-light" id="teachingHours" readonly>
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
</div>

<div class="modal fade" id="editCourseModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content white-theme">
            <div class="modal-header white-theme">
                <h5 class="modal-title white-theme">แก้ไขข้อมูลวิชา</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form action="edit_curriculum_course_process.php" method="POST">
                    <input type="hidden" name="course_id" id="edit_course_id">
                    <input type="hidden" name="curriculum_id" value="<?= $curriculum_id ?>">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label class="form-label text-dark">วิชา</label>
                            <select id="edit_course_select" name="course_id" class="form-select bg-light" readonly style="pointer-events: none;">
                                <?php foreach ($all_courses_modal as $ac): ?>
                                    <option value="<?= $ac['id'] ?>" data-credits="<?= $ac['credits'] ?>" data-hours="<?= $ac['teaching_hours'] ?>">
                                        <?= htmlspecialchars($ac['course_code']) ?> - <?= htmlspecialchars($ac['course_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label text-dark">อาจารย์ผู้สอน</label>
                            <div id="edit_teacher_list" class="form-control" style="max-height: 200px; overflow-y: auto;">
                                <?php foreach ($teachers_modal as $t): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="teacher_id[]" value="<?= $t['id'] ?>" id="edit_t_<?= $t['id'] ?>">
                                        <label class="form-check-label text-dark" for="edit_t_<?= $t['id'] ?>">
                                            <?= htmlspecialchars($t['name']) ?> (<?= htmlspecialchars($t['initials']) ?>)
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-dark">ห้องเรียน</label>
                            <div id="edit_room_list" class="form-control" style="max-height: 200px; overflow-y: auto;">
                                <?php foreach ($rooms_modal as $room): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="classroom_id[]" value="<?= $room['room_id'] ?>" id="edit_r_<?= $room['room_id'] ?>">
                                        <label class="form-check-label text-dark" for="edit_r_<?= $room['room_id'] ?>">
                                            <?= htmlspecialchars($room['room_number']) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer white-theme">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-warning">บันทึกการแก้ไข</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content white-theme">
            <div class="modal-header white-theme">
                <h5 class="modal-title text-danger fw-bold">
                    <i class="fas fa-exclamation-triangle me-2"></i>ยืนยันการลบ
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-dark">
                <p>คุณต้องการลบวิชา <strong id="deleteCourseName"></strong> ออกจากหลักสูตรใช่หรือไม่?</p>
                <p class="text-muted small mb-0">การกระทำนี้ไม่สามารถเรียกคืนได้</p>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// 🟢 ฟังก์ชันส่ง Log พฤติกรรมผ่าน AJAX
function logUserAction(action, details) {
    fetch('log/ajax_log_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=${encodeURIComponent(action)}&details=${encodeURIComponent(details)}`
    });
}

// Auto-fill credits/hours
document.getElementById('courseSelect').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    document.getElementById('credits').value = opt.dataset.credits || '';
    document.getElementById('teachingHours').value = opt.dataset.hours || '';
});

function editCourse(courseId) {
    const row = document.querySelector(`tr[data-course-id="${courseId}"]`);
    if (!row) return;

    document.getElementById('edit_course_id').value = courseId;
    document.getElementById('edit_course_select').value = courseId;
    
    // จัดการติ๊ก Checkbox อาจารย์
    const teacherIds = row.dataset.teacherIds ? row.dataset.teacherIds.split(',') : [];
    document.querySelectorAll('#edit_teacher_list input[type="checkbox"]').forEach(cb => {
        cb.checked = teacherIds.includes(cb.value);
    });

    // จัดการติ๊ก Checkbox ห้องเรียน
    const roomIds = row.dataset.classroomIds ? row.dataset.classroomIds.split(',') : [];
    document.querySelectorAll('#edit_room_list input[type="checkbox"]').forEach(cb => {
        cb.checked = roomIds.includes(cb.value);
    });

    // 🟢 ส่ง Log ว่ากดแก้ไข
    const courseName = row.cells[1].textContent;
    logUserAction('Click Button', `ผู้ใช้กดปุ่ม [แก้ไข] ข้อมูลวิชา: ${courseName} ในหลักสูตร ID: <?= $curriculum_id ?>`);

    const editModal = new bootstrap.Modal(document.getElementById('editCourseModal'));
    editModal.show();
}

function confirmDelete(courseId, courseName) {
    document.getElementById('deleteCourseName').innerText = courseName;
    const deleteUrl = `delete_curriculum_course.php?course_id=${courseId}&curriculum_id=<?= $curriculum_id ?>`;
    document.getElementById('confirmDeleteBtn').setAttribute('href', deleteUrl);
    
    // 🟢 ส่ง Log ว่าเตรียมลบ
    logUserAction('Click Button', `ผู้ใช้กดปุ่ม [ลบ] เตรียมลบวิชา: ${courseName} ออกจากหลักสูตร ID: <?= $curriculum_id ?>`);

    const deleteModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
    deleteModal.show();
}

// --- SweetAlert2 Logic สำหรับแจ้งเตือน ---
document.addEventListener("DOMContentLoaded", function() {
    const urlParams = new URLSearchParams(window.location.search);
    const status = urlParams.get('status');

    if (status === 'duplicate') {
        Swal.fire({
            icon: 'warning',
            title: 'วิชาซ้ำซ้อน!',
            text: 'วิชานี้มีอยู่ในหลักสูตรเรียบร้อยแล้ว ไม่สามารถเพิ่มซ้ำได้',
            confirmButtonColor: '#ffc107',
            confirmButtonText: 'ตกลง',
            background: '#fff',
            color: '#333'
        });
        window.history.replaceState(null, null, window.location.pathname + '?id=<?= $curriculum_id ?>');
    } else if (status === 'success') {
        Swal.fire({
            icon: 'success',
            title: 'สำเร็จ',
            text: 'เพิ่มวิชาเรียบร้อยแล้ว',
            timer: 1500,
            showConfirmButton: false,
            background: '#fff',
            color: '#333'
        });
        window.history.replaceState(null, null, window.location.pathname + '?id=<?= $curriculum_id ?>');
    }
});
</script>
</body>
</html>