<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$is_bookings = in_array($current_page, ['bookings', 'booking-details'], true);
?>
<style>
#fes-dashboard-sidebar {
    background: #28292c !important;
    border-right: 1px solid rgba(255,255,255,.07);
    box-shadow: 8px 0 24px rgba(0,0,0,.2);
}

#fes-dashboard-sidebar .brand {
    height: 84px;
    padding: 0 20px;
    border-bottom: 1px solid rgba(255,255,255,.09);
    gap: 12px;
}

#fes-dashboard-sidebar .brand-icon {
    width: 38px;
    height: 38px;
    background: #D93434;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    color: #fff;
    box-shadow: 0 6px 14px rgba(217,52,52,.32);
    flex-shrink: 0;
}

#fes-dashboard-sidebar .brand-name {
    font-family: 'Barlow Condensed', sans-serif;
    font-size: 1.28rem;
    font-weight: 800;
    letter-spacing: .05em;
    color: #fff;
    line-height: 1;
}

#fes-dashboard-sidebar .brand-sub {
    font-size: .68rem;
    letter-spacing: .14em;
    text-transform: uppercase;
    color: rgba(255,255,255,.45);
    margin-top: 4px;
}

#fes-dashboard-sidebar nav {
    padding: 16px 10px !important;
}

#fes-dashboard-sidebar nav .nav-title {
    font-size: .66rem;
    text-transform: uppercase;
    letter-spacing: .15em;
    color: rgba(255,255,255,.34);
    font-weight: 700;
    padding: 0 10px 8px;
}

#fes-dashboard-sidebar nav a {
    border-radius: 10px !important;
    padding: 12px 14px !important;
    font-size: 1.02rem !important;
    font-weight: 600 !important;
    color: rgba(255,255,255,.68) !important;
    margin-bottom: 6px;
    gap: 12px !important;
    position: relative;
    transition: background .2s, color .2s, transform .2s !important;
    font-family: 'Barlow', sans-serif !important;
}

#fes-dashboard-sidebar nav a i {
    color: rgba(255,255,255,.42) !important;
    font-size: 1.05rem;
    transition: color .2s;
    width: 22px;
    text-align: center;
}

#fes-dashboard-sidebar nav a:hover {
    background: rgba(255,255,255,.08) !important;
    color: #fff !important;
    transform: translateX(2px);
}

#fes-dashboard-sidebar nav a:hover i {
    color: rgba(255,255,255,.9) !important;
}

#fes-dashboard-sidebar nav a.bg-fes-red {
    background: #D93434 !important;
    color: #fff !important;
    box-shadow: 0 8px 18px rgba(217,52,52,.3) !important;
}

#fes-dashboard-sidebar nav a.bg-fes-red::before {
    content: '';
    position: absolute;
    left: 0;
    top: 50%;
    transform: translateY(-50%);
    width: 3px;
    height: 58%;
    background: rgba(255,255,255,.72);
    border-radius: 0 4px 4px 0;
}

#fes-dashboard-sidebar nav a.bg-fes-red i {
    color: #fff !important;
}

#fes-dashboard-sidebar .sidebar-footer {
    padding: 14px 10px 18px;
    border-top: 1px solid rgba(255,255,255,.09);
}

.logout-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 11px 14px;
    border-radius: 10px;
    text-decoration: none;
    width: 100%;
    border: 1px solid rgba(255,255,255,.1);
    background: rgba(255,255,255,.03);
    transition: background .2s, border-color .2s;
}

.logout-btn:hover {
    background: rgba(217,52,52,.16);
    border-color: rgba(217,52,52,.45);
}

.logout-icon {
    font-size: 1.02rem;
    color: rgba(255,255,255,.45);
    transition: color .2s;
}

.logout-text {
    font-size: 1rem;
    font-weight: 600;
    color: rgba(255,255,255,.62);
    transition: color .2s;
    font-family: 'Barlow', sans-serif;
}

.logout-btn:hover .logout-icon,
.logout-btn:hover .logout-text {
    color: #fff;
}
</style>

<aside id="fes-dashboard-sidebar" class="fixed inset-y-0 left-0 z-40 w-64 flex flex-col bg-fes-dark text-white -translate-x-full transition-transform duration-200 ease-out md:translate-x-0 md:flex">
    <a href="../../Pages/index.php" class="brand flex items-center hover:bg-white/5 transition no-underline">
        <div class="brand-icon">
            <i class="fas fa-tractor"></i>
        </div>
        <div>
            <div class="brand-name">FES</div>
            <div class="brand-sub">Customer Panel</div>
        </div>
    </a>

    <nav class="flex-1 px-3 py-4">
        <div class="nav-title">Main</div>
        <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-lg <?php echo $current_page === 'dashboard' ? 'bg-fes-red shadow-md shadow-black/10 font-medium' : 'text-white/80 hover:text-white hover:bg-white/5'; ?> transition">
            <i class="fas fa-th-large w-5"></i>
            Dashboard
        </a>
        <a href="../equipment.php" class="mt-1 flex items-center gap-3 px-4 py-3 rounded-lg <?php echo $current_page === 'equipment' ? 'bg-fes-red shadow-md shadow-black/10 font-medium' : 'text-white/80 hover:text-white hover:bg-white/5'; ?> transition">
            <i class="fas fa-search w-5"></i>
            Browse Equipment
        </a>
        <a href="bookings.php" class="mt-1 flex items-center gap-3 px-4 py-3 rounded-lg <?php echo $is_bookings ? 'bg-fes-red shadow-md shadow-black/10 font-medium' : 'text-white/80 hover:text-white hover:bg-white/5'; ?> transition">
            <i class="fas fa-calendar-check w-5"></i>
            Bookings
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

    <div class="sidebar-footer">
        <button class="logout-btn" title="Logout" onclick="window.location.href='../auth/logout.php'">
            <i class="fas fa-sign-out-alt logout-icon"></i>
            <span class="logout-text">Logout</span>
        </button>
    </div>
</aside>

