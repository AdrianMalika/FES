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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - FES Operator</title>
    <link rel="icon" type="image/png" href="../../assets/images/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;600;700;800;900&family=Barlow:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { theme: { extend: { colors: { fes: { red: '#D32F2F', dark: '#424242' } } } } };</script>
    <style>
        * { font-family: 'Barlow', sans-serif; }
        h1, h2, h3, h4 { font-family: 'Barlow Condensed', sans-serif; }
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
                <button id="fes-dashboard-menu-btn" class="md:hidden inline-flex items-center justify-center h-10 w-10 rounded-lg border border-gray-200 text-gray-600">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <div class="text-sm text-gray-500">Operator</div>
                    <h1 class="text-xl font-semibold text-gray-900">Notifications</h1>
                    <p class="text-xs text-gray-500 mt-1">Static examples — connect to jobs/notifications later.</p>
                </div>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-6">
            <section class="bg-white rounded-xl shadow-card p-5 max-w-3xl">
                <h2 class="text-base font-semibold text-gray-900 mb-4">All notifications</h2>
                <p class="text-sm text-gray-500 mb-4">New job assignments, booking updates, and admin messages.</p>
                <ul class="divide-y divide-gray-200">
                    <li class="py-4 flex items-start gap-3 bg-red-50/50">
                        <span class="mt-1.5 h-2.5 w-2.5 rounded-full bg-fes-red flex-shrink-0" title="Unread"></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm text-gray-900 font-medium">
                                New job <span class="font-semibold">JOB-2026-004</span> has been assigned to you.
                            </p>
                            <p class="text-sm text-gray-700 mt-1">
                                Agri-Co Central · Service date: Mar 15, 2026 · 07:30
                            </p>
                            <p class="text-xs text-gray-500 mt-2">Mar 8, 2026 · 09:15</p>
                        </div>
                        <span class="text-xs font-medium text-fes-red flex-shrink-0">Unread</span>
                    </li>
                    <li class="py-4 flex items-start gap-3">
                        <span class="mt-1.5 h-2.5 w-2.5 rounded-full bg-gray-300 flex-shrink-0" title="Read"></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm text-gray-700">
                                Booking update for <span class="font-semibold">JOB-2026-002</span>: status set to <span class="font-semibold">Approved</span>. You can start at the scheduled time.
                            </p>
                            <p class="text-xs text-gray-500 mt-2">Mar 7, 2026 · 14:00</p>
                        </div>
                        <span class="text-xs text-gray-500 flex-shrink-0">Read</span>
                    </li>
                    <li class="py-4 flex items-start gap-3 bg-red-50/50">
                        <span class="mt-1.5 h-2.5 w-2.5 rounded-full bg-fes-red flex-shrink-0" title="Unread"></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm text-gray-900 font-medium">
                                Admin message: Please record hours and update status for <span class="font-semibold">JOB-2026-001</span> (still In Progress).
                            </p>
                            <p class="text-xs text-gray-500 mt-2">Mar 9, 2026 · 08:00</p>
                        </div>
                        <span class="text-xs font-medium text-fes-red flex-shrink-0">Unread</span>
                    </li>
                </ul>
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
    function openSidebar() { sidebar.classList.remove('-translate-x-full'); sidebar.classList.add('translate-x-0'); overlay.classList.remove('hidden'); }
    function closeSidebar() { sidebar.classList.add('-translate-x-full'); sidebar.classList.remove('translate-x-0'); overlay.classList.add('hidden'); }
    btn.addEventListener('click', function () { sidebar.classList.contains('translate-x-0') ? closeSidebar() : openSidebar(); });
    overlay.addEventListener('click', closeSidebar);
})();
</script>
</body>
</html>


