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
require_once '../../includes/fes_skill_types.php';

$operator_id = (int)($_GET['id'] ?? 0);
$skillTypeOptions = fes_operator_skill_types();
$error = '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['success']);

$operator = null;
$skills = [];
$assigned_equipment = [];
$activeCount = 0; // Active bookings count (pending/confirmed/in_progress) for this operator.
$feedbackAvg = null;
$feedbackCount = 0;

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

        // Load assigned equipment / workload summary
        // Workload is driven by bookings/operator assignment (not equipment.operator_id),
        // because operator-to-equipment assignment happens via bookings.
        $activeJobsSql = "
            SELECT COUNT(*) AS cnt
            FROM bookings
            WHERE operator_id = ?
              AND status IN ('pending','confirmed','in_progress')
        ";
        if ($stmt = $conn->prepare($activeJobsSql)) {
            $stmt->bind_param('i', $operator_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $activeCount = (int)($row['cnt'] ?? 0);
            $stmt->close();
        }

        $eqSql = "
            SELECT DISTINCT
                e.id,
                e.equipment_id,
                e.equipment_name,
                e.category,
                e.status,
                e.location
            FROM equipment e
            INNER JOIN bookings b ON b.equipment_id = e.equipment_id
            WHERE b.operator_id = ?
              AND b.status IN ('pending','confirmed','in_progress')
            ORDER BY e.status DESC, e.equipment_name ASC
        ";
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

        try {
            $fbStmt = $conn->prepare('SELECT AVG(rating) AS av, COUNT(*) AS c FROM booking_feedback WHERE operator_id = ?');
            if ($fbStmt) {
                $fbStmt->bind_param('i', $operator_id);
                $fbStmt->execute();
                $fbRes = $fbStmt->get_result();
                if ($fbRes && ($fbRow = $fbRes->fetch_assoc())) {
                    $feedbackCount = (int)($fbRow['c'] ?? 0);
                    if ($feedbackCount > 0 && $fbRow['av'] !== null) {
                        $feedbackAvg = round((float)$fbRow['av'], 2);
                    }
                }
                $fbStmt->close();
            }
        } catch (Throwable $fbe) {
            error_log('operator_manage booking_feedback: ' . $fbe->getMessage());
        }
    }

    $conn->close();
} catch (Exception $e) {
    error_log('Operator manage page error: ' . $e->getMessage());
    $error = 'Could not load operator details right now.';
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
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
                            <p class="text-sm text-gray-600 mt-2 max-w-xl">Update skills and see which jobs and machines this operator is tied to right now.</p>
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
                    <?php
                    $skillsCount = count($skills);
                    $eqOnJobsCount = count($assigned_equipment);
                    ?>

                    <nav class="mb-6 flex flex-wrap gap-2 rounded-xl border border-gray-200 bg-white p-2 shadow-sm" aria-label="On this page">
                        <a href="#snapshot" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900">Overview</a>
                        <a href="#workload" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900">Equipment on jobs</a>
                        <a href="#skills" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900">Skills</a>
                    </nav>

                    <section id="snapshot" class="scroll-mt-24 mb-6 rounded-xl border border-gray-100 bg-white p-5 shadow-card">
                        <h2 class="display text-lg font-bold text-gray-900">At a glance</h2>
                        <p class="mt-1 text-sm text-gray-500">Numbers update from live bookings and what you have configured below.</p>
                        <div class="mt-4 grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                            <div class="rounded-lg border border-gray-100 bg-gray-50/80 px-4 py-3">
                                <div class="text-xs font-medium text-gray-500">Active job bookings</div>
                                <div class="mt-1 text-2xl font-semibold text-gray-900"><?php echo (int)$activeCount; ?></div>
                                <div class="mt-0.5 text-[11px] text-gray-400">Pending, confirmed, or in progress</div>
                            </div>
                            <div class="rounded-lg border border-gray-100 bg-gray-50/80 px-4 py-3">
                                <div class="text-xs font-medium text-gray-500">Machines on those jobs</div>
                                <div class="mt-1 text-2xl font-semibold text-gray-900"><?php echo (int)$eqOnJobsCount; ?></div>
                                <div class="mt-0.5 text-[11px] text-gray-400">Distinct equipment from bookings</div>
                            </div>
                            <div class="rounded-lg border border-gray-100 bg-gray-50/80 px-4 py-3">
                                <div class="text-xs font-medium text-gray-500">Skills listed</div>
                                <div class="mt-1 text-2xl font-semibold text-gray-900"><?php echo (int)$skillsCount; ?></div>
                            </div>
                            <div class="rounded-lg border border-amber-100 bg-amber-50/50 px-4 py-3">
                                <div class="text-xs font-medium text-gray-500">Customer rating</div>
                                <?php if ($feedbackAvg !== null && $feedbackCount > 0): ?>
                                    <div class="mt-1 text-2xl font-semibold text-amber-700"><?php echo htmlspecialchars((string)$feedbackAvg); ?><span class="text-base font-normal text-gray-500">/5</span></div>
                                    <div class="mt-0.5 text-xs text-gray-600"><?php echo (int)$feedbackCount; ?> review<?php echo $feedbackCount === 1 ? '' : 's'; ?> · <a href="feedback.php" class="text-fes-red font-medium hover:underline">View all feedback</a></div>
                                <?php else: ?>
                                    <div class="mt-1 text-sm text-gray-500">No reviews yet</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </section>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                        <section id="workload" class="scroll-mt-24 bg-white rounded-xl shadow-card p-5 lg:col-span-2 border border-gray-100">
                            <h2 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-fes-red/10 text-fes-red text-sm font-bold">1</span>
                                Equipment on active jobs
                            </h2>
                            <p class="mt-2 text-sm text-gray-600 leading-relaxed">
                                This list comes from <strong>bookings</strong> that are not finished yet. It shows which machines appear on those jobs (not the same as permanently assigning equipment to the operator in the fleet table).
                            </p>
                            <div class="overflow-x-auto mt-4 rounded-lg border border-gray-100">
                                <table class="min-w-full">
                                    <caption class="sr-only">Equipment referenced by this operator active bookings</caption>
                                    <thead>
                                        <tr class="text-left text-xs font-medium text-gray-500 bg-gray-50 border-b border-gray-100 uppercase tracking-wider">
                                            <th class="py-3 px-4">Equipment</th>
                                            <th class="py-3 pr-4">Category</th>
                                            <th class="py-3 pr-4">Fleet status</th>
                                            <th class="py-3 pr-4">Location</th>
                                        </tr>
                                    </thead>
                                    <tbody class="text-sm text-gray-900">
                                        <?php if (empty($assigned_equipment)): ?>
                                            <tr>
                                                <td colspan="4" class="py-10 px-4 text-center text-gray-500">
                                                    <i class="fas fa-truck-moving text-2xl mb-2 block text-gray-300"></i>
                                                    None right now — this operator has no pending, confirmed, or in-progress bookings tied to equipment.
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($assigned_equipment as $eq): ?>
                                                <tr class="border-b border-gray-50 last:border-0 hover:bg-gray-50/80">
                                                    <td class="py-3 px-4">
                                                        <div class="font-medium text-gray-900"><?php echo htmlspecialchars($eq['equipment_id']); ?></div>
                                                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars($eq['equipment_name']); ?></div>
                                                    </td>
                                                    <td class="py-3 pr-4"><?php echo htmlspecialchars(ucfirst($eq['category'] ?? '')); ?></td>
                                                    <td class="py-3 pr-4">
                                                        <?php
                                                        $status = $eq['status'] ?? '';
                                                        $cls = 'bg-gray-100 text-gray-700';
                                                        if ($status === 'available') {
                                                            $cls = 'bg-emerald-50 text-emerald-700';
                                                        }
                                                        if ($status === 'in_use') {
                                                            $cls = 'bg-amber-50 text-amber-700';
                                                        }
                                                        if ($status === 'maintenance') {
                                                            $cls = 'bg-red-50 text-red-700';
                                                        }
                                                        ?>
                                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium <?php echo $cls; ?>">
                                                            <?php echo htmlspecialchars(str_replace('_', ' ', ucfirst($status))); ?>
                                                        </span>
                                                    </td>
                                                    <td class="py-3 pr-4 text-gray-600"><?php echo htmlspecialchars($eq['location'] ?? '—'); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <section class="bg-white rounded-xl shadow-card p-5 border border-gray-100 flex flex-col">
                            <h2 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                                <i class="fas fa-id-card text-fes-red"></i>
                                Account
                            </h2>
                            <p class="mt-2 text-sm text-gray-500">Read-only details from the user record.</p>
                            <dl class="mt-4 space-y-4 text-sm flex-1">
                                <div class="border-b border-gray-100 pb-3">
                                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Operator ID</dt>
                                    <dd class="mt-1 font-semibold text-gray-900">#<?php echo (int)$operator['user_id']; ?></dd>
                                </div>
                                <div class="border-b border-gray-100 pb-3">
                                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Email</dt>
                                    <dd class="mt-1 text-gray-800 break-all"><?php echo htmlspecialchars($operator['email']); ?></dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Member since</dt>
                                    <dd class="mt-1 font-medium text-gray-900">
                                        <?php echo !empty($operator['created_at']) ? htmlspecialchars(date('M d, Y', strtotime($operator['created_at']))) : '—'; ?>
                                    </dd>
                                </div>
                            </dl>
                            <a href="users.php" class="mt-6 inline-flex items-center justify-center gap-2 rounded-lg border border-gray-200 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Back to operator list</a>
                        </section>
                    </div>

                    <section id="skills" class="scroll-mt-24 bg-white rounded-xl shadow-card p-5 sm:p-6 border border-gray-100">
                            <h2 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-fes-red/10 text-fes-red text-sm font-bold">2</span>
                                Skills
                            </h2>
                            <p class="mt-2 text-sm text-gray-600 max-w-3xl">Skill types match the services customers can book (land prep, planting, harvesting, and so on). Operators see these on their dashboard; assignment checks skills against the booking.</p>

                            <div class="mt-5 rounded-xl border border-gray-200 bg-gray-50/90 p-4 sm:p-5">
                                <h3 class="text-sm font-semibold text-gray-900">Add a skill</h3>
                                <p class="text-xs text-gray-500 mt-1 mb-4">Pick a skill type and proficiency, then save.</p>
                                <form action="include/process_add_operator_skill.php" method="POST" class="flex flex-col gap-4 lg:flex-row lg:flex-wrap lg:items-end">
                                    <input type="hidden" name="operator_id" value="<?php echo (int)$operator['user_id']; ?>">
                                    <div class="flex-1 min-w-[200px]">
                                        <label for="skill_name_input" class="block text-xs font-medium text-gray-600 mb-1">Skill type</label>
                                        <select id="skill_name_input" name="skill_name" required class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-fes-red focus:border-fes-red">
                                            <option value="" selected disabled>Select a skill type</option>
                                            <?php foreach ($skillTypeOptions as $val => $label): ?>
                                                <option value="<?php echo htmlspecialchars($val); ?>"><?php echo htmlspecialchars($label); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="w-full sm:w-48">
                                        <label for="skill_level_input" class="block text-xs font-medium text-gray-600 mb-1">Proficiency</label>
                                        <select id="skill_level_input" name="skill_level" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-fes-red focus:border-fes-red">
                                            <option value="beginner">Beginner</option>
                                            <option value="intermediate" selected>Intermediate</option>
                                            <option value="advanced">Advanced</option>
                                            <option value="expert">Expert</option>
                                        </select>
                                    </div>
                                    <div class="lg:shrink-0">
                                        <button type="submit" class="w-full lg:w-auto inline-flex items-center justify-center gap-2 bg-fes-red hover:bg-[#b71c1c] text-white font-medium px-5 py-2.5 rounded-lg shadow transition text-sm">
                                            <i class="fas fa-plus"></i>
                                            Add skill
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <div class="overflow-x-auto mt-5 rounded-lg border border-gray-100">
                                <table class="min-w-full">
                                    <caption class="sr-only">Skills for this operator</caption>
                                    <thead>
                                        <tr class="text-left text-xs font-medium text-gray-500 bg-gray-50 border-b border-gray-100 uppercase tracking-wider">
                                            <th class="py-3 px-4">Skill type</th>
                                            <th class="py-3 pr-4">Level</th>
                                            <th class="py-3 pr-4">Added</th>
                                            <th class="py-3 pr-4 w-28">Remove</th>
                                        </tr>
                                    </thead>
                                    <tbody class="text-sm text-gray-900">
                                        <?php if (empty($skills)): ?>
                                            <tr>
                                                <td colspan="4" class="py-8 px-4 text-center text-gray-500">
                                                    No skills yet. Use the form above to add the first one.
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($skills as $skill): ?>
                                                <tr class="border-b border-gray-50 last:border-0 hover:bg-gray-50/80">
                                                    <td class="py-3 px-4 font-medium"><?php echo htmlspecialchars(fes_operator_skill_type_label($skill['skill_name'] ?? '')); ?></td>
                                                    <td class="py-3 pr-4 text-gray-700"><?php echo htmlspecialchars(ucfirst($skill['skill_level'])); ?></td>
                                                    <td class="py-3 pr-4 text-gray-600">
                                                        <?php echo !empty($skill['created_at']) ? htmlspecialchars(date('M d, Y', strtotime($skill['created_at']))) : '—'; ?>
                                                    </td>
                                                    <td class="py-3 pr-4">
                                                        <form action="include/process_delete_operator_skill.php" method="POST" class="inline" onsubmit="return confirm('Remove this skill?');">
                                                            <input type="hidden" name="operator_id" value="<?php echo (int)$operator['user_id']; ?>">
                                                            <input type="hidden" name="skill_id" value="<?php echo (int)$skill['id']; ?>">
                                                            <button type="submit" class="inline-flex items-center gap-1.5 text-sm text-red-600 hover:text-red-800 hover:underline">
                                                                <i class="fas fa-trash-alt text-xs"></i> Remove
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


