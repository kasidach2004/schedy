// js/schedule_js.js

// --- 0. Loading Screen ---
document.addEventListener("DOMContentLoaded", function() {
    setTimeout(function() {
        const loader = document.getElementById('loading-overlay');
        if(loader) {
            loader.style.opacity = '0';
            setTimeout(() => loader.remove(), 500); 
        }
    }, 1000); 
});

// === ส่วนการทำงานของ Action Logger (ส่ง Log ผ่าน AJAX) ===
function logAction(actionName, details) {
    fetch('api_log_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: actionName, details: details })
    }).catch(err => console.error('Log Error:', err));
}
// ========================================================

// --- 1. เตรียมข้อมูล ---
const data = window.scheduleData;
data.selectedCurriculumId = data.curriculums.length ? data.curriculums[0].id : null;
let scheduledItemsMemory = [];
const scheduledCourseIds = new Set();
const teacherLoads = {};
if (data.teachers) {
    data.teachers.forEach(t => {
        teacherLoads[t.id] = { initials: t.initials, max: parseFloat(t.max_load) || 0, used: 0 };
    });
}

const gridContainer = document.getElementById('schedule-grid-container');
const courseList = document.getElementById('course-list');
const selectCurriculum = document.getElementById('curriculum-select');
const roomFilter = document.getElementById('room-print-filter');
const sidebar = document.getElementById('sidebar');
const sidebarToggle = document.getElementById('sidebarToggle');

let dragSourceInfo = null; 

function findCourse(courseId, curriculumId) {
    let found = null;
    if (curriculumId && data.curriculumCourses[curriculumId]) {
        found = data.curriculumCourses[curriculumId].find(c => c.course_id == courseId);
    }
    if (!found) {
        for (const key in data.curriculumCourses) {
            found = data.curriculumCourses[key].find(c => c.course_id == courseId);
            if (found) break;
        }
    }
    return found;
}

function scrollToDay(dayId) {
    const dayElement = document.getElementById(`day-anchor-${dayId}`);
    if (dayElement) dayElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function toggleDayNavigator() {
    const navContainer = document.getElementById('dayNavContainer');
    const navPanel = document.getElementById('dayNavPanel');
    navContainer.classList.toggle('collapsed');
    navPanel.classList.toggle('collapsed');
}

function printSchedule() {
    logAction('Print Schedule', `สั่งพิมพ์ตารางสอน: ${data.schedule_info.schedule_name}`);
    if (!document.body.classList.contains('single-room-mode')) {
        generatePrintSplitPages();
    }
    window.print();
}

window.addEventListener('beforeprint', function() {
    const sidebarEl = document.getElementById('sidebar');
    if (sidebarEl) sidebarEl.style.cssText = 'display:none!important;visibility:hidden!important;width:0!important;overflow:hidden!important;padding:0!important;border:none!important;';
});
window.addEventListener('afterprint', function() {
    const sidebarEl = document.getElementById('sidebar');
    if (sidebarEl) sidebarEl.style.cssText = '';
    document.getElementById('print-split-container').innerHTML = '';
});

function exportSchedule() {
    swalKeepScroll({
        title: 'ต้องการ Export .xls หรือไม่?',
        text: "ระบบจะทำการบันทึกข้อมูลก่อน Export",
        icon: 'info',
        showCancelButton: true,
        confirmButtonText: 'บันทึกและ Export',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            logAction('Export Schedule', `กด Export ตารางสอน: ${data.schedule_info.schedule_name}`);
            saveSchedule(true);
        }
    });
}

function getScrollEl() { return document.querySelector('.content'); }

async function swalKeepScroll(options) {
    const el = getScrollEl();
    const top  = el ? el.scrollTop  : 0;
    const left = el ? el.scrollLeft : 0;

    const origDidOpen   = options.didOpen;
    const origWillClose = options.willClose;

    options.heightAuto = false;
    options.returnFocus = false;
    options.focusConfirm = false;

    options.didOpen = (popup) => {
        if (el) { el.scrollTop = top; el.scrollLeft = left; }
        if (origDidOpen) origDidOpen(popup);
    };
    options.willClose = (popup) => {
        if (origWillClose) origWillClose(popup);
    };
    
    const result = await Swal.fire(options);
    
    if (el) {
        el.scrollTop  = top;
        el.scrollLeft = left;
        requestAnimationFrame(() => { el.scrollTop  = top; el.scrollLeft = left; });
    }
    return result;
}

let hasUnsavedChanges = false;
function markUnsaved() { hasUnsavedChanges = true; document.getElementById('btnSaveFloat').classList.add('has-unsaved'); }
function markSaved() { hasUnsavedChanges = false; document.getElementById('btnSaveFloat').classList.remove('has-unsaved'); }

window.addEventListener('beforeunload', function(e) {
    if (hasUnsavedChanges) { e.preventDefault(); e.returnValue = ''; }
});

document.addEventListener('click', function(e) {
    const link = e.target.closest('a[href]');
    if (!link) return;
    const href = link.getAttribute('href');
    if (!href || href === '#' || href.startsWith('javascript')) return;
    if (!hasUnsavedChanges) return;

    e.preventDefault();
    swalKeepScroll({
        title: 'ยังไม่ได้บันทึก!',
        html: `มีการเปลี่ยนแปลงที่ยังไม่ได้บันทึก<br><small class="text-muted">ต้องการบันทึกก่อนออกจากหน้านี้หรือไม่?</small>`,
        icon: 'warning',
        showDenyButton: true,
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-save"></i> บันทึกแล้วออก',
        denyButtonText: '<i class="fas fa-sign-out-alt"></i> ออกเลย',
        cancelButtonText: 'อยู่ต่อ',
        confirmButtonColor: '#198754',
        denyButtonColor: '#dc3545',
    }).then((result) => {
        if (result.isConfirmed) { saveScheduleAndRedirect(href); } 
        else if (result.isDenied) { hasUnsavedChanges = false; window.location.href = href; }
    });
});

function saveScheduleAndRedirect(redirectUrl) {
    const scheduleData = [];
    document.querySelectorAll('.scheduled-course').forEach(div => {
        const td = div.closest('td');
        const tr = td.closest('tr');
        let chunkId = div.dataset.chunkId;
        if (!chunkId) chunkId = `${div.dataset.curriculumId}_${div.dataset.courseId}_chunk_0`;
        scheduleData.push({
            room_id: tr.dataset.roomId,
            day_of_week: tr.dataset.day,
            timeslot_id: td.dataset.time,
            course_id: div.dataset.courseId,
            curriculum_id: div.dataset.curriculumId,
            teacher_ids: JSON.parse(div.dataset.teachers),
            duration: div.dataset.hours,
            credits: parseFloat(div.dataset.credits) || 0,
            chunk_id: chunkId,
            physical_room: div.dataset.physicalRoom || '',
            start_date: div.dataset.startDate || null,
            end_date: div.dataset.endDate || null
        });
    });
    Swal.fire({ title: 'กำลังบันทึก...', allowOutsideClick: false, heightAuto: false, didOpen: () => Swal.showLoading() });
    fetch('save_schedule_process.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ schedule_id: data.schedule_info.id, items: scheduleData })
    })
    .then(r => r.json())
    .then(() => { hasUnsavedChanges = false; window.location.href = redirectUrl; })
    .catch(() => { swalKeepScroll({ title: 'เกิดข้อผิดพลาด', text: 'ไม่สามารถบันทึกได้', icon: 'error' }).then(() => { window.location.href = redirectUrl; }); });
}

document.addEventListener('dragstart', function(e) {
    const target = e.target.closest('.draggable-course, .scheduled-course');
    if (!target) return;

    if (target.classList.contains('scheduled-course')) {
        const td = target.closest('td');
        const tr = td ? td.closest('tr') : null;
        if (td && tr) {
            dragSourceInfo = { roomId: tr.dataset.roomId, dayIdx: tr.dataset.day, timeslotId: td.dataset.time, element: target };
        }
    } else { dragSourceInfo = null; }

    e.dataTransfer.setData('course-id', target.dataset.id || target.dataset.courseId);
    e.dataTransfer.setData('curriculum-id', target.dataset.curriculumId || '');
    e.dataTransfer.setData('hours', target.dataset.hours);
    e.dataTransfer.setData('credits', target.dataset.credits);
    e.dataTransfer.setData('teachers', target.dataset.teachers);
    e.dataTransfer.setData('chunk-id', target.dataset.chunkId || '');
    e.dataTransfer.setData('physical-room', target.dataset.physicalRoom || '');
    e.dataTransfer.setData('start-date', target.dataset.startDate || '');
    e.dataTransfer.setData('end-date', target.dataset.endDate || '');
    setTimeout(() => target.style.opacity = '0.5', 0);
});

document.addEventListener('dragend', function(e) {
    const target = e.target.closest('.draggable-course, .scheduled-course');
    if (target) target.style.opacity = '1';
    dragSourceInfo = null;
});

document.addEventListener('dragover', function(e) {
    const dropzone = e.target.closest('.dropzone');
    if (dropzone && !dropzone.classList.contains('merged')) { e.preventDefault(); dropzone.classList.add('drag-over'); }
});

document.addEventListener('dragleave', function(e) {
    const dropzone = e.target.closest('.dropzone');
    if (dropzone) dropzone.classList.remove('drag-over');
});

document.addEventListener('drop', function(e) {
    const dropzone = e.target.closest('.dropzone');
    if (!dropzone) return;
    e.preventDefault();
    dropzone.classList.remove('drag-over');
    handleDropLogic(e, dropzone);
});

sidebarToggle.addEventListener('click', () => {
    sidebar.classList.toggle('collapsed');
    const icon = sidebarToggle.querySelector('i');
    icon.className = sidebar.classList.contains('collapsed') ? 'fas fa-chevron-right' : 'fas fa-chevron-left';
});

roomFilter.addEventListener('change', (e) => {
    const selectedRoomId = e.target.value;
    const allRows = document.querySelectorAll('#schedule-grid-container tr[data-room-id]');
    
    if (selectedRoomId === 'all') {
        document.body.classList.remove('single-room-mode');
        allRows.forEach(row => row.style.display = '');
        document.getElementById('combined-print-container').innerHTML = '';
    } else {
        document.body.classList.add('single-room-mode');
        allRows.forEach(row => { row.style.display = (row.dataset.roomId === selectedRoomId) ? '' : 'none'; });
        generateCombinedTable(selectedRoomId);
    }
});

function getRoomColor(roomName) {
    if (!roomName) return 'linear-gradient(135deg, #6c757d, #495057)'; 
    const prefix = roomName.trim().split(/[\s-]+/)[0].toUpperCase();
    let hash = 0;
    for (let i = 0; i < prefix.length; i++) hash = prefix.charCodeAt(i) + ((hash << 5) - hash);
    const palettes = ['linear-gradient(135deg, #fd7e14, #d65b00)', 'linear-gradient(135deg, #0d6efd, #0a58ca)', 'linear-gradient(135deg, #6f42c1, #4e2c8a)', 'linear-gradient(135deg, #dc3545, #b02a37)', 'linear-gradient(135deg, #198754, #115c39)', 'linear-gradient(135deg, #20c997, #148a73)', 'linear-gradient(135deg, #e83e8c, #c91f69)', 'linear-gradient(135deg, #0dcaf0, #0aa2c0)'];
    return palettes[Math.abs(hash % palettes.length)];
}

