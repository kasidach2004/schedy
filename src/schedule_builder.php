<?php
// schedule_builder.php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header("Location: login.php");
    exit;
}

$schedule_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// ==========================================

require_once 'config/db.php';

// 2. ดึงข้อมูล User (เพิ่ม role เข้ามาด้วย เพื่อใช้เช็คสิทธิ์)
$sql_user = "SELECT name, email, `group`, role FROM users WHERE id = ?";
$stmt_user = $conn->prepare($sql_user);
$stmt_user->execute([$user_id]);
$user_info = $stmt_user->fetch(PDO::FETCH_ASSOC);

// 3. ดึงและตรวจสอบสิทธิ์ Schedule
$sql_schedule = "SELECT schedule_name FROM schedules WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($sql_schedule);
$stmt->execute([$schedule_id, $user_id]);
$schedule_info = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$schedule_info) {
    die("ไม่พบตารางสอน หรือคุณไม่มีสิทธิ์เข้าถึงตารางนี้");
}

// === ส่วนการเก็บ Log การเข้าใช้งาน (ตอนโหลดหน้า) ===
if (function_exists('writeLog')) {
    $log_details = "เข้าใช้งานหน้าจัดตารางสอน: " . $schedule_info['schedule_name'] . " (ID: $schedule_id)";
    writeLog($conn, $user_id, $user_info['name'], 'Access Schedule Builder', $log_details);
}
// ==========================================

// เตรียมตัวแปร data
$data = [
    'user_info' => $user_info,
    'schedule_info' => $schedule_info,
    'classrooms' => [],
    'timeslots' => [],
    'curriculums' => [],
    'teachers' => [],
    'scheduled_items' => [],
    'days' => [
        1 => ['name' => 'วันจันทร์', 'short' => 'จ.', 'color' => '#e7c478', 'text' => '#212529', 'btn_class' => 'btn-warning'],  // สีเหลืองมัสตาร์ดพาสเทล
        2 => ['name' => 'วันอังคาร', 'short' => 'อ.', 'color' => '#E68A9E', 'text' => '#fff', 'btn_class' => 'btn-danger'],     // สีชมพูตุ่น
        3 => ['name' => 'วันพุธ', 'short' => 'พ.', 'color' => '#76B98F', 'text' => '#fff', 'btn_class' => 'btn-success'],    // สีเขียวเสจ (Sage Green)
        4 => ['name' => 'วันพฤหัสบดี', 'short' => 'พฤ.', 'color' => '#F09D63', 'text' => '#fff', 'btn_class' => 'btn-orange'],     // สีส้มชาไทยพาสเทล
        5 => ['name' => 'วันศุกร์', 'short' => 'ศ.', 'color' => '#79AEDB', 'text' => '#fff', 'btn_class' => 'btn-info'],       // สีฟ้าหม่น
        6 => ['name' => 'วันเสาร์', 'short' => 'ส.', 'color' => '#A88ACB', 'text' => '#fff', 'btn_class' => 'btn-purple'],     // สีม่วงเผือก
        7 => ['name' => 'วันอาทิตย์', 'short' => 'อา.', 'color' => '#E77E7E', 'text' => '#fff', 'btn_class' => 'btn-danger']      // สีแดงคอรัล (Coral)
    ]
];

// 3. ดึง Master Data
$stmt_room = $conn->prepare("SELECT id, room_name FROM classrooms WHERE user_id = ? ORDER BY room_name");
$stmt_room->execute([$user_id]);
$data['classrooms'] = $stmt_room->fetchAll(PDO::FETCH_ASSOC);


// ดึงข้อมูลหมายเลขห้องที่ User เป็นคนสร้างไปแสดงใน Popup
$stmt_phys = $conn->prepare("SELECT room_id, room_number FROM rooms WHERE user_id = ? ORDER BY room_number ASC");
$stmt_phys->execute([$user_id]);
$data['physical_rooms'] = $stmt_phys->fetchAll(PDO::FETCH_ASSOC);


$data['timeslots'] = $conn->query("SELECT id, start_time, end_time FROM timeslots ORDER BY start_time")->fetchAll(PDO::FETCH_ASSOC);

