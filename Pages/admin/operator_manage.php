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

$operator_id = (int)($_GET['id'] ?? 0);
$error = '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['success']);

$operator = null;
$skills = [];
$availability = [];
$assigned_equipment = [];

if ($operator_id <= 0) {
    header('Location: users.php');
    exit();
}

try {
    $conn = getDBConnection();

    // Load operator
    $stmt = $conn->prepare('SELECT user_id, name, email, created_at FROM users WHERE user_id = ? AND role = \'operator\'');
    if ($stmt) {
        $stmt->bind_param('i', $operator_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $operator = $res->fetch_assoc();
        $stmt->close();
    }

    if (!$operator) {
        $error = 'Operator not found.';
    } else {
        // Load skills
        $skillSql = 'SELECT id, skill_name, skill_level, created_at FROM operator_skills WHERE operator_id = ? ORDER BY skill_name ASC';
        $stmt = $conn->prepare($skillSql);
        if ($stmt) {
            $stmt->bind_param('i', $operator_id);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $skills[] = $row;
            }
            $stmt->close();
        }

        // Load availability
        $availSql = 'SELECT id, day_of_week, start_time, end_time, is_available, note FROM operator_availability WHERE operator_id = ? ORDER BY day_of_week ASC, start_time ASC';
        $stmt = $conn->prepare($availSql);
        if ($stmt) {
            $stmt->bind_param('i', $operator_id);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $availability[] = $row;
            }
            $stmt->close();
        }

        // Load assigned equipment / workload summary
        $eqSql = '
            SELECT
                e.id,
                e.equipment_id,
                e.equipment_name,
                e.category,
                e.status,
                e.location
            FROM equipment e
            WHERE e.operator_id = ?
            ORDER BY e.status DESC, e.equipment_name ASC
        ';
        $stmt = $conn->prepare($eqSql);
        if ($stmt) {
            $stmt->bind_param('i', $operator_id);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $assigned_equipment[] = $row;
            }
            $stmt->close();
        }
    }

    $conn->close();
} catch (Exception $e) {
    error_log('Operator manage page error: ' . $e->getMessage());
    $error = 'Could not load operator details right now.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Operator Management - FES Admin</title>
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
                        <div class="text-sm text-gray-500">Manage Operators</div>
                        <h1 class="text-xl font-semibold text-gray-900">
                            <?php echo $operator ? htmlspecialchars($operator['name']) : 'Operator'; ?>
                        </h1>
                        <?php if ($operator): ?>
                            <p class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($operator['email']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <a href="users.php" class="inline-flex items-center gap-2 border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 font-medium px-4 py-2 rounded-lg transition">
                    <i class="fas fa-arrow-left"></i>
                    Back to Operators
                </a>
            </header>

            <main class="flex-1 overflow-y-auto p-6" style="width: 100%; overflow-x: hidden;">
                <?php if ($error !== ''): ?>
                    <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 flex items-center gap-3">
                        <i class="fas fa-exclamation-circle text-red-600"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success !== ''): ?>
                    <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 flex items-center gap-3">
                        <i class="fas fa-check-circle text-emerald-600"></i>
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <?php if ($operator): ?>
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                        <section class="bg-white rounded-xl shadow-card p-5 lg:col-span-2">
                            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                                <i class="fas fa-briefcase text-fes-red"></i>
                                Workload
                            </h2>
                            <div class="flex flex-wrap gap-4 mb-4">
                                <div class="px-4 py-3 rounded-lg bg-gray-50 border border-gray-200">
                                    <div class="text-xs text-gray-500 uppercase">Assigned Equipment</div>
                                    <div class="mt-1 text-xl font-semibold text-gray-900"><?php echo count($assigned_equipment); ?></div>
                                </div>
                                <?php
                                $activeCount = 0;
                                foreach ($assigned_equipment as $eq) {
                                    if ($eq['status'] === 'in_use') {
                                        $activeCount++;
                                    }
                                }
                                ?>
                                <div class="px-4 py-3 rounded-lg bg-gray-50 border border-gray-200">
                                    <div class="text-xs text-gray-500 uppercase">Active Jobs</div>
                                    <div class="mt-1 text-xl font-semibold text-gray-900"><?php echo $activeCount; ?></div>
                                </div>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="min-w-full">
                                    <thead>
                                        <tr class="text-left text-xs font-medium text-gray-500 border-b uppercase tracking-wider">
                                            <th class="py-3 pr-4">Equipment</th>
                                            <th class="py-3 pr-4">Category</th>
                                            <th class="py-3 pr-4">Status</th>
                                            <th class="py-3 pr-4">Location</th>
                                        </tr>
                                    </thead>
                                    <tbody class="text-sm text-gray-900">
                                        <?php if (empty($assigned_equipment)): ?>
                                            <tr>
                                                <td colspan="4" class="py-8 text-center text-gray-500">
                                                    <i class="fas fa-truck-moving text-2xl mb-2 block text-gray-300"></i>
                                                    No equipment currently assigned.
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($assigned_equipment as $eq): ?>
                                                <tr class="border-b hover:bg-gray-50">
                                                    <td class="py-3 pr-4">
                                                        <div class="font-medium"><?php echo htmlspecialchars($eq['equipment_id']); ?></div>
                                                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars($eq['equipment_name']); ?></div>
                                                    </td>
                                                    <td class="py-3 pr-4"><?php echo htmlspecialchars(ucfirst($eq['category'])); ?></td>
                                                    <td class="py-3 pr-4">
                                                        <?php
                                                        $status = $eq['status'];
                                                        $cls = 'bg-gray-100 text-gray-700';
                                                        if ($status === 'available') $cls = 'bg-emerald-50 text-emerald-700';
                                                        if ($status === 'in_use') $cls = 'bg-amber-50 text-amber-700';
                                                        if ($status === 'maintenance') $cls = 'bg-red-50 text-red-700';
                                                        ?>
                                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium <?php echo $cls; ?>">
                                                            <?php echo htmlspecialchars(str_replace('_', ' ', ucfirst($status))); ?>
                                                        </span>
                                                    </td>
                                                    <td class="py-3 pr-4 text-gray-600"><?php echo htmlspecialchars($eq['location']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <section class="bg-white rounded-xl shadow-card p-5">
                            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                                <i class="fas fa-user-cog text-fes-red"></i>
                                Quick Info
                            </h2>
                            <ul class="space-y-3 text-sm text-gray-700">
                                <li class="flex items-center justify-between">
                                    <span class="text-gray-500">Operator ID</span>
                                    <span class="font-medium">#<?php echo (int)$operator['user_id']; ?></span>
                                </li>
                                <li class="flex items-center justify-between">
                                    <span class="text-gray-500">Created</span>
                                    <span class="font-medium">
                                        <?php echo !empty($operator['created_at']) ? htmlspecialchars(date('M d, Y', strtotime($operator['created_at']))) : '-'; ?>
                                    </span>
                                </li>
                                <li class="flex items-center justify-between">
                                    <span class="text-gray-500">Assigned Equipment</span>
                                    <span class="font-medium"><?php echo count($assigned_equipment); ?></span>
                                </li>
                                <li class="flex items-center justify-between">
                                    <span class="text-gray-500">Active Jobs</span>
                                    <span class="font-medium"><?php echo $activeCount; ?></span>
                                </li>
                            </ul>
                        </section>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <section class="bg-white rounded-xl shadow-card p-5">
                            <div class="flex items-center justify-between mb-4">
                                <h2 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                                    <i class="fas fa-tools text-fes-red"></i>
                                    Skills
                                </h2>
                            </div>

                            <form action="include/process_add_operator_skill.php" method="POST" class="mb-4 flex flex-col md:flex-row gap-3 items-stretch md:items-end">
                                <input type="hidden" name="operator_id" value="<?php echo (int)$operator['user_id']; ?>">
                                <div class="flex-1">
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Skill</label>
                                    <input type="text" name="skill_name" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-fes-red focus:border-fes-red" placeholder="e.g., Tractor operation" required>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Level</label>
                                    <select name="skill_level" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-fes-red focus:border-fes-red">
                                        <option value="beginner">Beginner</option>
                                        <option value="intermediate" selected>Intermediate</option>
                                        <option value="advanced">Advanced</option>
                                        <option value="expert">Expert</option>
                                    </select>
                                </div>
                                <div>
                                    <button type="submit" class="inline-flex items-center gap-2 bg-fes-red hover:bg-[#b71c1c] text-white font-medium px-4 py-2.5 rounded-lg shadow transition text-sm">
                                        <i class="fas fa-plus"></i>
                                        Add
                                    </button>
                                </div>
                            </form>

                            <div class="overflow-x-auto">
                                <table class="min-w-full">
                                    <thead>
                                        <tr class="text-left text-xs font-medium text-gray-500 border-b uppercase tracking-wider">
                                            <th class="py-3 pr-4">Skill</th>
                                            <th class="py-3 pr-4">Level</th>
                                            <th class="py-3 pr-4">Added</th>
                                            <th class="py-3 pr-4">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="text-sm text-gray-900">
                                        <?php if (empty($skills)): ?>
                                            <tr>
                                                <td colspan="4" class="py-6 text-center text-gray-500">
                                                    No skills recorded yet.
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($skills as $skill): ?>
                                                <tr class="border-b hover:bg-gray-50">
                                                    <td class="py-3 pr-4 font-medium"><?php echo htmlspecialchars($skill['skill_name']); ?></td>
                                                    <td class="py-3 pr-4 text-gray-700">
                                                        <?php echo htmlspecialchars(ucfirst($skill['skill_level'])); ?>
                                                    </td>
                                                    <td class="py-3 pr-4 text-gray-600">
                                                        <?php echo !empty($skill['created_at']) ? htmlspecialchars(date('M d, Y', strtotime($skill['created_at']))) : '-'; ?>
                                                    </td>
                                                    <td class="py-3 pr-4">
                                                        <form action="include/process_delete_operator_skill.php" method="POST" onsubmit="return confirm('Remove this skill?');">
                                                            <input type="hidden" name="operator_id" value="<?php echo (int)$operator['user_id']; ?>">
                                                            <input type="hidden" name="skill_id" value="<?php echo (int)$skill['id']; ?>">
                                                            <button type="submit" class="text-red-500 hover:text-red-700 text-sm">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <section class="bg-white rounded-xl shadow-card p-5">
                            <div class="flex items-center justify-between mb-4">
                                <h2 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                                    <i class="fas fa-calendar-check text-fes-red"></i>
                                    Availability
                                </h2>
                            </div>

                            <form action="include/process_add_operator_availability.php" method="POST" class="mb-4 grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                                <input type="hidden" name="operator_id" value="<?php echo (int)$operator['user_id']; ?>">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Day</label>
                                    <select name="day_of_week" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-fes-red focus:border-fes-red">
                                        <option value="1">Monday</option>
                                        <option value="2">Tuesday</option>
                                        <option value="3">Wednesday</option>
                                        <option value="4">Thursday</option>
                                        <option value="5">Friday</option>
                                        <option value="6">Saturday</option>
                                        <option value="0">Sunday</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Start</label>
                                    <input type="time" name="start_time" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-fes-red focus:border-fes-red" required>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">End</label>
                                    <input type="time" name="end_time" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-fes-red focus:border-fes-red" required>
                                </div>
                                <div class="flex items-end justify-between gap-2">
                                    <label class="inline-flex items-center text-xs text-gray-600">
                                        <input type="checkbox" name="is_available" value="1" class="mr-2" checked>
                                        Available
                                    </label>
                                    <button type="submit" class="inline-flex items-center gap-2 bg-fes-red hover:bg-[#b71c1c] text-white font-medium px-4 py-2.5 rounded-lg shadow transition text-sm">
                                        <i class="fas fa-plus"></i>
                                        Add
                                    </button>
                                </div>
                            </form>

                            <div class="overflow-x-auto">
                                <table class="min-w-full">
                                    <thead>
                                        <tr class="text-left text-xs font-medium text-gray-500 border-b uppercase tracking-wider">
                                            <th class="py-3 pr-4">Day</th>
                                            <th class="py-3 pr-4">Time</th>
                                            <th class="py-3 pr-4">Status</th>
                                            <th class="py-3 pr-4">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="text-sm text-gray-900">
                                        <?php
                                        $days = [
                                            0 => 'Sunday',
                                            1 => 'Monday',
                                            2 => 'Tuesday',
                                            3 => 'Wednesday',
                                            4 => 'Thursday',
                                            5 => 'Friday',
                                            6 => 'Saturday',
                                        ];
                                        ?>
                                        <?php if (empty($availability)): ?>
                                            <tr>
                                                <td colspan="4" class="py-6 text-center text-gray-500">
                                                    No availability set. Add working slots for this operator.
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($availability as $slot): ?>
                                                <tr class="border-b hover:bg-gray-50">
                                                    <td class="py-3 pr-4">
                                                        <?php
                                                        $d = (int)$slot['day_of_week'];
                                                        echo htmlspecialchars($days[$d] ?? 'Day ' . $d);
                                                        ?>
                                                    </td>
                                                    <td class="py-3 pr-4 text-gray-700">
                                                        <?php echo htmlspecialchars(substr($slot['start_time'], 0, 5)); ?>
                                                        –
                                                        <?php echo htmlspecialchars(substr($slot['end_time'], 0, 5)); ?>
                                                    </td>
                                                    <td class="py-3 pr-4">
                                                        <?php if ((int)$slot['is_available'] === 1): ?>
                                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700">
                                                                Available
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                                                Unavailable
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="py-3 pr-4">
                                                        <form action="include/process_delete_operator_availability.php" method="POST" onsubmit="return confirm('Remove this availability slot?');">
                                                            <input type="hidden" name="operator_id" value="<?php echo (int)$operator['user_id']; ?>">
                                                            <input type="hidden" name="slot_id" value="<?php echo (int)$slot['id']; ?>">
                                                            <button type="submit" class="text-red-500 hover:text-red-700 text-sm">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </div>
                <?php endif; ?>
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
    </script>
</body>
</html>