function getTeacherInitialsString(idsArray) {
    if (!idsArray || !Array.isArray(idsArray)) return '';
    return idsArray.map(id => teacherLoads[id] ? teacherLoads[id].initials : '').filter(Boolean).join(', ');
}

function formatThaiDate(dateStr) {
    if (!dateStr) return '';
    const [y, m, d] = dateStr.split('-');
    if (!y || !m || !d) return '';
    const months = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    const thaiYear = parseInt(y) + 543;
    return `${parseInt(d)} ${months[parseInt(m) - 1]} ${thaiYear}`;
}

function removeItemFromGridSilently(roomId, dayIdx, timeslotId) {
    const tr = document.querySelector(`tr[data-room-id="${roomId}"][data-day="${dayIdx}"]`);
    if (!tr) return;
    const td = tr.querySelector(`td[data-time="${timeslotId}"]`);
    if (!td) return;

    const div = td.querySelector('.scheduled-course');
    if (!div) return;

    const courseId = div.dataset.courseId;
    const currId = div.dataset.curriculumId;
    const hours = parseInt(div.dataset.hours);
    const credits = parseFloat(div.dataset.credits) || 0;
    const chunkId = div.dataset.chunkId;
    const teacherIds = JSON.parse(div.dataset.teachers || '[]');

    const loadPerHead = parseFloat(credits) || 0; 
    teacherIds.forEach(tid => { 
        if(teacherLoads[tid]) { teacherLoads[tid].used = Math.max(0, teacherLoads[tid].used - loadPerHead); }
    });

    scheduledItemsMemory = scheduledItemsMemory.filter(i => !(i.room_id == roomId && i.day == dayIdx && i.timeslot_id == timeslotId));
    if (chunkId) { scheduledCourseIds.delete(chunkId); } else { scheduledCourseIds.delete(`${currId}_${courseId}`); }

    const times = data.timeslots;
    const startIndex = times.findIndex(t => t.id == timeslotId);

    td.innerHTML = ''; td.removeAttribute('colspan'); td.classList.remove('merged'); td.classList.add('dropzone');

    for(let i = 1; i < hours; i++) {
        const nextTime = times[startIndex + i];
        if(nextTime) {
            const newTd = document.createElement('td');
            newTd.className = 'dropzone'; newTd.dataset.room = roomId; newTd.dataset.day = dayIdx; newTd.dataset.time = nextTime.id;
            td.after(newTd);
        }
    }
}

