<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get current page filename
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<style>
/* ── FES Sidebar Reskin ── */
#fes-dashboard-sidebar {
    background: #2b2b2b !important;
    border-right: 1px solid rgba(255,255,255,.06) !important;
    border-left: none !important;
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    right: auto !important;
    height: 100vh !important;
    z-index: 40 !important;
    transform: translateX(-100%);
    transition: transform 0.3s ease-in-out;
}

#fes-dashboard-sidebar.show {
    transform: translateX(0);
}

@media (min-width: 768px) {
    #fes-dashboard-sidebar {
        transform: translateX(0) !important;
    }
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

<aside id="fes-dashboard-sidebar" class="fixed inset-y-0 left-0 z-40 w-72 flex flex-col bg-fes-dark text-white -translate-x-full transition-transform duration-200 ease-out md:translate-x-0 md:static md:flex">
    <div class="h-24 px-8 border-b border-white/10 flex items-center gap-3">
        <i class="fas fa-tractor text-xl"></i>
        <div class="font-semibold tracking-wide">FES</div>
    </div>

    <nav class="flex-1 px-4 py-4">
        <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-lg <?php echo $current_page === 'dashboard' ? 'bg-fes-red shadow-md shadow-black/10 font-medium' : 'text-white/80 hover:text-white hover:bg-white/5'; ?> transition">
            <i class="fas fa-th-large w-5"></i>
            Dashboard
        </a>
        <a href="add_equipment.php" class="mt-1 flex items-center gap-3 px-4 py-3 rounded-lg <?php echo $current_page === 'add_equipment' ? 'bg-fes-red shadow-md shadow-black/10 font-medium' : 'text-white/80 hover:text-white hover:bg-white/5'; ?> transition">
            <i class="fas fa-truck-pickup w-5"></i>
            Equipment
        </a>
        <a href="bookings.php" class="mt-1 flex items-center gap-3 px-4 py-3 rounded-lg <?php echo $current_page === 'bookings' ? 'bg-fes-red shadow-md shadow-black/10 font-medium' : 'text-white/80 hover:text-white hover:bg-white/5'; ?> transition">
            <i class="fas fa-calendar-check w-5"></i>
            Bookings
        </a>
        <a href="users.php" class="mt-1 flex items-center gap-3 px-4 py-3 rounded-lg <?php echo $current_page === 'users' ? 'bg-fes-red shadow-md shadow-black/10 font-medium' : 'text-white/80 hover:text-white hover:bg-white/5'; ?> transition">
            <i class="fas fa-users w-5"></i>
            Users
        </a>
        <a href="add_operator.php" class="mt-1 flex items-center gap-3 px-4 py-3 rounded-lg <?php echo $current_page === 'add_operator' ? 'bg-fes-red shadow-md shadow-black/10 font-medium' : 'text-white/80 hover:text-white hover:bg-white/5'; ?> transition">
            <i class="fas fa-hard-hat"></i>
            Add Operator
        </a>
        <a href="reports.php" class="mt-1 flex items-center gap-3 px-4 py-3 rounded-lg <?php echo $current_page === 'reports' ? 'bg-fes-red shadow-md shadow-black/10 font-medium' : 'text-white/80 hover:text-white hover:bg-white/5'; ?> transition">
            <i class="fas fa-chart-line w-5"></i>
            Reports
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="../auth/logout.php" class="logout-btn" data-tip="Logout">
            <i class="fas fa-sign-out-alt logout-icon"></i>
            <span class="logout-text">Logout</span>
        </a>
    </div>
</aside>
