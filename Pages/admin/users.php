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

$q = trim($_GET['q'] ?? '');
$operators = [];
$stats = [
    'total' => 0,
    'created_today' => 0,
];
$error = '';

try {
    $conn = getDBConnection();

    $stats_sql = "
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) AS created_today
        FROM users
        WHERE role = 'operator'
    ";
    $stats_result = $conn->query($stats_sql);
    if ($stats_result && $stats_row = $stats_result->fetch_assoc()) {
        $stats['total'] = (int)($stats_row['total'] ?? 0);
        $stats['created_today'] = (int)($stats_row['created_today'] ?? 0);
    }

    $base_sql = "
        SELECT
            u.user_id,
            u.name,
            u.email,
            u.created_at,
            COUNT(e.id) AS assigned_equipment,
            SUM(CASE WHEN e.status = 'in_use' THEN 1 ELSE 0 END) AS active_jobs
        FROM users u
        LEFT JOIN equipment e ON e.operator_id = u.user_id
        WHERE u.role = 'operator'
    ";

    if ($q !== '') {
        $sql = $base_sql . " AND (u.name LIKE ? OR u.email LIKE ?) GROUP BY u.user_id ORDER BY u.created_at DESC, u.user_id DESC";
        $stmt = $conn->prepare($sql);
        $like = '%' . $q . '%';
        $stmt->bind_param('ss', $like, $like);
    } else {
        $sql = $base_sql . " GROUP BY u.user_id ORDER BY u.created_at DESC, u.user_id DESC";
        $stmt = $conn->prepare($sql);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $operators[] = $row;
    }
    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    error_log('Operators page error: ' . $e->getMessage());
    $error = 'Could not load operators right now. Please try again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Operators - FES Admin</title>
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
                        <h1 class="text-xl font-semibold text-gray-900">Operators</h1>
                    </div>
                </div>

                <a href="add_operator.php" class="inline-flex items-center gap-2 bg-fes-red hover:bg-[#b71c1c] text-white font-medium px-4 py-2 rounded-lg shadow transition">
                    <i class="fas fa-user-plus"></i>
                    Add Operator
                </a>
            </header>

            <main class="flex-1 overflow-y-auto p-6" style="width: 100%; overflow-x: hidden;">
                <?php if ($error !== ''): ?>
                    <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 flex items-center gap-3">
                        <i class="fas fa-exclamation-circle text-red-600"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 mb-6">
                    <div class="bg-white rounded-xl shadow-card p-5 flex items-start justify-between">
                        <div>
                            <div class="text-sm text-gray-500">Total Operators</div>
                            <div class="mt-1 text-2xl font-semibold text-gray-900"><?php echo (int)$stats['total']; ?></div>
                        </div>
                        <div class="h-11 w-11 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                            <i class="fas fa-hard-hat"></i>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-card p-5 flex items-start justify-between">
                        <div>
                            <div class="text-sm text-gray-500">Created Today</div>
                            <div class="mt-1 text-2xl font-semibold text-gray-900"><?php echo (int)$stats['created_today']; ?></div>
                        </div>
                        <div class="h-11 w-11 rounded-xl bg-fes-red/10 text-fes-red flex items-center justify-center">
                            <i class="fas fa-user-plus"></i>
                        </div>
                    </div>
                </div>

                <section class="bg-white rounded-xl shadow-card p-5">
                    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-5">
                        <div class="md:col-span-3">
                            <label for="q" class="block text-sm text-gray-600 mb-1">Search Operators</label>
                            <input id="q" name="q" type="text" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search by name or email" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-fes-red focus:border-fes-red">
                        </div>
                        <div class="flex items-end gap-2">
                            <button type="submit" class="flex-1 inline-flex items-center justify-center gap-2 bg-fes-red hover:bg-[#b71c1c] text-white font-medium px-4 py-2.5 rounded-lg shadow transition">
                                <i class="fas fa-search"></i>
                                Search
                            </button>
                            <a href="users.php" class="inline-flex items-center justify-center px-3 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition" title="Reset">
                                <i class="fas fa-rotate-left"></i>
                            </a>
                        </div>
                    </form>

                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead>
                                <tr class="text-left text-xs font-medium text-gray-500 border-b uppercase tracking-wider">
                                    <th class="py-3 pr-4">Operator</th>
                                    <th class="py-3 pr-4">Assigned Equipment</th>
                                    <th class="py-3 pr-4">Active Jobs</th>
                                    <th class="py-3 pr-4">Created</th>
                                    <th class="py-3 pr-4">Created By</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm text-gray-900">
                                <?php if (empty($operators)): ?>
                                    <tr>
                                        <td colspan="5" class="py-10 text-center text-gray-500">
                                            <i class="fas fa-hard-hat text-2xl mb-2 block text-gray-300"></i>
                                            No operators found.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($operators as $op): ?>
                                        <tr class="border-b hover:bg-gray-50">
                                            <td class="py-3 pr-4">
                                                <div class="font-medium"><?php echo htmlspecialchars($op['name']); ?></div>
                                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($op['email']); ?></div>
                                            </td>
                                            <td class="py-3 pr-4">
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-700">
                                                    <?php echo (int)$op['assigned_equipment']; ?> assigned
                                                </span>
                                            </td>
                                            <td class="py-3 pr-4">
                                                <?php $active = (int)($op['active_jobs'] ?? 0); ?>
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium <?php echo $active > 0 ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700'; ?>">
                                                    <?php echo $active; ?> active
                                                </span>
                                            </td>
                                            <td class="py-3 pr-4 text-gray-600">
                                                <?php echo !empty($op['created_at']) ? htmlspecialchars(date('M d, Y', strtotime($op['created_at']))) : '-'; ?>
                                            </td>
                                            <td class="py-3 pr-4 text-gray-600">
                                                <?php echo !empty($op['creator_name']) ? htmlspecialchars($op['creator_name']) : 'System'; ?>
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
    </script>
</body>
</html>
