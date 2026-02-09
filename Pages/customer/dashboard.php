<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard - FES</title>
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
</head>

<body>
    <div class="min-h-screen w-full bg-gray-100" style="font-family: Georgia, 'Times New Roman', serif;">
        <div class="flex min-h-screen">
            <!-- Sidebar -->
            <?php include __DIR__ . '/include/sidebar.php'; ?>

            <!-- Main -->
            <div class="flex-1 flex flex-col min-w-0">
                <!-- Top bar -->
                <header class="bg-white px-6 py-7 flex items-center justify-between shadow-sm">
                    <div class="flex items-center gap-3">
                        <button class="md:hidden inline-flex items-center justify-center h-10 w-10 rounded-lg border border-gray-200 text-gray-600" aria-label="Open menu">
                            <i class="fas fa-bars"></i>
                        </button>
                        <div>
                            <div class="text-sm text-gray-500">Customer</div>
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
                            New Booking
                        </button>
                    </div>
                </header>

                <!-- Content -->
                <main class="flex-1 overflow-y-auto p-6">
                    <!-- Stats -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5 mb-6">
                        <div class="bg-white rounded-xl shadow-card p-5 flex items-start justify-between">
                            <div>
                                <div class="text-sm text-gray-500">Active Bookings</div>
                                <div class="mt-1 text-2xl font-semibold text-gray-900">2</div>
                            </div>
                            <div class="h-11 w-11 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                        </div>
                        <div class="bg-white rounded-xl shadow-card p-5 flex items-start justify-between">
                            <div>
                                <div class="text-sm text-gray-500">Pending Requests</div>
                                <div class="mt-1 text-2xl font-semibold text-gray-900">1</div>
                            </div>
                            <div class="h-11 w-11 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                                <i class="fas fa-clock"></i>
                            </div>
                        </div>
                        <div class="bg-white rounded-xl shadow-card p-5 flex items-start justify-between">
                            <div>
                                <div class="text-sm text-gray-500">Completed Rentals</div>
                                <div class="mt-1 text-2xl font-semibold text-gray-900">7</div>
                            </div>
                            <div class="h-11 w-11 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                        <div class="bg-white rounded-xl shadow-card p-5 flex items-start justify-between">
                            <div>
                                <div class="text-sm text-gray-500">Outstanding Balance</div>
                                <div class="mt-1 text-2xl font-semibold text-gray-900">$320</div>
                            </div>
                            <div class="h-11 w-11 rounded-xl bg-red-50 text-fes-red flex items-center justify-center">
                                <i class="fas fa-file-invoice-dollar"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Recent bookings -->
                    <section class="bg-white rounded-xl shadow-card p-5">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-base font-semibold text-gray-900">Recent Bookings</h2>
                            <a href="#" class="text-sm font-medium text-fes-red hover:underline">View All</a>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full">
                                <thead>
                                    <tr class="text-left text-xs font-medium text-gray-500 border-b">
                                        <th class="py-3 pr-4">ID</th>
                                        <th class="py-3 pr-4">Equipment</th>
                                        <th class="py-3 pr-4">Dates</th>
                                        <th class="py-3 pr-4">Status</th>
                                        <th class="py-3">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="text-sm text-gray-900">
                                    <tr class="border-b hover:bg-gray-50">
                                        <td class="py-3 pr-4 font-medium">#CU-1004</td>
                                        <td class="py-3 pr-4">John Deere 5075E</td>
                                        <td class="py-3 pr-4">Feb 12 - Feb 14</td>
                                        <td class="py-3 pr-4"><span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700">Approved</span></td>
                                        <td class="py-3"><button class="text-gray-500 hover:text-gray-900" aria-label="More"><i class="fas fa-ellipsis-h"></i></button></td>
                                    </tr>
                                    <tr class="border-b hover:bg-gray-50">
                                        <td class="py-3 pr-4 font-medium">#CU-1003</td>
                                        <td class="py-3 pr-4">CAT Excavator</td>
                                        <td class="py-3 pr-4">Feb 18 - Feb 20</td>
                                        <td class="py-3 pr-4"><span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-amber-50 text-amber-700">Pending</span></td>
                                        <td class="py-3"><button class="text-gray-500 hover:text-gray-900" aria-label="More"><i class="fas fa-ellipsis-h"></i></button></td>
                                    </tr>
                                    <tr class="hover:bg-gray-50">
                                        <td class="py-3 pr-4 font-medium">#CU-1002</td>
                                        <td class="py-3 pr-4">Irrigation Pump</td>
                                        <td class="py-3 pr-4">Jan 29 - Jan 30</td>
                                        <td class="py-3 pr-4"><span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700">Completed</span></td>
                                        <td class="py-3"><button class="text-gray-500 hover:text-gray-900" aria-label="More"><i class="fas fa-ellipsis-h"></i></button></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6">
                        <!-- Recommended -->
                        <section class="lg:col-span-2 bg-white rounded-xl shadow-card p-5">
                            <h2 class="text-base font-semibold text-gray-900 mb-4">Recommended For You</h2>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="rounded-xl border border-gray-200 p-4 hover:bg-gray-50 transition">
                                    <div class="text-sm text-gray-500">Tractor</div>
                                    <div class="mt-1 font-semibold text-gray-900">Massey Ferguson 2600</div>
                                    <div class="mt-2 text-xs text-gray-500">Popular for land prep and haulage</div>
                                </div>
                                <div class="rounded-xl border border-gray-200 p-4 hover:bg-gray-50 transition">
                                    <div class="text-sm text-gray-500">Harvester</div>
                                    <div class="mt-1 font-semibold text-gray-900">Harvester X5</div>
                                    <div class="mt-2 text-xs text-gray-500">High throughput harvesting</div>
                                </div>
                            </div>
                        </section>

                        <!-- Quick actions -->
                        <section class="bg-white rounded-xl shadow-card p-5">
                            <h2 class="text-base font-semibold text-gray-900 mb-4">Quick Actions</h2>
                            <div class="space-y-3">
                                <button class="w-full flex items-center gap-3 px-4 py-3 rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50 transition text-sm font-medium">
                                    <i class="fas fa-search text-fes-red"></i>
                                    Browse Equipment
                                </button>
                                <button class="w-full flex items-center gap-3 px-4 py-3 rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50 transition text-sm font-medium">
                                    <i class="fas fa-calendar-plus text-fes-red"></i>
                                    Request Booking
                                </button>
                                <button class="w-full flex items-center gap-3 px-4 py-3 rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50 transition text-sm font-medium">
                                    <i class="fas fa-file-invoice-dollar text-fes-red"></i>
                                    View Payments
                                </button>
                            </div>
                        </section>
                    </div>
                </main>
            </div>
        </div>
    </div>

</body>

</html>