$stmt_curr = $conn->prepare("SELECT id, curriculum_name FROM curriculums WHERE user_id = ? ORDER BY curriculum_name");
$stmt_curr->execute([$user_id]);
$data['curriculums'] = $stmt_curr->fetchAll(PDO::FETCH_ASSOC);

$stmt_teacher = $conn->prepare("SELECT id, initials, max_load FROM teachers WHERE user_id = ? ORDER BY initials ASC");
$stmt_teacher->execute([$user_id]);
$data['teachers'] = $stmt_teacher->fetchAll(PDO::FETCH_ASSOC);

// 4. ดึงรายวิชา (แก้ SQL เพื่อรองรับห้องเรียนหลายห้อง)
$sql_curriculum_courses = "SELECT 
                            cc.curriculum_id, 
                            curr.curriculum_name,
                            c.id as course_id, c.course_code, c.course_name, 
                            c.teaching_hours, c.credits, 
                            GROUP_CONCAT(DISTINCT t.id ORDER BY t.id ASC SEPARATOR ',') as teacher_ids,
                            GROUP_CONCAT(DISTINCT t.initials ORDER BY t.id ASC SEPARATOR ',') as teacher_initials,
                            GROUP_CONCAT(DISTINCT r.room_number ORDER BY r.room_id ASC SEPARATOR ', ') as assigned_room
                           FROM curriculum_courses cc
                           JOIN courses c ON cc.course_id = c.id
                           JOIN curriculums curr ON cc.curriculum_id = curr.id
                           LEFT JOIN curriculum_course_teachers cct ON cc.curriculum_id = cct.curriculum_id AND cc.course_id = cct.course_id
                           LEFT JOIN teachers t ON cct.teacher_id = t.id
                           LEFT JOIN rooms r ON FIND_IN_SET(r.room_id, cc.classroom_id) > 0
                           WHERE curr.user_id = ?
                           GROUP BY c.id, cc.curriculum_id";

$stmt_cc = $conn->prepare($sql_curriculum_courses);
$stmt_cc->execute([$user_id]);
$result_cc = $stmt_cc->fetchAll(PDO::FETCH_ASSOC);

$curriculumCourses = [];
foreach ($result_cc as $row) {
    $t_ids = $row['teacher_ids'] ? explode(',', $row['teacher_ids']) : [];
    $t_initials = $row['teacher_initials'] ? explode(',', $row['teacher_initials']) : [];
    
    $row['teachers'] = [];
    for($i=0; $i<count($t_ids); $i++){
        $row['teachers'][] = ['id' => $t_ids[$i], 'initial' => $t_initials[$i]];
    }
    $cid = $row['curriculum_id'];
    if (!isset($curriculumCourses[$cid])) $curriculumCourses[$cid] = [];
    $curriculumCourses[$cid][] = $row;
}
$data['curriculumCourses'] = $curriculumCourses;

// 5. ดึงข้อมูลตารางสอน
$sql_items = "SELECT * FROM schedule_items WHERE schedule_id = ?";
$stmt_items = $conn->prepare($sql_items);
$stmt_items->execute([$schedule_id]);
$data['scheduled_items'] = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

