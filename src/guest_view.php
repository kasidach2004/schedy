<?php
session_start();
require_once 'config/db.php';

if (!isset($_GET['id'])) die("ไม่พบรหัสตารางเรียน");
$schedule_id = $_GET['id'];

// 1. ดึงข้อมูลรายละเอียดของตาราง และชื่อผู้สร้าง
$sql_info = "SELECT s.schedule_name, u.name AS creator_name, u.group AS major FROM schedules s 
             LEFT JOIN users u ON s.user_id = u.id WHERE s.id = ?";
$stmt_info = $conn->prepare($sql_info);
$stmt_info->execute([$schedule_id]);
$info = $stmt_info->fetch(PDO::FETCH_ASSOC);

if (!$info) die("ไม่พบข้อมูลตารางเรียน");

// 2. ดึงข้อมูล Timeslots
$timeslots = $conn->query("SELECT * FROM timeslots ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

// 3. ดึงข้อมูลวิชา จอยชื่อเต็มอาจารย์ และเลขห้อง (มี start_date, end_date มากับ si.* อยู่แล้ว)
$sql_items = "SELECT si.*, c.course_code, c.course_name, c.credits, r.room_name,
              (SELECT GROUP_CONCAT(DISTINCT t.initials SEPARATOR ', ') FROM curriculum_course_teachers cct 
               JOIN teachers t ON cct.teacher_id = t.id WHERE cct.course_id = si.course_id) AS initials,
              (SELECT GROUP_CONCAT(DISTINCT t.name SEPARATOR ' / ') FROM curriculum_course_teachers cct 
               JOIN teachers t ON cct.teacher_id = t.id WHERE cct.course_id = si.course_id) AS teacher_full_names,
              si.physical_room AS room_number
              FROM schedule_items si
              LEFT JOIN courses c ON si.course_id = c.id
              LEFT JOIN classrooms r ON si.room_id = r.id
              WHERE si.schedule_id = ?";
$stmt_items = $conn->prepare($sql_items);
$stmt_items->execute([$schedule_id]);
$items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

$classrooms_used = [];
foreach ($items as $item) {
    if (!isset($classrooms_used[$item['room_id']])) {
        $classrooms_used[$item['room_id']] = $item['room_name'] ?? 'ไม่ระบุห้อง';
    }
}
ksort($classrooms_used);

$js_data = json_encode(['timeslots' => $timeslots, 'items' => $items, 'classrooms' => $classrooms_used]);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบตารางเรียน - <?= htmlspecialchars($info['schedule_name']) ?></title>
    <link rel="icon" type="image/png" href="img/FTE.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root { --primary-dark: #1e293b; --accent-blue: #2563eb; --border-gray: #e2e8f0; }
        body { font-family: 'Prompt', sans-serif; background-color: #f8fafc; color: #334155; font-size: 0.85rem; }
        
        .header-section { background: var(--primary-dark); color: #fff; padding: 20px 0 60px 0; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .creator-badge { background: rgba(255,255,255,0.15); padding: 3px 10px; border-radius: 20px; font-size: 0.7rem; border: 1px solid rgba(255,255,255,0.2); display: inline-block; margin-top: 5px; }

        .view-card { background: #fff; border-radius: 15px; border: 1px solid var(--border-gray); padding: 10px; margin-top: -35px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); position: relative; z-index: 10; }
        .mode-switcher { background: #f1f5f9; padding: 3px; border-radius: 10px; display: inline-flex; width: 100%; }
        .mode-switcher .btn { flex: 1; border: none; border-radius: 8px; font-size: 0.8rem; font-weight: 500; color: #64748b; padding: 6px; transition: 0.2s; }
        .mode-switcher .btn-check:checked + .btn { background: #fff; color: var(--accent-blue); box-shadow: 0 2px 6px rgba(0,0,0,0.05); }

        .white-card { background: #fff; border: 1px solid var(--border-gray); border-radius: 12px; padding: 12px; margin-bottom: 20px; }
        
        /* ปรับปรุงขนาดตาราง */
        .table-schedule { 
            table-layout: fixed; 
            width: 100%; 
            min-width: 1100px; 
            border-collapse: collapse; 
            font-size: 0.65rem; 
        }
        .table-schedule th { background: #f8fafc; color: #64748b; font-weight: 600; border: 1px solid var(--border-gray); padding: 4px 2px; }
        .table-schedule td { border: 1px solid var(--border-gray); height: 55px; vertical-align: middle; padding: 2px; position: relative; }
        
        .label-col { width: 75px; background: #fff !important; font-weight: 600; text-align: center; border-left: 6px solid #dee2e6 !important; font-size: 0.75rem; }

        .course-item { 
            height: 100%; width: 100%; padding: 4px; border-radius: 5px; 
            display: flex; flex-direction: column; justify-content: center; align-items: center; 
            line-height: 1.05; border: 1px solid rgba(0,0,0,0.05); text-align: center;
        }
        .course-item strong { color: #1e40af; font-size: 0.65rem; }
        .course-name-text { color: #475569; font-size: 0.6rem; margin-top: 1px; height: 2.2em; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
        .room-text { color: #ef4444; font-weight: 700; font-size: 0.7rem; margin-top: 1px; background: rgba(239, 68, 68, 0.1); padding: 0px 4px; border-radius: 8px; }
        
        /* สไตล์สำหรับ Badge วันที่เริ่ม-สิ้นสุด */
        .date-badge { background: #31697E; color: #fff; font-size: 0.55rem; padding: 2px 4px; border-radius: 4px; margin-top: 2px; line-height: 1.1; width: 100%; word-break: break-word; }

        .day-1 { border-left-color: #fbbf24 !important; } .day-2 { border-left-color: #f472b6 !important; }
        .day-3 { border-left-color: #34d399 !important; } .day-4 { border-left-color: #fb923c !important; }
        .day-5 { border-left-color: #60a5fa !important; } .day-6 { border-left-color: #a78bfa !important; } .day-7 { border-left-color: #f87171 !important; }
        
        .day-strip { padding: 8px 15px; border-radius: 10px 10px 0 0; font-weight: 700; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; }
        .form-select-custom { border-radius: 8px; border: 1.5px solid var(--border-gray); font-size: 0.8rem; font-weight: 600; padding: 6px 10px; }
        
        .table-responsive-custom {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            border-radius: 8px;
        }

        @media print { .no-print { display: none !important; } .view-card { margin-top: 0; } .table-schedule { min-width: 100%; } }
    </style>
</head>
<body>

<div class="header-section no-print">
    <div class="container d-flex justify-content-between align-items-center">
        <a href="index.php" class="btn btn-outline-light btn-sm rounded-pill px-3"><i class="fas fa-home me-1"></i> หน้าหลัก</a>
        <div class="text-center">
            <h5 class="fw-bold mb-1"><?= htmlspecialchars($info['schedule_name']) ?></h5>
            <div class="d-flex flex-column align-items-center">
                <span class="small opacity-75 mb-0" style="font-size: 0.75rem;"><i class="fas fa-graduation-cap me-1"></i><?= htmlspecialchars($info['major']) ?></span>
                <span class="creator-badge"><i class="fas fa-user-edit me-1"></i>ผู้จัดทำ: <?= htmlspecialchars($info['creator_name']) ?></span>
            </div>
        </div>
        <a href="guest_schedules.php" class="btn btn-outline-light btn-sm rounded-pill px-3"><i class="fas fa-search me-1"></i> ค้นหาตาราง</a>
    </div>
</div>

<div class="container-fluid px-lg-5">
    <div class="view-card no-print shadow-sm">
        <div class="row g-2 align-items-center">
            <div class="col-lg-5">
                <div class="mode-switcher">
                    <input type="radio" class="btn-check" name="vMode" id="modeRoom" checked onchange="switchView('room')">
                    <label class="btn" for="modeRoom">รายหลักสูตร</label>
                    <input type="radio" class="btn-check" name="vMode" id="modeTeacher" onchange="switchView('teacher')">
                    <label class="btn" for="modeTeacher">รายผู้สอน</label>
                    <input type="radio" class="btn-check" name="vMode" id="modeOverview" onchange="switchView('overview')">
                    <label class="btn" for="modeOverview">ตรวจสอบห้อง</label>
                </div>
            </div>
            <div class="col-lg-7">
                <div id="filter-area" class="d-flex gap-2 justify-content-lg-end">
                    <div id="room-select-area" class="w-100" style="max-width: 250px;">
                        <select id="roomSelect" class="form-select form-select-custom shadow-sm" onchange="renderRoom(this.value)">
                            <?php foreach ($classrooms_used as $id => $name): ?>
                                <option value="<?= $id ?>"><?= htmlspecialchars($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div id="teacher-select-area" style="display:none;" class="w-100" style="max-width: 250px;">
                        <select id="teacherSelect" class="form-select form-select-custom shadow-sm" onchange="renderTeacher()"></select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="main-display" class="mt-4"></div>

    <div id="summary-section" class="white-card shadow-sm">
        <h6 class="fw-bold mb-2 text-primary" style="font-size: 0.85rem;"><i class="fas fa-info-circle me-2"></i>รายละเอียดรายวิชา</h6>
        <div class="table-responsive">
            <table class="table table-sm table-hover border-0 mb-0">
                <thead class="table-light">
                    <tr class="text-center" style="font-size: 0.75rem; color: #64748b;">
                        <th>รหัสวิชา</th>
                        <th>ชื่อย่อ</th>
                        <th class="text-start">ชื่อรายวิชา</th>
                        <th>นก.</th>
                        <th class="text-start">อาจารย์ผู้สอน</th>
                        <th>ห้องเรียน</th>
                        <th>ระยะเวลาเรียน</th> </tr>
                </thead>
                <tbody id="summary-body" style="font-size: 0.75rem;"></tbody>
            </table>
        </div>
    </div>
</div>

<script>
const data = <?= $js_data ?>;
const dayInfo = {
    1: { name: 'จันทร์', bg: '#fffceb', color: '#fbbf24', cls: 'day-1' },
    2: { name: 'อังคาร', bg: '#fff5f9', color: '#f472b6', cls: 'day-2' },
    3: { name: 'พุธ', bg: '#f0fff4', color: '#34d399', cls: 'day-3' },
    4: { name: 'พฤหัสบดี', bg: '#fffaf5', color: '#fb923c', cls: 'day-4' },
    5: { name: 'ศุกร์', bg: '#f0f7ff', color: '#60a5fa', cls: 'day-5' },
    6: { name: 'เสาร์', bg: '#f8f5ff', color: '#a78bfa', cls: 'day-6' },
    7: { name: 'อาทิตย์', bg: '#fff5f5', color: '#f87171', cls: 'day-7' }
};

let teachers = new Set();
data.items.forEach(item => { if(item.initials) item.initials.split(',').forEach(t => teachers.add(t.trim())); });
const tSelect = document.getElementById('teacherSelect');
Array.from(teachers).sort().forEach(t => { tSelect.add(new Option(t, t)); });

// ฟังก์ชันแปลงวันที่เป็นภาษาไทย
function formatThaiDate(dateStr) {
    if (!dateStr || dateStr === 'null') return '';
    const [y, m, d] = dateStr.split('-');
    if (!y || !m || !d) return '';
    const months = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    const thaiYear = parseInt(y) + 543;
    return `${parseInt(d)} ${months[parseInt(m) - 1]} ${thaiYear}`;
}

function createBlock(item, dayId) {
    let dateBadge = '';
    // ตรวจสอบและแสดงวันที่ถ้ามี
    if (item.start_date && item.end_date && item.start_date !== 'null' && item.end_date !== 'null') {
        dateBadge = `<div class="date-badge"><i>Start ${formatThaiDate(item.start_date)} End ${formatThaiDate(item.end_date)}</i></div>`;
    }

    return `<div class="course-item" style="background: ${dayInfo[dayId].bg}">
        <strong>${item.course_code} (${item.initials || '-'})</strong>
        <div class="course-name-text">${item.course_name}</div>
        <span class="room-text">${item.room_number || '-'}</span>
        ${dateBadge}
    </div>`;
}

function setupTable(title, bgColor = '#f8fafc') {
    document.getElementById('main-display').innerHTML = `
        <div class="white-card p-0 shadow-sm border-0">
            <div class="p-2 table-responsive-custom">
                <table class="table-schedule text-center">
                    <thead>
                        <tr>
                            <th width="75" style="background:${bgColor}">${title}</th>
                            ${data.timeslots.map(ts => `<th style="background:${bgColor}">${ts.start_time.substring(0,5)} - ${ts.end_time.substring(0,5)}</th>`).join('')}
                        </tr>
                    </thead>
                    <tbody id="grid-body"></tbody>
                </table>
            </div>
        </div>`;
    return document.getElementById('grid-body');
}

function renderRoom(rid) {
    const tbody = setupTable('วัน/เวลา');
    const roomItems = data.items.filter(x => x.room_id == rid);
    for (let d = 1; d <= 7; d++) {
        let tr = document.createElement('tr');
        tr.innerHTML = `<td class="label-col ${dayInfo[d].cls}">${dayInfo[d].name}</td>`;
        for (let i = 1; i <= data.timeslots.length; i++) {
            let item = roomItems.find(x => x.day_of_week == d && x.timeslot_id == data.timeslots[i-1].id);
            let td = document.createElement('td');
            if (item) { 
                td.colSpan = item.duration; 
                td.innerHTML = createBlock(item, d); 
                i += (item.duration - 1); 
            }
            tr.appendChild(td);
        }
        tbody.appendChild(tr);
    }
    renderSummary(roomItems);
}

function renderTeacher() {
    const tName = tSelect.value;
    const tbody = setupTable('วัน/เวลา');
    const tItems = data.items.filter(x => x.initials && x.initials.includes(tName));
    for (let d = 1; d <= 7; d++) {
        let tr = document.createElement('tr');
        tr.innerHTML = `<td class="label-col ${dayInfo[d].cls}">${dayInfo[d].name}</td>`;
        for (let i = 1; i <= data.timeslots.length; i++) {
            let item = tItems.find(x => x.day_of_week == d && x.timeslot_id == data.timeslots[i-1].id);
            let td = document.createElement('td');
            if (item) { 
                td.colSpan = item.duration; 
                td.innerHTML = createBlock(item, d); 
                i += (item.duration - 1); 
            }
            tr.appendChild(td);
        }
        tbody.appendChild(tr);
    }
    renderSummary(tItems);
}

function renderOverview() {
    const area = document.getElementById('main-display'); area.innerHTML = '';
    for (let d = 1; d <= 7; d++) {
        const info = dayInfo[d];
        let section = document.createElement('div');
        section.className = 'white-card p-0 overflow-hidden shadow-sm border-0 mb-3';
        section.innerHTML = `
            <h6 class="day-strip" style="background:${info.bg}; margin:0; border-left:8px solid ${info.color}; padding: 10px 15px;">
                <i class="fas fa-calendar-day me-2" style="color: ${info.color}"></i>วัน${info.name}
            </h6>
            <div class="p-2 table-responsive-custom">
                <table class="table-schedule text-center">
                    <thead><tr><th width="100" style="background: #f8fafc;">ห้องเรียน</th>${data.timeslots.map(ts => `<th style="background: #f8fafc;">${ts.start_time.substring(0,5)}</th>`).join('')}</tr></thead>
                    <tbody>${Object.entries(data.classrooms).map(([rid, rname]) => `<tr><td class="bg-light fw-bold" style="font-size:0.6rem; border-right: 1px solid #eee;">${rname}</td>${renderCells(d, rid)}</tr>`).join('')}</tbody>
                </table>
            </div>`;
        area.appendChild(section);
    }
    document.getElementById('summary-section').style.display = 'none';
}

function renderCells(day, rid) {
    let html = '';
    for (let i = 1; i <= data.timeslots.length; i++) {
        let item = data.items.find(x => x.day_of_week == day && x.timeslot_id == data.timeslots[i-1].id && x.room_id == rid);
        if (item) { 
            let cellDateBadge = '';
            if (item.start_date && item.end_date && item.start_date !== 'null' && item.end_date !== 'null') {
                cellDateBadge = `<div style="font-size: 0.5rem; color: #31697E; margin-top: 1px;">${formatThaiDate(item.start_date)} - ${formatThaiDate(item.end_date)}</div>`;
            }

            html += `<td colspan="${item.duration}">
                <div class="course-item" style="background:${dayInfo[day].bg}; border:1px dashed #ef4444; padding:2px;">
                    <span style="color:#ef4444; font-weight:bold; font-size:0.75rem;">${item.room_number || '-'}</span>
                    ${cellDateBadge}
                </div>
            </td>`; 
            i += (item.duration - 1); 
        } else { html += `<td></td>`; }
    }
    return html;
}

function renderSummary(items) {
    document.getElementById('summary-section').style.display = 'block';
    const tbody = document.getElementById('summary-body'); tbody.innerHTML = '';
    
    // กรองวิชาซ้ำ โดยเช็คคู่รหัสวิชา + วันที่ เพื่อให้บล็อกพิเศษที่แยกกันแสดงครบถ้วน
    const unique = []; 
    items.forEach(item => { 
        if(!unique.find(c => c.course_id === item.course_id && c.start_date === item.start_date)) {
            unique.push(item);
        }
    });

    unique.forEach(c => {
        let dateTxt = '-';
        if (c.start_date && c.end_date && c.start_date !== 'null' && c.end_date !== 'null') {
            dateTxt = `<span style="font-size: 0.65rem; color: #31697E; font-weight: 600;">
                        <i class="far fa-calendar-alt"></i> ${formatThaiDate(c.start_date)} <br>ถึง ${formatThaiDate(c.end_date)}
                       </span>`;
        }

        tbody.insertAdjacentHTML('beforeend', `<tr class="text-center">
            <td class="fw-bold">${c.course_code}</td>
            <td class="text-primary fw-bold">${c.initials || '-'}</td>
            <td class="text-start">${c.course_name}</td>
            <td>${c.credits || '-'}</td>
            <td class="text-start">${c.teacher_full_names || '-'}</td>
            <td><span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-2 py-1" style="font-size: 0.65rem;">${c.room_number || '-'}</span></td>
            <td>${dateTxt}</td>
        </tr>`);
    });
}

function switchView(mode) {
    document.getElementById('room-select-area').style.display = mode === 'room' ? 'block' : 'none';
    document.getElementById('teacher-select-area').style.display = mode === 'teacher' ? 'block' : 'none';
    if (mode === 'room') renderRoom(document.getElementById('roomSelect').value);
    else if (mode === 'teacher') renderTeacher();
    else renderOverview();
}

window.onload = () => renderRoom(Object.keys(data.classrooms)[0]);
</script>
</body>
</html>