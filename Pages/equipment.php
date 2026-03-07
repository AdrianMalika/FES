<?php
// Start session at the very beginning, before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FES - Equipment Catalogue</title>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
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
                    fontFamily: {
                        'playfair': ['Playfair Display', 'serif'],
                        'inter': ['Inter', 'sans-serif'],
                        'poppins': ['Poppins', 'sans-serif']
                    }
                }
            }
        };
    </script>
</head>

<body class="font-inter bg-gray-50 text-gray-900">
    <?php include '../includes/header.php'; ?>
    
    <!-- Hero Section -->
    <section class="bg-fes-dark text-white py-24 relative overflow-hidden">
        <div class="max-w-7xl mx-auto grid lg:grid-cols-2 gap-20 items-center relative z-10">
            <!-- Left Side: Content -->
            <div>
                <div class="text-sm font-bold text-fes-red uppercase tracking-wider mb-5">
                    Farming & Engineering Services
                </div>
                <h1 class="text-5xl lg:text-6xl font-black mb-6 leading-tight">
                    Equipment <span class="text-fes-red">Catalogue</span>
                </h1>

                <p class="text-lg text-gray-300 leading-relaxed mb-10">
                    Browse available equipment, check real-time availability, and book resources for your farming and engineering operations.
                </p>
                
                <div class="flex flex-col sm:flex-row gap-5 items-center">
                    <?php if(isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])): ?>
                        <a href="booking.php" class="inline-flex items-center justify-center px-10 py-4 bg-fes-red hover:bg-red-700 text-white font-bold rounded-lg shadow-lg transition-all duration-300 text-base uppercase tracking-wide">
                            + Book Equipment
                        </a>
                    <?php else: ?>
                        <a href="auth/signin.php?redirect=equipment.php" class="inline-flex items-center justify-center px-10 py-4 bg-fes-red hover:bg-red-700 text-white font-bold rounded-lg shadow-lg transition-all duration-300 text-base uppercase tracking-wide">
                            + Book Equipment
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Side: Icon -->
            <div class="text-center opacity-15">
                <i class="fas fa-tractor text-[300px]"></i>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <div class="bg-white border-b border-gray-200 py-8">
        <div class="max-w-7xl mx-auto grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
            <div class="stat-item">
                <div class="text-3xl font-bold text-gray-900" id="sTotal">—</div>
                <div class="text-sm text-gray-600">Total Equipment</div>
            </div>
            <div class="stat-item">
                <div class="text-3xl font-bold text-fes-red" id="sAvail">—</div>
                <div class="text-sm text-gray-600">Available Now</div>
            </div>
            <div class="stat-item">
                <div class="text-3xl font-bold text-gray-900" id="sBooked">—</div>
                <div class="text-sm text-gray-600">Currently Booked</div>
            </div>
            <div class="stat-item">
                <div class="text-3xl font-bold text-orange-600" id="sMaint">—</div>
                <div class="text-sm text-gray-600">Under Maintenance</div>
            </div>
        </div>
    </div>

    <!-- Toolbar Section -->
    <div class="bg-white border-b border-gray-200 py-6">
        <div class="max-w-7xl mx-auto flex flex-col lg:flex-row gap-6 items-center">
            <div class="relative flex-1 min-w-[220px]">
                <input type="text" id="searchInput" placeholder="Search by name, category, or specification…" 
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 pl-12 text-gray-900 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-fes-red focus:border-fes-red transition-colors duration-300">
                <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
            </div>
            
            <div class="flex gap-2 flex-wrap">
                <div class="bg-fes-red text-white border border-fes-red rounded-lg px-4 py-2 text-sm font-medium hover:bg-red-700 transition-colors duration-300 cursor-pointer" data-cat="all">
                    All
                </div>
                <div class="bg-gray-100 border border-gray-300 rounded-lg px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors duration-300 cursor-pointer" data-cat="Farming">
                    Farming
                </div>
                <div class="bg-gray-100 border border-gray-300 rounded-lg px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors duration-300 cursor-pointer" data-cat="Engineering">
                    Engineering
                </div>
                <div class="bg-gray-100 border border-gray-300 rounded-lg px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors duration-300 cursor-pointer" data-cat="Transport">
                    Transport
                </div>
                <div class="bg-gray-100 border border-gray-300 rounded-lg px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors duration-300 cursor-pointer" data-cat="Power">
                    Power
                </div>
            </div>
            
            <select class="bg-gray-100 border border-gray-300 rounded-lg px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-fes-red focus:border-fes-red transition-colors duration-300" id="sortSel">
                <option value="name">Sort: Name A-Z</option>
                <option value="rate-asc">Rate: Low → High</option>
                <option value="rate-desc">Rate: High → Low</option>
                <option value="available">Available First</option>
            </select>
        </div>
    </div>

    <!-- Main Equipment Grid -->
    <section class="py-16">
        <div class="max-w-7xl mx-auto">
            <div class="mb-8">
                <h2 class="text-3xl font-playfair font-bold text-gray-900 mb-4">Available Equipment</h2>
                <div class="w-10 h-1 bg-fes-red"></div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8" id="equipGrid">
                <!-- Equipment items will be dynamically inserted here -->
            </div>
        </div>
    </section>

    <!-- Equipment Details Modal -->
    <div class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-4" id="modalOv">
        <div class="bg-white rounded-xl shadow-2xl max-w-4xl max-h-[90vh] overflow-y-auto w-full">
            <div class="p-6 border-b border-gray-200 flex justify-between items-start">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900" id="mName">—</h2>
                    <div class="mt-2" id="mBadge"></div>
                </div>
                <button class="text-gray-400 hover:text-gray-600 transition-colors duration-200" onclick="closeModal()">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div class="p-6">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <div class="h-36 bg-gray-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-tractor text-6xl text-gray-400" id="mImg"></i>
                    </div>
                    
                    <div class="grid grid-cols-1 gap-4 text-sm">
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">Category</label>
                            <span id="mCat">—</span>
                        </div>
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">Equipment ID</label>
                            <span id="mId">—</span>
                        </div>
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">Daily Rate</label>
                            <span id="mRate" style="color: #D32F2F">—</span>
                        </div>
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">Operator</label>
                            <span id="mOp">—</span>
                        </div>
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">Location</label>
                            <span id="mLoc">—</span>
                        </div>
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">Last Service</label>
                            <span id="mServ">—</span>
                        </div>
                    </div>
                </div>
                
                <div class="mt-6">
                    <div class="text-sm font-medium text-gray-700 mb-2">Specifications</div>
                    <div class="flex flex-wrap gap-2" id="mSpecs">
                        <!-- Specs will be dynamically inserted here -->
                    </div>
                </div>
                
                <div class="mt-6">
                    <div class="text-sm font-medium text-gray-700 mb-2">Description</div>
                    <p class="text-gray-600 leading-relaxed" id="mDesc">—</p>
                </div>
            </div>
            
            <div class="p-6 border-t border-gray-200 bg-gray-50 rounded-b-xl">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <button class="w-full py-3 text-gray-700 hover:text-gray-900 transition-colors duration-200 border border-gray-300 rounded-lg hover:bg-gray-50" onclick="closeModal()">
                        Close
                    </button>
                    <button class="w-full py-3 text-white bg-fes-red hover:bg-red-700 transition-colors duration-200 rounded-lg" id="mBookBtn">
                        Book Now
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
     <?php include '../includes/footer.php'; ?>


    <script>
        // Equipment data
        const equipment = [
            {id:'EQ-001',name:'Tractor – MF 375',cat:'Farming',icon:'fa-tractor',status:'available',rate:45000,operator:'John Banda',location:'Blantyre Depot',lastService:'Jan 2026',specs:['75 HP','4WD','PTO Shaft'],desc:'Heavy-duty Massey Ferguson tractor for ploughing, harrowing, and ridging on large-scale farms.'},
            {id:'EQ-002',name:'Combine Harvester',cat:'Farming',icon:'fa-wheat-awn',status:'booked',rate:85000,operator:'Peter Mvula',location:'Lilongwe Hub',lastService:'Dec 2025',specs:['220 HP','7m Header','9000L Tank'],desc:'High-capacity combine harvester for maize and wheat. Currently booked through end of month.'},
            {id:'EQ-003',name:'Excavator – Komatsu PC200',cat:'Engineering',icon:'fa-helmet-safety',status:'available',rate:120000,operator:'Moses Chirwa',location:'Blantyre Depot',lastService:'Feb 2026',specs:['148 HP','20T','9.7m Reach','GPS'],desc:'Medium-duty hydraulic excavator for earthworks, trenching, and foundation digging.'},
            {id:'EQ-004',name:'Generator – 100 KVA',cat:'Power',icon:'fa-bolt',status:'available',rate:35000,operator:'Unassigned',location:'Limbe Store',lastService:'Jan 2026',specs:['100 KVA','3-Phase','Auto-Start','Silent'],desc:'Perkins-powered silent generator providing stable power for industrial and construction sites.'},
            {id:'EQ-005',name:'Irrigation Pump Set',cat:'Farming',icon:'fa-faucet',status:'available',rate:18000,operator:'Grace Nkhoma',location:'Blantyre Depot',lastService:'Jan 2026',specs:['15 HP','6 inch','200m³/hr','Diesel'],desc:'Heavy duty diesel irrigation pump suitable for large-scale surface and borehole water delivery.'},
            {id:'EQ-006',name:'Bulldozer – D6T CAT',cat:'Engineering',icon:'fa-truck-monster',status:'maintenance',rate:150000,operator:'Chris Phiri',location:'Workshop',lastService:'Ongoing',specs:['215 HP','19T','6-Way Blade','ROPS'],desc:'Undergoing scheduled maintenance. Estimated return to service: mid-March 2026.'},
            {id:'EQ-007',name:'Water Bowser – 5000L',cat:'Transport',icon:'fa-truck-ramp-box',status:'booked',rate:25000,operator:'Samuel Jere',location:'Lilongwe Hub',lastService:'Nov 2025',specs:['5000L','4WD','Spray Bar','Pump'],desc:'Mobile water bowser for dust suppression, construction support, and irrigation logistics.'},
            {id:'EQ-008',name:'Tipper Truck – 10T',cat:'Transport',icon:'fa-truck-ramp-box',status:'available',rate:60000,operator:'Richard Mwale',location:'Lilongwe Hub',lastService:'Feb 2026',specs:['10T Payload','Hydraulic Tip','Steel Body'],desc:'10-tonne tipper for aggregate, sand, and material transport across construction sites.'},
            {id:'EQ-009',name:'Boom Sprayer – 18m',cat:'Farming',icon:'fa-spray-can',status:'available',rate:22000,operator:'Alice Dube',location:'Blantyre Depot',lastService:'Jan 2026',specs:['18m Boom','2000L Tank','GPS Guidance'],desc:'GPS-guided boom sprayer for precision chemical application across large crop fields.'},
            {id:'EQ-010',name:'Compactor – Roller 10T',cat:'Engineering',icon:'fa-circle',status:'available',rate:55000,operator:'James Tembo',location:'Blantyre Depot',lastService:'Feb 2026',specs:['10T','Vibratory','Sprinkler','ROPS'],desc:'Smooth drum vibratory roller for road construction and compaction of sub-base layers.'},
            {id:'EQ-011',name:'Loader – JCB 3CX',cat:'Engineering',icon:'fa-person-digging',status:'booked',rate:75000,operator:'Frank Lungu',location:'Mzuzu Branch',lastService:'Jan 2026',specs:['74 HP','4-in-1 Bucket','Extendahoe','4WD'],desc:'Versatile backhoe loader for digging, loading, lifting, and site clearance.'},
            {id:'EQ-012',name:'Transformer – 50 KVA',cat:'Power',icon:'fa-plug',status:'maintenance',rate:28000,operator:'N/A',location:'Workshop',lastService:'Ongoing',specs:['50 KVA','11kV/440V','Oil-Cooled'],desc:'Step-down transformer undergoing electrical inspection. Estimated return to service: March 15, 2026.'}
        ];

        let activeFilter = 'all';
        let searchQuery = '';
        let sortMode = 'name';

        function getStatusBadge(status) {
            if(status === 'available') return '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800"><i class="fas fa-circle mr-2 text-xs"></i>Available</span>';
            if(status === 'booked') return '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800"><i class="fas fa-circle mr-2 text-xs"></i>Booked</span>';
            if(status === 'maintenance') return '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800"><i class="fas fa-circle mr-2 text-xs"></i>Maintenance</span>';
            return '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800"><i class="fas fa-circle mr-2 text-xs"></i>Unavailable</span>';
        }

        function getFilteredEquipment() {
            let filtered = equipment;
            
            // Apply search filter
            if(searchQuery) {
                filtered = filtered.filter(item => 
                    item.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
                    item.cat.toLowerCase().includes(searchQuery.toLowerCase()) ||
                    item.specs.some(spec => spec.toLowerCase().includes(searchQuery.toLowerCase()))
                );
            }
            
            // Apply category filter
            if(activeFilter !== 'all') {
                filtered = filtered.filter(item => item.cat === activeFilter);
            }
            
            // Apply sorting
            if(sortMode === 'name') {
                filtered.sort((a, b) => a.name.localeCompare(b.name));
            } else if(sortMode === 'rate-asc') {
                filtered.sort((a, b) => a.rate - b.rate);
            } else if(sortMode === 'rate-desc') {
                filtered.sort((a, b) => b.rate - a.rate);
            } else if(sortMode === 'available') {
                filtered.sort((a, b) => {
                    if(a.status === 'available' && b.status !== 'available') return -1;
                    if(a.status !== 'available' && b.status === 'available') return 1;
                    return 0;
                });
            }
            
            return filtered;
        }

        function renderEquipment() {
            const grid = document.getElementById('equipGrid');
            const filtered = getFilteredEquipment();
            
            if(filtered.length === 0) {
                grid.innerHTML = '<div class="col-span-full text-center py-16"><i class="fas fa-search text-4xl text-gray-300 mb-4"></i><p class="text-gray-500">No equipment found matching your criteria.</p></div>';
                return;
            }
            
            grid.innerHTML = filtered.map((item, index) => `
                <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200 hover:shadow-xl transition-all duration-300 hover:-translate-y-2 cursor-pointer" onclick="openModal('${item.id}')">
                    <div class="relative h-36 bg-gray-100 rounded-lg flex items-center justify-center mb-6">
                        <i class="fas ${item.icon} text-6xl text-gray-400"></i>
                        ${getStatusBadge(item.status)}
                        <span class="absolute top-2 right-2 bg-fes-red text-white text-xs font-bold px-2 py-1 rounded">${item.cat}</span>
                    </div>
                    <div class="card-body">
                        <div class="card-name text-lg font-bold text-gray-900 mb-2">${item.name}</div>
                        <div class="card-meta text-sm text-gray-600 mb-4">
                            <span class="inline-flex items-center mr-4"><i class="fas fa-location-dot mr-2"></i>${item.location}</span>
                            <span class="inline-flex items-center"><i class="fas fa-user mr-2"></i>${item.operator}</span>
                        </div>
                        <div class="specs-row mb-4">
                            ${item.specs.slice(0, 3).map(spec => `<span class="inline-flex items-center px-3 py-1 bg-gray-100 border border-gray-300 rounded-lg text-sm text-gray-700">${spec}</span>`).join('')}
                        </div>
                    </div>
                    <div class="card-footer flex justify-between items-center pt-4 border-t border-gray-200">
                        <div class="rate text-xl font-bold text-fes-red">MK ${item.rate.toLocaleString()}/day</div>
                        <div class="btn-row flex gap-2">
                            <button class="px-4 py-2 text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors duration-200" onclick="event.stopPropagation(); openModal('${item.id}')">View</button>
                            <button class="px-4 py-2 text-white bg-fes-red rounded-lg hover:bg-red-700 transition-colors duration-200 ${item.status !== 'available' ? 'opacity-50 cursor-not-allowed' : ''}" onclick="event.stopPropagation(); bookEquipment('${item.id}')" ${item.status !== 'available' ? 'disabled' : ''}>
                                ${item.status === 'available' ? 'Book Now' : 'N/A'}
                            </button>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        function renderStats() {
            const total = equipment.length;
            const available = equipment.filter(item => item.status === 'available').length;
            const booked = equipment.filter(item => item.status === 'booked').length;
            const maintenance = equipment.filter(item => item.status === 'maintenance').length;
            
            document.getElementById('sTotal').textContent = total;
            document.getElementById('sAvail').textContent = available;
            document.getElementById('sBooked').textContent = booked;
            document.getElementById('sMaint').textContent = maintenance;
        }

        function openModal(id) {
            const item = equipment.find(e => e.id === id);
            if(!item) return;
            
            document.getElementById('mName').textContent = item.name;
            document.getElementById('mBadge').innerHTML = getStatusBadge(item.status);
            document.getElementById('mImg').className = `fas ${item.icon} text-6xl text-gray-400`;
            document.getElementById('mCat').textContent = item.cat;
            document.getElementById('mId').textContent = item.id;
            document.getElementById('mRate').textContent = `MK ${item.rate.toLocaleString()}/day`;
            document.getElementById('mOp').textContent = item.operator;
            document.getElementById('mLoc').textContent = item.location;
            document.getElementById('mServ').textContent = item.lastService;
            document.getElementById('mSpecs').innerHTML = item.specs.map(spec => `<span class="inline-flex items-center px-3 py-1 bg-gray-100 border border-gray-300 rounded-lg text-sm text-gray-700">${spec}</span>`).join('');
            document.getElementById('mDesc').textContent = item.desc;
            
            const bookBtn = document.getElementById('mBookBtn');
            bookBtn.disabled = item.status !== 'available';
            bookBtn.textContent = item.status === 'available' ? 'Book Now' : 'N/A';
            
            document.getElementById('modalOv').classList.remove('hidden');
            document.getElementById('modalOv').classList.add('flex');
        }

        function bookEquipment(id) {
            const item = equipment.find(e => e.id === id);
            if(item && item.status === 'available') {
                <?php if(isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])): ?>
                    // User is logged in, redirect to booking page
                    window.location.href = `booking.php?equipment_id=${id}`;
                <?php else: ?>
                    // User is not logged in, redirect to login page
                    window.location.href = `auth/signin.php?redirect=equipment.php&equipment_id=${id}`;
                <?php endif; ?>
            }
        }

        function closeModal() {
            document.getElementById('modalOv').classList.add('hidden');
            document.getElementById('modalOv').classList.remove('flex');
        }

        // Event listeners
        document.getElementById('searchInput').addEventListener('input', (e) => {
            searchQuery = e.target.value;
            renderEquipment();
        });

        document.getElementById('sortSel').addEventListener('change', (e) => {
            sortMode = e.target.value;
            renderEquipment();
        });

        document.querySelectorAll('[data-cat]').forEach(chip => {
            chip.addEventListener('click', () => {
                document.querySelectorAll('[data-cat]').forEach(c => {
                    c.classList.remove('bg-fes-red', 'text-white', 'border-fes-red');
                    c.classList.add('bg-gray-100', 'text-gray-700', 'border-gray-300');
                });
                chip.classList.remove('bg-gray-100', 'text-gray-700', 'border-gray-300');
                chip.classList.add('bg-fes-red', 'text-white', 'border-fes-red');
                activeFilter = chip.dataset.cat;
                renderEquipment();
            });
        });

        // Close modal when clicking outside
        document.getElementById('modalOv').addEventListener('click', (e) => {
            if(e.target === e.currentTarget) {
                closeModal();
            }
        });

        // Initial render
        renderStats();
        renderEquipment();
    </script>
</body>
</html>
