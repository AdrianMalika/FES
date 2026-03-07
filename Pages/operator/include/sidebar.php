<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get user info from session
$userName = $_SESSION['name'] ?? 'Operator';
$userInitials = '';
if (!empty($userName)) {
    $nameParts = explode(' ', $userName);
    $userInitials = strtoupper(substr($nameParts[0], 0, 1));
    if (count($nameParts) > 1) {
        $userInitials .= strtoupper(substr($nameParts[1], 0, 1));
    }
}
?>
<style>
/* ── FES Sidebar Reskin ── */
#fes-dashboard-sidebar {
    background: #2b2b2b !important;
    border-right: 1px solid rgba(255,255,255,.06);
}

/* Header */
#fes-dashboard-sidebar > div:first-child {
    height: 70px !important;
    padding: 0 22px !important;
    border-bottom: 1px solid rgba(255,255,255,.08) !important;
    gap: 11px !important;
}
#fes-dashboard-sidebar > div:first-child .fas.fa-tractor {
    width: 34px;
    height: 34px;
    background: #D32F2F;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px !important;
    box-shadow: 0 4px 10px rgba(211,47,47,.4);
    flex-shrink: 0;
}
#fes-dashboard-sidebar > div:first-child .font-semibold {
    font-family: 'Playfair Display', serif !important;
    font-size: 1.15rem !important;
    font-weight: 700 !important;
    letter-spacing: .02em;
    color: #fff;
}

/* Nav container */
#fes-dashboard-sidebar nav {
    padding: 12px 10px !important;
}

/* All nav links */
#fes-dashboard-sidebar nav a {
    border-radius: 8px !important;
    padding: 14px 18px !important;
    font-size: 1.05rem !important;
    font-weight: 500 !important;
    color: rgba(255,255,255,.65) !important;
    margin-bottom: 2px;
    gap: 16px !important;
    position: relative;
    transition: background .2s, color .2s !important;
}
#fes-dashboard-sidebar nav a i {
    color: rgba(255,255,255,.38) !important;
    font-size: 1.1rem;
    transition: color .2s;
}
#fes-dashboard-sidebar nav a:hover {
    background: rgba(255,255,255,.07) !important;
    color: #fff !important;
}
#fes-dashboard-sidebar nav a:hover i {
    color: rgba(255,255,255,.85) !important;
}

/* Active link */
#fes-dashboard-sidebar nav a.bg-fes-red {
    background: #D32F2F !important;
    color: #fff !important;
    box-shadow: 0 4px 16px rgba(211,47,47,.3) !important;
}
#fes-dashboard-sidebar nav a.bg-fes-red::before {
    content: '';
    position: absolute;
    left: 0; top: 50%;
    transform: translateY(-50%);
    width: 3px; height: 55%;
    background: rgba(255,255,255,.5);
    border-radius: 0 3px 3px 0;
}
#fes-dashboard-sidebar nav a.bg-fes-red i {
    color: #fff !important;
}

/* Logout footer */
#fes-dashboard-sidebar .sidebar-footer {
    padding: 12px 10px 18px;
    border-top: 1px solid rgba(255,255,255,.08);
}
.logout-btn {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 14px;
    border-radius: 8px;
    text-decoration: none;
    width: 100%;
    transition: background .2s;
}
.logout-btn:hover { background: rgba(211,47,47,.14); }
.logout-icon { font-size: 1.1rem; color: rgba(255,255,255,.35); transition: color .2s; }
.logout-text { font-size: 1.05rem; font-weight: 500; color: rgba(255,255,255,.45); transition: color .2s; }
.logout-btn:hover .logout-icon,
.logout-btn:hover .logout-text { color: #ef5350; }
</style>
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
            <i class="fas fa-briefcase w-5"></i>
            My Jobs
        </a>
        <a href="#" class="mt-1 flex items-center gap-3 px-4 py-3 rounded-lg text-white/80 hover:text-white hover:bg-white/5 transition">
            <i class="fas fa-tools w-5"></i>
            Equipment Status
        </a>
        <a href="#" class="mt-1 flex items-center gap-3 px-4 py-3 rounded-lg text-white/80 hover:text-white hover:bg-white/5 transition">
            <i class="fas fa-route w-5"></i>
            Schedule
        </a>
        <a href="#" class="mt-1 flex items-center gap-3 px-4 py-3 rounded-lg text-white/80 hover:text-white hover:bg-white/5 transition">
            <i class="fas fa-user w-5"></i>
            Profile
        </a>
    </nav>

    <!-- User Info & Logout -->
    <button class="w-full px-6 py-5 border-t border-white/10 text-white/60 hover:text-white hover:bg-white/5 transition flex items-center justify-end gap-2" title="Logout" onclick="window.location.href='../auth/logout.php'">
        <span>Logout</span>
        <i class="fas fa-sign-out-alt"></i>
    </button>
</aside>
