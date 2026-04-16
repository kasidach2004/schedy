<?php
/**
 * Migration: Update curriculum_courses foreign key from classrooms to rooms
 * Run this once to fix the database constraint
 */

require_once 'config/db.php';

try {
    // 1. Drop the existing foreign key constraint
    $sql1 = "ALTER TABLE `curriculum_courses` DROP FOREIGN KEY `fk_cc_classroom`";
    $conn->exec($sql1);
    echo "✓ Dropped old foreign key constraint<br>";
    
    // 2. Add the new foreign key constraint that references rooms table
    $sql2 = "ALTER TABLE `curriculum_courses` 
             ADD CONSTRAINT `fk_cc_room` 
             FOREIGN KEY (`classroom_id`) REFERENCES `rooms` (`room_id`) ON DELETE SET NULL";
    $conn->exec($sql2);
    echo "✓ Added new foreign key constraint to rooms table<br>";
    
    echo "<br><strong style='color: green;'>✓ Migration completed successfully!</strong><br>";
    echo "curriculum_courses now references rooms instead of classrooms<br>";
    
} catch (PDOException $e) {
    echo "<strong style='color: red;'>✗ Migration failed:</strong><br>";
    echo "Error: " . htmlspecialchars($e->getMessage()) . "<br>";
}
?>
