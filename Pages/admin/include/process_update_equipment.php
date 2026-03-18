<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../equipment.php');
    exit();
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    $_SESSION['error'] = 'Access denied.';
    header('Location: ../equipment.php');
    exit();
}

require_once '../../../includes/database.php';

$equipment_id       = (int)($_POST['equipment_id'] ?? 0);
$equipment_name     = trim($_POST['equipment_name'] ?? '');
$category           = trim($_POST['category'] ?? '');
$status             = trim($_POST['status'] ?? '');
$location           = trim($_POST['location'] ?? '');
$model              = trim($_POST['model'] ?? '');
$description        = trim($_POST['description'] ?? '');
$purchase_date      = trim($_POST['purchase_date'] ?? '');
$daily_rate         = trim($_POST['daily_rate'] ?? '');
$hourly_rate        = trim($_POST['hourly_rate'] ?? '');
$per_hectare_rate   = trim($_POST['per_hectare_rate'] ?? '');
$fuel_type          = trim($_POST['fuel_type'] ?? '');
$total_usage_hours  = trim($_POST['total_usage_hours'] ?? '');
$year_manufactured  = trim($_POST['year_manufactured'] ?? '');
$weight_kg          = trim($_POST['weight_kg'] ?? '');
$last_maintenance   = trim($_POST['last_maintenance'] ?? '');
$icon               = trim($_POST['icon'] ?? '');
$current_image_path = trim($_POST['current_image_path'] ?? '');

$allowed_status = ['available', 'in_use', 'maintenance', 'retired'];

$errors = [];
if ($equipment_id <= 0) {
    $errors[] = 'Invalid equipment selected.';
}
if ($equipment_name === '') {
    $errors[] = 'Equipment name is required.';
}
if ($category === '') {
    $errors[] = 'Category is required.';
}
if ($location === '') {
    $errors[] = 'Location is required.';
}
if (!in_array($status, $allowed_status, true)) {
    $errors[] = 'Invalid status value.';
}
if ($daily_rate !== '' && (!is_numeric($daily_rate) || $daily_rate < 0)) {
    $errors[] = 'Daily rate must be a positive number.';
}
if ($hourly_rate !== '' && (!is_numeric($hourly_rate) || $hourly_rate < 0)) {
    $errors[] = 'Hourly rate must be a positive number.';
}
if ($total_usage_hours !== '' && (!is_numeric($total_usage_hours) || $total_usage_hours < 0)) {
    $errors[] = 'Usage hours must be a positive number.';
}
if ($year_manufactured !== '' && (!is_numeric($year_manufactured) || $year_manufactured < 1970 || $year_manufactured > 2035)) {
    $errors[] = 'Year manufactured must be between 1970 and 2035.';
}
if ($weight_kg !== '' && (!is_numeric($weight_kg) || $weight_kg < 0)) {
    $errors[] = 'Weight must be a positive number.';
}

// Handle image upload if a new file was provided
$image_path = $current_image_path;
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    // Store under the main /assets/images/equipment directory at project root
    $upload_dir = '../../../assets/images/equipment/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $file_name = time() . '_' . basename($_FILES['image']['name']);
    $target_file = $upload_dir . $file_name;

    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
    $max_size = 5 * 1024 * 1024; // 5MB

    if (!in_array($_FILES['image']['type'], $allowed_types, true)) {
        $errors[] = 'Image must be a JPG or PNG file.';
    } elseif ($_FILES['image']['size'] > $max_size) {
        $errors[] = 'Image must be 5MB or smaller.';
    } elseif (!move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
        $errors[] = 'Failed to upload image.';
    } else {
        // Web path from project root (used by front-end)
        $image_path = 'assets/images/equipment/' . $file_name;
    }
}

if (!empty($errors)) {
    $_SESSION['error'] = implode('<br>', $errors);
    header('Location: ../equipment.php');
    exit();
}

try {
    $conn = getDBConnection();

    $stmt = $conn->prepare('
        UPDATE equipment
        SET
            equipment_name    = ?,
            category          = ?,
            model             = ?,
            description       = ?,
            status            = ?,
            location          = ?,
            purchase_date     = ?,
            daily_rate        = ?,
            hourly_rate       = ?,
            per_hectare_rate  = ?,
            fuel_type         = ?,
            total_usage_hours = ?,
            year_manufactured = ?,
            weight_kg         = ?,
            last_maintenance  = ?,
            icon              = ?,
            image_path        = ?,
            updated_at        = NOW()
        WHERE id = ?
    ');

    $stmt->bind_param(
        'sssssssssssssssssi',
        $equipment_name,
        $category,
        $model,
        $description,
        $status,
        $location,
        $purchase_date,
        $daily_rate,
        $hourly_rate,
        $per_hectare_rate,
        $fuel_type,
        $total_usage_hours,
        $year_manufactured,
        $weight_kg,
        $last_maintenance,
        $icon,
        $image_path,
        $equipment_id
    );

    if ($stmt->execute()) {
        $_SESSION['success'] = 'Equipment updated successfully.';
    } else {
        $_SESSION['error'] = 'Failed to update equipment.';
    }

    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    error_log('Equipment update error: ' . $e->getMessage());
    $_SESSION['error'] = 'Unexpected error while updating equipment.';
}

header('Location: ../equipment.php');
exit();
?>