async function handleDropLogic(e, dropzone) {
    const roomId = parseInt(dropzone.dataset.room);
    const dayIdx = parseInt(dropzone.dataset.day);
    const timeId = parseInt(dropzone.dataset.time);
    const courseId = e.dataTransfer.getData('course-id');
    const curriculumId = e.dataTransfer.getData('curriculum-id');
    let hours = parseInt(e.dataTransfer.getData('hours'));
    const originalCredits = parseFloat(e.dataTransfer.getData('credits')) || 0; 
    let chunkId = e.dataTransfer.getData('chunk-id') || '';
    const physicalRoomFromDrag = e.dataTransfer.getData('physical-room') || '';
    
    let teacherIds = [];
    try { teacherIds = JSON.parse(e.dataTransfer.getData('teachers')); } catch(err) { teacherIds = []; }

    const sourceInfo = dragSourceInfo;
    dragSourceInfo = null;
    let currentWorkload = originalCredits;
    let currentPhysicalRoom = physicalRoomFromDrag;
    
    // วันที่และโหมด
    let startDate = e.dataTransfer.getData('start-date') || '';
    let endDate = e.dataTransfer.getData('end-date') || '';
    let isSpecialMode = false;
    let splitBlock = false;
    
    // เช็คว่านี่คือ Custom Chunk ที่ถูกแบ่งและสร้างไว้ก่อนหน้านี้หรือเปล่า
    const isCustomChunk = chunkId && chunkId.includes('_custom_');

    if (!currentPhysicalRoom && !sourceInfo) {
        const courseData = findCourse(courseId, curriculumId);
        if (courseData && courseData.assigned_room) currentPhysicalRoom = courseData.assigned_room;
    }

    // กรณี: ย้ายบล็อกที่วางไว้แล้วบนตาราง
    if (sourceInfo) {
        if (sourceInfo.roomId == roomId && sourceInfo.dayIdx == dayIdx && sourceInfo.timeslotId == timeId) return;
        currentWorkload = parseFloat(sourceInfo.element.dataset.credits) || 0;
        currentPhysicalRoom = sourceInfo.element.dataset.physicalRoom || currentPhysicalRoom;
        startDate = sourceInfo.element.dataset.startDate || '';
        endDate = sourceInfo.element.dataset.endDate || '';
        hours = parseInt(sourceInfo.element.dataset.hours) || hours;
        try { teacherIds = JSON.parse(sourceInfo.element.dataset.teachers || '[]'); } catch(ex){}
        logAction('Move Course', `ย้ายวิชา (Course ID: ${courseId}) ไปห้อง ${roomId} วันที่ ${dayIdx}`);
    } 
    // กรณี: ลากบล็อกมาจากแถบเมนูด้านขวา
    else {
        // === STEP 0: เช็คชื่อห้อง/หลักสูตรก่อน ===
        const curObj = data.curriculums.find(c => c.id == curriculumId);
        const roomObj = data.classrooms.find(r => r.id == roomId);
        
        if (curObj && roomObj) {
            const normRoom = roomObj.room_name.replace(/\s+/g, '').toUpperCase();
            const normCurr = curObj.curriculum_name.replace(/\s+/g, '').toUpperCase();
            if (normRoom !== normCurr) {
                await swalKeepScroll({ 
                    title: 'ชื่อห้องไม่ตรงกับหลักสูตร!', 
                    text: `หลักสูตร "${curObj.curriculum_name}" ต้องลงในห้องที่ชื่อตรงกัน 100% เท่านั้น`, 
                    icon: 'error', 
                    confirmButtonText: 'ตกลง', 
                    confirmButtonColor: '#d33' 
                });
                return;
            }
        }

        let allBlocksData = [];

        // หากลาก Custom Chunk (บล็อกที่สองหรือสาม) มาจาก Sidebar ไม่ต้องถามโหมด/วันที่แล้ว
        if (isCustomChunk) {
            isSpecialMode = true; 
            splitBlock = false;
        } else {
            // --- STEP 1: แสดงเมนูเลือกประเภทการวางวิชา (ปกติ/พิเศษ) ---
            const modeChoice = await new Promise((resolve) => {
                const el = getScrollEl();
                const scrollTop = el ? el.scrollTop : 0;
                const scrollLeft = el ? el.scrollLeft : 0;

                Swal.fire({
                    title: 'เลือกรูปแบบการวางวิชา',
                    html: `
                        <div style="display:flex; gap:20px; justify-content:center; padding:12px 0;">
                            <button id="mode-btn-normal"
                                style="flex:1;max-width:220px;padding:24px 16px;border-radius:8px;
                                       border:2px solid #0d6efd;background:#f8faff;cursor:pointer;
                                       transition:all .2s ease;outline:none;font-family:inherit;font-size:inherit;">
                                <div style="font-weight:700;font-size:1.1rem;color:#0d6efd;margin-bottom:8px;">ปกติ</div>
                                <div style="font-size:0.85rem;color:#495057;line-height:1.5;">
                                    ตามหลักสูตร ป.ตรี<br>ไม่ระบุวันที่พิเศษ
                                </div>
                            </button>
                            <button id="mode-btn-special"
                                style="flex:1;max-width:220px;padding:24px 16px;border-radius:8px;
                                       border:2px solid #6f42c1;background:#f8f5ff;cursor:pointer;
                                       transition:all .2s ease;outline:none;font-family:inherit;font-size:inherit;">
                                <div style="font-weight:700;font-size:1.1rem;color:#6f42c1;margin-bottom:8px;">พิเศษ</div>
                                <div style="font-size:0.85rem;color:#495057;line-height:1.5;">
                                    ป.โท / บล็อกพิเศษ<br>กำหนดชั่วโมง + วันที่
                                </div>
                            </button>
                        </div>`,
                    showConfirmButton: false,
                    showCancelButton: true,
                    cancelButtonText: 'ยกเลิก',
                    heightAuto: false,
                    returnFocus: false,
                    width: 550,
                    didOpen: () => {
                        if (el) { el.scrollTop = scrollTop; el.scrollLeft = scrollLeft; }
                        document.getElementById('mode-btn-normal').addEventListener('click', () => {
                            Swal.close(); resolve('normal');
                        });
                        document.getElementById('mode-btn-special').addEventListener('click', () => {
                            Swal.close(); resolve('special');
                        });
                    }
                }).then((res) => {
                    if (res.isDismissed) resolve('cancel');
                });
            });

            if (modeChoice === 'cancel') return;
            isSpecialMode = (modeChoice === 'special');

            // --- STEP 1b: ถ้าเลือกโหมดพิเศษ ถามเรื่องการแบ่งบล็อก และวันที่ ---
            if (isSpecialMode) {
                const splitChoice = await swalKeepScroll({
                    title: 'ตั้งค่าบล็อก',
                    html: `
                        <div class="text-start">
                            <p class="fw-bold mb-3">ต้องการแบ่งบล็อกหรือไม่?</p>
                            <div class="mb-3">
                                <label class="form-check">
                                    <input class="form-check-input" type="radio" name="split-choice" value="no" checked>
                                    <span class="form-check-label" style="margin-left: 8px;">ไม่แบ่ง - ใช้บล็อกเดียว</span>
                                </label>
                            </div>
                            <div class="mb-3">
                                <label class="form-check">
                                    <input class="form-check-input" type="radio" name="split-choice" value="yes">
                                    <span class="form-check-label" style="margin-left: 8px;">แบ่งบล็อก</span>
                                </label>
                            </div>
                            <div style="border-left: 3px solid #6f42c1; padding-left: 12px; margin-top: 12px;">
                                <p style="font-size: 0.9rem; color: #495057; margin: 0;">
                                    ตัวอย่าง: แบ่งวิชา 2 ส่วนที่เรียนคนละเวลา บล็อกแรกจะลงตารางทันที บล็อกที่เหลือจะกลับไปรอที่เมนูขวามือ
                                </p>
                            </div>
                        </div>`,
                    showCancelButton: true,
                    confirmButtonText: 'ถัดไป',
                    cancelButtonText: 'ยกเลิก',
                    confirmButtonColor: '#6f42c1',
                    preConfirm: () => {
                        const choice = document.querySelector('input[name="split-choice"]:checked').value;
                        return { split: choice === 'yes' };
                    }
                });

                if (splitChoice.isDismissed || !splitChoice.value) return;
                splitBlock = splitChoice.value.split;

                let numBlocks = 1;
                if (splitBlock) {
                    const blockCountResult = await swalKeepScroll({
                        title: 'เลือกจำนวนบล็อก',
                        html: `
                            <div class="text-start">
                                <p class="mb-3">ต้องการแบ่งเป็นกี่บล็อก?</p>
                                <div class="mb-2"><label class="form-check"><input class="form-check-input" type="radio" name="block-count" value="2" checked><span class="form-check-label ms-2">2 บล็อก</span></label></div>
                                <div class="mb-2"><label class="form-check"><input class="form-check-input" type="radio" name="block-count" value="3"><span class="form-check-label ms-2">3 บล็อก</span></label></div>
                                <div><label class="form-check"><input class="form-check-input" type="radio" name="block-count" value="4"><span class="form-check-label ms-2">4 บล็อก</span></label></div>
                            </div>`,
                        showCancelButton: true,
                        confirmButtonText: 'ถัดไป',
                        cancelButtonText: 'ยกเลิก',
                        confirmButtonColor: '#6f42c1',
                        preConfirm: () => {
                            return { blockCount: parseInt(document.querySelector('input[name="block-count"]:checked').value) };
                        }
                    });

                    if (blockCountResult.isDismissed || !blockCountResult.value) return;
                    numBlocks = blockCountResult.value.blockCount;
                }

                // Loop ถามชั่วโมงแต่ละบล็อก (ถามวันที่แค่รอบแรกเท่านั้น!)
                let sharedStartDate = '';
                let sharedEndDate = '';

                for (let blockIdx = 0; blockIdx < numBlocks; blockIdx++) {
                    const blockNum = blockIdx + 1;
                    const defaultHours = numBlocks === 1 ? hours : Math.ceil(hours / numBlocks);
                    const isFirstBlock = blockIdx === 0;

                    let dateInputs = '';
                    if (isFirstBlock) {
                        dateInputs = `
                            <div class="row mt-3">
                                <div class="col-6">
                                    <label class="form-label fw-bold" style="font-size: 0.9rem;">วันที่เริ่มเรียน</label>
                                    <input id="shared-start-date" type="date" class="form-control" style="border-color: #6f42c1;">
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-bold" style="font-size: 0.9rem;">วันสิ้นสุด</label>
                                    <input id="shared-end-date" type="date" class="form-control" style="border-color: #6f42c1;">
                                </div>
                            </div>
                        `;
                    } else {
                        dateInputs = `
                            <div class="mt-3 p-2 rounded" style="background: #f8f9fa; border-left: 3px solid #6f42c1;">
                                <small class="text-muted"><i class="fas fa-info-circle"></i> วันที่เรียนอ้างอิงจากบล็อก 1 อัตโนมัติ</small>
                            </div>
                        `;
                    }

                    const blockResult = await swalKeepScroll({
                        title: numBlocks > 1 ? `ตั้งค่าชั่วโมง บล็อกที่ ${blockNum}/${numBlocks}` : `ตั้งค่าชั่วโมงและวันที่`,
                        html: `
                            <div class="text-start">
                                <div class="mb-2">
                                    <label class="form-label fw-bold" style="font-size: 0.9rem;">จำนวนชั่วโมงเรียน</label>
                                    <input id="block-hours-${blockIdx}" type="number" class="form-control text-center fw-bold text-primary"
                                        style="font-size: 1.2rem; border-color: #6f42c1;"
                                        value="${defaultHours}" min="1" max="12">
                                </div>
                                ${dateInputs}
                            </div>`,
                        showCancelButton: true,
                        confirmButtonText: blockIdx < numBlocks - 1 ? 'ถัดไป <i class="fas fa-arrow-right"></i>' : 'เลือกอาจารย์และห้อง',
                        cancelButtonText: 'ยกเลิก',
                        confirmButtonColor: '#6f42c1',
                        preConfirm: () => {
                            const h = parseInt(document.getElementById(`block-hours-${blockIdx}`).value);
                            if (!h || h < 1) { Swal.showValidationMessage('กรุณาระบุชั่วโมงเรียน (อย่างน้อย 1)'); return false; }
                            
                            let s = sharedStartDate;
                            let en = sharedEndDate;
                            if (isFirstBlock) {
                                s = document.getElementById('shared-start-date').value;
                                en = document.getElementById('shared-end-date').value;
                                if (s && en && s > en) { Swal.showValidationMessage('วันที่เริ่มต้นต้องไม่อยู่หลังวันสิ้นสุด'); return false; }
                            }
                            return { hours: h, startDate: s, endDate: en };
                        }
                    });

                    if (blockResult.isDismissed || !blockResult.value) return;

                    if (isFirstBlock) {
                        sharedStartDate = blockResult.value.startDate;
                        sharedEndDate = blockResult.value.endDate;
                    }

                    allBlocksData.push({
                        blockNum: blockNum,
                        hours: blockResult.value.hours,
                        startDate: sharedStartDate,
                        endDate: sharedEndDate
                    });
                }
            }
        } // End of popup 1 & 2 & 3 flow

        // Popup 4: ยืนยันข้อมูลอาจารย์และห้อง
        let teacherCheckboxes = '<div class="list-group list-group-flush">';
        data.teachers.forEach(t => {
            const checked = (teacherIds.includes(t.id.toString()) || teacherIds.includes(t.id)) ? 'checked' : '';
            teacherCheckboxes += `
                <label class="list-group-item d-flex align-items-center" style="cursor: pointer; padding: 6px 12px; border-bottom: 1px solid #eee;">
                    <input class="form-check-input me-3 swal-teacher-cb" type="checkbox" value="${t.id}" ${checked}>
                    <span class="text-dark fw-bold" style="font-size: 0.85rem;">${t.initials}</span>
                </label>`;
        });
        teacherCheckboxes += '</div>';

        let roomOptions = '<div class="list-group list-group-flush">';
        let selectedRoomsArr = currentPhysicalRoom ? currentPhysicalRoom.split(',').map(s => s.trim()) : [];
        
        let roomsSource = (data.physical_rooms && data.physical_rooms.length > 0) 
            ? data.physical_rooms.map(r => r.room_number) : [];
            
        if (roomsSource.length === 0) { 
            let allKnown = new Set();
            for (const curr in data.curriculumCourses) {
                data.curriculumCourses[curr].forEach(c => {
                    if (c.assigned_room) c.assigned_room.split(',').forEach(r => allKnown.add(r.trim()));
                });
            }
            roomsSource = Array.from(allKnown).filter(r => r).sort();
        }

        roomsSource.forEach(rName => {
            const checked = selectedRoomsArr.includes(rName) ? 'checked' : '';
            roomOptions += `
                <label class="list-group-item d-flex align-items-center" style="cursor: pointer; padding: 6px 12px; border-bottom: 1px solid #eee;">
                    <input class="form-check-input me-3 swal-room-cb" type="checkbox" value="${rName}" ${checked}>
                    <span class="text-dark" style="font-size: 0.85rem;">${rName}</span>
                </label>`;
        });
        roomOptions += '</div>';

        let unlistedRooms = selectedRoomsArr.filter(r => !roomsSource.includes(r));
        let manualRoomVal = unlistedRooms.join(', ');

        const isChunk = chunkId && chunkId.includes('_chunk_');

        // ข้อมูลที่จะแสดงใน Badge ด้านบน
        let h_display = hours;
        let start_display = startDate;
        let end_display = endDate;
        if (allBlocksData.length > 0) {
            h_display = allBlocksData[0].hours;
            start_display = allBlocksData[0].startDate;
            end_display = allBlocksData[0].endDate;
        }

        let specialModeHTML = '';
        if (isSpecialMode || splitBlock || isCustomChunk) {
            const displayStart = start_display ? start_display.split('-').reverse().join('/') : '-';
            const displayEnd   = end_display   ? end_display.split('-').reverse().join('/')   : '-';
            const modeLabel = isCustomChunk ? 'บล็อกที่แบ่งไว้' : (splitBlock ? 'แบ่งบล็อก' : 'บล็อกพิเศษ');
            const bgColor = splitBlock ? '#f0fafb' : '#f8f0ff';
            const borderColor = splitBlock ? '#31697E' : '#6f42c1';
            specialModeHTML = `
                <div class="text-start mb-3 p-3 rounded" style="background-color: ${bgColor}; border: 2px solid ${borderColor};">
                    <h6 class="fw-bold mb-2" style="color: ${borderColor};"><i class="fas fa-star text-warning"></i> ${modeLabel} — ข้อมูลที่กรอกไว้</h6>
                    <div class="d-flex gap-3 flex-wrap">
                        <div class="badge fs-6 px-3 py-2" style="background:${borderColor};"><i class="fas fa-clock"></i> ${h_display} ชั่วโมง</div>
                        <div class="badge fs-6 px-3 py-2 bg-success"><i class="fas fa-calendar-plus"></i> เริ่ม: ${displayStart}</div>
                        <div class="badge fs-6 px-3 py-2 bg-danger"><i class="fas fa-calendar-check"></i> สิ้นสุด: ${displayEnd}</div>
                    </div>
                </div>
            `;
        }

        const { value: formValues, isDismissed } = await swalKeepScroll({
            title: isCustomChunk ? 'ยืนยันข้อมูลลงตาราง' : (isSpecialMode ? (splitBlock ? 'ตั้งค่าข้อมูล (แบ่งบล็อก)' : 'ตั้งค่าข้อมูล (บล็อกพิเศษ)') : (isChunk ? 'ตั้งค่าบล็อกแบ่งสอน' : 'ตั้งค่าข้อมูลก่อนลงตาราง')),
            width: 750,
            html: `
                ${specialModeHTML}
                <div class="row text-start mb-3">
                    <div class="col-6">
                        <label class="form-label text-dark fw-bold" style="font-size: 0.95rem;"><i class="fas fa-users text-primary"></i> 1. เลือกอาจารย์:</label>
                        <div class="border rounded bg-white shadow-sm" style="height: 180px; overflow-y: auto;">
                            ${teacherCheckboxes}
                        </div>
                         <div class="mt-2">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white border-secondary"><i class="fas fa-search text-muted"></i></span>
                                <input type="text" class="form-control border-secondary" placeholder="ค้นหาอาจารย์..." oninput="
                                    let filter = this.value.toUpperCase();
                                    let labels = this.parentElement.parentElement.previousElementSibling.querySelectorAll('label');
                                    labels.forEach(label => {
                                        let text = label.textContent || label.innerText;
                                        if (text.toUpperCase().indexOf(filter) > -1) {
                                            label.style.setProperty('display', 'flex', 'important');
                                        } else {
                                            label.style.setProperty('display', 'none', 'important');
                                        }
                                    });
                                ">
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <label class="form-label text-dark fw-bold" style="font-size: 0.95rem;"><i class="fas fa-door-open text-danger"></i> 2. เลือกหมายเลขห้อง:</label>
                        <div class="border rounded bg-white shadow-sm" style="height: 145px; overflow-y: auto;">
                            ${roomOptions}
                        </div>

                        <div class="mt-2">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white border-secondary"><i class="fas fa-search text-muted"></i></span>
                                <input type="text" class="form-control border-secondary" placeholder="ค้นหาหมายเลขห้องเรียน..." oninput="
                                    let filter = this.value.toUpperCase();
                                    let labels = this.parentElement.parentElement.previousElementSibling.querySelectorAll('label');
                                    labels.forEach(label => {
                                        let text = label.textContent || label.innerText;
                                        if (text.toUpperCase().indexOf(filter) > -1) {
                                            label.style.setProperty('display', 'flex', 'important');
                                        } else {
                                            label.style.setProperty('display', 'none', 'important');
                                        }
                                    });
                                ">
                            </div>
                            <input type="hidden" id="swal-input-room-manual" value="${manualRoomVal}">
                        </div>
                    </div>
                </div>
                <div class="text-start p-3 bg-light border rounded">
                    <label class="form-label text-dark fw-bold" style="font-size: 0.95rem;"><i class="fas fa-balance-scale text-warning"></i> 3. กำหนดภาระงาน (ต่อ 1 คน):</label>
                    <div class="input-group">
                        <input id="swal-input-workload" type="number" step="0.5" min="0" class="form-control text-center text-primary fw-bold" style="font-size: 1.1rem;" value="${originalCredits}">
                        <span class="input-group-text bg-white text-dark fw-bold">ภาระงานเต็มจำนวน</span>
                    </div>
                    <small class="text-danger mt-1 d-block" style="font-size: 0.8rem;">
                        <i class="fas fa-info-circle"></i> อาจารย์ทุกคนที่เลือก จะได้รับภาระงานตามที่ระบุด้านบนนี้เต็มจำนวน
                    </small>
                </div>
            `,
            focusConfirm: false,
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-check"></i> วางลงตาราง',
            cancelButtonText: 'ยกเลิก',
            preConfirm: () => {
                const selectedTeachers = Array.from(document.querySelectorAll('.swal-teacher-cb:checked')).map(cb => parseInt(cb.value));
                const assignedWorkload = parseFloat(document.getElementById('swal-input-workload').value) || 0;
                
                let selectedCbRooms = Array.from(document.querySelectorAll('.swal-room-cb:checked')).map(cb => cb.value);
                const manualRooms = document.getElementById('swal-input-room-manual').value.split(',').map(s => s.trim()).filter(s => s);
                
                let finalRooms = [...new Set([...selectedCbRooms, ...manualRooms])];
                const assignedRoom = finalRooms.join(', ');

                if(selectedTeachers.length === 0) {
                    Swal.showValidationMessage('กรุณาเลือกอาจารย์ผู้สอนอย่างน้อย 1 ท่าน');
                    return false;
                }
                return { 
                    teachers: selectedTeachers, 
                    workload: assignedWorkload, 
                    room: assignedRoom
                };
            }
        });

        if (isDismissed || !formValues) return;

        teacherIds = formValues.teachers;
        currentWorkload = formValues.workload;
        currentPhysicalRoom = formValues.room;

        // ถ้าเพิ่งกดแบ่งบล็อกใหม่ๆ เราต้องสร้าง Custom Chunks ส่งกลับไปที่ Sidebar
        if (splitBlock && allBlocksData.length > 1) {
            const courseOriginal = findCourse(courseId, curriculumId);
            if (courseOriginal) {
                // สร้าง Custom Chunks เข้าไปใน object ของวิชานี้
                courseOriginal.custom_chunks = allBlocksData.map((b, idx) => ({
                    ...courseOriginal,
                    chunk_hours: b.hours,
                    is_chunked: true,
                    original_hours: courseOriginal.teaching_hours,
                    chunk_index: idx,
                    chunk_id: `${curriculumId}_${courseId}_custom_${idx}`,
                    start_date: b.startDate,
                    end_date: b.endDate,
                    pre_teachers: teacherIds,           // ฝังอาจารย์กลับไปที่ sidebar
                    pre_physical_room: currentPhysicalRoom, // ฝังห้องกลับไป
                    pre_credits: currentWorkload        // ฝังภาระงานกลับไป
                }));

                // เซตค่า item ตัวแรก (ที่จะลงตารางตอนนี้เลย)
                chunkId = courseOriginal.custom_chunks[0].chunk_id;
                hours = courseOriginal.custom_chunks[0].chunk_hours;
                startDate = courseOriginal.custom_chunks[0].start_date;
                endDate = courseOriginal.custom_chunks[0].end_date;
            }
        } 
        else if (isSpecialMode && !isCustomChunk) {
            hours = allBlocksData[0].hours;
            startDate = allBlocksData[0].startDate;
            endDate = allBlocksData[0].endDate;
        }

        logAction('Add Course', `ลากวิชา (Course ID: ${courseId}) ลงตารางห้อง ${roomId} วันที่ ${dayIdx}`);
    }

    const item = { 
        room_id: roomId, day: dayIdx, timeslot_id: timeId, 
        course_id: courseId, curriculum_id: curriculumId, duration: hours, 
        credits: currentWorkload, teacher_ids: teacherIds, chunk_id: chunkId,
        physical_room: currentPhysicalRoom,
        start_date: startDate,
        end_date: endDate
    };
    await checkAndPlace(item, sourceInfo);
}

