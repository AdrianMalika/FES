<?php
// Start session at the very beginning, before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fetch equipment from database
$equipment = [];
try {
    require_once '../includes/database.php';
    $conn = getDBConnection();
    $sql = "SELECT * FROM equipment ORDER BY created_at DESC";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $equipment[] = $row;
        }
    }
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    $equipment = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FES — Equipment Catalogue</title>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
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
                        fes: { red: '#D32F2F', dark: '#1a1a1a', mid: '#2e2e2e' }
                    },
                    fontFamily: {
                        display: ['"Barlow Condensed"', 'sans-serif'],
                        body: ['Barlow', 'sans-serif'],
                    }
                }
            }
        };
    </script>
    <style>
        * { font-family: 'Barlow', sans-serif; }
        h1, h2, h3, .display { font-family: 'Barlow Condensed', sans-serif; }

        :root {
            --red: #D32F2F;
            --red-deep: #b71c1c;
            --dark: #1a1a1a;
            --mid: #2e2e2e;
        }

        /* Hero diagonal slice */
        .hero-section {
            background: var(--dark);
            clip-path: polygon(0 0, 100% 0, 100% 88%, 0 100%);
            padding-bottom: 7rem;
        }

        /* Noise texture overlay */
        .noise::after {
            content: '';
            position: absolute;
            inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.04'/%3E%3C/svg%3E");
            pointer-events: none;
            mix-blend-mode: overlay;
            opacity: 0.4;
        }

        /* Grid lines background */
        .grid-bg {
            background-image:
                linear-gradient(rgba(211,47,47,0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(211,47,47,0.04) 1px, transparent 1px);
            background-size: 60px 60px;
        }

        /* Red accent line */
        .accent-line {
            display: block;
            width: 3rem;
            height: 3px;
            background: var(--red);
            margin-bottom: 1.5rem;
        }

        /* Card hover */
        .equip-card {
            transition: transform 0.35s cubic-bezier(.22,.68,0,1.2), box-shadow 0.35s ease;
            background: #fff;
        }
        .equip-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 24px 60px -12px rgba(211,47,47,0.18), 0 8px 32px -8px rgba(0,0,0,0.12);
        }

        /* Image overlay effect */
        .card-img-wrap::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, transparent 50%, rgba(26,26,26,0.65) 100%);
            transition: opacity 0.35s ease;
        }

        /* Status dots */
        .status-dot {
            width: 7px; height: 7px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 6px;
        }

        /* Filter chips active */
        .chip-active {
            background: var(--red) !important;
            color: white !important;
            border-color: var(--red) !important;
        }

        /* Modal backdrop */
        #modalOv { backdrop-filter: blur(6px); }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: #f5f5f5; }
        ::-webkit-scrollbar-thumb { background: var(--red); border-radius: 99px; }

        /* Stat counter animation */
        @keyframes countUp {
            from { opacity: 0; transform: translateY(12px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .stat-val { animation: countUp 0.6s ease both; }

        /* Card stagger */
        @keyframes fadeSlideIn {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .equip-card { animation: fadeSlideIn 0.45s ease both; }

        /* Red diagonal accent in hero */
        .hero-accent {
            position: absolute;
            right: 0; top: 0; bottom: 0;
            width: 42%;
            background: linear-gradient(135deg, transparent 30%, rgba(211,47,47,0.07) 100%);
            pointer-events: none;
        }

        /* Search input focus ring */
        #searchInput:focus { box-shadow: 0 0 0 3px rgba(211,47,47,0.18); }

        /* Skeleton shimmer (loading) */
        @keyframes shimmer {
            0%   { background-position: -400px 0; }
            100% { background-position: 400px 0; }
        }
        .skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e8e8e8 50%, #f0f0f0 75%);
            background-size: 800px 100%;
            animation: shimmer 1.4s infinite;
            border-radius: 8px;
        }

        /* Modal entrance */
        @keyframes modalIn {
            from { opacity: 0; transform: scale(0.94) translateY(16px); }
            to   { opacity: 1; transform: scale(1) translateY(0); }
        }
        #modalContent { animation: modalIn 0.28s cubic-bezier(.22,.68,0,1.2) both; }

        /* Spec tag */
        .spec-tag {
            border: 1px solid #e5e5e5;
            background: #fafafa;
            transition: all 0.2s;
            font-family: 'Barlow Condensed', sans-serif;
            font-weight: 600;
            letter-spacing: 0.03em;
            font-size: 0.78rem;
        }
        .spec-tag:hover { border-color: var(--red); background: rgba(211,47,47,0.05); color: var(--red); }
    </style>
</head>

<body class="bg-gray-50 font-body text-gray-900 antialiased">
    <?php include '../includes/header.php'; ?>

    <!-- ═══════════ HERO ═══════════ -->
    <section class="hero-section relative overflow-hidden noise">
        <div class="hero-accent"></div>

        <!-- Large background tractor icon -->
        <div class="absolute right-0 bottom-0 translate-x-16 opacity-5 pointer-events-none select-none">
            <i class="fas fa-tractor" style="font-size: 480px; color: #D32F2F;"></i>
        </div>

        <div class="max-w-7xl mx-auto px-6 py-24 lg:py-32 relative z-10">
            <div class="max-w-2xl">
                <!-- Eyebrow -->
                <div class="flex items-center gap-3 mb-6">
                    <span class="text-fes-red font-display font-700 text-sm uppercase tracking-[0.2em]">Farming & Engineering Services</span>
                    <span class="block h-px w-12 bg-fes-red opacity-60"></span>
                </div>

                <!-- Heading -->
                <h1 class="font-display font-900 text-white leading-none mb-6" style="font-size: clamp(3rem,7vw,5.5rem); letter-spacing:-0.01em;">
                    Equipment<br>
                    <span class="text-fes-red">Catalogue</span>
                </h1>

                <p class="text-gray-400 text-base leading-relaxed mb-10 max-w-lg" style="font-size:1.05rem;">
                    Browse our full fleet of farming &amp; engineering machinery. Check live availability and book the equipment you need — instantly.
                </p>

                <?php if(isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])): ?>
                    <a href="#available-fleet"
                       class="inline-flex items-center gap-3 bg-fes-red hover:bg-red-700 text-white font-display font-700 uppercase tracking-wider px-8 py-4 rounded-sm shadow-lg transition-all duration-300 hover:shadow-fes-red/40 hover:shadow-xl"
                       style="font-size:1rem; letter-spacing:0.1em;">
                        <i class="fas fa-calendar-check text-sm"></i>
                        Book Equipment
                    </a>
                <?php else: ?>
                    <a href="auth/signin.php?redirect=equipment.php"
                       class="inline-flex items-center gap-3 bg-fes-red hover:bg-red-700 text-white font-display font-700 uppercase tracking-wider px-8 py-4 rounded-sm shadow-lg transition-all duration-300 hover:shadow-xl"
                       style="font-size:1rem; letter-spacing:0.1em;">
                        <i class="fas fa-calendar-check text-sm"></i>
                        Book Equipment
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Bottom red stripe -->
        <div class="absolute bottom-0 left-0 w-full h-1 bg-fes-red opacity-70"></div>
    </section>


    <!-- ═══════════ STATS BAR ═══════════ -->
    <div class="bg-white border-b border-gray-100 shadow-sm -mt-1">
        <div class="max-w-7xl mx-auto px-6 py-7 grid grid-cols-2 md:grid-cols-4 divide-x divide-gray-100">
            <?php
            $stats = [
                ['id'=>'sTotal','label'=>'Total Equipment','icon'=>'fa-layer-group','color'=>'text-gray-900'],
                ['id'=>'sAvail','label'=>'Available Now','icon'=>'fa-circle-check','color'=>'text-fes-red'],
                ['id'=>'sBooked','label'=>'Currently Booked','icon'=>'fa-calendar','color'=>'text-blue-600'],
                ['id'=>'sMaint','label'=>'Under Maintenance','icon'=>'fa-wrench','color'=>'text-orange-500'],
            ];
            foreach($stats as $i => $s): ?>
            <div class="px-6 <?= $i===0?'pl-0':'' ?> flex items-center gap-4">
                <i class="fas <?= $s['icon'] ?> text-xl <?= $s['color'] ?> opacity-60"></i>
                <div>
                    <div class="stat-val text-2xl font-display font-800 <?= $s['color'] ?>" id="<?= $s['id'] ?>">—</div>
                    <div class="text-xs text-gray-500 uppercase tracking-wider mt-0.5"><?= $s['label'] ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>


    <!-- ═══════════ TOOLBAR ═══════════ -->
    <div class="bg-white border-b border-gray-100 sticky top-0 z-30 shadow-sm">
        <div class="max-w-7xl mx-auto px-6 py-4 flex flex-col lg:flex-row gap-4 items-center">

            <!-- Search -->
            <div class="relative flex-1 min-w-[220px] w-full lg:w-auto">
                <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm pointer-events-none"></i>
                <input type="text" id="searchInput" placeholder="Search equipment, category, spec…"
                       class="w-full border border-gray-200 bg-gray-50 rounded-sm pl-11 pr-4 py-2.5 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-fes-red focus:bg-white transition-all duration-200">
            </div>

            <!-- Category chips -->
            <div class="flex gap-2 flex-wrap">
                <?php
                $cats = ['all'=>'All','Farming'=>'Farming','Engineering'=>'Engineering','Transport'=>'Transport','Power'=>'Power'];
                foreach($cats as $val => $label): ?>
                <button class="chip border text-xs font-display font-700 uppercase tracking-widest px-4 py-2 rounded-sm transition-all duration-200 <?= $val==='all' ? 'chip-active border-fes-red' : 'border-gray-200 bg-white text-gray-600 hover:border-fes-red hover:text-fes-red' ?>"
                        data-cat="<?= $val ?>"><?= $label ?></button>
                <?php endforeach; ?>
            </div>

            <!-- Sort -->
            <select id="sortSel"
                    class="border border-gray-200 bg-white rounded-sm px-4 py-2.5 text-sm text-gray-700 focus:outline-none focus:border-fes-red transition-colors duration-200 cursor-pointer">
                <option value="name">Name A–Z</option>
                <option value="rate-asc">Rate: Low → High</option>
                <option value="rate-desc">Rate: High → Low</option>
                <option value="available">Available First</option>
            </select>
        </div>
    </div>


    <!-- ═══════════ EQUIPMENT GRID ═══════════ -->
    <section class="py-16 grid-bg min-h-[50vh]">
        <div class="max-w-7xl mx-auto px-6">

            <!-- Section heading -->
            <div class="flex items-end justify-between mb-10" id="available-fleet">
                <div>
                    <span class="accent-line"></span>
                    <h2 class="font-display font-800 text-3xl lg:text-4xl text-gray-900" style="letter-spacing:-0.01em;">
                        Available Fleet
                    </h2>
                </div>
                <div class="text-sm text-gray-500" id="resultCount"></div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-7" id="equipGrid">
                <!-- cards injected by JS -->
            </div>
        </div>
    </section>


    <!-- ═══════════ MODAL ═══════════ -->
    <div class="fixed inset-0 bg-black/70 z-50 hidden items-start justify-center p-4 pt-14 md:p-5 md:pt-20" id="modalOv">
        <div id="modalContent" class="bg-white rounded-sm shadow-2xl w-full max-w-4xl max-h-[calc(100vh-2.25rem)] md:max-h-[calc(100vh-3.25rem)] overflow-hidden flex flex-col">

            <!-- Modal Header -->
            <div class="bg-white border-b border-gray-100 px-6 py-4 flex items-start justify-between z-10 shrink-0">
                <div>
                    <h2 class="font-display font-800 text-2xl text-gray-900" id="mName">—</h2>
                    <div id="mBadge" class="mt-2"></div>
                </div>
                <button onclick="closeModal()"
                        class="text-gray-400 hover:text-fes-red hover:bg-red-50 rounded-sm p-2 transition-all duration-200">
                    <i class="fas fa-xmark text-lg"></i>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6 overflow-y-auto flex-1">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">

                    <!-- LEFT: Image + quick stats -->
                    <div class="space-y-5">
                        <div class="bg-gray-100 rounded-sm overflow-hidden h-[240px] md:h-[300px] flex items-center justify-center relative" id="mImg">
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="bg-fes-red rounded-sm p-4 text-white">
                                <div class="text-xs uppercase tracking-widest opacity-70 mb-1">Daily Rate</div>
                                <div class="font-display font-800 text-xl" id="mRate">—</div>
                            </div>
                            <div class="bg-fes-dark rounded-sm p-4 text-white">
                                <div class="text-xs uppercase tracking-widest opacity-70 mb-1">Equipment ID</div>
                                <div class="font-display font-700 text-xl" id="mId">—</div>
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT: Details -->
                    <div class="space-y-6">
                        <div class="grid grid-cols-2 gap-4">
                            <?php
                            $fields = [
                                ['id'=>'mCat','label'=>'Category','icon'=>'fa-layer-group'],
                                ['id'=>'mOp','label'=>'Operator','icon'=>'fa-user-tie'],
                                ['id'=>'mLoc','label'=>'Location','icon'=>'fa-map-marker-alt'],
                                ['id'=>'mServ','label'=>'Last Service','icon'=>'fa-wrench'],
                            ];
                            foreach($fields as $f): ?>
                            <div class="bg-gray-50 border border-gray-100 rounded-sm p-4 hover:border-fes-red/30 transition-colors duration-200">
                                <div class="text-xs uppercase tracking-widest text-gray-500 mb-1 flex items-center gap-2">
                                    <i class="fas <?= $f['icon'] ?> text-fes-red text-xs"></i>
                                    <?= $f['label'] ?>
                                </div>
                                <div class="font-display font-700 text-base text-gray-900" id="<?= $f['id'] ?>">—</div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div>
                            <div class="text-xs uppercase tracking-widest text-gray-500 mb-3 flex items-center gap-2">
                                <i class="fas fa-cogs text-fes-red text-xs"></i>
                                Specifications
                            </div>
                            <div class="flex flex-wrap gap-2" id="mSpecs"></div>
                        </div>

                        <div>
                            <div class="text-xs uppercase tracking-widest text-gray-500 mb-3 flex items-center gap-2">
                                <i class="fas fa-info-circle text-fes-red text-xs"></i>
                                Description
                            </div>
                            <p class="text-gray-600 text-sm leading-relaxed bg-gray-50 border border-gray-100 rounded-sm p-4" id="mDesc">—</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="bg-gray-50 border-t border-gray-100 px-6 py-4 flex gap-4 shrink-0">
                <button onclick="closeModal()"
                        class="flex-1 py-3 border-2 border-gray-200 text-gray-600 hover:border-gray-400 font-display font-700 uppercase tracking-wider text-sm rounded-sm transition-all duration-200">
                    Close
                </button>
                <button id="mBookBtn"
                        class="flex-1 py-3 bg-fes-red hover:bg-red-700 text-white font-display font-800 uppercase tracking-wider text-sm rounded-sm shadow-lg hover:shadow-fes-red/30 hover:shadow-xl transition-all duration-300">
                    <i class="fas fa-calendar-check mr-2"></i>Book This Equipment
                </button>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>


    <!-- ═══════════ JAVASCRIPT ═══════════ -->
    <script>
    const equipment = <?php
        $out = [];
        foreach ($equipment as $item) {
            $specs = [];
            if (!empty($item['model'])) $specs[] = htmlspecialchars($item['model']);
            if (!empty($item['year_manufactured'])) $specs[] = (string)$item['year_manufactured'];
            if (!empty($item['weight_kg'])) $specs[] = $item['weight_kg'] . 'kg';
            if (!empty($item['fuel_type'])) $specs[] = htmlspecialchars($item['fuel_type']);
            $out[] = [
                'id'       => htmlspecialchars($item['equipment_id']),
                'name'     => htmlspecialchars($item['equipment_name']),
                'cat'      => ucfirst(htmlspecialchars($item['category'])),
                'icon'     => htmlspecialchars($item['icon'] ?? 'fa-tractor'),
                'status'   => htmlspecialchars($item['status']),
                'rate'     => floatval($item['daily_rate']),
                'operator' => !empty($item['operator_id']) ? 'Operator ' . htmlspecialchars($item['operator_id']) : 'Unassigned',
                'location' => htmlspecialchars($item['location']),
                'lastService' => !empty($item['last_maintenance']) ? date('M Y', strtotime($item['last_maintenance'])) : 'N/A',
                'specs'    => $specs,
                'desc'     => htmlspecialchars($item['description']),
                'image'    => !empty($item['image_path']) ? '../' . htmlspecialchars($item['image_path']) : null,
            ];
        }
        echo json_encode($out);
    ?>;

    let activeFilter = 'all', searchQuery = '', sortMode = 'name';

    /* ── Status badge ───────────────────────────────────────── */
    const STATUS = {
        available:   { dot:'bg-green-500',  pill:'bg-green-50 text-green-700 border-green-200',  label:'Available' },
        in_use:      { dot:'bg-blue-500',   pill:'bg-blue-50 text-blue-700 border-blue-200',     label:'Booked' },
        maintenance: { dot:'bg-orange-400', pill:'bg-orange-50 text-orange-700 border-orange-200',label:'Maintenance' },
        retired:     { dot:'bg-gray-400',   pill:'bg-gray-100 text-gray-600 border-gray-200',    label:'Retired' },
    };

    function badge(status) {
        const s = STATUS[status] || STATUS.retired;
        return `<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-display font-700 uppercase tracking-wider border ${s.pill}">
                    <span class="status-dot ${s.dot}"></span>${s.label}</span>`;
    }

    /* ── Filter / Sort ──────────────────────────────────────── */
    function filtered() {
        let list = [...equipment];
        if (searchQuery) list = list.filter(e =>
            e.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
            e.cat.toLowerCase().includes(searchQuery.toLowerCase()) ||
            e.specs.some(s => s.toLowerCase().includes(searchQuery.toLowerCase()))
        );
        if (activeFilter !== 'all') list = list.filter(e => e.cat === activeFilter);
        if (sortMode === 'name')       list.sort((a,b) => a.name.localeCompare(b.name));
        if (sortMode === 'rate-asc')   list.sort((a,b) => a.rate - b.rate);
        if (sortMode === 'rate-desc')  list.sort((a,b) => b.rate - a.rate);
        if (sortMode === 'available')  list.sort((a,b) => (a.status==='available'?-1:1) - (b.status==='available'?-1:1));
        return list;
    }

    /* ── Render Grid ────────────────────────────────────────── */
    function renderGrid() {
        const grid = document.getElementById('equipGrid');
        const list = filtered();
        document.getElementById('resultCount').textContent = `${list.length} item${list.length!==1?'s':''} found`;

        if (!list.length) {
            grid.innerHTML = `<div class="col-span-full py-20 text-center text-gray-400">
                <i class="fas fa-magnifying-glass text-4xl mb-4 block opacity-30"></i>
                <p class="font-display font-700 text-xl">No equipment found</p>
                <p class="text-sm mt-1">Try adjusting your search or filters</p>
            </div>`;
            return;
        }

        grid.innerHTML = list.map((item, i) => `
        <article class="equip-card rounded-sm overflow-hidden border border-gray-100 cursor-pointer group"
                 style="animation-delay:${i*0.06}s"
                 onclick="openModal('${item.id}')">

            <!-- Image -->
            <div class="card-img-wrap relative h-52 bg-gray-100 overflow-hidden">
                ${item.image
                    ? `<img src="${item.image}" alt="${item.name}"
                             class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                       <div class="absolute inset-0 items-center justify-center" style="display:none;">
                           <i class="fas ${item.icon} text-6xl text-gray-300"></i></div>`
                    : `<div class="absolute inset-0 flex items-center justify-center">
                           <i class="fas ${item.icon} text-6xl text-gray-200 group-hover:text-gray-300 transition-colors duration-300"></i></div>`
                }
                <!-- category pill -->
                <span class="absolute top-3 left-3 bg-fes-dark/80 text-white text-xs font-display font-700 uppercase tracking-widest px-3 py-1 rounded-sm backdrop-blur-sm">
                    ${item.cat}
                </span>
                <!-- quick-view -->
                <div class="absolute inset-0 flex items-end p-4 translate-y-full group-hover:translate-y-0 transition-transform duration-400 z-10">
                    <div class="w-full bg-white text-fes-dark text-xs font-display font-800 uppercase tracking-widest py-2.5 px-4 rounded-sm text-center shadow-lg">
                        <i class="fas fa-eye mr-2"></i>View Details
                    </div>
                </div>
            </div>

            <!-- Body -->
            <div class="p-5">
                <div class="mb-3">${badge(item.status)}</div>

                <h3 class="font-display font-800 text-lg leading-tight text-gray-900 mb-3 group-hover:text-fes-red transition-colors duration-300 line-clamp-2"
                    style="letter-spacing:-0.01em;">
                    ${item.name}
                </h3>

                <div class="space-y-1.5 mb-4">
                    <div class="flex items-center gap-2 text-xs text-gray-500">
                        <i class="fas fa-map-marker-alt text-fes-red w-3.5 flex-shrink-0"></i>
                        <span class="truncate">${item.location}</span>
                    </div>
                    <div class="flex items-center gap-2 text-xs text-gray-500">
                        <i class="fas fa-user-tie text-fes-red w-3.5 flex-shrink-0"></i>
                        <span class="truncate">${item.operator}</span>
                    </div>
                </div>

                <div class="flex flex-wrap gap-1.5 mb-4">
                    ${item.specs.slice(0,3).map(s=>`
                        <span class="spec-tag px-2.5 py-1 rounded-sm text-gray-600">${s}</span>
                    `).join('')}
                    ${item.specs.length>3?`<span class="spec-tag px-2.5 py-1 rounded-sm text-fes-red border-fes-red/20">+${item.specs.length-3}</span>`:''}
                </div>

                <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                    <div>
                        <span class="font-display font-900 text-xl text-fes-red" style="letter-spacing:-0.01em;">
                            MK ${item.rate.toLocaleString()}
                        </span>
                        <span class="text-xs text-gray-400 ml-1">/ day</span>
                    </div>
                    <button class="
                        px-4 py-2 text-xs font-display font-800 uppercase tracking-wider rounded-sm transition-all duration-200
                        ${item.status==='available'
                            ? 'bg-fes-red text-white hover:bg-red-700 shadow-sm hover:shadow-md'
                            : 'bg-gray-100 text-gray-400 cursor-not-allowed'}
                    "
                    onclick="event.stopPropagation(); bookEquipment('${item.id}')"
                    ${item.status!=='available'?'disabled':''}>
                        ${item.status==='available'
                            ? '<i class="fas fa-calendar-check mr-1.5"></i>Book'
                            : '<i class="fas fa-ban mr-1.5"></i>N/A'}
                    </button>
                </div>
            </div>
        </article>`).join('');
    }

    /* ── Stats ──────────────────────────────────────────────── */
    function renderStats() {
        document.getElementById('sTotal').textContent = equipment.length;
        document.getElementById('sAvail').textContent = equipment.filter(e=>e.status==='available').length;
        document.getElementById('sBooked').textContent = equipment.filter(e=>e.status==='in_use').length;
        document.getElementById('sMaint').textContent = equipment.filter(e=>e.status==='maintenance').length;
    }

    /* ── Modal ──────────────────────────────────────────────── */
    function openModal(id) {
        const item = equipment.find(e=>e.id===id);
        if (!item) return;

        document.getElementById('mName').textContent = item.name;
        document.getElementById('mBadge').innerHTML = badge(item.status);
        document.getElementById('mRate').textContent = `MK ${item.rate.toLocaleString()} / day`;
        document.getElementById('mId').textContent = item.id;
        document.getElementById('mCat').textContent = item.cat;
        document.getElementById('mOp').textContent = item.operator;
        document.getElementById('mLoc').textContent = item.location;
        document.getElementById('mServ').textContent = item.lastService;
        document.getElementById('mDesc').textContent = item.desc || 'No description provided.';

        const imgBox = document.getElementById('mImg');
        imgBox.innerHTML = item.image
            ? `<img src="${item.image}" alt="${item.name}" class="w-full h-full object-cover"
                    onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
               <div class="absolute inset-0 flex items-center justify-center" style="display:none;">
                   <i class="fas ${item.icon} text-7xl text-gray-300"></i></div>`
            : `<div class="w-full h-full flex items-center justify-center">
                   <i class="fas ${item.icon} text-7xl text-gray-300"></i></div>`;

        document.getElementById('mSpecs').innerHTML = item.specs.map(s=>
            `<span class="spec-tag px-3 py-1.5 rounded-sm text-gray-700">${s}</span>`
        ).join('') || '<span class="text-sm text-gray-400">No specifications listed.</span>';

        const btn = document.getElementById('mBookBtn');
        if (item.status === 'available') {
            btn.disabled = false;
            btn.className = 'flex-1 py-3 bg-fes-red hover:bg-red-700 text-white font-display font-800 uppercase tracking-wider text-sm rounded-sm transition-all duration-300';
            btn.innerHTML = '<i class="fas fa-calendar-check mr-2"></i>Book This Equipment';
            btn.onclick = () => bookEquipment(id);
        } else {
            btn.disabled = true;
            btn.className = 'flex-1 py-3 bg-gray-200 text-gray-400 font-display font-800 uppercase tracking-wider text-sm rounded-sm cursor-not-allowed';
            btn.innerHTML = '<i class="fas fa-ban mr-2"></i>Currently Unavailable';
        }

        const ov = document.getElementById('modalOv');
        ov.classList.remove('hidden');
        ov.classList.add('flex');
    }

    function closeModal() {
        const ov = document.getElementById('modalOv');
        ov.classList.add('hidden');
        ov.classList.remove('flex');
    }

    function bookEquipment(id) {
        const item = equipment.find(e=>e.id===id);
        if (item && item.status === 'available') {
            <?php if(isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])): ?>
                window.location.href = `booking.php?equipment_id=${id}`;
            <?php else: ?>
                window.location.href = `auth/signin.php?redirect=equipment.php&equipment_id=${id}`;
            <?php endif; ?>
        }
    }

    /* ── Event listeners ────────────────────────────────────── */
    document.getElementById('searchInput').addEventListener('input', e => {
        searchQuery = e.target.value;
        renderGrid();
    });

    document.getElementById('sortSel').addEventListener('change', e => {
        sortMode = e.target.value;
        renderGrid();
    });

    document.querySelectorAll('.chip').forEach(chip => {
        chip.addEventListener('click', () => {
            document.querySelectorAll('.chip').forEach(c => {
                c.classList.remove('chip-active');
                c.classList.add('border-gray-200','bg-white','text-gray-600');
            });
            chip.classList.add('chip-active');
            chip.classList.remove('border-gray-200','bg-white','text-gray-600');
            activeFilter = chip.dataset.cat;
            renderGrid();
        });
    });

    document.getElementById('modalOv').addEventListener('click', e => {
        if (e.target === e.currentTarget) closeModal();
    });

    document.addEventListener('keydown', e => { if(e.key==='Escape') closeModal(); });

    /* ── Init ───────────────────────────────────────────────── */
    renderStats();
    renderGrid();
    </script>
</body>
</html>

