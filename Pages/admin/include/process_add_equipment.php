<?php
// Start session at the very beginning
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin privileges required.';
    header('Location: ../add_equipment.php');
    exit();
}

// Include database connection
require_once '../../../includes/database.php';

/**
 * Generate automatic equipment ID in format EQ-001, EQ-002, etc.
 */
function generateEquipmentId($conn) {
    // Get the last equipment ID
    $sql = "SELECT equipment_id FROM equipment ORDER BY id DESC LIMIT 1";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $last_id = $result->fetch_assoc()['equipment_id'];
        // Extract numeric part and increment
        $number = intval(substr($last_id, 3)) + 1;
    } else {
        $number = 1; // Start with 1 if no equipment exists
    }
    
    return 'EQ-' . str_pad($number, 3, '0', STR_PAD_LEFT);
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Get form data and sanitize
    $equipment_name = trim($_POST['equipment_name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = trim($_POST['status'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $operator_id = trim($_POST['operator_id'] ?? '');
    $purchase_date = trim($_POST['purchase_date'] ?? '');
    $daily_rate = trim($_POST['daily_rate'] ?? '');
    $hourly_rate = trim($_POST['hourly_rate'] ?? '0');
    $fuel_type = trim($_POST['fuel_type'] ?? '');
    $total_usage_hours = trim($_POST['total_usage_hours'] ?? '0');
    $year_manufactured = trim($_POST['year_manufactured'] ?? '');
    $weight_kg = trim($_POST['weight_kg'] ?? '');
    $last_maintenance = trim($_POST['last_maintenance'] ?? '');
    $icon = trim($_POST['icon'] ?? '');
    
    // Handle image upload
    $image_path = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../../assets/images/equipment/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_name = time() . '_' . basename($_FILES['image']['name']);
        $target_file = $upload_dir . $file_name;
        
        // Check file type and size
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (in_array($_FILES['image']['type'], $allowed_types) && $_FILES['image']['size'] <= $max_size) {
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                $image_path = 'assets/images/equipment/' . $file_name;
            }
        }
    }
    
    // Validation
    $errors = [];
    
    if (empty($equipment_name)) {
        $errors[] = 'Equipment name is required.';
    }
    
    if (empty($category)) {
        $errors[] = 'Category is required.';
    }
    
    if (empty($model)) {
        $errors[] = 'Model is required.';
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
    
    if (empty($icon)) {
        $errors[] = 'Display icon is required.';
    }
    
    if (!empty($hourly_rate) && (!is_numeric($hourly_rate) || $hourly_rate < 0)) {
        $errors[] = 'Valid hourly rate is required.';
    }
    
    if (!empty($total_usage_hours) && (!is_numeric($total_usage_hours) || $total_usage_hours < 0)) {
        $errors[] = 'Valid usage hours is required.';
    }
    
    if (!empty($year_manufactured) && (!is_numeric($year_manufactured) || $year_manufactured < 1970 || $year_manufactured > 2030)) {
        $errors[] = 'Valid year manufactured is required.';
    }
    
    if (!empty($weight_kg) && (!is_numeric($weight_kg) || $weight_kg < 0)) {
        $errors[] = 'Valid weight is required.';
    }
    
    // If there are errors, redirect back with error message
    if (!empty($errors)) {
        $_SESSION['error'] = implode('<br>', $errors);
        header('Location: ../add_equipment.php');
        exit();
    }
    
    try {
        // Get database connection
        $conn = getDBConnection();
        
        // Generate automatic equipment ID
        $equipment_id = generateEquipmentId($conn);
        
        // Check if equipment ID already exists (shouldn't happen with auto-generation)
        $check_sql = "SELECT id FROM equipment WHERE equipment_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $equipment_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows > 0) {
            $_SESSION['error'] = 'Equipment ID already exists. Please use a different ID.';
            header('Location: ../add_equipment.php');
            exit();
        }
        
        // Insert new equipment
        $sql = "INSERT INTO equipment (equipment_name, category, equipment_id, model, description, status, location, operator_id, purchase_date, daily_rate, hourly_rate, fuel_type, total_usage_hours, year_manufactured, weight_kg, last_maintenance, icon, image_path, created_at, updated_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssssssssssssss", 
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
            $hourly_rate,
            $fuel_type,
            $total_usage_hours,
            $year_manufactured,
            $weight_kg,
            $last_maintenance,
            $icon,
            $image_path
        );
        
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Equipment added successfully!';
            header('Location: ../add_equipment.php');
            exit();
        } else {
            $_SESSION['error'] = 'Error adding equipment. Please try again.';
            header('Location: ../add_equipment.php');
            exit();
        }
        
    } catch (Exception $e) {
        $_SESSION['error'] = 'Database error: ' . $e->getMessage();
        header('Location: ../add_equipment.php');
        exit();
    }
} else {
    // If not POST request, redirect to form
    header('Location: ../add_equipment.php');
    exit();
}
?>


