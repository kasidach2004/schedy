<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/db.php';

if (isset($_GET['course_id']) && isset($_GET['curriculum_id'])) {
    $course_id = (int)$_GET['course_id'];
    $curriculum_id = (int)$_GET['curriculum_id'];

    try {
        // ลบความสัมพันธ์กับผู้สอนก่อน
        $delTeachers = "DELETE FROM curriculum_course_teachers WHERE curriculum_id = ? AND course_id = ?";
        $stmtT = $conn->prepare($delTeachers);
        $stmtT->execute([$curriculum_id, $course_id]);

        // ลบความสัมพันธ์หลักสูตร-วิชา
        $delSql = "DELETE FROM curriculum_courses WHERE curriculum_id = ? AND course_id = ?";
        $stmt = $conn->prepare($delSql);

        if ($stmt->execute([$curriculum_id, $course_id])) {
            header("Location: manage_curriculum.php?id={$curriculum_id}");
        } else {
            echo "Error: " . $stmt->errorInfo()[2];
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
} else {
    header("Location: curriculums.php");
}
exit;
