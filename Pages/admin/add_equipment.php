<?php
// Initialize variables for error handling
$success = '';
$error = '';

// Check for success/error messages from URL parameters
if (isset($_GET['success'])) {
    $success = urldecode($_GET['success']);
}
if (isset($_GET['error'])) {
    $error = urldecode($_GET['error']);
}

// Start session at the very beginning, before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/signin.php');
    exit();
}

// Fetch operators from database
$operators = [];
try {
    // Include database connection
    require_once '../../includes/database.php';
    
    // Get database connection
    $conn = getDBConnection();
    
    // Query to get operators - using 'name' column instead of 'full_name'
    $sql = "SELECT user_id, name FROM users WHERE role = 'operator' ORDER BY name ASC";
    $result = $conn->query($sql);
    
    // Debug: Check connection and query
    if (!$conn) {
        error_log("Database connection failed");
    }
    if (!$result) {
        error_log("Query failed: " . $sql);
    } else {
        error_log("Query executed, rows found: " . $result->num_rows);
    }
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $operators[] = $row;
        }
    }
} catch (Exception $e) {
    // Handle database error gracefully
    error_log("Database error: " . $e->getMessage());
    $operators = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Equipment - FES Admin</title>
    <link rel="icon" type="image/png" href="../../assets/images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        fes: {
                            red: '#D32F2F',
                            dark: '#424242'
                        }
                    },
                    fontFamily: {
                        'playfair': ['Playfair Display', 'serif'],
                        'inter': ['Inter', 'sans-serif'],
                        'poppins': ['Poppins', 'sans-serif']
                    }
                }
            }
        };
    </script>
    <style>
        @media (max-width: 767px) {
            #main-content {
                margin-left: 0 !important;
                width: 100% !important;
            }
        }
        @media (min-width: 768px) {
            #main-content {
                margin-left: 300px !important;
                width: calc(100% - 300px) !important;
            }
        }
    </style>
</head>