async function checkAndPlace(item, sourceInfo = null) {
    const tr = document.querySelector(`tr[data-room-id="${item.room_id}"][data-day="${item.day}"]`);
    const times = data.timeslots;
    const startIndex = times.findIndex(t => t.id == item.timeslot_id);
    const hours = parseInt(item.duration);

    if (startIndex + hours > times.length) {
        await swalKeepScroll({ title: 'เตือน', text: 'เวลาไม่เพียงพอ (ติดขอบตาราง)', icon: 'warning' });
        return;
    }

    if (sourceInfo) { removeItemFromGridSilently(sourceInfo.roomId, sourceInfo.dayIdx, sourceInfo.timeslotId); }

    let needCells = [];
    for (let i = 0; i < hours; i++) {
        let cell = tr.querySelector(`td[data-time="${times[startIndex + i].id}"]`);
        if (!cell || cell.classList.contains('merged') || cell.querySelector('.scheduled-course')) {
            await swalKeepScroll({ title: 'ช่องตารางไม่ว่าง', text: 'ช่วงเวลานี้มีการลงวิชาอื่นไว้แล้ว', icon: 'error' });
            if (sourceInfo) { restoreSingleItem(item, sourceInfo); }
            return;
        }
        needCells.push(cell);
    }

    const newStartIdx = startIndex;
    const newEndIdx = startIndex + hours;
    
    if (item.physical_room) { 
        let physicalRoomConflict = false;
        let conflictDetails = '';

        scheduledItemsMemory.forEach(exist => {
            if (exist.day == item.day) { 
                const existStartIdx = times.findIndex(t => t.id == exist.timeslot_id);
                const existEndIdx = existStartIdx + parseInt(exist.duration);

                if (newStartIdx < existEndIdx && newEndIdx > existStartIdx) {
                    if (exist.physical_room && exist.physical_room === item.physical_room) {
                        physicalRoomConflict = true;
                        const existCourseDetails = findCourse(exist.course_id, exist.curriculum_id);
                        const conflictRowObj = data.classrooms.find(r => r.id == exist.room_id);
                        const conflictRowName = conflictRowObj ? conflictRowObj.room_name : '??';
                        conflictDetails = `ห้อง "${item.physical_room}" ถูกใช้ไปแล้ว (วิชา ${existCourseDetails ? existCourseDetails.course_code : ''})`;
                    }
                }
            }
        });

        if (physicalRoomConflict) {
            const confirmRoom = await swalKeepScroll({
                title: 'ห้องเรียนมีการใช้งานซ้ำ!', text: `${conflictDetails} ยืนยันจะลงวิชานี้ซ้อนในห้องเดียวกันหรือไม่?`, icon: 'warning', showCancelButton: true, confirmButtonText: 'ยืนยัน', cancelButtonText: 'ยกเลิก', confirmButtonColor: '#d33'
            });
            if (!confirmRoom.isConfirmed) {
                if (sourceInfo) { restoreSingleItem(item, sourceInfo); }
                return;
            }
        }
    }

    let teacherConflict = [];
    scheduledItemsMemory.forEach(exist => {
        if (exist.day == item.day) { 
            const existStartIdx = times.findIndex(t => t.id == exist.timeslot_id);
            const existEndIdx = existStartIdx + parseInt(exist.duration);
            if (newStartIdx < existEndIdx && newEndIdx > existStartIdx) {
                const common = item.teacher_ids.filter(id => exist.teacher_ids.includes(id));
                common.forEach(id => { if (!teacherConflict.includes(id)) teacherConflict.push(id); });
            }
        }
    });

    if (teacherConflict.length > 0) {
        const names = teacherConflict.map(id => teacherLoads[id] ? teacherLoads[id].initials : id).join(', ');
        const resultTeach = await swalKeepScroll({
            title: 'อาจารย์ติดสอน!', text: `อาจารย์ ${names} มีสอนวิชาอื่นในเวลานี้ ยืนยันจะลงซ้อนหรือไม่?`, icon: 'warning', showCancelButton: true, confirmButtonText: 'ยืนยัน', cancelButtonText: 'ยกเลิก'
        });
        if (!resultTeach.isConfirmed) {
            if (sourceInfo) { restoreSingleItem(item, sourceInfo); }
            return;
        }
    }

    const loadPerHead = parseFloat(item.credits) || 0; 
    let loadOver = [];
    item.teacher_ids.forEach(id => {
        if (teacherLoads[id] && (teacherLoads[id].used + loadPerHead) > teacherLoads[id].max) { loadOver.push(teacherLoads[id].initials); }
    });

    if (loadOver.length > 0) {
        const resultLoad = await swalKeepScroll({
            title: 'ภาระงานเกิน!', text: `อาจารย์ ${loadOver.join(', ')} จะมีภาระงานเกินกำหนด ยืนยันจะลงหรือไม่?`, icon: 'info', showCancelButton: true, confirmButtonText: 'ตกลง', cancelButtonText: 'ยกเลิก'
        });
        if (!resultLoad.isConfirmed) {
            if (sourceInfo) { restoreSingleItem(item, sourceInfo); }
            return;
        }
    }

    placeCourseOnGrid(item, needCells, sourceInfo !== null);
}

function restoreSingleItem(item, sourceInfo) {
    const restoreItem = { 
        room_id: sourceInfo.roomId, day: sourceInfo.dayIdx, 
        timeslot_id: sourceInfo.timeslotId, course_id: item.course_id,
        curriculum_id: item.curriculum_id, duration: item.duration,
        credits: item.credits, teacher_ids: item.teacher_ids,
        chunk_id: item.chunk_id, physical_room: item.physical_room,
        start_date: item.start_date, end_date: item.end_date
    };
    const tr = document.querySelector(`tr[data-room-id="${restoreItem.room_id}"][data-day="${restoreItem.day}"]`);
    if (!tr) return;
    const times = data.timeslots;
    const startIdx = times.findIndex(t => t.id == restoreItem.timeslot_id);
    if (startIdx === -1) return;
    let needCells = [];
    for (let i = 0; i < restoreItem.duration; i++) {
        if (times[startIdx + i]) {
            const c = tr.querySelector(`td[data-time="${times[startIdx + i].id}"]`);
            if (c) needCells.push(c);
        }
    }
    if (needCells.length === restoreItem.duration) { placeCourseOnGrid(restoreItem, needCells, false); }
}

