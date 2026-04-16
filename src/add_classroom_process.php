<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['room_name'])) {
    $room_name = $_POST['room_name'];
    $notes = $_POST['notes'];

    try {

        $sql = "INSERT INTO classrooms (room_name, notes, user_id) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        
        if ($stmt->execute([$room_name, $notes, $_SESSION['user_id']])) {
            header("Location: classrooms.php");
        } else {
            echo "Error: " . $stmt->errorInfo()[2];
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
} else {
    header("Location: classrooms.php");
}
exit;
?>