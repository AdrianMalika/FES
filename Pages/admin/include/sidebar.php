<aside id="fes-dashboard-sidebar" class="fixed inset-y-0 left-0 z-40 w-64 flex flex-col bg-fes-dark text-white -translate-x-full transition-transform duration-200 ease-out md:translate-x-0 md:static md:flex">
    <div class="h-24 px-7 border-b border-white/10 flex items-center gap-3">
        <i class="fas fa-tractor text-xl"></i>
        <div class="font-semibold tracking-wide">FES ADMIN</div>
    </div>

    <nav class="flex-1 px-3 py-4">
        <a href="#" class="flex items-center gap-3 px-4 py-3 rounded-lg bg-fes-red shadow-md shadow-black/10 font-medium">
            <i class="fas fa-th-large w-5"></i>
            Dashboard
        </a>
        <a href="#" class="mt-1 flex items-center gap-3 px-4 py-3 rounded-lg text-white/80 hover:text-white hover:bg-white/5 transition">
            <i class="fas fa-truck-pickup w-5"></i>
            Equipment
        </a>
        <a href="#" class="mt-1 flex items-center gap-3 px-4 py-3 rounded-lg text-white/80 hover:text-white hover:bg-white/5 transition">
            <i class="fas fa-calendar-check w-5"></i>
            Bookings
        </a>
        <a href="#" class="mt-1 flex items-center gap-3 px-4 py-3 rounded-lg text-white/80 hover:text-white hover:bg-white/5 transition">
            <i class="fas fa-users w-5"></i>
            Users
        </a>
        <a href="add_operator.php" class="mt-1 flex items-center gap-3 px-4 py-3 rounded-lg text-white/80 hover:text-white hover:bg-white/5 transition">
            <i class="fas fa-users w-5"></i>
            Add Operator
        </a>
        <a href="#" class="mt-1 flex items-center gap-3 px-4 py-3 rounded-lg text-white/80 hover:text-white hover:bg-white/5 transition">
            <i class="fas fa-chart-line w-5"></i>
            Reports
        </a>
    </nav>

    <div class="px-6 py-5 border-t border-white/10 flex items-center gap-3">
        <div class="h-10 w-10 rounded-full bg-white/20 flex items-center justify-center font-semibold">AD</div>
        <div class="min-w-0">
            <div class="text-sm font-medium truncate">Admin User</div>
            <div class="text-xs text-white/60">Manager</div>
        </div>
        <button class="ml-auto text-white/60 hover:text-white transition" title="Logout">
            <i class="fas fa-sign-out-alt"></i>
        </button>
    </div>
</aside>
