// JS for editCourse popup in manage_curriculum.php
function editCourse(id) {
    // Find the row for this course
    const row = document.querySelector('button[onclick="editCourse(' + id + ')"]').closest('tr');
    // Extract data attributes from the row
    const courseCode = row.children[0].textContent.trim();
    const courseName = row.children[1].textContent.trim();
    const teacherId = row.dataset.teacherId || '';
    const classroomId = row.dataset.classroomId || '';
    const teachingHours = row.dataset.hours || row.children[3].textContent.trim().replace(' ชั่วโมง', '');
    const credits = row.dataset.credits || row.children[4].textContent.trim();

    // Set values in modal
    document.getElementById('edit_course_id').value = id;
    // Set selected course in select
    const editCourseSelect = document.getElementById('edit_course_select');
    if (editCourseSelect) {
        editCourseSelect.value = row.dataset.courseId || id;
        // Update credits/hours from selected option
        const selOpt = editCourseSelect.options[editCourseSelect.selectedIndex];
        if (selOpt) {
            document.getElementById('edit_credits').value = selOpt.dataset.credits || credits;
            document.getElementById('edit_teaching_hours').value = selOpt.dataset.hours || teachingHours;
        }
    }
    // Set selects by value (if present)
    // handle checkbox list for teachers
    const teacherContainer = document.getElementById('edit_teacher');
    if (teacherContainer) {
        const ids = (row.dataset.teacherIds || '').split(',').map(x => x.trim()).filter(Boolean);
        // uncheck all first
        const inputs = teacherContainer.querySelectorAll('input[type="checkbox"]').forEach(ch => ch.checked = false);
        // check those present
        ids.forEach(idVal => {
            const ch = document.getElementById('edit_teacher_' + idVal);
            if (ch) ch.checked = true;
        });
    }
    const classroomSelect = document.getElementById('edit_classroom');
    if (classroomSelect) classroomSelect.value = classroomId;
    // Set credits and hours if not already set
    if (!document.getElementById('edit_credits').value) document.getElementById('edit_credits').value = credits;
    if (!document.getElementById('edit_teaching_hours').value) document.getElementById('edit_teaching_hours').value = teachingHours;

    // Show modal
    var myModal = new bootstrap.Modal(document.getElementById('editCourseModal'));
    myModal.show();
}

// Update credits/hours when user changes course in edit modal
document.addEventListener('DOMContentLoaded', function() {
    const editCourseSelect = document.getElementById('edit_course_select');
    if (editCourseSelect) {
        editCourseSelect.addEventListener('change', function() {
            const opt = this.options[this.selectedIndex];
            document.getElementById('edit_credits').value = opt.dataset.credits || '';
            document.getElementById('edit_teaching_hours').value = opt.dataset.hours || '';
        });
    }
    // Add modal teacher search/filter (if present)
    const addSearch = document.getElementById('add_teacher_search');
    if (addSearch) {
        addSearch.addEventListener('input', function() {
            const q = this.value.trim().toLowerCase();
            const items = document.querySelectorAll('#addCourseModal .form-check');
            items.forEach(it => {
                const label = it.querySelector('label').textContent.toLowerCase();
                it.style.display = label.includes(q) ? '' : 'none';
            });
        });
    }
});
