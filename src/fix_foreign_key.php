<?php
/**
 * Fix foreign key constraint for curriculum_courses
 */

require_once 'config/db.php';

try {
    // First, check the current constraints
    echo "<h3>Current Constraints:</h3>";
    $result = $conn->query("SHOW CREATE TABLE curriculum_courses");
    $row = $result->fetch();
    echo "<pre>" . htmlspecialchars($row['Create Table']) . "</pre>";
    
    echo "<hr>";
    
    // Drop all existing foreign keys
    echo "<h3>Dropping existing foreign keys...</h3>";
    
    $constraints = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='curriculum_courses' AND COLUMN_NAME='classroom_id' AND REFERENCED_TABLE_NAME IS NOT NULL");
    
    foreach ($constraints as $constraint) {
        $name = $constraint['CONSTRAINT_NAME'];
        echo "Dropping constraint: $name<br>";
        $conn->exec("ALTER TABLE `curriculum_courses` DROP FOREIGN KEY `$name`");
    }
    
    echo "<hr>";
    
    // Now add the correct foreign key
    echo "<h3>Adding new foreign key...</h3>";
    
    $sql = "ALTER TABLE `curriculum_courses` 
            ADD CONSTRAINT `fk_cc_room` 
            FOREIGN KEY (`classroom_id`) 
            REFERENCES `rooms` (`room_id`) 
            ON DELETE SET NULL";
    
    $conn->exec($sql);
    echo "✓ Foreign key added successfully!<br>";
    
    echo "<hr>";
    
    // Verify
    echo "<h3>Updated table structure:</h3>";
    $result = $conn->query("SHOW CREATE TABLE curriculum_courses");
    $row = $result->fetch();
    echo "<pre>" . htmlspecialchars($row['Create Table']) . "</pre>";
    
} catch (PDOException $e) {
    echo "<strong style='color: red;'>Error:</strong><br>";
    echo htmlspecialchars($e->getMessage()) . "<br>";
    echo "Code: " . $e->getCode();
}
?>
