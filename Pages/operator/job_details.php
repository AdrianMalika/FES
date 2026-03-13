<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/signin.php');
    exit();
}
if (($_SESSION['role'] ?? '') !== 'operator') {
    switch ($_SESSION['role'] ?? '') {
        case 'admin':
            header('Location: ../admin/dashboard.php');
            exit();
        case 'customer':
            header('Location: ../customer/dashboard.php');
            exit();
        default:
            header('Location: ../auth/signin.php');
            exit();
    }
}

$operatorName = $_SESSION['name'] ?? 'Operator';

// Static example job ID (later: load from DB using this)
$jobId = $_GET['id'] ?? 'JOB-2026-001';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Details - <?php echo htmlspecialchars($jobId); ?></title>
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
                        fes: { red: '#D32F2F', dark: '#424242' }
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
        @media (max-width: 767px) { #main-content { margin-left: 0 !important; width: 100% !important; } }
        @media (min-width: 768px) { #main-content { margin-left: 256px !important; width: calc(100% - 256px) !important; } }
    </style>
</head>
<body class="bg-gray-100">
<div class="min-h-screen w-full">
    <?php include __DIR__ . '/include/sidebar.php'; ?>

    <div id="fes-dashboard-overlay" class="fixed inset-0 bg-black/40 z-30 hidden md:hidden"></div>

    <div class="min-h-screen" id="main-content">
        <header class="bg-white px-6 py-7 flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-3">
                <button id="fes-dashboard-menu-btn" class="md:hidden inline-flex items-center justify-center h-10 w-10 rounded-lg border border-gray-200 text-gray-600" aria-label="Open menu">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <div class="text-sm text-gray-500">Operator · Job Details</div>
                    <h1 class="text-xl font-semibold text-gray-900">
                        Job <?php echo htmlspecialchars($jobId); ?>
                    </h1>
                    <p class="text-xs text-gray-500 mt-1">
                        Static mock data — wire this to real bookings later.
                    </p>
                </div>
            </div>

            <a href="jobs.php" class="inline-flex items-center gap-2 border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 font-medium px-4 py-2 rounded-lg text-sm">
                <i class="fas fa-arrow-left"></i> Back to My Jobs
            </a>
        </header>

        <main class="flex-1 overflow-y-auto p-6">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                <!-- Left: main job info -->
                <section class="lg:col-span-2 bg-white rounded-xl shadow-card p-5">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Service Details</h2>

                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4 text-sm text-gray-800">
                        <div>
                            <dt class="text-gray-500 text-xs uppercase tracking-wide">Customer</dt>
                            <dd class="mt-1 font-medium">Agri-Tech Solutions</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 text-xs uppercase tracking-wide">Contact</dt>
                            <dd class="mt-1">+265 999 000 111 · customer@example.com</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 text-xs uppercase tracking-wide">Service Type</dt>
                            <dd class="mt-1">Land Preparation (Tractor)</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 text-xs uppercase tracking-wide">Service Date</dt>
                            <dd class="mt-1">Mar 10, 2026 · 08:00</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 text-xs uppercase tracking-wide">Land Size</dt>
                            <dd class="mt-1">18 Acres</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 text-xs uppercase tracking-wide">Location</dt>
                            <dd class="mt-1">Chikwawa · GPS: -16.035, 34.790</dd>
                            <dd class="mt-2">
                                <a href="https://www.google.com/maps?q=-16.035,34.790" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 text-sm text-fes-red hover:underline font-medium">
                                    <i class="fas fa-map-marker-alt"></i> View on map
                                </a>
                            </dd>
                        </div>
                    </dl>

                    <div class="mt-6 border-t border-gray-200 pt-4">
                        <h3 class="text-sm font-semibold text-gray-900 mb-2">Special Notes</h3>
                        <p class="text-sm text-gray-700 leading-relaxed">
                            Access road is narrow — use smaller trailer where possible.
                            Watch out for irrigation pipes crossing the field on the eastern side.
                        </p>
                    </div>

                    <div class="mt-6 border-t border-gray-200 pt-4">
                        <h3 class="text-sm font-semibold text-gray-900 mb-2">Assigned Equipment</h3>
                        <div class="flex items-center gap-3">
                            <div class="h-10 w-10 rounded-lg bg-red-50 flex items-center justify-center text-fes-red">
                                <i class="fas fa-tractor"></i>
                            </div>
                            <div>
                                <div class="font-medium text-gray-900">John Deere 5075E</div>
                                <div class="text-xs text-gray-500">Equipment ID: EQ-010 · Blantyre Depot</div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Right: quick actions -->
                <section class="bg-white rounded-xl shadow-card p-5">
                    <h2 class="text-base font-semibold text-gray-900 mb-4">Job Actions</h2>
                    <div class="space-y-3 text-sm">
                        <a href="job_status.php?id=<?php echo urlencode($jobId); ?>" class="w-full flex items-center gap-3 px-4 py-3 rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50 font-medium">
                            <i class="fas fa-signal text-fes-red"></i>
                            Update Job Status
                        </a>
                        <a href="job_hours.php?id=<?php echo urlencode($jobId); ?>" class="w-full flex items-center gap-3 px-4 py-3 rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50 font-medium">
                            <i class="fas fa-clock text-fes-red"></i>
                            Record Work Hours
                        </a>
                        <a href="job_damage.php?id=<?php echo urlencode($jobId); ?>" class="w-full flex items-center gap-3 px-4 py-3 rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50 font-medium">
                            <i class="fas fa-triangle-exclamation text-fes-red"></i>
                            Report Equipment Damage
                        </a>
                    </div>

                    <div class="mt-6 border-t border-gray-200 pt-4 text-xs text-gray-500">
                        <p><span class="font-semibold text-gray-700">Current Status:</span>
                            <span class="inline-flex items-center px-2.5 py-1 ml-1 rounded-full text-[11px] font-medium bg-amber-50 text-amber-700">
                                In Progress
                            </span>
                        </p>
                        <p class="mt-2">
                            Last updated: Mar 10, 2026 · 09:15 by <?php echo htmlspecialchars($operatorName); ?>
                        </p>
                    </div>
                </section>
            </div>

            <!-- Static section for recorded hours & damage summary -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <section class="bg-white rounded-xl shadow-card p-5">
                    <h2 class="text-base font-semibold text-gray-900 mb-3">Recorded Hours (example)</h2>
                    <ul class="text-sm text-gray-800 space-y-2">
                        <li><span class="font-medium">Start:</span> 08:10 · Mar 10, 2026</li>
                        <li><span class="font-medium">End:</span> 14:45 · Mar 10, 2026</li>
                        <li><span class="font-medium">Total:</span> 6.6 hours</li>
                    </ul>
                </section>

                <section class="bg-white rounded-xl shadow-card p-5">
                    <h2 class="text-base font-semibold text-gray-900 mb-3">Damage / Faults (example)</h2>
                    <p class="text-sm text-gray-700 leading-relaxed">
                        No major damage reported. Minor hydraulic leak observed on right ram — logged for workshop inspection
                        after completion of this job.
                    </p>
                </section>
            </div>
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
    })();
</script>
</body>
</html>