function placeCourseOnGrid(item, needCells, isMove = false) {
    const course = findCourse(item.course_id, item.curriculum_id);
    const roomObj = data.classrooms.find(r => r.id == item.room_id);
    const roomName = roomObj ? roomObj.room_name : "";
    const targetCell = needCells[0];
    const hours = parseInt(item.duration);

    const physicalRoom = item.physical_room || '';

    scheduledItemsMemory.push(item);
    const chunkId = item.chunk_id || `${item.curriculum_id}_${item.course_id}`;
    scheduledCourseIds.add(chunkId);
    markUnsaved();

    const loadPerHead = parseFloat(item.credits) || 0; 
    item.teacher_ids.forEach(tid => { if (teacherLoads[tid]) teacherLoads[tid].used += loadPerHead; });

    targetCell.setAttribute('colspan', hours);
    targetCell.classList.add('merged');
    
    // UI Data
    const originalCredits = course ? course.credits : '?';
    const teacherStr = getTeacherInitialsString(item.teacher_ids);
    const courseCode = course ? course.course_code : '??';
    const courseName = course ? course.course_name : 'Unknown';

    // วันที่สำหรับ ป.โท (ถ้ามี) - รูปแบบไทยเต็ม
    let dateBadge = '';
    if (item.start_date && item.end_date) {
        let dateStr = `เริ่มเรียน ${formatThaiDate(item.start_date)}<br>วันสุดท้ายของการเรียน ${formatThaiDate(item.end_date)}`;
        dateBadge = `<div class="badge text-white w-100 mt-1" style="font-size:0.55rem; white-space:normal; line-height:1.2; background-color: #31697E !important; font-weight: normal; border: 1px solid rgba(255,255,255,0.2);">
                        <i class="fas fa-calendar-alt"></i><br>${dateStr}
                     </div>`;
    }

    let innerHTMLContent = '';

    if (hours > 1) {
        innerHTMLContent = `
            <button class="remove-course" onclick="removeCourse(this)" style="position: absolute; top: 2px; right: 2px; padding: 0; font-size: 0.55rem; line-height: 1; z-index: 10; width: 16px; height: 16px;"><i class="fas fa-times"></i></button>

            <div class="w-100" style="padding-right: 18px;">
                <div class="d-flex justify-content-between align-items-start w-100" style="line-height: 1;">
                    <div class="fw-bold text-white text-truncate text-start" style="font-size: 0.65rem; max-width: 65%;" title="${teacherStr}">
                        <i class="fas fa-user-tie"></i> ${teacherStr}
                    </div>
                    <div class="text-white text-end" style="font-size: 0.6rem; opacity: 0.9; white-space: nowrap;">
                        ${originalCredits} นก. | ${hours} ชม.
                    </div>
                </div>
                <div class="text-warning fw-bold text-start w-100 mt-1" style="font-size: 0.6rem; line-height: 1;">
                    <i class="fas fa-balance-scale"></i> โหลด: ${item.credits}
                </div>
            </div>

            <div class="text-center w-100 my-auto px-1">
                <div class="fw-bold text-white" style="font-size: 0.75rem; letter-spacing: 0.5px;">${courseCode}</div>
                <div class="text-white mt-1" style="font-size: 0.65rem; line-height: 1.1; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis;" title="${courseName}">
                    ${courseName}
                </div>
                ${dateBadge}
            </div>

            <div class="text-start text-white w-100 mt-auto text-truncate" style="font-size: 0.6rem;" title="${physicalRoom}">
                ${physicalRoom ? `<i class="fas fa-door-open"></i> ${physicalRoom}` : ''}
            </div>
        `;
    } else {
        innerHTMLContent = `
            <button class="remove-course" onclick="removeCourse(this)" style="position: absolute; top: 1px; right: 1px; padding: 0; font-size: 0.5rem; line-height: 1; z-index: 10; width: 14px; height: 14px; background: rgba(0,0,0,0.3);"><i class="fas fa-times"></i></button>

            <div class="d-flex flex-column h-100 w-100 text-start" style="line-height: 1.1; overflow: hidden; padding-right: 12px;">
                <div class="fw-bold text-white text-truncate" style="font-size: 0.55rem; width: 100%;" title="${teacherStr}">
                    <i class="fas fa-user-tie"></i> ${teacherStr}
                </div>

                <div class="text-white" style="font-size: 0.5rem; opacity: 0.9; margin-top: 1px;">
                    ${originalCredits} นก. | ${hours} ชม.
                </div>

                <div class="text-warning fw-bold text-truncate" style="font-size: 0.5rem; margin-top: 1px;">
                    <i class="fas fa-balance-scale"></i> โหลด: ${item.credits}
                </div>

                <div class="fw-bold text-white text-center mt-1 text-truncate" style="font-size: 0.6rem; letter-spacing: 0.5px; width: 100%;" title="${courseCode}">
                    ${courseCode} 
                </div>

                ${dateBadge}

                <div class="text-white text-center text-truncate" style="font-size: 0.55rem; width: 100%;" title="${courseName}">
                    ${courseName}
                </div>

                <div class="text-white text-truncate mt-auto" style="font-size: 0.5rem; width: 100%;" title="${physicalRoom}">
                    ${physicalRoom ? `<i class="fas fa-door-open"></i> ${physicalRoom}` : ''}
                </div>
            </div>
        `;
    }

    targetCell.innerHTML = `
        <div class="scheduled-course p-1" draggable="true" 
            style="background: ${getRoomColor(roomName)}; box-sizing: border-box; height: 100%; display: flex; flex-direction: column; position: relative; overflow: hidden;" 
            data-course-id="${item.course_id}" data-curriculum-id="${item.curriculum_id}"
            data-hours="${hours}" data-credits="${item.credits}"
            data-chunk-id="${item.chunk_id || ''}"
            data-physical-room="${physicalRoom}"
            data-start-date="${item.start_date || ''}"
            data-end-date="${item.end_date || ''}"
            data-teachers='${JSON.stringify(item.teacher_ids)}'>
            
            ${innerHTMLContent}

        </div>`;

    needCells.slice(1).forEach(td => td.remove());
    if (document.body.classList.contains('single-room-mode')) generateCombinedTable(item.room_id);
    if (!isMove) { renderCourseList(selectCurriculum.value); }
}

window.removeCourse = function(btn) {
    const div = btn.closest('.scheduled-course');
    const td = div.closest('td');
    const tr = td.closest('tr');
    
    const courseId = div.dataset.courseId;
    const currId = div.dataset.curriculumId;
    const hours = parseInt(div.dataset.hours);
    const credits = parseFloat(div.dataset.credits) || 0;
    const chunkId = div.dataset.chunkId;
    const teacherIds = JSON.parse(div.dataset.teachers || '[]');

    logAction('Remove Course', `ลบวิชา (Course ID: ${courseId}) ออกจากตาราง`);
    markUnsaved();

    const loadPerHead = parseFloat(credits) || 0; 
    teacherIds.forEach(tid => { 
        if(teacherLoads[tid]) { teacherLoads[tid].used = Math.max(0, teacherLoads[tid].used - loadPerHead); }
    });

    const roomId = tr.dataset.roomId;
    const dayIdx = tr.dataset.day;
    const startTimeId = td.dataset.time;

    scheduledItemsMemory = scheduledItemsMemory.filter(i => !(i.room_id == roomId && i.day == dayIdx && i.timeslot_id == startTimeId));
    if (chunkId) { scheduledCourseIds.delete(chunkId); } else { scheduledCourseIds.delete(`${currId}_${courseId}`); }
    if (currId) { if (selectCurriculum.value != currId) { selectCurriculum.value = currId; } }

    td.innerHTML = ''; td.removeAttribute('colspan'); td.classList.remove('merged'); td.classList.add('dropzone');
    
    const times = data.timeslots;
    const startIndex = times.findIndex(t => t.id == startTimeId);
    
    for(let i=1; i<hours; i++) {
        const nextTime = times[startIndex + i];
        if(nextTime) {
            const newTd = document.createElement('td');
            newTd.className = 'dropzone'; newTd.dataset.room = roomId; newTd.dataset.day = dayIdx; newTd.dataset.time = nextTime.id;
            td.after(newTd);
        }
    }
    
    renderCourseList(selectCurriculum.value);
    if(document.getElementById('teacherLoadModal').classList.contains('show')){ renderTeacherLoadModal(); }
    if (document.body.classList.contains('single-room-mode')) { generateCombinedTable(roomId); }
}

/* =========================================
   ปริ้นตารางแบบรวม
   ========================================= */
function generateCombinedTable(roomId) {
    const times = data.timeslots;
    const days = data.days;
    
    // ✅ [แก้ปัญหา Error เปิดไม่ได้] ดึงชื่อห้องจาก Database ตรงๆ จะไม่มีทาง Error แน่นอน
    const roomObj = data.classrooms.find(r => r.id == roomId);
    const roomName = roomObj ? roomObj.room_name : "ไม่ทราบชื่อห้อง";
    
    const now = new Date();
    const thaiMonths = ["ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.", "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."];
    const dateStr = `${now.getDate()} ${thaiMonths[now.getMonth()]} ${now.getFullYear() + 543}`;

    let html = `
        <div class="combined-print-header" style="display:block; margin-bottom:8px;">
            <div style="font-size:12pt; font-weight:bold;">FTE.Schedule</div>
            <div style="font-size:9pt; margin-top:2px;">วันที่พิมพ์: ${dateStr}</div>
        </div>

        <div style="text-align:center; font-size:16pt; font-weight:bold; margin-bottom:8px; color:black;">
            ตารางเรียนรวม ห้อง: ${roomName}
        </div>

        <table class="combined-table" style="width:100%; border-collapse:collapse; table-layout:fixed;">
            <colgroup>
                <col style="width:50px">
                ${times.map(() => `<col>`).join('')}
            </colgroup>
            <thead>
                <tr>
                    <th style="width:50px;">วัน</th>
                    ${times.map(t => `<th>${t.start_time.substring(0, 5)}<br>${t.end_time.substring(0, 5)}</th>`).join('')}
                </tr>
            </thead>
            <tbody>
    `;

    for (let d = 1; d <= 7; d++) {
        if (!days[d]) continue;

        html += `
            <tr>
                <td style="font-weight:bold; background-color:#e9ecef; vertical-align:middle; text-align:center;">
                    ${days[d].short}
                </td>
        `;

        let dayItems = scheduledItemsMemory.filter(item => item.room_id == roomId && item.day == d);

        for (let i = 0; i < times.length; i++) {
            let item = dayItems.find(it => it.timeslot_id == times[i].id);

            if (item) {
                let c = findCourse(item.course_id, item.curriculum_id);
                let physicalRoom = item.physical_room || null;

                let roomDisplay = physicalRoom 
                    ? `<div style="font-size:7.5pt; font-weight:normal; word-break:break-word; margin-top:2px;">
                            ห้อง: ${physicalRoom}
                       </div>` 
                    : '';

                // ✅ ดึงวันที่และจัดรูปแบบ (ตารางรวม)
                let dateDisplay = '';
                try {
                    if (item.start_date && item.end_date && String(item.start_date).trim() !== '' && String(item.end_date).trim() !== '' && item.start_date !== 'null' && item.end_date !== 'null') {
                        let sDate = formatThaiDate(String(item.start_date));
                        let eDate = formatThaiDate(String(item.end_date));
                        if (sDate && eDate) {
                            dateDisplay = `
                                <div style="font-size:7pt; margin-top:4px; color:#444; border-top:1px dashed #bbb; padding-top:3px; display:inline-block;">
                                    <i>${sDate} - ${eDate}</i>
                                </div>`;
                        }
                    }
                } catch (e) {
                    console.error("Date parse error:", e);
                }

                html += `
                    <td colspan="${item.duration}" 
                        style="vertical-align:middle; text-align:center; border:1px solid #000; padding:3px 2px; height:60px; overflow:hidden;">
                        
                        <div class="scheduled-course-print">
                            <div style="font-weight:bold; font-size:9pt; line-height:1.2;">
                                ${c ? c.course_code : ''}
                            </div>

                            <div style="font-size:8pt; word-break:break-word; line-height:1.2;">
                                ${c ? c.course_name : ''}
                            </div>

                            <div style="font-size:7.5pt; line-height:1.2;">
                                (อ.${getTeacherInitialsString(item.teacher_ids)})
                            </div>

                            ${roomDisplay}
                            ${dateDisplay}
                        </div>
                    </td>
                `;

                i += (parseInt(item.duration) - 1);
            } else {
                html += `<td style="border:1px solid #000; height:60px;"></td>`;
            }
        }
        html += `</tr>`;
    }

    html += `
            </tbody>
        </table>
    `;

    const container = document.getElementById('combined-print-container');
    if (container) {
        container.innerHTML = html;
    }
}


