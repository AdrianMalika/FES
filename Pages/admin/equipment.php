<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/signin.php');
    exit();
}

if (($_SESSION['role'] ?? '') !== 'admin') {
    switch ($_SESSION['role'] ?? '') {
        case 'operator':
            header('Location: ../operator/dashboard.php');
            exit();
        case 'customer':
            header('Location: ../customer/dashboard.php');
            exit();
        default:
            header('Location: ../auth/signin.php');
            exit();
    }
}

require_once '../../includes/database.php';

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

$equipment = [];
$operators = [];
$busyOperators = [];

try {
    $conn = getDBConnection();

    $eqSql = "
        SELECT
            e.id,
            e.equipment_id,
            e.equipment_name,
            e.category,
            e.model,
            e.description,
            e.status,
            e.location,
            e.operator_id,
            e.purchase_date,
            e.daily_rate,
            e.hourly_rate,
            e.fuel_type,
            e.total_usage_hours,
            e.year_manufactured,
            e.weight_kg,
            e.last_maintenance,
            e.icon,
            e.image_path,
            u.name AS operator_name
        FROM equipment e
        LEFT JOIN users u ON u.user_id = e.operator_id
        ORDER BY e.created_at DESC, e.id DESC
    ";
    $eqRes = $conn->query($eqSql);
    if ($eqRes) {
        while ($row = $eqRes->fetch_assoc()) {
            $equipment[] = $row;
        }
    }

    $opSql = "SELECT user_id, name FROM users WHERE role = 'operator' ORDER BY name ASC";
    $opRes = $conn->query($opSql);
    if ($opRes) {
        while ($row = $opRes->fetch_assoc()) {
            $operators[] = $row;
        }
    }

    $busySql = "
        SELECT
            e.operator_id,
            e.equipment_id,
            e.equipment_name
        FROM equipment e
        WHERE e.operator_id IS NOT NULL
    ";
    $busyRes = $conn->query($busySql);
    if ($busyRes) {
        while ($row = $busyRes->fetch_assoc()) {
            $busyOperators[(int)$row['operator_id']] = [
                'equipment_id' => $row['equipment_id'],
                'equipment_name' => $row['equipment_name']
            ];
        }
    }

    $conn->close();
} catch (Exception $e) {
    error_log('Admin equipment page error: ' . $e->getMessage());
    $error = 'Failed to load equipment data.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Equipment Management - FES Admin</title>
    <link rel="icon" type="image/png" href="../../assets/images/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;600;700;800;900&family=Barlow:wght@300;400;500;600&display=swap" rel="stylesheet">
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
                    boxShadow: {
                        card: '0 4px 15px rgba(0,0,0,0.05)'
                    }
                }
            }
        };
    </script>
    <style>
        * { font-family: 'Barlow', sans-serif; }
        h1, h2, h3, h4, .display { font-family: 'Barlow Condensed', sans-serif; }

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
    <div class="min-h-screen w-full bg-gray-100">
        <?php include __DIR__ . '/include/sidebar.php'; ?>

        <div id="fes-dashboard-overlay" class="fixed inset-0 bg-black/40 z-30 hidden md:hidden"></div>

        <div class="min-h-screen" style="margin-left: 300px; width: calc(100% - 300px);" id="main-content">
            <header class="bg-white px-6 py-7 flex items-center justify-between shadow-sm md:pl-6">
                <div class="flex items-center gap-3">
                    <button id="fes-dashboard-menu-btn" class="md:hidden inline-flex items-center justify-center h-10 w-10 rounded-lg border border-gray-200 text-gray-600" aria-label="Open menu" aria-controls="fes-dashboard-sidebar" aria-expanded="false">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div>
                        <div class="text-sm text-gray-500">Equipment</div>
                        <h1 class="text-xl font-semibold text-gray-900">Manage Equipment</h1>
                    </div>
                </div>

                <a href="add_equipment.php" class="inline-flex items-center gap-2 bg-fes-red hover:bg-[#b71c1c] text-white font-medium px-4 py-2 rounded-lg shadow transition">
                    <i class="fas fa-plus"></i>
                    Add Equipment
                </a>
            </header>

            <main class="flex-1 overflow-y-auto p-6" style="width: 100%; overflow-x: hidden;">
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

                <section class="bg-white rounded-xl shadow-card p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-base font-semibold text-gray-900">All Equipment</h2>
                        <span class="text-sm text-gray-500"><?php echo count($equipment); ?> item(s)</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead>
                                <tr class="text-left text-xs font-medium text-gray-500 border-b uppercase tracking-wider">
                                    <th class="py-3 pr-4">Equipment ID</th>
                                    <th class="py-3 pr-4">Equipment</th>
                                    <th class="py-3 pr-4">Category</th>
                                    <th class="py-3 pr-4">Status</th>
                                    <th class="py-3 pr-4">Current Operator</th>
                                    <th class="py-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm text-gray-900">
                                <?php if (empty($equipment)): ?>
                                    <tr>
                                        <td colspan="6" class="py-10 text-center text-gray-500">No equipment found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($equipment as $row): ?>
                                        <tr class="border-b hover:bg-gray-50">
                                            <td class="py-3 pr-4">
                                                <div class="font-medium"><?php echo htmlspecialchars($row['equipment_id']); ?></div>
                                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($row['location']); ?></div>
                                            </td>
                                            <td class="py-3 pr-4">
                                                <div class="font-medium"><?php echo htmlspecialchars($row['equipment_name']); ?></div>
                                            </td>
                                            <td class="py-3 pr-4"><?php echo htmlspecialchars(ucfirst($row['category'])); ?></td>
                                            <td class="py-3 pr-4">
                                                <?php
                                                $status = $row['status'];
                                                $cls = 'bg-gray-100 text-gray-700';
                                                if ($status === 'available') $cls = 'bg-emerald-50 text-emerald-700';
                                                if ($status === 'in_use') $cls = 'bg-amber-50 text-amber-700';
                                                if ($status === 'maintenance') $cls = 'bg-red-50 text-red-700';
                                                ?>
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium <?php echo $cls; ?>">
                                                    <?php echo htmlspecialchars(str_replace('_', ' ', ucfirst($status))); ?>
                                                </span>
                                            </td>
                                            <td class="py-3 pr-4">
                                                <?php if (!empty($row['operator_name'])): ?>
                                                    <span class="font-medium"><?php echo htmlspecialchars($row['operator_name']); ?></span>
                                                <?php else: ?>
                                                    <span class="text-gray-500">Unassigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-3">
                                                <div class="flex items-center gap-2">
                                                    <button type="button"
                                                        class="row-toggle inline-flex items-center justify-center h-12 w-20 rounded-md border border-slate-500 text-slate-700 hover:bg-slate-50 transition"
                                                        data-target="edit-row-<?php echo (int)$row['id']; ?>"
                                                        title="Edit equipment">
                                                        <i class="fas fa-pen"></i>
                                                    </button>
                                                    <form action="include/process_delete_equipment.php" method="POST" class="inline" onsubmit="return confirm('Delete this equipment permanently?');">
                                                        <input type="hidden" name="equipment_id" value="<?php echo (int)$row['id']; ?>">
                                                        <button type="submit"
                                                            class="inline-flex items-center justify-center h-12 w-12 rounded-md text-red-500 hover:bg-red-50 transition"
                                                            title="Delete equipment">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                    <button type="button"
                                                        class="inline-flex items-center justify-center h-12 w-12 rounded-md text-amber-500 hover:bg-amber-50 transition"
                                                        title="Assign operator"
                                                        data-target="assign-row-<?php echo (int)$row['id']; ?>">
                                                        <i class="fas fa-user"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr id="edit-row-<?php echo (int)$row['id']; ?>" class="hidden bg-gray-50">
                                            <td colspan="5" class="py-4 px-4 border-b">
                                                <form action="include/process_update_equipment.php" method="POST" enctype="multipart/form-data" class="space-y-3">
                                                    <input type="hidden" name="equipment_id" value="<?php echo (int)$row['id']; ?>">
                                                    <input type="hidden" name="current_image_path" value="<?php echo htmlspecialchars($row['image_path'] ?? ''); ?>">

                                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                                        <div>
                                                            <label class="block text-xs font-medium text-gray-600 mb-1">Equipment Name</label>
                                                            <input type="text" name="equipment_name" value="<?php echo htmlspecialchars($row['equipment_name']); ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-fes-red focus:border-fes-red" required>
                                                        </div>
                                                        <div>
                                                            <label class="block text-xs font-medium text-gray-600 mb-1">Category</label>
                                                            <select name="category" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-fes-red focus:border-fes-red" required>
                                                                <option value="">Select category</option>
                                                                <option value="tractor" <?php echo $row['category'] === 'tractor' ? 'selected' : ''; ?>>Tractor</option>
                                                                <option value="harvester" <?php echo $row['category'] === 'harvester' ? 'selected' : ''; ?>>Harvester</option>
                                                                <option value="excavator" <?php echo $row['category'] === 'excavator' ? 'selected' : ''; ?>>Excavator</option>
                                                                <option value="generator" <?php echo $row['category'] === 'generator' ? 'selected' : ''; ?>>Generator</option>
                                                                <option value="pump" <?php echo $row['category'] === 'pump' ? 'selected' : ''; ?>>Irrigation Pump</option>
                                                                <option value="truck" <?php echo $row['category'] === 'truck' ? 'selected' : ''; ?>>Truck / Transport</option>
                                                                <option value="roller" <?php echo $row['category'] === 'roller' ? 'selected' : ''; ?>>Compactor / Roller</option>
                                                                <option value="sprayer" <?php echo $row['category'] === 'sprayer' ? 'selected' : ''; ?>>Sprayer</option>
                                                                <option value="loader" <?php echo $row['category'] === 'loader' ? 'selected' : ''; ?>>Loader</option>
                                                                <option value="other" <?php echo $row['category'] === 'other' ? 'selected' : ''; ?>>Other</option>
                                                            </select>
                                                        </div>
                                                        <div>
                                                            <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
                                                            <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-fes-red focus:border-fes-red" required>
                                                                <option value="available" <?php echo $row['status'] === 'available' ? 'selected' : ''; ?>>Available</option>
                                                                <option value="in_use" <?php echo $row['status'] === 'in_use' ? 'selected' : ''; ?>>In Use</option>
                                                                <option value="maintenance" <?php echo $row['status'] === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                                                                <option value="retired" <?php echo $row['status'] === 'retired' ? 'selected' : ''; ?>>Retired</option>
                                                            </select>
                                                        </div>
                                                    </div>

                                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                                        <div>
                                                            <label class="block text-xs font-medium text-gray-600 mb-1">Model / Make</label>
                                                            <input type="text" name="model" value="<?php echo htmlspecialchars($row['model'] ?? ''); ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-fes-red focus:border-fes-red">
                                                        </div>
                                                        <div>
                                                            <label class="block text-xs font-medium text-gray-600 mb-1">Location</label>
                                                            <select name="location" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-fes-red focus:border-fes-red" required>
                                                                <option value="Blantyre Depot" <?php echo $row['location'] === 'Blantyre Depot' ? 'selected' : ''; ?>>Blantyre Depot</option>
                                                                <option value="Lilongwe Hub" <?php echo $row['location'] === 'Lilongwe Hub' ? 'selected' : ''; ?>>Lilongwe Hub</option>
                                                                <option value="Mzuzu Branch" <?php echo $row['location'] === 'Mzuzu Branch' ? 'selected' : ''; ?>>Mzuzu Branch</option>
                                                                <option value="Limbe Store" <?php echo $row['location'] === 'Limbe Store' ? 'selected' : ''; ?>>Limbe Store</option>
                                                                <option value="Workshop" <?php echo $row['location'] === 'Workshop' ? 'selected' : ''; ?>>Workshop</option>
                                                            </select>
                                                        </div>
                                                        <div>
                                                            <label class="block text-xs font-medium text-gray-600 mb-1">Purchase Date</label>
                                                            <input type="date" name="purchase_date" value="<?php echo htmlspecialchars($row['purchase_date'] ?? ''); ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-fes-red focus:border-fes-red">
                                                        </div>
                                                    </div>

                                                    <div>
                                                        <label class="block text-xs font-medium text-gray-600 mb-1">Description</label>
                                                        <textarea name="description" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-fes-red focus:border-fes-red resize-none"><?php echo htmlspecialchars($row['description'] ?? ''); ?></textarea>
                                                    </div>

                                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                                        <div>
                                                            <label class="block text-xs font-medium text-gray-600 mb-1">Daily Rate (MWK)</label>
                                                            <input type="number" name="daily_rate" value="<?php echo htmlspecialchars($row['daily_rate'] ?? '0'); ?>" min="0" step="0.01" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-fes-red focus:border-fes-red">
                                                        </div>
                                                        <div>
                                                            <label class="block text-xs font-medium text-gray-600 mb-1">Hourly Rate (MWK)</label>
                                                            <input type="number" name="hourly_rate" value="<?php echo htmlspecialchars($row['hourly_rate'] ?? '0'); ?>" min="0" step="0.01" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-fes-red focus:border-fes-red">
                                                        </div>
                                                        <div>
                                                            <label class="block text-xs font-medium text-gray-600 mb-1">Fuel Type</label>
                                                            <select name="fuel_type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-fes-red focus:border-fes-red">
                                                                <option value="diesel" <?php echo $row['fuel_type'] === 'diesel' ? 'selected' : ''; ?>>Diesel</option>
                                                                <option value="petrol" <?php echo $row['fuel_type'] === 'petrol' ? 'selected' : ''; ?>>Petrol</option>
                                                                <option value="electric" <?php echo $row['fuel_type'] === 'electric' ? 'selected' : ''; ?>>Electric</option>
                                                                <option value="na" <?php echo $row['fuel_type'] === 'na' ? 'selected' : ''; ?>>N/A</option>
                                                            </select>
                                                        </div>
                                                    </div>

                                                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                                                        <div>
                                                            <label class="block text-xs font-medium text-gray-600 mb-1">Usage Hours</label>
                                                            <input type="number" name="total_usage_hours" value="<?php echo htmlspecialchars($row['total_usage_hours'] ?? '0'); ?>" min="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-fes-red focus:border-fes-red">
                                                        </div>
                                                        <div>
                                                            <label class="block text-xs font-medium text-gray-600 mb-1">Year Manufactured</label>
                                                            <input type="number" name="year_manufactured" value="<?php echo htmlspecialchars($row['year_manufactured'] ?? ''); ?>" min="1970" max="2035" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-fes-red focus:border-fes-red">
                                                        </div>
                                                        <div>
                                                            <label class="block text-xs font-medium text-gray-600 mb-1">Weight (kg)</label>
                                                            <input type="number" name="weight_kg" value="<?php echo htmlspecialchars($row['weight_kg'] ?? ''); ?>" min="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-fes-red focus:border-fes-red">
                                                        </div>
                                                        <div>
                                                            <label class="block text-xs font-medium text-gray-600 mb-1">Last Maintenance</label>
                                                            <input type="date" name="last_maintenance" value="<?php echo htmlspecialchars($row['last_maintenance'] ?? ''); ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-fes-red focus:border-fes-red">
                                                        </div>
                                                    </div>

                                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                                        <div>
                                                            <label class="block text-xs font-medium text-gray-600 mb-1">Display Icon</label>
                                                            <select name="icon" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-fes-red focus:border-fes-red">
                                                                <option value="">Select icon</option>
                                                                <option value="fa-tractor" <?php echo $row['icon'] === 'fa-tractor' ? 'selected' : ''; ?>>Tractor</option>
                                                                <option value="fa-wheat-awn" <?php echo $row['icon'] === 'fa-wheat-awn' ? 'selected' : ''; ?>>Harvester</option>
                                                                <option value="fa-helmet-safety" <?php echo $row['icon'] === 'fa-helmet-safety' ? 'selected' : ''; ?>>Excavator</option>
                                                                <option value="fa-bolt" <?php echo $row['icon'] === 'fa-bolt' ? 'selected' : ''; ?>>Generator</option>
                                                                <option value="fa-faucet" <?php echo $row['icon'] === 'fa-faucet' ? 'selected' : ''; ?>>Pump</option>
                                                                <option value="fa-truck-ramp-box" <?php echo $row['icon'] === 'fa-truck-ramp-box' ? 'selected' : ''; ?>>Truck</option>
                                                                <option value="fa-spray-can" <?php echo $row['icon'] === 'fa-spray-can' ? 'selected' : ''; ?>>Sprayer</option>
                                                                <option value="fa-circle" <?php echo $row['icon'] === 'fa-circle' ? 'selected' : ''; ?>>Roller</option>
                                                                <option value="fa-person-digging" <?php echo $row['icon'] === 'fa-person-digging' ? 'selected' : ''; ?>>Loader</option>
                                                            </select>
                                                        </div>
                                                        <div>
                                                            <label class="block text-xs font-medium text-gray-600 mb-1">Image</label>
                                                            <div class="flex items-center gap-3">
                                                                <input type="file" name="image" accept="image/*" class="text-xs">
                                                                <?php if (!empty($row['image_path'])): ?>
                                                                    <span class="text-xs text-gray-500 truncate max-w-[160px]">Current: <?php echo htmlspecialchars(basename($row['image_path'])); ?></span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="flex items-center gap-2 pt-1">
                                                        <button type="submit" class="inline-flex items-center gap-2 bg-fes-red hover:bg-[#b71c1c] text-white font-medium px-4 py-2.5 rounded-lg shadow transition">
                                                            <i class="fas fa-save"></i>
                                                            Save
                                                        </button>
                                                        <button type="button"
                                                            class="row-toggle inline-flex items-center gap-2 border border-gray-300 bg-white hover:bg-gray-100 text-gray-700 font-medium px-4 py-2.5 rounded-lg transition"
                                                            data-target="edit-row-<?php echo (int)$row['id']; ?>">
                                                            <i class="fas fa-times"></i>
                                                            Cancel
                                                        </button>
                                                    </div>
                                                </form>
                                            </td>
                                        </tr>
                                        <tr id="assign-row-<?php echo (int)$row['id']; ?>" class="hidden bg-gray-50">
                                            <td colspan="5" class="py-3 px-3 border-b">
                                                <form action="include/process_assign_operator.php" method="POST" class="flex flex-col md:flex-row md:items-center gap-2">
                                                    <input type="hidden" name="equipment_id" value="<?php echo (int)$row['id']; ?>">
                                                    <label class="text-sm text-gray-600 min-w-[170px]">Assign operator to <?php echo htmlspecialchars($row['equipment_id']); ?>:</label>
                                                    <select name="operator_id" class="min-w-[260px] border border-gray-300 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-fes-red focus:border-fes-red">
                                                        <option value="">Unassigned</option>
                                                        <?php foreach ($operators as $op): ?>
                                                            <?php
                                                            $opId = (int)$op['user_id'];
                                                            $isCurrent = ((int)$row['operator_id'] === $opId);
                                                            $isBusyElsewhere = isset($busyOperators[$opId]) && !$isCurrent;
                                                            $busyLabel = $isBusyElsewhere
                                                                ? ' (Busy: ' . $busyOperators[$opId]['equipment_id'] . ')'
                                                                : '';
                                                            ?>
                                                            <option value="<?php echo $opId; ?>"
                                                                <?php echo $isCurrent ? 'selected' : ''; ?>
                                                                <?php echo $isBusyElsewhere ? 'disabled' : ''; ?>>
                                                                <?php echo htmlspecialchars($op['name'] . $busyLabel); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <div class="flex items-center gap-2">
                                                        <button type="submit" class="inline-flex items-center gap-2 bg-fes-red hover:bg-[#b71c1c] text-white font-medium px-4 py-2.5 rounded-lg shadow transition">
                                                            <i class="fas fa-link"></i>
                                                            Save
                                                        </button>
                                                        <button type="button"
                                                            class="row-toggle inline-flex items-center gap-2 border border-gray-300 bg-white hover:bg-gray-100 text-gray-700 font-medium px-4 py-2.5 rounded-lg transition"
                                                            data-target="assign-row-<?php echo (int)$row['id']; ?>">
                                                            <i class="fas fa-times"></i>
                                                            Cancel
                                                        </button>
                                                    </div>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <script>
        (function () {
            var btn = document.getElementById('fes-dashboard-menu-btn');
            var sidebar = document.getElementById('fes-dashboard-sidebar');
            var overlay = document.getElementById('fes-dashboard-overlay');
            if (!btn || !sidebar || !overlay) return;

            function openSidebar() {
                sidebar.classList.remove('-translate-x-full');
                sidebar.classList.add('translate-x-0');
                sidebar.classList.add('show');
                overlay.classList.remove('hidden');
                btn.setAttribute('aria-expanded', 'true');
            }

            function closeSidebar() {
                sidebar.classList.add('-translate-x-full');
                sidebar.classList.remove('translate-x-0');
                sidebar.classList.remove('show');
                overlay.classList.add('hidden');
                btn.setAttribute('aria-expanded', 'false');
            }

            btn.addEventListener('click', function () {
                var isOpen = sidebar.classList.contains('translate-x-0') || sidebar.classList.contains('show');
                if (isOpen) closeSidebar();
                else openSidebar();
            });

            overlay.addEventListener('click', closeSidebar);

            window.addEventListener('resize', function () {
                if (window.matchMedia('(min-width: 768px)').matches) {
                    overlay.classList.add('hidden');
                    btn.setAttribute('aria-expanded', 'false');
                    sidebar.classList.remove('show');
                } else {
                    closeSidebar();
                }
            });
        })();

        (function () {
            var toggles = document.querySelectorAll('.row-toggle, button[data-target]');
            toggles.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var targetId = btn.getAttribute('data-target');
                    if (!targetId) return;
                    var row = document.getElementById(targetId);
                    if (!row) return;
                    row.classList.toggle('hidden');
                });
            });
        })();
    </script>
</body>
</html>

