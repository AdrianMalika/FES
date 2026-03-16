<?php
require_once 'includes/database.php';

try {
    $conn = getDBConnection();
    echo "Connected to database.\n";

    $sql = "ALTER TABLE bookings ADD COLUMN operator_end_time DATETIME DEFAULT NULL;";
    if ($conn->query($sql) === TRUE) {
        echo "Column 'operator_end_time' added successfully.\n";
    } else {
        echo "Error adding column: " . $conn->error . "\n";
    }

    $conn->close();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>