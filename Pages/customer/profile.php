<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/signin.php');
    exit();
}

if (($_SESSION['role'] ?? '') !== 'customer') {
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
    <title>Profile - FES</title>
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
                    colors: { fes: { red: '#D32F2F', dark: '#424242' } },
                    fontFamily: {
                        display: ['"Barlow Condensed"', 'sans-serif'],
                        body: ['Barlow', 'sans-serif'],
                    },
                    boxShadow: { card: '0 4px 15px rgba(0,0,0,0.05)' }
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
            <?php include __DIR__ . '/include/sidebar.php'; ?>
            <div id="fes-dashboard-overlay" class="fixed inset-0 bg-black/40 z-30 hidden md:hidden"></div>

            <div class="flex-1 flex flex-col min-w-0 md:ml-64">
                <header class="bg-white px-6 py-7 flex items-center justify-between shadow-sm">
                    <div class="flex items-center gap-3">
                        <button id="fes-dashboard-menu-btn" class="md:hidden inline-flex items-center justify-center h-10 w-10 rounded-lg border border-gray-200 text-gray-600" aria-label="Open menu" aria-controls="fes-dashboard-sidebar" aria-expanded="false">
                            <i class="fas fa-bars"></i>
                        </button>
                        <div>
                            <div class="text-sm text-gray-500">Customer</div>
                            <h1 class="text-xl font-semibold text-gray-900">Profile</h1>
                            <p class="text-xs text-gray-500 mt-1">Manage your account details</p>
                        </div>
                    </div>
                </header>

                <main class="flex-1 overflow-y-auto p-6">
                    <?php if ($msg !== null): ?>
                        <?php $box = $msgType === 'success'
                            ? 'border-emerald-200 bg-emerald-50 text-emerald-900'
                            : 'border-red-200 bg-red-50 text-red-900'; ?>
                        <div class="mb-5 rounded-xl border px-4 py-3 text-sm <?php echo $box; ?>">
                            <?php echo htmlspecialchars($msg); ?>
                        </div>
                    <?php endif; ?>

                    <section class="bg-white rounded-xl shadow-card p-6 max-w-3xl">
                        <h2 class="text-base font-semibold text-gray-900 mb-5">Your information</h2>
                        <form method="post" class="space-y-5">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="name" class="block text-xs text-gray-500 uppercase tracking-wider mb-2">Full name</label>
                                    <input id="name" name="name" required value="<?php echo htmlspecialchars($profile['name']); ?>" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-fes-red/25 focus:border-fes-red">
                                </div>
                                <div>
                                    <label for="email" class="block text-xs text-gray-500 uppercase tracking-wider mb-2">Email</label>
                                    <input id="email" value="<?php echo htmlspecialchars($profile['email']); ?>" disabled class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm text-gray-500 bg-gray-50 cursor-not-allowed">
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="phone" class="block text-xs text-gray-500 uppercase tracking-wider mb-2">Phone</label>
                                    <input id="phone" name="phone" value="<?php echo htmlspecialchars($profile['phone']); ?>" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-fes-red/25 focus:border-fes-red">
                                </div>
                                <div>
                                    <label for="city" class="block text-xs text-gray-500 uppercase tracking-wider mb-2">City</label>
                                    <input id="city" name="city" value="<?php echo htmlspecialchars($profile['city']); ?>" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-fes-red/25 focus:border-fes-red">
                                </div>
                            </div>
                            <div>
                                <label for="address" class="block text-xs text-gray-500 uppercase tracking-wider mb-2">Address</label>
                                <textarea id="address" name="address" rows="4" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-fes-red/25 focus:border-fes-red"><?php echo htmlspecialchars($profile['address']); ?></textarea>
                            </div>
                            <div class="pt-2">
                                <button type="submit" class="inline-flex items-center gap-2 bg-fes-red hover:bg-[#b71c1c] text-white font-semibold px-4 py-2 rounded-lg text-sm">
                                    <i class="fas fa-save"></i> Save changes
                                </button>
                            </div>
                        </form>
                    </section>
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