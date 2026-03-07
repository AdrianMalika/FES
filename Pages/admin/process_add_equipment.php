<?php
// Start session at the very beginning
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin privileges required.';
    header('Location: add_equipment.php');
    exit();
}

// Include database connection
require_once '../../includes/db_connection.php';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Get form data and sanitize
    $equipment_name = trim($_POST['equipment_name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $equipment_id = trim($_POST['equipment_id'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = trim($_POST['status'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $operator_id = trim($_POST['operator_id'] ?? '');
    $purchase_date = trim($_POST['purchase_date'] ?? '');
    $daily_rate = trim($_POST['daily_rate'] ?? '');
    $fuel_type = trim($_POST['fuel_type'] ?? '');
    
    // Validation
    $errors = [];
    
    if (empty($equipment_name)) {
        $errors[] = 'Equipment name is required.';
    }
    
    if (empty($category)) {
        $errors[] = 'Category is required.';
    }
    
    if (empty($equipment_id)) {
        $errors[] = 'Equipment ID is required.';
    }
    
    if (empty($status)) {
        $errors[] = 'Status is required.';
    }
    
    if (empty($location)) {
        $errors[] = 'Location is required.';
    }
    
    if (empty($daily_rate) || !is_numeric($daily_rate) || $daily_rate < 0) {
        $errors[] = 'Valid daily rate is required.';
    }
    
    if (empty($description)) {
        $errors[] = 'Description is required.';
    }
    
    if (empty($purchase_date)) {
        $errors[] = 'Purchase date is required.';
    }
    
    if (empty($fuel_type)) {
        $errors[] = 'Fuel type is required.';
    }
    
    // If there are errors, redirect back with error message
    if (!empty($errors)) {
        $_SESSION['error'] = implode('<br>', $errors);
        header('Location: add_equipment.php');
        exit();
    }
    
    try {
        // Check if equipment ID already exists
        $check_sql = "SELECT id FROM equipment WHERE equipment_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $equipment_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows > 0) {
            $_SESSION['error'] = 'Equipment ID already exists. Please use a different ID.';
            header('Location: add_equipment.php');
            exit();
        }
        
        // Insert new equipment
        $sql = "INSERT INTO equipment (equipment_name, category, equipment_id, model, description, status, location, operator_id, purchase_date, daily_rate, fuel_type, created_at, updated_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssssssss", 
            $equipment_name, 
            $category, 
            $equipment_id, 
            $model, 
            $description, 
            $status, 
            $location, 
            $operator_id, 
            $purchase_date, 
            $daily_rate, 
            $fuel_type
        );
        
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Equipment added successfully!';
            header('Location: add_equipment.php');
            exit();
        } else {
            $_SESSION['error'] = 'Error adding equipment. Please try again.';
            header('Location: add_equipment.php');
            exit();
        }
        
    } catch (Exception $e) {
        $_SESSION['error'] = 'Database error: ' . $e->getMessage();
        header('Location: add_equipment.php');
        exit();
    }
} else {
    // If not POST request, redirect to form
    header('Location: add_equipment.php');
    exit();
}
?>
