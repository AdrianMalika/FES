<?php
session_start();

// Check if user is logged in and has admin role


$error = '';
$success = '';

if (isset($_GET['success']) && $_GET['success'] === '1') {
    $success = 'Operator account created successfully. A welcome email has been sent to the operator.';
}

if (isset($_GET['error'])) {
    $error = (string)$_GET['error'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Operator - FES Admin</title>
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
        body { font-family: Georgia, 'Times New Roman', serif; }

        /* Sidebar */
        .sidebar-link {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 16px; border-radius: 8px;
            color: #9ca3af; font-size: 0.875rem; text-decoration: none;
            transition: background .15s, color .15s;
        }
        .sidebar-link:hover { background: rgba(255,255,255,.07); color: #fff; }
        .sidebar-link.active { background: #D32F2F; color: #fff; }

        /* Input styles */
        .fes-input {
            width: 100%; border: 1px solid #e5e7eb; border-radius: 8px;
            padding: 10px 14px; font-size: 0.875rem; font-family: inherit;
            color: #111827; background: #f9fafb; outline: none;
            transition: border-color .2s, box-shadow .2s;
        }
        .fes-input:focus { border-color: #D32F2F; box-shadow: 0 0 0 3px rgba(211,47,47,.1); background: #fff; }
        .fes-label { display: block; font-size: 0.8rem; font-weight: 600; color: #374151; margin-bottom: 6px; }
    </style>
</head>
<body>
<div class="min-h-screen w-full bg-gray-100">
<div class="flex min-h-screen">

    <!-- Sidebar -->
    <aside id="fes-dashboard-sidebar"
        class="fixed inset-y-0 left-0 z-40 w-64 bg-fes-dark flex flex-col
               -translate-x-full md:translate-x-0 md:static transition-transform duration-300">
        <!-- Logo -->
        <div class="flex items-center gap-3 px-6 py-5 border-b border-white/10">
            <div class="w-9 h-9 rounded-lg bg-fes-red flex items-center justify-center text-white font-bold text-lg">F</div>
            <div>
                <div class="text-white font-semibold text-sm leading-tight">FES Admin</div>
                <div class="text-gray-400 text-xs">Equipment System</div>
            </div>
        </div>
        <!-- Nav -->
        <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
            <a href="dashboard.php" class="sidebar-link"><i class="fas fa-th-large w-4"></i> Dashboard</a>
            <a href="#" class="sidebar-link"><i class="fas fa-tractor w-4"></i> Equipment</a>
            <a href="#" class="sidebar-link"><i class="fas fa-clipboard-list w-4"></i> Bookings</a>
            <a href="add_operator.php" class="sidebar-link active"><i class="fas fa-users w-4"></i> Operators</a>
            <a href="#" class="sidebar-link"><i class="fas fa-users-cog w-4"></i> Customers</a>
            <a href="#" class="sidebar-link"><i class="fas fa-chart-bar w-4"></i> Reports</a>
            <a href="#" class="sidebar-link"><i class="fas fa-wrench w-4"></i> Maintenance</a>
            <a href="#" class="sidebar-link"><i class="fas fa-cog w-4"></i> Settings</a>
        </nav>
        <div class="px-3 pb-4">
            <a href="../auth/signin.php" class="sidebar-link"><i class="fas fa-sign-out-alt w-4"></i> Logout</a>
        </div>
    </aside>

    <div id="fes-dashboard-overlay" class="fixed inset-0 bg-black/40 z-30 hidden md:hidden"></div>

    <!-- Main -->
    <div class="flex-1 flex flex-col min-w-0">
        <!-- Top bar -->
        <header class="bg-white px-6 py-4 flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-3">
                <button id="fes-dashboard-menu-btn" class="md:hidden inline-flex items-center justify-center h-10 w-10 rounded-lg border border-gray-200 text-gray-600">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <div class="text-xs text-gray-400 flex items-center gap-1">
                        <a href="dashboard.php" class="hover:text-fes-red">Dashboard</a>
                        <i class="fas fa-chevron-right text-[10px]"></i>
                        <a href="add_operator.php" class="hover:text-fes-red">Operators</a>
                        <i class="fas fa-chevron-right text-[10px]"></i>
                        <span class="text-gray-600">Add Operator</span>
                    </div>
                    <h1 class="text-lg font-semibold text-gray-900 mt-0.5">Add New Operator</h1>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <button class="relative h-10 w-10 rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50">
                    <i class="fas fa-bell"></i>
                    <span class="absolute top-2 right-2 h-2 w-2 rounded-full bg-fes-red"></span>
                </button>
                <div class="w-9 h-9 rounded-full bg-fes-red flex items-center justify-center text-white font-semibold text-sm">A</div>
            </div>
        </header>

        <!-- Content -->
        <main class="flex-1 overflow-y-auto p-6">
            <div class="max-w-3xl mx-auto">

                <!-- Success/Error Messages -->
                <?php if (!empty($success)): ?>
                    <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <!-- Single Form Section -->
                <div class="bg-white rounded-xl shadow-card p-6">
                    <h2 class="text-base font-semibold text-gray-900 mb-5 flex items-center gap-2">
                        <i class="fas fa-user text-fes-red text-sm"></i> Operator Information
                    </h2>

                    <form action="process_add_operator.php" method="post" id="operatorForm">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <label class="fes-label">Full Name <span class="text-fes-red">*</span></label>
                                <input type="text" name="full_name" class="fes-input" id="fullName" placeholder="e.g. James Phiri" required>
                            </div>
                            <div>
                                <label class="fes-label">Email Address <span class="text-fes-red">*</span></label>
                                <input type="email" name="email" class="fes-input" id="email" placeholder="operator@fes.africa" required>
                            </div>
                        </div>

                        <div class="flex justify-end mt-6">
                            <button type="submit" class="inline-flex items-center gap-2 bg-fes-red hover:bg-[#b71c1c] text-white font-medium px-6 py-2.5 rounded-lg shadow transition">
                                <i class="fas fa-user-plus"></i> Create Operator Account
                            </button>
                        </div>
                    </form>
                </div>

            </div>
        </main>
    </div>
</div>
</div>

<!-- Toast -->
<div id="toast">
    <i class="fas fa-check-circle" id="toast-icon"></i>
    <span id="toast-msg">Saved</span>
</div>

<script>
// Mobile sidebar
(function(){
    var btn = document.getElementById('fes-dashboard-menu-btn');
    var sb  = document.getElementById('fes-dashboard-sidebar');
    var ov  = document.getElementById('fes-dashboard-overlay');
    if (!btn) return;
    btn.addEventListener('click', () => { sb.classList.toggle('-translate-x-full'); ov.classList.toggle('hidden'); });
    ov.addEventListener('click', () => { sb.classList.add('-translate-x-full'); ov.classList.add('hidden'); });
})();
</script>
</body>
</html>
