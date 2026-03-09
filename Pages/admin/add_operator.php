<?php
$success = '';
$error = '';
$error_code = $_GET['error_code'] ?? '';
$existing_email = trim($_GET['email'] ?? '');

if (isset($_GET['success'])) {
    $success = urldecode($_GET['success']);
}
if (isset($_GET['error'])) {
    $error = urldecode($_GET['error']);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../auth/signin.php');
    exit();
}

require_once '../../includes/database.php';

$available_equipment = [];
$recent_operators = [];

try {
    $conn = getDBConnection();

    $eq_sql = "SELECT id, equipment_id, equipment_name FROM equipment WHERE status IN ('available','maintenance') ORDER BY equipment_name ASC";
    $eq_res = $conn->query($eq_sql);
    if ($eq_res) {
        while ($row = $eq_res->fetch_assoc()) {
            $available_equipment[] = $row;
        }
    }

    $op_sql = "
        SELECT u.user_id, u.name, u.email, u.created_at,
               COUNT(e.id) AS assigned_equipment,
               SUM(CASE WHEN e.status = 'in_use' THEN 1 ELSE 0 END) AS active_jobs
        FROM users u
        LEFT JOIN equipment e ON e.operator_id = u.user_id
        WHERE u.role = 'operator'
        GROUP BY u.user_id
        ORDER BY u.created_at DESC
        LIMIT 8
    ";
    $op_res = $conn->query($op_sql);
    if ($op_res) {
        while ($row = $op_res->fetch_assoc()) {
            $recent_operators[] = $row;
        }
    }

    $conn->close();
} catch (Exception $e) {
    error_log('Add operator page load error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Operator - FES</title>
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
            .grid-2 {
                grid-template-columns: 1fr !important;
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
                        <h1 class="text-xl font-semibold text-gray-900">Register New Operator</h1>
                    </div>
                </div>
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

                <?php if ($error_code === 'duplicate_email'): ?>
                    <div class="mb-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        <div class="flex items-start gap-3">
                            <i class="fas fa-triangle-exclamation text-amber-600 mt-0.5"></i>
                            <div>
                                <p class="font-semibold">Operator account could not be created.</p>
                                <p class="mt-1 text-amber-800">
                                    The email
                                    <span class="font-semibold"><?php echo htmlspecialchars($existing_email); ?></span>
                                    is already registered to an existing user account.
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <section class="bg-white rounded-xl shadow-card border border-gray-200 mb-6">
                    <div class="px-6 py-5 border-b border-gray-200">
                        <h2 class="text-2xl font-semibold text-gray-900">Operator Information</h2>
                    </div>

                    <div class="p-6">
                        <form id="add-operator-form" action="include/process_add_operator.php?v=<?php echo time(); ?>" method="post" class="space-y-6">
                            <div class="grid grid-cols-2 gap-6 grid-2">
                                <div>
                                    <label class="block text-base font-medium text-gray-700 mb-2">Full Name</label>
                                    <input type="text" name="full_name" class="w-full border border-gray-300 rounded-lg px-4 py-3 text-base text-gray-900 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-fes-red focus:border-fes-red" required>
                                </div>

                                <div>
                                    <label class="block text-base font-medium text-gray-700 mb-2">Email</label>
                                    <input type="email" name="email" class="w-full border border-gray-300 rounded-lg px-4 py-3 text-base text-gray-900 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-fes-red focus:border-fes-red" required>
                                </div>

                                <div>
                                    <label class="block text-base font-medium text-gray-700 mb-2">Password</label>
                                    <input type="password" name="password" class="w-full border border-gray-300 rounded-lg px-4 py-3 text-base text-gray-900 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-fes-red focus:border-fes-red" placeholder="At least 8 characters" required>
                                </div>
                            </div>

                            <div>
                                <label class="block text-base font-medium text-gray-700 mb-2">Assign Equipment (Optional)</label>
                                <select name="assign_equipment_id" class="w-full border border-gray-300 rounded-lg px-4 py-3 text-base text-gray-900 focus:outline-none focus:ring-2 focus:ring-fes-red focus:border-fes-red">
                                    <option value="">-- Select equipment --</option>
                                    <?php foreach ($available_equipment as $eq): ?>
                                        <option value="<?php echo (int)$eq['id']; ?>">
                                            <?php echo htmlspecialchars($eq['equipment_id'] . ' - ' . $eq['equipment_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="flex items-center justify-between pt-2">
                                <a href="users.php" class="inline-flex items-center gap-2 border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 font-medium px-5 py-3 rounded-lg transition">
                                    <i class="fas fa-users"></i>
                                    Manage All Operators
                                </a>
                                <button id="create-operator-btn" type="submit" class="inline-flex items-center justify-center gap-3 bg-fes-red hover:bg-[#b71c1c] text-white font-medium px-6 py-3 rounded-lg shadow transition text-base">
                                    <i class="fas fa-user-plus"></i>
                                    <span>Register Operator</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </section>

                <section class="bg-white rounded-xl shadow-card border border-gray-200">
                    <div class="px-6 py-5 border-b border-gray-200 flex items-center justify-between">
                        <h2 class="text-2xl font-semibold text-gray-900">Recent Operators</h2>
                        <a href="users.php" class="inline-flex items-center gap-2 border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 font-medium px-4 py-2.5 rounded-lg transition">
                            <i class="fas fa-users"></i>
                            View All
                        </a>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead>
                                <tr class="text-left text-xs font-medium text-gray-500 border-b uppercase tracking-wider">
                                    <th class="py-3 px-6">Name</th>
                                    <th class="py-3 pr-4">Email</th>
                                    <th class="py-3 pr-4">Assigned Equipment</th>
                                    <th class="py-3 pr-4">Status</th>
                                    <th class="py-3 pr-6">Created</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm text-gray-900">
                                <?php if (empty($recent_operators)): ?>
                                    <tr>
                                        <td colspan="5" class="py-8 text-center text-gray-500">No operators yet.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recent_operators as $op): ?>
                                        <?php $active = (int)($op['active_jobs'] ?? 0); ?>
                                        <tr class="border-b hover:bg-gray-50">
                                            <td class="py-3 px-6 font-medium"><?php echo htmlspecialchars($op['name']); ?></td>
                                            <td class="py-3 pr-4 text-gray-600"><?php echo htmlspecialchars($op['email']); ?></td>
                                            <td class="py-3 pr-4"><?php echo (int)$op['assigned_equipment']; ?></td>
                                            <td class="py-3 pr-4">
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium <?php echo $active > 0 ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700'; ?>">
                                                    <?php echo $active > 0 ? 'Busy' : 'Available'; ?>
                                                </span>
                                            </td>
                                            <td class="py-3 pr-6 text-gray-500"><?php echo htmlspecialchars(date('M d, Y', strtotime($op['created_at']))); ?></td>
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
                overlay.classList.remove('hidden');
                btn.setAttribute('aria-expanded', 'true');
            }

            function closeSidebar() {
                sidebar.classList.add('-translate-x-full');
                sidebar.classList.remove('translate-x-0');
                overlay.classList.add('hidden');
                btn.setAttribute('aria-expanded', 'false');
            }

            btn.addEventListener('click', function () {
                var isOpen = sidebar.classList.contains('translate-x-0');
                if (isOpen) closeSidebar();
                else openSidebar();
            });

            overlay.addEventListener('click', closeSidebar);

            window.addEventListener('resize', function () {
                if (window.matchMedia('(min-width: 768px)').matches) {
                    overlay.classList.add('hidden');
                    btn.setAttribute('aria-expanded', 'false');
                } else {
                    closeSidebar();
                }
            });
        })();

        (function () {
            var form = document.getElementById('add-operator-form');
            var submitBtn = document.getElementById('create-operator-btn');
            if (!form || !submitBtn) return;

            form.addEventListener('submit', function () {
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-70', 'cursor-not-allowed');
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Registering...</span>';
            });
        })();
    </script>
</body>
</html>
