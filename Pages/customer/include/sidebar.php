<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get user info from session
$userName = $_SESSION['name'] ?? 'Customer';
$userInitials = '';
if (!empty($userName)) {
    $nameParts = explode(' ', $userName);
    $userInitials = strtoupper(substr($nameParts[0], 0, 1));
    if (count($nameParts) > 1) {
        $userInitials .= strtoupper(substr($nameParts[1], 0, 1));
    }
}
?>
<aside id="fes-dashboard-sidebar" class="fixed inset-y-0 left-0 z-40 w-64 flex flex-col bg-fes-dark text-white -translate-x-full transition-transform duration-200 ease-out md:translate-x-0 md:static md:flex">
    <!-- Logo Section -->
    <a href="../../Pages/index.php" class="h-24 px-7 border-b border-white/10 flex items-center gap-3 hover:bg-white/5 transition no-underline">
        <img src="../../assets/images/logo.png" alt="FES Logo" class="h-10 w-auto">
        <div class="font-semibold tracking-wide text-white">FES</div>
    </a>

    <nav class="flex-1 px-3 py-4">
        <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-lg bg-fes-red shadow-md shadow-black/10 font-medium">
            <i class="fas fa-th-large w-5"></i>
            Dashboard
        </a>
        <a href="#" class="mt-1 flex items-center gap-3 px-4 py-3 rounded-lg text-white/80 hover:text-white hover:bg-white/5 transition">
            <i class="fas fa-search w-5"></i>
            Browse Equipment
        </a>
        <a href="#" class="mt-1 flex items-center gap-3 px-4 py-3 rounded-lg text-white/80 hover:text-white hover:bg-white/5 transition">
            <i class="fas fa-calendar-check w-5"></i>
            My Bookings
        </a>
        <a href="#" class="mt-1 flex items-center gap-3 px-4 py-3 rounded-lg text-white/80 hover:text-white hover:bg-white/5 transition">
            <i class="fas fa-file-invoice-dollar w-5"></i>
            Payments
        </a>
        <a href="#" class="mt-1 flex items-center gap-3 px-4 py-3 rounded-lg text-white/80 hover:text-white hover:bg-white/5 transition">
            <i class="fas fa-user w-5"></i>
            Profile
        </a>
    </nav>

    <!-- User Info & Logout -->
    <div class="px-6 py-5 border-t border-white/10 flex items-center gap-3">
        <div class="h-10 w-10 rounded-full bg-white/20 flex items-center justify-center font-semibold text-sm">
            <?php echo htmlspecialchars($userInitials); ?>
        </div>
        <div class="min-w-0 flex-1">
            <div class="text-sm font-medium truncate"><?php echo htmlspecialchars($userName); ?></div>
            <div class="text-xs text-white/60">Customer</div>
        </div>
        <a href="../auth/logout.php" class="text-white/60 hover:text-white transition" title="Logout">
            <i class="fas fa-sign-out-alt"></i>
        </a>
    </div>
</aside>