<body>
    <div class="min-h-screen w-full bg-gray-100" style="font-family: Georgia, 'Times New Roman', serif;">
        <!-- Fixed Sidebar (Left Side) -->
        <?php include __DIR__ . '/include/sidebar.php'; ?>

        <!-- Mobile Overlay -->
        <div id="fes-dashboard-overlay" class="fixed inset-0 bg-black/40 z-30 hidden md:hidden"></div>

        <!-- Main Content Container (Right Side) -->
        <div class="min-h-screen" style="margin-left: 300px; width: calc(100% - 300px);" id="main-content">
            <!-- Top bar -->
            <header class="bg-white px-6 py-7 flex items-center justify-between shadow-sm md:pl-6">
                <div class="flex items-center gap-3">
                    <button id="fes-dashboard-menu-btn" class="md:hidden inline-flex items-center justify-center h-10 w-10 rounded-lg border border-gray-200 text-gray-600" aria-label="Open menu" aria-controls="fes-dashboard-sidebar" aria-expanded="false">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div>
                        <div class="text-sm text-gray-500">Equipment</div>
                        <h1 class="text-xl font-semibold text-gray-900">Add Equipment</h1>
                    </div>
                </div>
           </header>

            <!-- Content -->
            <main class="flex-1 overflow-y-auto p-6 md:pl-6" style="width: 100%; overflow-x: hidden;">
                    <!-- Success/Error Messages -->
                    <?php if (!empty($success)): ?>
                        <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 flex items-center gap-3">
                            <i class="fas fa-check-circle text-emerald-600"></i>
                            <?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($error)): ?>
                        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 flex items-center gap-3">
                            <i class="fas fa-exclamation-circle text-red-600"></i>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Session Messages -->
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 flex items-center gap-3">
                            <i class="fas fa-check-circle text-emerald-600"></i>
                            <?php 
                            echo htmlspecialchars($_SESSION['success']);
                            unset($_SESSION['success']);
                            ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 flex items-center gap-3">
                            <i class="fas fa-exclamation-circle text-red-600"></i>
                            <?php 
                            echo htmlspecialchars($_SESSION['error']);
                            unset($_SESSION['error']);
                            ?>
                        </div>
                    <?php endif; ?>

                    <!-- Form Container -->
                    <div class="max-w-5xl mx-auto">
                        <!-- Form Header -->
                        <div class="bg-white rounded-t-xl shadow-sm border border-gray-200 px-8 py-6">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 bg-gradient-to-br from-fes-red to-red-600 rounded-xl flex items-center justify-center shadow-lg">
                                    <i class="fas fa-tractor text-white text-lg"></i>
                                </div>
                                <div>
                                    <h2 class="text-2xl font-bold text-gray-900">Equipment Registration</h2>
                                    <p class="text-gray-600 mt-1">Add new equipment to the FES fleet management system</p>
                                </div>
                            </div>
                        </div>

                        <form action="process_add_equipment.php" method="POST" enctype="multipart/form-data" class="bg-white shadow-sm border border-t-0 border-gray-200 rounded-b-xl">
                            
                            <!-- Basic Information Section -->
                            <div class="px-8 py-6 border-b border-gray-100">
                                <div class="flex items-center gap-3 mb-6">
                                    <div class="w-8 h-8 bg-blue-50 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-info text-blue-600 text-sm"></i>
                                    </div>
                                    <h3 class="text-lg font-semibold text-gray-900">Basic Information</h3>
                                    <div class="ml-auto text-xs text-gray-500">Required fields marked with *</div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                    <div class="lg:col-span-2">
                                        <label for="equipment_name" class="block text-sm font-medium text-gray-700 mb-2">
                                            Equipment Name <span class="text-red-500">*</span>
                                        </label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i class="fas fa-tag text-gray-400"></i>
                                            </div>
                                            <input type="text" id="equipment_name" name="equipment_name" required
                                                   class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-fes-red focus:border-fes-red transition-colors"
                                                   placeholder="e.g., Tractor MF 375">
                                        </div>
                                    </div>

                                    <div>
                                        <label for="equipment_id" class="block text-sm font-medium text-gray-700 mb-2">
                                            Equipment ID <span class="text-red-500">*</span>
                                        </label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i class="fas fa-hashtag text-gray-400"></i>
                                            </div>
                                            <input type="text" id="equipment_id" name="equipment_id" required
                                                   class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-fes-red focus:border-fes-red transition-colors"
                                                   placeholder="e.g., EQ-001">
                                        </div>
                                    </div>

                                    <div>
                                        <label for="category" class="block text-sm font-medium text-gray-700 mb-2">
                                            Category <span class="text-red-500">*</span>
                                        </label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i class="fas fa-layer-group text-gray-400"></i>
                                            </div>
                                            <select id="category" name="category" required
                                                    class="block w-full pl-10 pr-10 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-fes-red focus:border-fes-red transition-colors appearance-none">
                                                <option value="">Select category</option>
                                                <option value="tractor">🚜 Tractor</option>
                                                <option value="harvester">🌾 Harvester</option>
                                                <option value="excavator">🏗️ Excavator</option>
                                                <option value="generator">⚡ Generator</option>
                                                <option value="pump">💧 Irrigation Pump</option>
                                                <option value="truck">🚚 Truck/Transport</option>
                                                <option value="roller">🔧 Compactor/Roller</option>
                                                <option value="sprayer">🌿 Sprayer</option>
                                                <option value="loader">🔨 Loader</option>
                                                <option value="other">📦 Other</option>
                                            </select>
                                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                                <i class="fas fa-chevron-down text-gray-400"></i>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <label for="model" class="block text-sm font-medium text-gray-700 mb-2">
                                            Model / Make
                                        </label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i class="fas fa-industry text-gray-400"></i>
                                            </div>
                                            <input type="text" id="model" name="model"
                                                   class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-fes-red focus:border-fes-red transition-colors"
                                                   placeholder="e.g., Massey Ferguson 375">
                                        </div>
                                    </div>


                                    <div class="lg:col-span-3">
                                        <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                                            Description <span class="text-red-500">*</span>
                                        </label>
                                        <div class="relative">
                                            <div class="absolute top-3 left-3 pointer-events-none">
                                                <i class="fas fa-align-left text-gray-400"></i>
                                            </div>
                                            <textarea id="description" name="description" rows="3" required
                                                      class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-fes-red focus:border-fes-red transition-colors resize-none"
                                                      placeholder="Describe equipment's purpose, capabilities, and notable features…"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Status & Location Section -->
                            <div class="px-8 py-6 border-b border-gray-100">
                                <div class="flex items-center gap-3 mb-6">
                                    <div class="w-8 h-8 bg-green-50 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-map-marker-alt text-green-600 text-sm"></i>
                                    </div>
                                    <h3 class="text-lg font-semibold text-gray-900">Status & Location</h3>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                                    <div>
                                        <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                                            Status <span class="text-red-500">*</span>
                                        </label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i class="fas fa-toggle-on text-gray-400"></i>
                                            </div>
                                            <select id="status" name="status" required
                                                    class="block w-full pl-10 pr-10 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-fes-red focus:border-fes-red transition-colors appearance-none">
                                                <option value="available"> Available</option>
                                                <option value="in_use"> In Use</option>
                                                <option value="maintenance"> Maintenance</option>
                                                <option value="retired">Retired</option>
                                            </select>
                                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                                <i class="fas fa-chevron-down text-gray-400"></i>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <label for="location" class="block text-sm font-medium text-gray-700 mb-2">
                                            Location <span class="text-red-500">*</span>
                                        </label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i class="fas fa-warehouse text-gray-400"></i>
                                            </div>
                                            <select id="location" name="location" required
                                                    class="block w-full pl-10 pr-10 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-fes-red focus:border-fes-red transition-colors appearance-none">
                                                <option value="">Select depot</option>
                                                <option value="Blantyre Depot">Blantyre Depot</option>
                                                <option value="Lilongwe Hub"> Lilongwe Hub</option>
                                                <option value="Mzuzu Branch"> Mzuzu Branch</option>
                                                <option value="Limbe Store"> Limbe Store</option>
                                                <option value="Workshop"> Workshop</option>
                                            </select>
                                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                                <i class="fas fa-chevron-down text-gray-400"></i>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <label for="operator_id" class="block text-sm font-medium text-gray-700 mb-2">
                                            Assigned Operator
                                        </label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i class="fas fa-hard-hat text-gray-400"></i>
                                            </div>
                                            <select id="operator_id" name="operator_id"
                                                    class="block w-full pl-10 pr-10 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-fes-red focus:border-fes-red transition-colors appearance-none">
                                                <option value="">Unassigned</option>
                                                <?php if (!empty($operators)): ?>
                                                    <?php foreach ($operators as $operator): ?>
                                                        <option value="<?php echo htmlspecialchars($operator['user_id']); ?>">
                                                            👤 <?php echo htmlspecialchars($operator['name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <option value="">No operators found</option>
                                                <?php endif; ?>
                                            </select>
                                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                                <i class="fas fa-chevron-down text-gray-400"></i>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <label for="purchase_date" class="block text-sm font-medium text-gray-700 mb-2">
                                            Purchase Date
                                        </label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i class="fas fa-calendar text-gray-400"></i>
                                            </div>
                                            <input type="date" id="purchase_date" name="purchase_date"
                                                   class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-fes-red focus:border-fes-red transition-colors">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Pricing Section -->
                            <div class="px-8 py-6 border-b border-gray-100">
                                <div class="flex items-center gap-3 mb-6">
                                    <div class="w-8 h-8 bg-yellow-50 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-dollar-sign text-yellow-600 text-sm"></i>
                                    </div>
                                    <h3 class="text-lg font-semibold text-gray-900">Pricing Information</h3>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="daily_rate" class="block text-sm font-medium text-gray-700 mb-2">
                                            Daily Rate (MWK) <span class="text-red-500">*</span>
                                        </label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <span class="text-gray-500 font-medium">MWK</span>
                                            </div>
                                            <input type="number" id="daily_rate" name="daily_rate" required min="0" step="0.01"
                                                   class="block w-full pl-16 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-fes-red focus:border-fes-red transition-colors"
                                                   placeholder="0.00">
                                        </div>
                                    </div>

                                    <div>
                                        <label for="hourly_rate" class="block text-sm font-medium text-gray-700 mb-2">
                                            Hourly Rate (MWK)
                                        </label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <span class="text-gray-500 font-medium">MWK</span>
                                            </div>
                                            <input type="number" id="hourly_rate" name="hourly_rate" min="0" step="0.01"
                                                   class="block w-full pl-16 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-fes-red focus:border-fes-red transition-colors"
                                                   placeholder="0.00">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Technical Specifications Section -->
                            <div class="px-8 py-6 border-b border-gray-100">
                                <div class="flex items-center gap-3 mb-6">
                                    <div class="w-8 h-8 bg-purple-50 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-cogs text-purple-600 text-sm"></i>
                                    </div>
                                    <h3 class="text-lg font-semibold text-gray-900">Technical Specifications</h3>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">

                                    <div>
                                        <label for="total_usage_hours" class="block text-sm font-medium text-gray-700 mb-2">
                                            Usage Hours
                                        </label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i class="fas fa-clock text-gray-400"></i>
                                            </div>
                                            <input type="number" id="total_usage_hours" name="total_usage_hours" min="0" value="0"
                                                   class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-fes-red focus:border-fes-red transition-colors"
                                                   placeholder="0">
                                        </div>
                                    </div>

                                    <div>
                                        <label for="fuel_type" class="block text-sm font-medium text-gray-700 mb-2">
                                            Fuel Type
                                        </label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i class="fas fa-gas-pump text-gray-400"></i>
                                            </div>
                                            <select id="fuel_type" name="fuel_type"
                                                    class="block w-full pl-10 pr-10 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-fes-red focus:border-fes-red transition-colors appearance-none">
                                                <option value="">Select fuel</option>
                                                <option value="diesel">Diesel</option>
                                                <option value="petrol">Petrol</option>
                                                <option value="electric">Electric</option>
                                                <option value="na"> N/A</option>
                                            </select>
                                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                                <i class="fas fa-chevron-down text-gray-400"></i>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <label for="year_manufactured" class="block text-sm font-medium text-gray-700 mb-2">
                                            Year Manufactured
                                        </label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i class="fas fa-calendar-alt text-gray-400"></i>
                                            </div>
                                            <input type="number" id="year_manufactured" name="year_manufactured" min="1970" max="2030"
                                                   class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-fes-red focus:border-fes-red transition-colors"
                                                   placeholder="2020">
                                        </div>
                                    </div>

                                    <div>
                                        <label for="weight_kg" class="block text-sm font-medium text-gray-700 mb-2">
                                            Weight (kg)
                                        </label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i class="fas fa-weight text-gray-400"></i>
                                            </div>
                                            <input type="number" id="weight_kg" name="weight_kg" min="0"
                                                   class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-fes-red focus:border-fes-red transition-colors"
                                                   placeholder="3500">
                                        </div>
                                    </div>


                                    <div>
                                        <label for="last_maintenance" class="block text-sm font-medium text-gray-700 mb-2">
                                            Last Maintenance
                                        </label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i class="fas fa-wrench text-gray-400"></i>
                                            </div>
                                            <input type="date" id="last_maintenance" name="last_maintenance"
                                                   class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-fes-red focus:border-fes-red transition-colors">
                                        </div>
                                    </div>

                                    <div>
                                        <label for="icon" class="block text-sm font-medium text-gray-700 mb-2">
                                            Display Icon <span class="text-red-500">*</span>
                                        </label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i class="fas fa-icons text-gray-400"></i>
                                            </div>
                                            <select id="icon" name="icon" required
                                                    class="block w-full pl-10 pr-10 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-fes-red focus:border-fes-red transition-colors appearance-none">
                                                <option value="">Select icon</option>
                                                <option value="fa-tractor">🚜 Tractor</option>
                                                <option value="fa-wheat-awn">🌾 Harvester</option>
                                                <option value="fa-helmet-safety">🏗️ Excavator</option>
                                                <option value="fa-bolt">⚡ Generator</option>
                                                <option value="fa-faucet">💧 Pump</option>
                                                <option value="fa-truck-monster">🚚 Bulldozer</option>
                                                <option value="fa-truck-ramp-box">📦 Truck</option>
                                                <option value="fa-spray-can">🌿 Sprayer</option>
                                                <option value="fa-circle">⭕ Roller</option>
                                                <option value="fa-person-digging">🔨 Loader</option>
                                                <option value="fa-plug">🔌 Transformer</option>
                                            </select>
                                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                                <i class="fas fa-chevron-down text-gray-400"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Additional Information Section -->
                            <div class="px-8 py-6 border-b border-gray-100">
                                <div class="flex items-center gap-3 mb-6">
                                    <div class="w-8 h-8 bg-indigo-50 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-clipboard-list text-indigo-600 text-sm"></i>
                                    </div>
                                    <h3 class="text-lg font-semibold text-gray-900">Additional Information</h3>
                                </div>



                                    <div class="lg:col-span-2">
                                        <label for="image" class="block text-sm font-medium text-gray-700 mb-2">
                                            Equipment Image
                                        </label>
                                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center cursor-pointer hover:border-fes-red hover:bg-red-50 transition-all duration-300"
                                             onclick="document.getElementById('image').click()">
                                            <div class="flex flex-col items-center">
                                                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                                    <i class="fas fa-cloud-upload-alt text-2xl text-gray-400" id="uploadIcon"></i>
                                                </div>
                                                <p class="text-gray-700 font-medium mb-1" id="uploadLabel">Click to upload equipment image</p>
                                                <p class="text-sm text-gray-500">PNG, JPG up to 5MB</p>
                                            </div>
                                            <input type="file" id="image" name="image" accept="image/*" class="hidden" onchange="previewImage(this)">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Form Actions -->
                            <div class="px-8 py-6 bg-gray-50 rounded-b-xl">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2 text-sm text-gray-500">
                                    </div>
                                    <div class="flex items-center gap-4">
                                        <button type="submit" 
                                                class="px-8 py-3 bg-gradient-to-r from-fes-red to-red-600 hover:from-red-600 hover:to-red-700 text-white font-medium rounded-lg transition-all duration-200 flex items-center gap-2 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5">
                                            <i class="fas fa-plus"></i>
                                            Add Equipment
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </main>
            </div>
        </div>
    </div>

    <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const icon = document.getElementById('uploadIcon');
                const label = document.getElementById('uploadLabel');
                icon.className = 'fas fa-check-circle text-2xl text-green-500';
                label.textContent = input.files[0].name;
            }
        }

        // Mobile menu toggle
        document.getElementById('fes-dashboard-menu-btn').addEventListener('click', function() {
            const sidebar = document.getElementById('fes-dashboard-sidebar');
            const overlay = document.getElementById('fes-dashboard-overlay');
            
            sidebar.classList.toggle('show');
            overlay.classList.toggle('hidden');
        });

        document.getElementById('fes-dashboard-overlay').addEventListener('click', function() {
            const sidebar = document.getElementById('fes-dashboard-sidebar');
            const overlay = document.getElementById('fes-dashboard-overlay');
            
            sidebar.classList.remove('show');
            overlay.classList.add('hidden');
        });

        // Form validation feedback
        document.querySelectorAll('input[required], select[required], textarea[required]').forEach(field => {
            field.addEventListener('blur', function() {
                if (!this.value) {
                    this.classList.add('border-red-300');
                    this.classList.remove('border-gray-300');
                } else {
                    this.classList.remove('border-red-300');
                    this.classList.add('border-gray-300');
                }
            });

            field.addEventListener('input', function() {
                if (this.value) {
                    this.classList.remove('border-red-300');
                    this.classList.add('border-gray-300');
                }
            });
        });
    </script>
        </main>
    </div>
    
    <!-- Fixed Sidebar (Right Side) -->
    <?php include __DIR__ . '/include/sidebar.php'; ?>

    <!-- Mobile Overlay -->
    <div id="fes-dashboard-overlay" class="fixed inset-0 bg-black/40 z-30 hidden md:hidden"></div>
    </div>
</body>
</html>