const ROOMS_PER_PAGE = 5;

/* =========================================
   ปริ้นตารางแบบเเยกหน้า
   ========================================= */
function generatePrintSplitPages() {
    const times = data.timeslots;
    const days = data.days;
    const rooms = data.classrooms;
    const scheduleName = data.schedule_info ? data.schedule_info.schedule_name : '';

    const now = new Date();
    const thaiMonths = ["ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.", "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."];
    const dateStr = `${now.getDate()} ${thaiMonths[now.getMonth()]} ${now.getFullYear() + 543}`;

    let fullHtml = '';
    let totalPages = 0;

    for (let day = 1; day <= 7; day++) {
        if (!days[day]) continue;
        const chunks = Math.ceil(rooms.length / ROOMS_PER_PAGE);
        totalPages += (chunks > 0 ? chunks : 1);
    }
    let pageNum = 0;

    for (let day = 1; day <= 7; day++) {
        const dayInfo = days[day];
        if (!dayInfo) continue;

        const roomChunks = [];
        if (rooms.length === 0) {
            roomChunks.push([]);
        } else {
            for (let i = 0; i < rooms.length; i += ROOMS_PER_PAGE) {
                roomChunks.push(rooms.slice(i, i + ROOMS_PER_PAGE));
            }
        }

        roomChunks.forEach((roomChunk, chunkIdx) => {
            pageNum++;
            const isLastPage = (pageNum === totalPages);
            const pageLabel = roomChunks.length > 1 ? ` (กลุ่มที่ ${chunkIdx + 1}/${roomChunks.length})` : '';

            fullHtml += `<div class="print-page-block${isLastPage ? ' last-page' : ''}">`;

            fullHtml += `
                <div class="print-day-header">
                    <div class="print-schedule-name">FTE.Schedule — ${scheduleName} &nbsp;|&nbsp; วันที่พิมพ์: ${dateStr} &nbsp;|&nbsp; หน้า ${pageNum}/${totalPages}</div>
                    <div style="font-size:13pt; font-weight:bold; margin-top:2px;">${dayInfo.name}${pageLabel}</div>
                </div>`;

            fullHtml += `<table class="print-split-table"><thead><tr>`;
            fullHtml += `<th style="width:70px;">ห้อง / เวลา</th>`;
            times.forEach(t => {
                fullHtml += `<th>${t.start_time.substring(0, 5)}<br>-<br>${t.end_time.substring(0, 5)}</th>`;
            });
            fullHtml += `</tr></thead><tbody>`;

            roomChunk.forEach(room => {
                fullHtml += `<tr>`;
                fullHtml += `<td class="room-label-cell">${room.room_name}</td>`;

                // ✅ [แก้ปัญหา วันที่ไม่ยอมขึ้น] ใช้ scheduledItemsMemory เหมือนตารางรวม เพื่อดึงข้อมูลที่แม่นยำที่สุด
                let dayItems = scheduledItemsMemory.filter(item => item.room_id == room.id && item.day == day);

                for (let i = 0; i < times.length; i++) {
                    let item = dayItems.find(it => it.timeslot_id == times[i].id);

                    if (item) {
                        let c = findCourse(item.course_id, item.curriculum_id);
                        let physicalRoom = item.physical_room || '';
                        let teacherStr = getTeacherInitialsString(item.teacher_ids);
                        
                        // ✅ ดึงวันที่และจัดรูปแบบ (ตารางแยกหน้า)
                        let dateDisplay = '';
                        try {
                            if (item.start_date && item.end_date && String(item.start_date).trim() !== '' && String(item.end_date).trim() !== '' && item.start_date !== 'null' && item.end_date !== 'null') {
                                let sDate = formatThaiDate(String(item.start_date));
                                let eDate = formatThaiDate(String(item.end_date));
                                if (sDate && eDate) {
                                    dateDisplay = `
                                        <div class="mt-1" style="font-size: 0.75em; color: #444; border-top: 1px dotted #aaa; padding-top: 2px;">
                                            <i>${sDate} - ${eDate}</i>
                                        </div>`;
                                }
                            }
                        } catch (e) {
                            console.error("Date parse error (SplitPages):", e);
                        }

                        fullHtml += `<td colspan="${item.duration}">
                            <div class="scheduled-course">
                                <div class="fw-bold">${c ? c.course_code : ''}</div>
                                <div class="text-truncate">${c ? c.course_name : ''}</div>
                                <div class="small">(อ.${teacherStr})</div>
                                ${physicalRoom ? `<div class="mt-1">ห้อง: ${physicalRoom}</div>` : ''}
                                ${dateDisplay}
                            </div>
                        </td>`;

                        i += (parseInt(item.duration) - 1);
                    } else {
                        fullHtml += `<td></td>`;
                    }
                }
                fullHtml += `</tr>`;
            });

            fullHtml += `</tbody></table></div>`; 
        });
    }
 
    document.getElementById('print-split-container').innerHTML = fullHtml;
}

function renderGrid() {
    const times = data.timeslots;
    const rooms = data.classrooms;
    const days = data.days;
    const scheduleName = data.schedule_info.schedule_name;

    let fullHtml = '';
    for (let day = 1; day <= 7; day++) {
        const dayInfo = days[day];
        if (!dayInfo) continue;
        fullHtml += `
        <div id="day-anchor-${day}" class="day-table-container">
            <div class="day-header" style="background-color: ${dayInfo.color}; color: ${dayInfo.text};">
                <div class="d-none d-print-block" style="font-size: 14pt; margin-bottom:5px;"><h5>FTE.Schedule</h5>${scheduleName}</div>
                <i class="far fa-calendar-alt me-2"></i>${dayInfo.name}
            </div>
            <div class="table-responsive">
                <table class="day-table">
                    <thead><tr><th style="width: 90px; font-size: 0.85rem;">ห้อง / เวลา</th>`;
        times.forEach(t => { fullHtml += `<th>${t.start_time.substring(0, 5)}<br>-<br>${t.end_time.substring(0, 5)}</th>`; });
        fullHtml += `</tr></thead><tbody>`;
        rooms.forEach(r => {
            fullHtml += `<tr data-room-id="${r.id}" data-day="${day}">
                <td class="bg-dark fw-bold" style="color:white; position:sticky; left:0; z-index:2; width: 90px; font-size: 0.85rem;">${r.room_name}</td>`;
            times.forEach(t => { fullHtml += `<td class="dropzone" data-room="${r.id}" data-time="${t.id}" data-day="${day}"></td>`; });
            fullHtml += `</tr>`;
        });
        fullHtml += `</tbody></table></div></div>`;
    }
    gridContainer.innerHTML = fullHtml;
}

// === แก้ไข function ให้รับ Custom Chunks ===
function splitCourseIntoChunks(course, maxHours = 4) {
    if (course.custom_chunks) {
        return course.custom_chunks;
    }
    const totalHours = course.teaching_hours;
    if (totalHours <= maxHours) { return [{ ...course, chunk_hours: totalHours, is_chunked: false }]; }
    const chunks = [];
    let remaining = totalHours;
    let chunkIndex = 0;
    while (remaining > 0) {
        const chunkSize = remaining > maxHours ? maxHours : remaining;
        chunks.push({ ...course, chunk_hours: chunkSize, is_chunked: true, original_hours: totalHours, chunk_index: chunkIndex });
        remaining -= chunkSize;
        chunkIndex++;
    }
    return chunks;
}

function renderCourseList(curriculumId) {
    const contentEl = document.querySelector('.content');
    const savedScrollTop  = contentEl ? contentEl.scrollTop  : 0;
    const savedScrollLeft = contentEl ? contentEl.scrollLeft : 0;

    const list = data.curriculumCourses[curriculumId] || [];
    let courseHtml = '';
    
    list.forEach(c => {
        let tTxt = c.teachers ? c.teachers.map(t => t.initial || t.initials).join(', ') : '';
        let tIds = c.teachers ? c.teachers.map(t => t.id) : [];

        // เช็คว่าถ้ามี custom_chunk บนกระดานแต่ไม่มีในเมม (refresh page) ให้กู้คืน (สำหรับก้อนที่ถูกใช้งานไปแล้ว)
        const hasCustomPlaced = Array.from(scheduledCourseIds).some(id => id.startsWith(`${curriculumId}_${c.course_id}_custom_`));
        if (hasCustomPlaced && !c.custom_chunks) {
            const placedCustoms = scheduledItemsMemory.filter(i => i.course_id == c.course_id && i.curriculum_id == curriculumId && i.chunk_id && i.chunk_id.includes('_custom_'));
            c.custom_chunks = placedCustoms.map((i, idx) => ({
                ...c, chunk_hours: i.duration, is_chunked: true, original_hours: c.teaching_hours,
                chunk_index: idx, chunk_id: i.chunk_id, start_date: i.start_date, end_date: i.end_date,
                pre_teachers: i.teacher_ids, pre_physical_room: i.physical_room, pre_credits: i.credits
            }));
        }

        const chunks = splitCourseIntoChunks(c);
        
        chunks.forEach((chunk, idx) => {
            const chunkId = chunk.chunk_id || `${curriculumId}_${c.course_id}_chunk_${idx}`;
            if (scheduledCourseIds.has(chunkId)) return; // ไม่แสดงอันที่ลงตารางไปแล้ว
            
            const chunkAttr = chunk.is_chunked 
                ? ` data-original-hours="${chunk.original_hours}" data-chunk-index="${idx}" data-chunk-id="${chunkId}"` 
                : ` data-chunk-id="${chunkId}"`;
            
            const badgeChunk = chunk.is_chunked 
                ? `<span class="badge bg-warning text-dark ms-1" style="font-size: 0.7rem;" title="แบ่งจากรายวิชา ${chunk.original_hours} ชม."><i class="fas fa-columns"></i> ชิ้น ${idx + 1}/${chunks.length}</span>` 
                : '';

            // ถ้ามีข้อมูลที่ pre-select ไว้จากบล็อกแรก (Custom Chunk)
            const tIdsToUse = chunk.pre_teachers !== undefined ? chunk.pre_teachers : tIds;
            const roomToUse = chunk.pre_physical_room !== undefined ? chunk.pre_physical_room : (c.assigned_room || '');
            const creditsToUse = chunk.pre_credits !== undefined ? chunk.pre_credits : c.credits;
            const startDateToUse = chunk.start_date || '';
            const endDateToUse = chunk.end_date || '';

            // สร้างข้อความชื่ออาจารย์
            let tTxtToUse = tTxt;
            if (chunk.pre_teachers && data.teachers) {
                tTxtToUse = chunk.pre_teachers.map(id => {
                    const t = data.teachers.find(teacher => teacher.id == id);
                    return t ? (t.initials || t.initial) : '';
                }).filter(Boolean).join(', ');
            }

            let roomTxt = roomToUse 
                ? `<div class="small text-info"><i class="fas fa-map-marker-alt"></i> ห้อง ${roomToUse}</div>` 
                : `<div class="small text-muted"><i class="fas fa-map-marker-alt"></i> ไม่ระบุห้อง</div>`;

            // ปุ่มสำหรับยกเลิกการแบ่งบล็อก
            let resetButton = '';
            if (chunk.chunk_id && chunk.chunk_id.includes('_custom_')) {
                resetButton = `<button class="btn btn-outline-danger p-0 px-2" style="font-size: 0.65rem; border-radius: 4px;" onclick="resetCustomChunk(event, '${curriculumId}', '${c.course_id}')" title="ยกเลิกการแบ่งบล็อก (ดึงชิ้นส่วนคืน)"><i class="fas fa-undo"></i> ยกเลิกแบ่ง</button>`;
            }

            courseHtml += `<div class="list-group-item draggable-course" draggable="true" 
                data-id="${c.course_id}" data-curriculum-id="${curriculumId}"
                data-hours="${chunk.chunk_hours}" data-credits="${creditsToUse}" 
                data-physical-room="${roomToUse}"
                data-teachers='${JSON.stringify(tIdsToUse)}'
                data-start-date="${startDateToUse}"
                data-end-date="${endDateToUse}"${chunkAttr}
                style="${chunk.is_chunked ? 'border-left: 4px solid #ffc107; background: rgba(255, 193, 7, 0.05);' : ''}">
                <div class="d-flex justify-content-between"><b>${c.course_code}</b>
                    <div>
                        <span class="badge bg-info text-dark me-1">${creditsToUse} นก.</span>
                        <span class="badge bg-primary">${chunk.chunk_hours} ชม.</span>
                        ${badgeChunk}
                    </div>
                </div>
                <div class="small text-truncate">${c.course_name}</div>
                <div class="small text-warning mt-1"><i class="fas fa-user"></i> ${tTxtToUse}</div>
                <div class="d-flex justify-content-between align-items-center mt-1">
                    ${roomTxt}
                    ${resetButton}
                </div>
            </div>`;
        });
    });
    courseList.innerHTML = courseHtml;

    if (contentEl) { 
        contentEl.scrollTop  = savedScrollTop; 
        contentEl.scrollLeft = savedScrollLeft; 
        requestAnimationFrame(() => {
            contentEl.scrollTop  = savedScrollTop; 
            contentEl.scrollLeft = savedScrollLeft; 
        });
    }
}

