<?php
session_start();

if (!isset($_SESSION['user_id'])) 
{
    header('Location: ../auth/signin.php');
    exit();
}

if (($_SESSION['role'] ?? '') !== 'customer') 
{
    switch ($_SESSION['role']) {
        case 'admin':
            header('Location: ../admin/dashboard.php');
            exit();
        case 'operator':
            header('Location: ../operator/dashboard.php');
            exit();
        default:
            header('Location: ../auth/signin.php');
            exit();
    }
}

require_once __DIR__ . '/../../includes/database.php';

$customerId = (int)$_SESSION['user_id'];
$profile = [
    'name' => (string)($_SESSION['name'] ?? ''),
    'email' => (string)($_SESSION['email'] ?? ''),
    'phone' => '',
    'address' => '',
    'city' => '',
];

$msg = null;
$msgType = 'success';
$status = $_GET['status'] ?? '';
if ($status === 'saved') {
    $msg = 'Profile updated successfully.';
    $msgType = 'success';
} elseif ($status === 'error') {
    $msg = 'Could not update your profile. Please try again.';
    $msgType = 'error';
}

try {
    $conn = getDBConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim((string)($_POST['name'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $city = trim((string)($_POST['city'] ?? ''));
        $address = trim((string)($_POST['address'] ?? ''));

        if ($name === '') {
            $msg = 'Name is required.';
            $msgType = 'error';
        } else {
            $conn->begin_transaction();
            try {
                $u = $conn->prepare('UPDATE users SET name = ? WHERE user_id = ? LIMIT 1');
                if (!$u) {
                    throw new RuntimeException('users update prepare failed');
                }
                $u->bind_param('si', $name, $customerId);
                $u->execute();
                $u->close();

                $c = $conn->prepare('
                    INSERT INTO customers (user_id, phone, address, city)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE phone = VALUES(phone), address = VALUES(address), city = VALUES(city)
                ');
                if (!$c) {
                    throw new RuntimeException('customers upsert prepare failed');
                }
                $c->bind_param('isss', $customerId, $phone, $address, $city);
                $c->execute();
                $c->close();

                $conn->commit();
                $_SESSION['name'] = $name;
                header('Location: profile.php?status=saved');
                exit();
            } catch (Throwable $e) {
                $conn->rollback();
                error_log('profile update error: ' . $e->getMessage());
                header('Location: profile.php?status=error');
                exit();
            }
        }
    }

    $q = $conn->prepare('
        SELECT u.name, u.email, c.phone, c.address, c.city
        FROM users u
        LEFT JOIN customers c ON c.user_id = u.user_id
        WHERE u.user_id = ?
        LIMIT 1
    ');
    if ($q) {
        $q->bind_param('i', $customerId);
        $q->execute();
        $res = $q->get_result();
        if ($row = $res->fetch_assoc()) {
            $profile['name'] = (string)($row['name'] ?? '');
            $profile['email'] = (string)($row['email'] ?? '');
            $profile['phone'] = (string)($row['phone'] ?? '');
            $profile['address'] = (string)($row['address'] ?? '');
            $profile['city'] = (string)($row['city'] ?? '');
        }
        $q->close();
    }

    $conn->close();
} catch (Throwable $e) {
    error_log('profile load error: ' . $e->getMessage());
    if ($msg === null) {
        $msg = 'Could not load your profile details.';
        $msgType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | FES</title>
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
                            dark: '#1F2937',
                            light: '#F9FAFB'
                        } 
                    },
                    fontFamily: {
                        display: ['"Barlow Condensed"', 'sans-serif'],
                        body: ['Barlow', 'sans-serif'],
                    },
                    boxShadow: { 
                        card: '0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06)',
                        'card-hover': '0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05)'
                    }
                }
            }
        };
    </script>
    <style>
        * { font-family: 'Barlow', sans-serif; }
        h1, h2, h3, h4, .display { font-family: 'Barlow Condensed', sans-serif; }
        .input-focus:focus {
            border-color: #D32F2F;
            box-shadow: 0 0 0 3px rgba(211, 47, 47, 0.1);
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-900">
    <div class="min-h-screen flex">
        <!-- Sidebar -->
        <?php include __DIR__ . '/include/sidebar.php'; ?>
        
        <!-- Mobile Overlay -->
        <div id="fes-dashboard-overlay" class="fixed inset-0 bg-black/50 z-30 hidden md:hidden backdrop-blur-sm transition-opacity"></div>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col min-w-0 md:ml-64">
            <!-- Header -->
            <header class="bg-white border-b border-gray-200 px-6 py-4 sticky top-0 z-20">
                <div class="flex items-center justify-between max-w-5xl mx-auto">
                    <div class="flex items-center gap-4">
                        <button id="fes-dashboard-menu-btn" class="md:hidden p-2 rounded-md text-gray-500 hover:bg-gray-100 transition-colors">
                            <i class="fas fa-bars text-lg"></i>
                        </button>
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Account Settings</h1>
                            <p class="text-sm text-gray-500">Manage your personal information and preferences</p>
                        </div>
                    </div>
                    <div class="hidden sm:flex items-center gap-3">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                            Customer Account
                        </span>
                    </div>
                </div>
            </header>

            <!-- Main Body -->
            <main class="flex-1 p-6 md:p-10">
                <div class="max-w-4xl mx-auto">
                    
                    <!-- Notifications -->
                    <?php if ($msg !== null): ?>
                        <div class="mb-8 animate-in fade-in slide-in-from-top-4 duration-300">
                            <?php if ($msgType === 'success'): ?>
                                <div class="flex items-center p-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800">
                                    <i class="fas fa-check-circle mr-3 text-emerald-500"></i>
                                    <span class="text-sm font-medium"><?php echo htmlspecialchars($msg); ?></span>
                                </div>
                            <?php else: ?>
                                <div class="flex items-center p-4 rounded-lg bg-red-50 border border-red-200 text-red-800">
                                    <i class="fas fa-exclamation-circle mr-3 text-red-500"></i>
                                    <span class="text-sm font-medium"><?php echo htmlspecialchars($msg); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Profile Section -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        
                        <!-- Left Column: Profile Summary -->
                        <div class="lg:col-span-1">
                            <div class="bg-white rounded-2xl shadow-card border border-gray-100 p-6 text-center">
                                <div class="relative inline-block mb-4">
                                    <div class="h-24 w-24 rounded-full bg-gray-100 border-4 border-white shadow-sm flex items-center justify-center text-gray-400 mx-auto overflow-hidden">
                                        <i class="fas fa-user text-4xl"></i>
                                    </div>
                                    <div class="absolute bottom-0 right-0 h-6 w-6 bg-emerald-500 border-2 border-white rounded-full"></div>
                                </div>
                                <h2 class="text-xl font-bold text-gray-900"><?php echo htmlspecialchars($profile['name']); ?></h2>
                                <p class="text-sm text-gray-500 mb-6"><?php echo htmlspecialchars($profile['email']); ?></p>
                                
                                <div class="pt-6 border-t border-gray-50 flex flex-col gap-2">
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="text-gray-400 uppercase font-semibold tracking-wider">Member Since</span>
                                        <span class="text-gray-700 font-medium">Active</span>
                                    </div>
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="text-gray-400 uppercase font-semibold tracking-wider">Account Type</span>
                                        <span class="text-gray-700 font-medium">Customer</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Edit Form -->
                        <div class="lg:col-span-2">
                            <div class="bg-white rounded-2xl shadow-card border border-gray-100 overflow-hidden">
                                <div class="px-6 py-5 border-b border-gray-50">
                                    <h3 class="text-lg font-bold text-gray-900">Personal Information</h3>
                                    <p class="text-xs text-gray-500">Update your contact details and location</p>
                                </div>
                                
                                <form method="post" class="p-6 space-y-6">
                                    <!-- Name & Email -->
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div class="space-y-2">
                                            <label for="name" class="block text-sm font-semibold text-gray-700">Full Name</label>
                                            <div class="relative">
                                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                                                    <i class="fas fa-user text-xs"></i>
                                                </span>
                                                <input type="text" id="name" name="name" required 
                                                    value="<?php echo htmlspecialchars($profile['name']); ?>" 
                                                    class="w-full pl-9 pr-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm transition-all outline-none input-focus"
                                                    placeholder="Enter your full name">
                                            </div>
                                        </div>
                                        <div class="space-y-2">
                                            <label for="email" class="block text-sm font-semibold text-gray-700">Email Address</label>
                                            <div class="relative">
                                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                                                    <i class="fas fa-envelope text-xs"></i>
                                                </span>
                                                <input type="email" id="email" disabled 
                                                    value="<?php echo htmlspecialchars($profile['email']); ?>" 
                                                    class="w-full pl-9 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-400 cursor-not-allowed">
                                            </div>
                                            <p class="text-[10px] text-gray-400 italic">Email cannot be changed</p>
                                        </div>
                                    </div>

                                    <!-- Phone & City -->
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div class="space-y-2">
                                            <label for="phone" class="block text-sm font-semibold text-gray-700">Phone Number</label>
                                            <div class="relative">
                                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                                                    <i class="fas fa-phone text-xs"></i>
                                                </span>
                                                <input type="tel" id="phone" name="phone" 
                                                    value="<?php echo htmlspecialchars($profile['phone']); ?>" 
                                                    class="w-full pl-9 pr-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm transition-all outline-none input-focus"
                                                    placeholder="+1 (555) 000-0000">
                                            </div>
                                        </div>
                                        <div class="space-y-2">
                                            <label for="city" class="block text-sm font-semibold text-gray-700">City</label>
                                            <div class="relative">
                                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                                                    <i class="fas fa-map-marker-alt text-xs"></i>
                                                </span>
                                                <input type="text" id="city" name="city" 
                                                    value="<?php echo htmlspecialchars($profile['city']); ?>" 
                                                    class="w-full pl-9 pr-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm transition-all outline-none input-focus"
                                                    placeholder="e.g. New York">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Address -->
                                    <div class="space-y-2">
                                        <label for="address" class="block text-sm font-semibold text-gray-700">Residential Address</label>
                                        <textarea id="address" name="address" rows="3" 
                                            class="w-full px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm transition-all outline-none input-focus resize-none"
                                            placeholder="Enter your full street address"><?php echo htmlspecialchars($profile['address']); ?></textarea>
                                    </div>

                                    <!-- Actions -->
                                    <div class="pt-4 flex items-center justify-end gap-3 border-t border-gray-50">
                                        <button type="reset" class="px-4 py-2 text-sm font-semibold text-gray-500 hover:text-gray-700 transition-colors">
                                            Discard Changes
                                        </button>
                                        <button type="submit" class="inline-flex items-center gap-2 bg-fes-red hover:bg-[#b71c1c] text-white font-bold px-6 py-2.5 rounded-xl text-sm shadow-sm transition-all hover:shadow-md active:scale-95">
                                            <i class="fas fa-save"></i> Save Profile
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        (function () {
            const btn = document.getElementById    ('fes-dashboard-menu-btn');
            const sidebar = document.getElementById('fes-dashboard-sidebar');
            const overlay = document.getElementById('fes-dashboard-overlay');
            
            if (!btn || !sidebar || !overlay) return;

            function openSidebar() {
                sidebar.classList.remove('-translate-x-full');
                sidebar.classList.add('translate-x-0');
                overlay.classList.remove('hidden');
                overlay.classList.add('opacity-100');
                btn.setAttribute('aria-expanded', 'true');
            }

            function closeSidebar() {
                sidebar.classList.add('-translate-x-full');
                sidebar.classList.remove('translate-x-0');
                overlay.classList.add('hidden');
                overlay.classList.remove('opacity-100');
                btn.setAttribute('aria-expanded', 'false');
            }

            btn.addEventListener('click', function () {
                const isOpen = sidebar.classList.contains('translate-x-0');
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
