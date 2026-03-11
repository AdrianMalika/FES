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

$jobId = $_GET['id'] ?? 'JOB-2026-001';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Work Hours - <?php echo htmlspecialchars($jobId); ?></title>
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
                    <h1 class="text-xl font-semibold text-gray-900">Record Work Hours</h1>
                    <p class="text-xs text-gray-500 mt-1">Job <?php echo htmlspecialchars($jobId); ?> · static form</p>
                </div>
            </div>
            <a href="job_details.php?id=<?php echo urlencode($jobId); ?>" class="inline-flex items-center gap-2 border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 font-medium px-4 py-2 rounded-lg text-sm">
                <i class="fas fa-arrow-left"></i> Back to Job
            </a>
        </header>

        <main class="flex-1 overflow-y-auto p-6">
            <section class="bg-white rounded-xl shadow-card p-6 max-w-xl">
                <form method="post" action="#" class="space-y-5" id="hours-form">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Job ID</label>
                        <input type="text" value="<?php echo htmlspecialchars($jobId); ?>" readonly class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm bg-gray-50 text-gray-700 font-medium">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Start time</label>
                            <input type="datetime-local" name="start_time" id="start_time" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-fes-red focus:border-fes-red">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">End time</label>
                            <input type="datetime-local" name="end_time" id="end_time" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-fes-red focus:border-fes-red">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Total hours</label>
                        <input type="text" id="total_hours" readonly value="—" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm bg-gray-50 text-gray-700 font-medium">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Notes (optional)</label>
                        <textarea name="hours_note" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-fes-red focus:border-fes-red" placeholder="Describe any breaks, delays, or special circumstances..."></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-3">
                        <a href="job_details.php?id=<?php echo urlencode($jobId); ?>" class="px-4 py-2.5 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">Cancel</a>
                        <button type="submit" class="px-5 py-2.5 bg-fes-red hover:bg-[#b71c1c] text-white rounded-lg text-sm font-medium shadow">
                            Submit
                        </button>
                    </div>
                </form>
            </section>
        </main>
    </div>
</div>

<script>
(function () {
    var startEl = document.getElementById('start_time');
    var endEl = document.getElementById('end_time');
    var totalEl = document.getElementById('total_hours');
    if (!startEl || !endEl || !totalEl) return;

    function updateTotal() {
        var start = startEl.value ? new Date(startEl.value) : null;
        var end = endEl.value ? new Date(endEl.value) : null;
        if (!start || !end || end <= start) {
            totalEl.value = '—';
            return;
        }
        var hours = (end - start) / (1000 * 60 * 60);
        totalEl.value = hours.toFixed(2) + ' hours';
    }

    startEl.addEventListener('change', updateTotal);
    endEl.addEventListener('change', updateTotal);
})();
</script>
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