$data['schedule_info']['id'] = (int)$schedule_id;
$js_data = json_encode($data, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($schedule_info['schedule_name']) ?> | Scheduling</title>
    <link rel="icon" type="image/png" href="img/FTE.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="css/schedule_style.css">
    <link rel="stylesheet" href="css/schedule_clean.css">
</head>
<body>

<div id="loading-overlay">
    <div class="spinner"></div>
    <div class="loading-text">กำลังโหลดข้อมูล...</div>
</div>

<div class="day-navigator-container" id="dayNavContainer">
    <div class="day-nav-panel" id="dayNavPanel">

        <button class="nav-action-btn" id="btnToggleTheme"
            style="background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.5);"
            onclick="toggleTheme()" title="เปลี่ยนโทนสี (Clean/Dark)">
            <i class="fas fa-sun" style="color:#fff;" id="themeIcon"></i>
            <span class="nav-btn-tooltip">เปลี่ยนโทนสี</span>
        </button>

        <div class="nav-panel-divider"></div>

        <button class="nav-action-btn"
            style="background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.5);"
            id="btnToggleCourseFloat" onclick="toggleCourseSidebar()" title="แสดง/ซ่อน หลักสูตร">
            <i class="fas fa-columns" style="color:#fff;"></i>
            <span class="nav-btn-tooltip">หลักสูตร</span>
        </button>

        <div class="nav-panel-divider"></div>

        <button class="nav-action-btn" id="btnSaveFloat" 
            style="background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.5);"
            onclick="saveSchedule()" title="บันทึก">
            <i class="fas fa-save" style="color:#fff;"></i>
            <span class="unsaved-dot" id="unsavedDot"></span>
            <span class="nav-btn-tooltip">บันทึก</span>
        </button>
        <button class="nav-action-btn" 
            style="background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.5);"
            onclick="exportSchedule()" title="Export .xls">
            <i class="fas fa-file-excel" style="color:#fff;"></i>
            <span class="nav-btn-tooltip">Export .xls</span>
        </button>
        <button class="nav-action-btn" 
            style="background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.5);"
            onclick="printSchedule()" title="พิมพ์">
            <i class="fas fa-print" style="color:#fff;"></i>
            <span class="nav-btn-tooltip">พิมพ์</span>
        </button>
        <button class="nav-action-btn" 
            style="background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.5);"
            onclick="renderTeacherLoadModal()" 
            data-bs-toggle="modal" data-bs-target="#teacherLoadModal" title="ภาระงาน">
            <i class="fas fa-chart-pie" style="color:#fff;"></i>
            <span class="nav-btn-tooltip">ภาระงาน</span>
        </button>

        <div class="nav-panel-divider"></div>

        <?php foreach ($data['days'] as $dayId => $dayData): ?>
            <a class="day-nav-btn <?= $dayData['btn_class'] ?? 'btn-primary' ?>" 
               style="background-color: <?= $dayData['color'] ?>; color: <?= $dayData['text'] ?>; border-color: <?= $dayData['color'] ?>;"
               onclick="scrollToDay(<?= $dayId ?>)" title="ไปที่ <?= $dayData['name'] ?>"><?= $dayData['short'] ?></a>
        <?php endforeach; ?>

    </div>
    <button class="day-nav-toggle" id="dayNavToggle" onclick="toggleDayNavigator()" title="ซ่อน/แสดง เมนู">
        <i class="fas fa-chevron-down"></i>
    </button>
</div>

<div class="d-flex">
    <div id="sidebar" class="sidebar d-flex flex-column p-3 text-white">
        <button class="sidebar-toggle" id="sidebarToggle"><i class="fas fa-chevron-left"></i></button>
        <hr>
        <div class="profile-card">
            <i class="fas fa-user-circle fa-3x mb-2"></i>
            <div class="profile-details">
                <h5><?= htmlspecialchars($user_info['name']) ?></h5>
                <small><?= htmlspecialchars($user_info['email']) ?></small>
                
                <br>
                <?php if (($user_info['role'] ?? '') === 'admin'): ?>
                    <span class="badge bg-danger"></i>ADMIN</span>
                <?php else: ?>
                    <span class="badge bg-primary mt-2"><?= htmlspecialchars($user_info['group']) ?></span>
                <?php endif; ?>
                </div>
        </div>
        <ul class="nav nav-pills flex-column mb-auto">
            <li class="nav-item"><a href="home.php" class="nav-link"><i class="fas fa-home me-2"></i><span class="sidebar-text"> หน้าหลัก</span></a></li>
            <li class="nav-item"><a href="schedules.php" class="nav-link active"><i class="fas fa-chalkboard me-2"></i><span class="sidebar-text"> ตารางสอน</span></a></li>
            <li><a href="teachers.php" class="nav-link"><i class="fas fa-users me-2"></i><span class="sidebar-text"> รายชื่ออาจารย์</span></a></li>
            <li><a href="courses.php" class="nav-link"><i class="fas fa-book me-2"></i><span class="sidebar-text"> วิชา</span></a></li>
            <li><a href="curriculums.php" class="nav-link"><i class="fas fa-graduation-cap me-2"></i><span class="sidebar-text"> หลักสูตร</span></a></li>
            <li><a href="classrooms.php" class="nav-link"><i class="fas fa-building me-2"></i><span class="sidebar-text"> ห้องเรียน</span></a></li>
            <li><a href="rooms.php" class="nav-link"><i class="fas fa-school me-2"></i><span class="sidebar-text"> หมายเลขห้องเรียน</span></a></li>
            <hr>
            <li><a href="logout.php" class="nav-link"><i class="fas fa-sign-out-alt me-2"></i><span class="sidebar-text"> ออกจากระบบ</span></a></li>
        </ul>
    </div>
    
    <div class="content flex-grow-1">
        <div class="main-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 style="font-size: 1.5rem;"><i class="fas fa-calendar-alt me-2"></i>จัดตารางสอน <?= htmlspecialchars($schedule_info['schedule_name']) ?></h2>
                <div class="header-actions">
                    </div>
            </div>
            
            <div class="row mb-3" id="room-filter-container">
                <div class="col-md-4">
                    <p><small class="text-warning">*ชื่อ "ห้อง" ต้องตรงกับชื่อ "หลักสูตร" เท่านั้นถึงจะใช้งานได้</small></p>
                    <label for="room-print-filter" class="form-label text-white theme-label">เลือกพิมพ์เฉพาะห้องเรียน:</label>
                    
                    <select id="room-print-filter" class="form-select bg-dark text-white border-secondary">
                        <option value="all">แสดงทั้งหมด (แยกตามวัน)</option>
                        <?php foreach ($data['classrooms'] as $room): ?>
                            <option value="<?= $room['id'] ?>"><?= htmlspecialchars($room['room_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col-md-9 col-transition" id="grid-col">
                    <div id="schedule-grid-container"></div>
                    <div id="combined-print-container"></div>
                    <div id="print-split-container"></div>
                </div>
                
                <div class="col-md-3 col-transition" id="course-sidebar-col">
                    <div class="sticky-course-list">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-white mb-0 theme-title">หลักสูตร</h5>
                            <button class="btn btn-sm btn-outline-secondary text-white theme-close-btn" onclick="toggleCourseSidebar()"><i class="fas fa-times"></i></button>
                        </div>
                        <div class="course-list-dropdown mb-3">
                            <label for="curriculum-select" class="form-label text-white theme-label">เลือกหลักสูตร:</label>
                            <select id="curriculum-select" class="form-select bg-dark text-white border-secondary">
                                <?php foreach ($data['curriculums'] as $cur): ?>
                                    <option value="<?= htmlspecialchars($cur['id']) ?>"><?= htmlspecialchars($cur['curriculum_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div id="course-list" class="list-group"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="teacherLoadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">ภาระงานอาจารย์</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <table class="table table-dark table-hover table-bordered text-center">
                    <thead><tr class="text-info"><th>ชื่อย่อ</th><th>Max</th><th>ใช้</th><th>เหลือ</th><th>สถานะ</th></tr></thead>
                    <tbody id="teacherLoadTableBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    window.scheduleData = <?= $js_data ?>;

    // ระบบจัดการ Theme 
    document.addEventListener("DOMContentLoaded", function() {
        const savedTheme = localStorage.getItem('scheduleTheme');
        if (savedTheme === 'clean') {
            document.body.classList.add('clean-theme');
            updateThemeIcon('clean');
        }
    });

    function toggleTheme() {
        document.body.classList.toggle('clean-theme');
        let currentTheme = document.body.classList.contains('clean-theme') ? 'clean' : 'dark';
        
        localStorage.setItem('scheduleTheme', currentTheme);
        updateThemeIcon(currentTheme);
    }

    function updateThemeIcon(theme) {
        const icon = document.getElementById('themeIcon');
        const themeBtns = document.querySelectorAll('.nav-action-btn i');
        
        if (theme === 'clean') {
            icon.className = 'fas fa-moon'; // เปลี่ยนเป็นปุ่มดวงจันทร์เพื่อกลับไปโหมดมืด
            icon.style.color = '#334155';
            // ปรับสีไอคอนปุ่มอื่นๆ ให้เป็นสีเข้ม
            themeBtns.forEach(btn => { if(btn.id !== 'themeIcon') btn.style.color = '#334155'; });
        } else {
            icon.className = 'fas fa-sun'; // เปลี่ยนเป็นปุ่มดวงอาทิตย์
            icon.style.color = '#fff';
            // ปรับสีไอคอนปุ่มอื่นๆ ให้กลับเป็นสีขาว
            themeBtns.forEach(btn => { btn.style.color = '#fff'; });
        }
    }
</script>

<script src="js/schedule_js.js"></script>

</body>
</html>