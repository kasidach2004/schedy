<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['course_id'])) {
    $course_id = (int)$_POST['course_id'];
    $course_code = $_POST['course_code'];
    $course_name = $_POST['course_name'];
    $credits = (int)$_POST['credits'];
    $teaching_hours = (int)$_POST['teaching_hours'];
    $teacher_id = !empty($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : null;

    try {
        $sql = "UPDATE courses SET course_code=?, course_name=?, credits=?, teaching_hours=?, teacher_id=? WHERE id=?";
        $stmt = $conn->prepare($sql);

        if ($stmt->execute([$course_code, $course_name, $credits, $teaching_hours, $teacher_id, $course_id])) {
            header("Location: courses.php");
        } else {
            echo "Error: " . $stmt->errorInfo()[2];
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
} else {
    header("Location: courses.php");
}
exit;
