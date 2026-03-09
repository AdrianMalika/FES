<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/signin.php');
    exit();
}

// Check if user has operator role
if ($_SESSION['role'] !== 'operator') {
    // Redirect based on actual role
    switch($_SESSION['role']) {
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
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Operator Dashboard - FES</title>
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
    </style>
</head>

<body>
    <div class="min-h-screen w-full bg-gray-100">
        <div class="flex min-h-screen">
            <!-- Sidebar -->
            <?php include __DIR__ . '/include/sidebar.php'; ?>

            <div id="fes-dashboard-overlay" class="fixed inset-0 bg-black/40 z-30 hidden md:hidden"></div>

            <!-- Main -->
            <div class="flex-1 flex flex-col min-w-0">
                <!-- Top bar -->
                <header class="bg-white px-6 py-7 flex items-center justify-between shadow-sm">
                    <div class="flex items-center gap-3">
                        <button id="fes-dashboard-menu-btn" class="md:hidden inline-flex items-center justify-center h-10 w-10 rounded-lg border border-gray-200 text-gray-600" aria-label="Open menu" aria-controls="fes-dashboard-sidebar" aria-expanded="false">
                            <i class="fas fa-bars"></i>
                        </button>
                        <div>
                            <div class="text-sm text-gray-500">Operator</div>
                            <h1 class="text-xl font-semibold text-gray-900">Dashboard</h1>
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <button class="relative h-10 w-10 rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50" aria-label="Notifications">
                            <i class="fas fa-bell"></i>
                            <span class="absolute top-2 right-2 h-2 w-2 rounded-full bg-fes-red"></span>
                        </button>
                        <button class="inline-flex items-center gap-2 bg-fes-red hover:bg-[#b71c1c] text-white font-medium px-4 py-2 rounded-lg shadow">
                            <i class="fas fa-plus"></i>
                            Log Update
                        </button>
                    </div>
                </header>

                <!-- Content -->
                <main class="flex-1 overflow-y-auto p-6">
                    <!-- Stats -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5 mb-6">
                        <div class="bg-white rounded-xl shadow-card p-5 flex items-start justify-between">
                            <div>
                                <div class="text-sm text-gray-500">Jobs Today</div>
                                <div class="mt-1 text-2xl font-semibold text-gray-900">3</div>
                            </div>
                            <div class="h-11 w-11 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                                <i class="fas fa-briefcase"></i>
                            </div>
                        </div>
                        <div class="bg-white rounded-xl shadow-card p-5 flex items-start justify-between">
                            <div>
                                <div class="text-sm text-gray-500">In Progress</div>
                                <div class="mt-1 text-2xl font-semibold text-gray-900">1</div>
                            </div>
                            <div class="h-11 w-11 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                                <i class="fas fa-hourglass-half"></i>
                            </div>
                        </div>
                        <div class="bg-white rounded-xl shadow-card p-5 flex items-start justify-between">
                            <div>
                                <div class="text-sm text-gray-500">Completed</div>
                                <div class="mt-1 text-2xl font-semibold text-gray-900">14</div>
                            </div>
                            <div class="h-11 w-11 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                        <div class="bg-white rounded-xl shadow-card p-5 flex items-start justify-between">
                            <div>
                                <div class="text-sm text-gray-500">Incidents</div>
                                <div class="mt-1 text-2xl font-semibold text-gray-900">0</div>
                            </div>
                            <div class="h-11 w-11 rounded-xl bg-red-50 text-fes-red flex items-center justify-center">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Today's schedule -->
                    <section class="bg-white rounded-xl shadow-card p-5">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-base font-semibold text-gray-900">Today's Schedule</h2>
                            <a href="#" class="text-sm font-medium text-fes-red hover:underline">View All</a>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full">
                                <thead>
                                    <tr class="text-left text-xs font-medium text-gray-500 border-b">
                                        <th class="py-3 pr-4">Job ID</th>
                                        <th class="py-3 pr-4">Customer</th>
                                        <th class="py-3 pr-4">Equipment</th>
                                        <th class="py-3 pr-4">Time</th>
                                        <th class="py-3 pr-4">Status</th>
                                        <th class="py-3">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="text-sm text-gray-900">
                                    <tr class="border-b hover:bg-gray-50">
                                        <td class="py-3 pr-4 font-medium">#OP-310</td>
                                        <td class="py-3 pr-4">Agri-Tech Solutions</td>
                                        <td class="py-3 pr-4">John Deere 5075E</td>
                                        <td class="py-3 pr-4">08:00</td>
                                        <td class="py-3 pr-4"><span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-amber-50 text-amber-700">In Progress</span></td>
                                        <td class="py-3"><button class="text-gray-500 hover:text-gray-900" aria-label="More"><i class="fas fa-ellipsis-h"></i></button></td>
                                    </tr>
                                    <tr class="border-b hover:bg-gray-50">
                                        <td class="py-3 pr-4 font-medium">#OP-309</td>
                                        <td class="py-3 pr-4">Farm Co. Ltd</td>
                                        <td class="py-3 pr-4">CAT Excavator</td>
                                        <td class="py-3 pr-4">12:30</td>
                                        <td class="py-3 pr-4"><span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-700">Assigned</span></td>
                                        <td class="py-3"><button class="text-gray-500 hover:text-gray-900" aria-label="More"><i class="fas fa-ellipsis-h"></i></button></td>
                                    </tr>
                                    <tr class="hover:bg-gray-50">
                                        <td class="py-3 pr-4 font-medium">#OP-308</td>
                                        <td class="py-3 pr-4">Sarah Banda</td>
                                        <td class="py-3 pr-4">Irrigation Pump</td>
                                        <td class="py-3 pr-4">16:00</td>
                                        <td class="py-3 pr-4"><span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700">Completed</span></td>
                                        <td class="py-3"><button class="text-gray-500 hover:text-gray-900" aria-label="More"><i class="fas fa-ellipsis-h"></i></button></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6">
                        <!-- Equipment status -->
                        <section class="lg:col-span-2 bg-white rounded-xl shadow-card p-5">
                            <h2 class="text-base font-semibold text-gray-900 mb-4">Equipment Status</h2>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="rounded-xl border border-gray-200 p-4">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div class="text-sm text-gray-500">Assigned</div>
                                            <div class="mt-1 font-semibold text-gray-900">John Deere 5075E</div>
                                        </div>
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700">Operational</span>
                                    </div>
                                    <div class="mt-3 text-xs text-gray-500">Next maintenance: 12 days</div>
                                </div>
                                <div class="rounded-xl border border-gray-200 p-4">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div class="text-sm text-gray-500">Assigned</div>
                                            <div class="mt-1 font-semibold text-gray-900">CAT Excavator</div>
                                        </div>
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-amber-50 text-amber-700">Check Needed</span>
                                    </div>
                                    <div class="mt-3 text-xs text-gray-500">Reported issue: low hydraulic pressure</div>
                                </div>
                            </div>
                        </section>

                        <!-- Quick actions -->
                        <section class="bg-white rounded-xl shadow-card p-5">
                            <h2 class="text-base font-semibold text-gray-900 mb-4">Quick Actions</h2>
                            <div class="space-y-3">
                                <button class="w-full flex items-center gap-3 px-4 py-3 rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50 transition text-sm font-medium">
                                    <i class="fas fa-tools text-fes-red"></i>
                                    Log Maintenance
                                </button>
                                <button class="w-full flex items-center gap-3 px-4 py-3 rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50 transition text-sm font-medium">
                                    <i class="fas fa-exclamation-triangle text-fes-red"></i>
                                    Report Incident
                                </button>
                                <button class="w-full flex items-center gap-3 px-4 py-3 rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50 transition text-sm font-medium">
                                    <i class="fas fa-route text-fes-red"></i>
                                    View Schedule
                                </button>
                            </div>
                        </section>
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