// === ฟังก์ชันใหม่: ยกเลิกและคืนค่าบล็อกพิเศษ (Custom Chunk) ===
window.resetCustomChunk = function(e, curriculumId, courseId) {
    e.stopPropagation(); // ป้องกันไม่ให้ trigger การ drag
    
    swalKeepScroll({
        title: 'ยืนยันยกเลิกการแบ่งบล็อก?',
        text: "ระบบจะคืนค่าวิชานี้กลับเป็นบล็อกเดียวแบบปกติ (หากมีชิ้นส่วนไหนของวิชานี้วางบนตารางแล้ว จะถูกดึงออกทั้งหมด)",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-undo"></i> ยืนยันคืนค่าเดิม',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            // 1. ค้นหาชิ้นส่วนที่ถูกวางบนตารางแล้วเพื่อดึงออก
            const onGrid = document.querySelectorAll(`.scheduled-course[data-course-id="${courseId}"][data-curriculum-id="${curriculumId}"]`);
            onGrid.forEach(div => {
                const chunkId = div.dataset.chunkId || '';
                if (chunkId.includes('_custom_')) {
                    const btn = div.querySelector('.remove-course');
                    if (btn) window.removeCourse(btn);
                }
            });

            // 2. ลบข้อมูล custom_chunks ออกจาก object ต้นทาง
            const courseOriginal = findCourse(courseId, curriculumId);
            if (courseOriginal && courseOriginal.custom_chunks) {
                delete courseOriginal.custom_chunks;
            }

            // 3. เคลียร์ chunk_id ออกจากหน่วยความจำ (กรณีหลงเหลือ)
            scheduledCourseIds.forEach(id => {
                if (id.startsWith(`${curriculumId}_${courseId}_custom_`)) {
                    scheduledCourseIds.delete(id);
                }
            });

            // 4. วาดเมนูด้านขวาใหม่
            renderCourseList(curriculumId);
            logAction('Reset Custom Chunk', `คืนค่าเดิมการแบ่งบล็อกวิชา (Course ID: ${courseId})`);
        }
    });
};

function loadSavedSchedule() {
    scheduledCourseIds.clear();
    scheduledItemsMemory = [];
    Object.keys(teacherLoads).forEach(id => { teacherLoads[id].used = 0; });

    const savedItemsGrouped = {};
    data.scheduled_items.forEach(item => {
        const groupKey = `${item.curriculum_id}_${item.course_id}`;
        if (!savedItemsGrouped[groupKey]) savedItemsGrouped[groupKey] = [];
        savedItemsGrouped[groupKey].push(item);
    });

    for (const [groupKey, items] of Object.entries(savedItemsGrouped)) {
        const [currId, courseId] = groupKey.split('_');
        const courseOriginal = findCourse(courseId, currId);
        
        if (courseOriginal) {
            const theoreticalChunks = splitCourseIntoChunks(courseOriginal);
            const usedChunkIndices = new Set();

            items.forEach(savedItem => {
                let assignedChunkId = '';

                if (savedItem.chunk_id) {
                    assignedChunkId = savedItem.chunk_id;
                    const parts = savedItem.chunk_id.split('_chunk_');
                    if(parts[1]) usedChunkIndices.add(parseInt(parts[1]));
                } else {
                    const matchedChunkIndex = theoreticalChunks.findIndex((chunk, idx) => {
                        return parseInt(chunk.chunk_hours) === parseInt(savedItem.duration) && !usedChunkIndices.has(idx);
                    });
                    if (matchedChunkIndex !== -1) {
                        usedChunkIndices.add(matchedChunkIndex);
                        assignedChunkId = `${currId}_${courseId}_chunk_${matchedChunkIndex}`;
                    } else { assignedChunkId = `${currId}_${courseId}_chunk_unknown_${Date.now()}`; }
                }

                scheduledCourseIds.add(assignedChunkId);

                let t_ids = [];
                try { t_ids = (typeof savedItem.teacher_ids === 'string') ? JSON.parse(savedItem.teacher_ids) : savedItem.teacher_ids; } catch (e) { t_ids = []; }

                const formatItem = {
                    room_id: savedItem.room_id,
                    day: savedItem.day || savedItem.day_of_week,
                    timeslot_id: savedItem.timeslot_id,
                    course_id: savedItem.course_id,
                    curriculum_id: savedItem.curriculum_id,
                    duration: savedItem.duration,
                    credits: parseFloat(savedItem.credits) || 0,
                    teacher_ids: t_ids || [],
                    chunk_id: assignedChunkId,
                    physical_room: ('physical_room' in savedItem && savedItem.physical_room !== null) ? savedItem.physical_room : (courseOriginal ? courseOriginal.assigned_room : ''),
                    start_date: savedItem.start_date || null,
                    end_date: savedItem.end_date || null
                };

                scheduledItemsMemory.push(formatItem);
                
                const loadPerHead = parseFloat(formatItem.credits) || 0; 
                formatItem.teacher_ids.forEach(tid => {
                    if (teacherLoads[tid]) teacherLoads[tid].used += loadPerHead;
                });

                drawItemOnGridFromLoad(formatItem);
            });
        }
    }
    renderCourseList(selectCurriculum.value);
    renderTeacherLoadModal();
}

function drawItemOnGridFromLoad(item) {
    const tr = document.querySelector(`tr[data-room-id="${item.room_id}"][data-day="${item.day}"]`);
    if (!tr) return;
    const targetCell = tr.querySelector(`td[data-time="${item.timeslot_id}"]`);
    if (!targetCell) return;
    
    const times = data.timeslots;
    const startIdx = times.findIndex(t => t.id == item.timeslot_id);
    if (startIdx === -1) return;

    let needCells = [];
    for (let i = 0; i < item.duration; i++) {
        if (times[startIdx + i]) {
            let c = tr.querySelector(`td[data-time="${times[startIdx + i].id}"]`);
            if (c) needCells.push(c);
        }
    }
    if (needCells.length == item.duration) { drawSavedItemOnGrid(item, needCells); }
}

