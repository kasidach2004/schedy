<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/db.php';

if (isset($_GET['id'])) {
    $curriculum_id = $_GET['id'];
    $user_id = $_SESSION['user_id'];

    try {
        $sql = "DELETE FROM curriculums WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);

        if ($stmt->execute([$curriculum_id, $user_id])) {
            header("Location: curriculums.php");
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
?>