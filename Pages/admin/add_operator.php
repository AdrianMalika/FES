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
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Operator - FES</title>
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
                    boxShadow: {
                        card: '0 4px 15px rgba(0,0,0,0.05)'
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
                        <div class="text-sm text-gray-500">Users</div>
                        <h1 class="text-xl font-semibold text-gray-900">Add Operator</h1>
                    </div>
                </div>
           </header>

            <!-- Content -->
            <main class="flex-1 overflow-y-auto p-6" style="width: 100%; overflow-x: hidden;">
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

                    <!-- Account Setup Card -->
                    <div class="bg-white rounded-xl shadow-card p-8 max-w-2xl mx-auto">
                        <div class="text-center mb-8">
                            <div class="w-16 h-16 rounded-full bg-fes-red flex items-center justify-center text-white text-2xl font-bold mx-auto mb-4">
                                <i class="fas fa-user-plus"></i>
                            </div>
                            <h2 class="text-2xl font-semibold text-gray-900 mb-2">Add New Operator</h2>
                            <p class="text-base text-gray-500">Create a new operator account with login credentials</p>
                        </div>

                        <form action="process_add_operator.php?v=<?php echo time(); ?>" method="post" class="space-y-6">
                            <div>
                                <label class="block text-base font-medium text-gray-700 mb-3">Full Name <span class="text-fes-red">*</span></label>
                                <input type="text" name="full_name" class="w-full border border-gray-300 rounded-lg px-4 py-3 text-base text-gray-900 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-fes-red focus:border-fes-red" placeholder="e.g. James Phiri" required>
                            </div>
                            
                            <div>
                                <label class="block text-base font-medium text-gray-700 mb-3">Email Address <span class="text-fes-red">*</span></label>
                                <input type="email" name="email" class="w-full border border-gray-300 rounded-lg px-4 py-3 text-base text-gray-900 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-fes-red focus:border-fes-red" placeholder="operator@fes.africa" required>
                            </div>
                            
                            <div>
                                <label class="block text-base font-medium text-gray-700 mb-3">Password <span class="text-fes-red">*</span></label>
                                <input type="password" name="password" class="w-full border border-gray-300 rounded-lg px-4 py-3 text-base text-gray-900 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-fes-red focus:border-fes-red" placeholder="Enter password (min 8 characters)" required>
                            </div>

                            <div class="pt-8">
                                <button type="submit" class="w-full inline-flex items-center justify-center gap-3 bg-fes-red hover:bg-[#b71c1c] text-white font-medium px-6 py-3 rounded-lg shadow transition text-base">
                                    <i class="fas fa-user-plus"></i> Create Operator
                                </button>
                            </div>
                        </form>
                    </div>
                </main>
            </div>
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
    </script>
</body>
</html>