function drawSavedItemOnGrid(item, needCells) {
    const course = findCourse(item.course_id, item.curriculum_id);
    const roomObj = data.classrooms.find(r => r.id == item.room_id);
    const roomName = roomObj ? roomObj.room_name : "";
    const targetCell = needCells[0];
    const hours = parseInt(item.duration);

    const physicalRoom = item.physical_room || '';
    const originalCredits = course ? course.credits : '?';
    const teacherStr = getTeacherInitialsString(item.teacher_ids);
    const courseCode = course ? course.course_code : '??';
    const courseName = course ? course.course_name : 'Unknown';

    targetCell.setAttribute('colspan', hours);
    targetCell.classList.add('merged');

    // --- จัดรูปแบบวันที่ให้ดูสวยงาม (Modern Badge) ---
    let dateBadge = '';
    if (item.start_date && item.end_date && item.start_date !== 'null' && item.end_date !== 'null') {
        dateBadge = `
            <div class="w-100 mt-2" style="
                background: linear-gradient(135deg, #31697E, #235163);
                border: 1px solid rgba(255, 255, 255, 0.25);
                border-radius: 6px;
                padding: 4px 6px;
                font-size: 0.55rem;
                line-height: 1.4;
                color: #ffffff;
                text-align: center;
                box-shadow: 0 2px 5px rgba(0,0,0,0.15);
                word-break: break-word;
            ">
                <span style="color: #ffd166; font-weight: 800; letter-spacing: 0.3px;">Start</span> ${formatThaiDate(item.start_date)} 
                <span style="display: inline-block; width: 6px;"></span> 
                <span style="color: #ffd166; font-weight: 800; letter-spacing: 0.3px;">End</span> ${formatThaiDate(item.end_date)}
            </div>
        `;
    }

    let innerHTMLContent = '';

    if (hours > 1) {
        innerHTMLContent = `
            <button class="remove-course" onclick="removeCourse(this)" style="position: absolute; top: 2px; right: 2px; padding: 0; font-size: 0.55rem; line-height: 1; z-index: 10; width: 16px; height: 16px;"><i class="fas fa-times"></i></button>

            <div class="w-100" style="padding-right: 18px;">
                <div class="d-flex justify-content-between align-items-start w-100" style="line-height: 1;">
                    <div class="fw-bold text-white text-truncate text-start" style="font-size: 0.65rem; max-width: 65%;" title="${teacherStr}">
                        <i class="fas fa-user-tie"></i> ${teacherStr}
                    </div>
                    <div class="text-white text-end" style="font-size: 0.6rem; opacity: 0.9; white-space: nowrap;">
                        ${originalCredits} นก. | ${hours} ชม.
                    </div>
                </div>
                <div class="text-warning fw-bold text-start w-100 mt-1" style="font-size: 0.6rem; line-height: 1;">
                    <i class="fas fa-balance-scale"></i> โหลด: ${item.credits}
                </div>
            </div>

            <div class="text-center w-100 my-auto px-1">
                <div class="fw-bold text-white" style="font-size: 0.75rem; letter-spacing: 0.5px;">${courseCode}</div>
                <div class="text-white mt-1" style="font-size: 0.65rem; line-height: 1.1; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis;" title="${courseName}">
                    ${courseName}
                </div>
                ${dateBadge}
            </div>

            <div class="text-start text-white w-100 mt-auto text-truncate" style="font-size: 0.6rem;" title="${physicalRoom}">
                ${physicalRoom ? `<i class="fas fa-door-open"></i> ${physicalRoom}` : ''}
            </div>
        `;
    } else {
        innerHTMLContent = `
            <button class="remove-course" onclick="removeCourse(this)" style="position: absolute; top: 1px; right: 1px; padding: 0; font-size: 0.5rem; line-height: 1; z-index: 10; width: 14px; height: 14px; background: rgba(0,0,0,0.3);"><i class="fas fa-times"></i></button>

            <div class="d-flex flex-column h-100 w-100 text-start" style="line-height: 1.1; overflow: hidden; padding-right: 12px;">
                <div class="fw-bold text-white text-truncate" style="font-size: 0.55rem; width: 100%;" title="${teacherStr}">
                    <i class="fas fa-user-tie"></i> ${teacherStr}
                </div>

                <div class="text-white" style="font-size: 0.5rem; opacity: 0.9; margin-top: 1px;">
                    ${originalCredits} นก. | ${hours} ชม.
                </div>

                <div class="text-warning fw-bold text-truncate" style="font-size: 0.5rem; margin-top: 1px;">
                    <i class="fas fa-balance-scale"></i> โหลด: ${item.credits}
                </div>

                <div class="fw-bold text-white text-center mt-1 text-truncate" style="font-size: 0.6rem; letter-spacing: 0.5px; width: 100%;" title="${courseCode}">
                    ${courseCode} 
                </div>

                ${dateBadge}

                <div class="text-white text-center text-truncate" style="font-size: 0.55rem; width: 100%;" title="${courseName}">
                    ${courseName}
                </div>

                <div class="text-white text-truncate mt-auto" style="font-size: 0.5rem; width: 100%;" title="${physicalRoom}">
                    ${physicalRoom ? `<i class="fas fa-door-open"></i> ${physicalRoom}` : ''}
                </div>
            </div>
        `;
    }

    targetCell.innerHTML = `
        <div class="scheduled-course p-1" draggable="true" 
            style="background: ${getRoomColor(roomName)}; box-sizing: border-box; height: 100%; display: flex; flex-direction: column; position: relative; overflow: hidden;" 
            data-course-id="${item.course_id}" data-curriculum-id="${item.curriculum_id}"
            data-hours="${hours}" data-credits="${item.credits}"
            data-chunk-id="${item.chunk_id || ''}"
            data-physical-room="${physicalRoom}"
            data-start-date="${item.start_date || ''}"
            data-end-date="${item.end_date || ''}"
            data-teachers='${JSON.stringify(item.teacher_ids)}'>
            
            ${innerHTMLContent}

        </div>`;

    needCells.slice(1).forEach(td => td.remove());
    if (document.body.classList.contains('single-room-mode')) generateCombinedTable(item.room_id);
}
function saveSchedule(exportAfter = false) {
    logAction('Save Schedule', `กดบันทึกตารางสอน: ${data.schedule_info.schedule_name}`);
    const scheduleData = [];
    document.querySelectorAll('.scheduled-course').forEach(div => {
        const td = div.closest('td');
        const tr = td.closest('tr');
        let chunkId = div.dataset.chunkId;
        if (!chunkId) { chunkId = `${div.dataset.curriculumId}_${div.dataset.courseId}_chunk_0`; }

        scheduleData.push({
            room_id: tr.dataset.roomId,
            day_of_week: tr.dataset.day,
            timeslot_id: td.dataset.time,
            course_id: div.dataset.courseId,
            curriculum_id: div.dataset.curriculumId,
            teacher_ids: JSON.parse(div.dataset.teachers),
            duration: div.dataset.hours,
            credits: parseFloat(div.dataset.credits) || 0,
            chunk_id: chunkId,
            physical_room: div.dataset.physicalRoom || '',
            start_date: div.dataset.startDate || null,
            end_date: div.dataset.endDate || null
        });
    });
    Swal.fire({ title: 'กำลังบันทึก...', allowOutsideClick: false, heightAuto: false, didOpen: () => { Swal.showLoading(); } });
    fetch('save_schedule_process.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ schedule_id: data.schedule_info.id, items: scheduleData })
    })
    .then(response => response.json())
    .then(res => {
        if (res.status === 'success') {
            if (exportAfter) {
                Swal.close(); window.location.href = 'export_schedule_csv.php?id=' + data.schedule_info.id;
            } else { swalKeepScroll({ title: 'สำเร็จ!', text: 'บันทึกข้อมูลเรียบร้อยแล้ว', icon: 'success' }); markSaved(); }
        } else { swalKeepScroll({ title: 'เกิดข้อผิดพลาด', text: res.message, icon: 'error' }); }
    })
    .catch((error) => { swalKeepScroll({ title: 'เกิดข้อผิดพลาด', text: 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', icon: 'error' }); });
}

function renderTeacherLoadModal() {
    Object.keys(teacherLoads).forEach(id => { teacherLoads[id].used = 0; });
    document.querySelectorAll('.scheduled-course').forEach(div => {
        const loadPerHead = parseFloat(div.dataset.credits) || 0; 
        let tIds = [];
        try { tIds = JSON.parse(div.dataset.teachers || '[]'); } catch(e) { tIds = []; }
        tIds.forEach(tid => { if (teacherLoads[tid]) teacherLoads[tid].used += loadPerHead; });
    });
    const tbody = document.getElementById('teacherLoadTableBody');
    tbody.innerHTML = '';
    Object.values(teacherLoads).forEach(t => {
        const remain = t.max - t.used;
        tbody.innerHTML += `<tr><td>${t.initials}</td><td>${t.max}</td><td>${t.used.toFixed(2)}</td><td class="${remain < 0 ? 'text-danger fw-bold' : 'text-success'}">${remain.toFixed(2)}</td><td>${remain < 0 ? '<span class="badge bg-danger">เกิน</span>' : '<span class="badge bg-success">ปกติ</span>'}</td></tr>`;
    });
}

function toggleCourseSidebar() {
    const sidebarCol = document.getElementById('course-sidebar-col');
    const gridCol = document.getElementById('grid-col');
    const floatIcon = document.querySelector('#btnToggleCourseFloat i');
    sidebarCol.classList.toggle('d-none');
    if (sidebarCol.classList.contains('d-none')) { gridCol.className = 'col-md-12'; if (floatIcon) floatIcon.className = 'fas fa-bars'; } 
    else { gridCol.className = 'col-md-9'; if (floatIcon) floatIcon.className = 'fas fa-columns'; }
}

// =====================================================================
// === Touch Drag-and-Drop Support (iPad / Tablet) =====================
// =====================================================================
(function setupTouchDnD() {
    let touchDragEl = null; 
    let touchSourceEl = null; 
    let touchData = {}; 
    let touchSourceInfo = null; 
    let lastDropzone = null; 

    function getTouchTarget(x, y) {
        if (touchDragEl) touchDragEl.style.display = 'none';
        const el = document.elementFromPoint(x, y);
        if (touchDragEl) touchDragEl.style.display = '';
        return el;
    }

    function cleanupTouchDrag() {
        if (touchDragEl) { touchDragEl.remove(); touchDragEl = null; }
        if (touchSourceEl) { touchSourceEl.style.opacity = '1'; }
        if (lastDropzone) { lastDropzone.classList.remove('drag-over'); lastDropzone = null; }
        touchSourceEl = null;
        touchData = {};
        touchSourceInfo = null;
    }

    document.addEventListener('touchstart', function(e) {
        const target = e.target.closest('.draggable-course, .scheduled-course');
        if (!target) return;

        const touch = e.touches[0];
        touchSourceEl = target;

        if (target.classList.contains('scheduled-course')) {
            const td = target.closest('td');
            const tr = td ? td.closest('tr') : null;
            if (td && tr) {
                touchSourceInfo = {
                    roomId: tr.dataset.roomId,
                    dayIdx: tr.dataset.day,
                    timeslotId: td.dataset.time,
                    element: target
                };
            }
        } else {
            touchSourceInfo = null;
        }

        touchData = {
            'course-id': target.dataset.id || target.dataset.courseId || '',
            'curriculum-id': target.dataset.curriculumId || '',
            'hours': target.dataset.hours || '1',
            'credits': target.dataset.credits || '0',
            'teachers': target.dataset.teachers || '[]',
            'chunk-id': target.dataset.chunkId || '',
            'physical-room': target.dataset.physicalRoom || '',
            'start-date': target.dataset.startDate || '',
            'end-date': target.dataset.endDate || ''
        };

        touchDragEl = target.cloneNode(true);
        touchDragEl.style.cssText = `
            position: fixed;
            z-index: 99999;
            pointer-events: none;
            opacity: 0.85;
            width: ${target.offsetWidth}px;
            transform: scale(1.05);
            box-shadow: 0 8px 25px rgba(0,0,0,0.4);
            border-radius: 6px;
            left: ${touch.clientX - target.offsetWidth / 2}px;
            top: ${touch.clientY - 30}px;
            transition: none;
        `;
        document.body.appendChild(touchDragEl);
        target.style.opacity = '0.4';

    }, { passive: true });

    document.addEventListener('touchmove', function(e) {
        if (!touchDragEl) return;
        e.preventDefault();

        const touch = e.touches[0];
        touchDragEl.style.left = `${touch.clientX - parseInt(touchDragEl.style.width) / 2}px`;
        touchDragEl.style.top  = `${touch.clientY - 30}px`;

        const el = getTouchTarget(touch.clientX, touch.clientY);
        const dz = el ? el.closest('.dropzone') : null;

        if (lastDropzone && lastDropzone !== dz) {
            lastDropzone.classList.remove('drag-over');
        }
        if (dz && !dz.classList.contains('merged')) {
            dz.classList.add('drag-over');
            lastDropzone = dz;
        } else {
            lastDropzone = null;
        }
    }, { passive: false });

    document.addEventListener('touchend', function(e) {
        if (!touchDragEl) return;

        const touch = e.changedTouches[0];
        const el = getTouchTarget(touch.clientX, touch.clientY);
        const dropzone = el ? el.closest('.dropzone') : null;

        if (lastDropzone) lastDropzone.classList.remove('drag-over');

        if (dropzone && !dropzone.classList.contains('merged')) {
            const fakeE = {
                dataTransfer: {
                    getData: (key) => touchData[key] || ''
                }
            };
            dragSourceInfo = touchSourceInfo;
            handleDropLogic(fakeE, dropzone);
        } else {
            if (touchSourceEl) touchSourceEl.style.opacity = '1';
        }

        cleanupTouchDrag();
    }, { passive: true });

    document.addEventListener('touchcancel', function() {
        if (touchSourceEl) touchSourceEl.style.opacity = '1';
        cleanupTouchDrag();
    }, { passive: true });
})();
// =====================================================================

// === Initialize ===
renderGrid();
loadSavedSchedule();
selectCurriculum.addEventListener('change', (e) => renderCourseList(e.target.value));