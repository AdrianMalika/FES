<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FES — Farm Equipment System</title>
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
        h1, h2, h3, h4, .display { font-family: 'Barlow Condensed', sans-serif; }

        :root {
            --red: #D32F2F;
            --red-deep: #b71c1c;
            --dark: #1a1a1a;
            --mid: #2e2e2e;
        }

        /* Diagonal hero slice */
        .hero-section {
            background: var(--dark);
            clip-path: polygon(0 0, 100% 0, 100% 88%, 0 100%);
            padding-bottom: 8rem;
        }

        /* Noise texture */
        .noise::after {
            content: '';
            position: absolute;
            inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.04'/%3E%3C/svg%3E");
            pointer-events: none;
            mix-blend-mode: overlay;
            opacity: 0.4;
        }

        /* Grid background texture */
        .grid-bg {
            background-image:
                linear-gradient(rgba(211,47,47,0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(211,47,47,0.04) 1px, transparent 1px);
            background-size: 60px 60px;
        }

        /* Dark grid bg */
        .grid-bg-dark {
            background-image:
                linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
            background-size: 60px 60px;
        }

        .accent-line {
            display: block;
            width: 3rem;
            height: 3px;
            background: var(--red);
            margin-bottom: 1.5rem;
        }

        .accent-line-center {
            display: block;
            width: 3rem;
            height: 3px;
            background: var(--red);
            margin: 0 auto 1.5rem;
        }

        /* Hero diagonal accent */
        .hero-accent {
            position: absolute;
            right: 0; top: 0; bottom: 0;
            width: 42%;
            background: linear-gradient(135deg, transparent 30%, rgba(211,47,47,0.07) 100%);
            pointer-events: none;
        }

        /* Feature card */
        .feat-card {
            transition: transform 0.35s cubic-bezier(.22,.68,0,1.2), box-shadow 0.35s ease;
            background: #fff;
        }
        .feat-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 24px 60px -12px rgba(211,47,47,0.14), 0 8px 32px -8px rgba(0,0,0,0.08);
        }
        .feat-card:hover .feat-icon {
            background: var(--red);
            color: white;
        }
        .feat-icon {
            transition: background 0.25s ease, color 0.25s ease;
        }

        /* Step connector line */
        .step-connector {
            position: absolute;
            top: 40px;
            left: calc(50% + 40px);
            right: calc(-50% + 40px);
            height: 2px;
            background: linear-gradient(90deg, #e5e5e5, #e5e5e5);
        }
        @media (max-width: 768px) {
            .step-connector { display: none; }
        }

        /* Benefit check item */
        .benefit-item {
            border-left: 3px solid transparent;
            transition: border-color 0.2s, background 0.2s;
            padding-left: 1.25rem;
        }
        .benefit-item:hover {
            border-left-color: var(--red);
            background: rgba(211,47,47,0.02);
        }

        /* Stat card */
        .stat-card {
            position: relative;
            overflow: hidden;
        }
        .stat-card::before {
            content: '';
            position: absolute;
            bottom: -20px; right: -20px;
            width: 100px; height: 100px;
            border-radius: 50%;
            background: rgba(255,255,255,0.06);
        }

        /* CTA section */
        .cta-section {
            background: var(--dark);
            position: relative;
            overflow: hidden;
        }
        .cta-section::before {
            content: '';
            position: absolute;
            top: -60px; left: -60px;
            width: 300px; height: 300px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(211,47,47,0.12) 0%, transparent 70%);
        }
        .cta-section::after {
            content: '';
            position: absolute;
            bottom: -80px; right: -80px;
            width: 400px; height: 400px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(211,47,47,0.08) 0%, transparent 70%);
        }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: #f5f5f5; }
        ::-webkit-scrollbar-thumb { background: var(--red); border-radius: 99px; }

        /* Staggered animations */
        @keyframes fadeSlideUp {
            from { opacity: 0; transform: translateY(24px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .animate-in { animation: fadeSlideUp 0.6s ease both; }

        /* Red bottom stripe on hero */
        .hero-stripe {
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 3px;
            background: var(--red);
            opacity: 0.7;
        }

        /* Number counter display */
        .big-number {
            font-family: 'Barlow Condensed', sans-serif;
            font-weight: 900;
            line-height: 1;
            letter-spacing: -0.02em;
        }
    </style>
</head>

<body class="bg-gray-50 font-body text-gray-900 antialiased">
    <?php include '../includes/header.php'; ?>

    <!-- ═══════════════════ HERO ═══════════════════ -->
    <section class="hero-section relative overflow-hidden noise">
        <div class="hero-accent"></div>

        <!-- Ghost tractor bg -->
        <div class="absolute right-0 bottom-0 translate-x-16 opacity-[0.04] pointer-events-none select-none">
            <i class="fas fa-tractor" style="font-size: 520px; color: #D32F2F;"></i>
        </div>

        <div class="max-w-7xl mx-auto px-6 py-28 lg:py-36 relative z-10">
            <div class="max-w-2xl">
                <!-- Eyebrow -->
                <div class="flex items-center gap-3 mb-6 animate-in" style="animation-delay:0.1s">
                    <span class="text-fes-red font-display font-700 text-sm uppercase tracking-[0.2em]">
                        Smart Farm Equipment Management
                    </span>
                    <span class="block h-px w-12 bg-fes-red opacity-60"></span>
                </div>

                <!-- Headline -->
                <h1 class="font-display font-900 text-white leading-none mb-6 animate-in" style="font-size: clamp(2.8rem,7vw,5.5rem); letter-spacing:-0.01em; animation-delay:0.2s;">
                    Equipment<br>
                    Management<br>
                    <span class="text-fes-red">Made Easy</span>
                </h1>

                <p class="text-gray-400 leading-relaxed mb-10 max-w-lg animate-in" style="font-size:1.05rem; animation-delay:0.35s;">
                    FES streamlines equipment booking, monitoring, maintenance, and reporting for agricultural stakeholders. Improve utilization, reduce downtime, and make data-driven decisions.
                </p>

                <div class="flex flex-col sm:flex-row gap-4 animate-in" style="animation-delay:0.5s;">
                    <a href="auth/register.php"
                       class="inline-flex items-center justify-center gap-3 bg-fes-red hover:bg-red-700 text-white font-display font-700 uppercase tracking-wider px-8 py-4 rounded-sm shadow-lg transition-all duration-300 hover:shadow-xl"
                       style="font-size:0.9rem; letter-spacing:0.1em;">
                        <i class="fas fa-arrow-right text-xs"></i>
                        Get Started
                    </a>
                    <a href="auth/signin.php"
                       class="inline-flex items-center justify-center gap-3 border-2 border-white/30 text-white hover:border-white/60 hover:bg-white/5 font-display font-700 uppercase tracking-wider px-8 py-4 rounded-sm transition-all duration-300"
                       style="font-size:0.9rem; letter-spacing:0.1em;">
                        <i class="fas fa-sign-in-alt text-xs"></i>
                        Login
                    </a>
                </div>
            </div>
        </div>

        <div class="hero-stripe"></div>
    </section>


    <!-- ═══════════════════ QUICK STATS ═══════════════════ -->
    <div class="bg-fes-dark grid-bg-dark border-b border-white/5 -mt-1">
        <div class="max-w-7xl mx-auto px-6 py-8 grid grid-cols-2 md:grid-cols-4 divide-x divide-white/10">
            <?php
            $stats = [
                ['num'=>'500+', 'label'=>'Bookings Made',     'icon'=>'fa-calendar-check'],
                ['num'=>'80+',  'label'=>'Equipment Units',   'icon'=>'fa-tractor'],
                ['num'=>'200+', 'label'=>'Active Users',      'icon'=>'fa-users'],
                ['num'=>'98%',  'label'=>'Uptime Guaranteed', 'icon'=>'fa-shield-halved'],
            ];
            foreach($stats as $i => $s): ?>
            <div class="px-6 <?= $i===0?'pl-0':'' ?> flex items-center gap-4">
                <i class="fas <?= $s['icon'] ?> text-fes-red text-lg opacity-70"></i>
                <div>
                    <div class="big-number text-white text-2xl"><?= $s['num'] ?></div>
                    <div class="text-xs text-gray-500 uppercase tracking-wider mt-0.5"><?= $s['label'] ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>


    <!-- ═══════════════════ FEATURES ═══════════════════ -->
    <section class="py-24 bg-gray-50 grid-bg">
        <div class="max-w-7xl mx-auto px-6">
            <!-- Heading -->
            <div class="text-center mb-16">
                <span class="accent-line-center"></span>
                <h2 class="font-display font-900 text-gray-900 mb-4" style="font-size: clamp(2rem,4vw,3rem); letter-spacing:-0.01em;">
                    Powerful Features for<br>Modern Farming
                </h2>
                <p class="text-gray-500 max-w-xl mx-auto text-base">Everything you need to manage, book, and maintain your agricultural equipment — in one platform.</p>
            </div>

            <!-- Features grid -->
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-7">
                <?php
                $features = [
                    ['icon'=>'fa-calendar-check', 'title'=>'Equipment Booking & Scheduling',
                     'desc'=>'Browse available equipment, check real-time availability, and book resources with an intuitive scheduling system.',
                     'link'=>null],
                    ['icon'=>'fa-tools',           'title'=>'Maintenance Monitoring & Alerts',
                     'desc'=>'Track equipment health, receive maintenance alerts, and schedule preventive maintenance to minimise unexpected breakdowns.',
                     'link'=>null],
                    ['icon'=>'fa-chart-line',      'title'=>'Usage Tracking & Reports',
                     'desc'=>'Generate comprehensive reports on equipment usage, utilization rates, and operational metrics for smarter resource allocation.',
                     'link'=>null],
                    ['icon'=>'fa-star',            'title'=>'Ratings & Feedback System',
                     'desc'=>'Collect user feedback and equipment ratings to continuously improve service quality and performance.',
                     'link'=>null],
                    ['icon'=>'fa-bell',            'title'=>'Notifications & Alerts',
                     'desc'=>'Stay informed with real-time notifications about bookings, maintenance schedules, and important system updates.',
                     'link'=>null],
                    ['icon'=>'fa-lock',            'title'=>'Data Security & Privacy',
                     'desc'=>'Your farm data is protected with enterprise-grade security and compliance with agricultural data protection standards.',
                     'link'=>null],
                    ['icon'=>'fa-tractor',         'title'=>'Equipment Catalogue',
                     'desc'=>'Browse our comprehensive catalogue of farming and engineering equipment available for immediate booking.',
                     'link'=>'equipment.php'],
                ];
                foreach($features as $f): ?>
                <div class="feat-card rounded-sm border border-gray-100 p-8 shadow-sm group cursor-default">
                    <div class="feat-icon w-14 h-14 bg-red-50 rounded-sm flex items-center justify-center text-fes-red text-xl mb-6">
                        <i class="fas <?= $f['icon'] ?>"></i>
                    </div>
                    <h3 class="font-display font-800 text-xl text-gray-900 mb-3 leading-tight" style="letter-spacing:-0.01em;">
                        <?= $f['title'] ?>
                    </h3>
                    <p class="text-gray-500 text-sm leading-relaxed mb-4"><?= $f['desc'] ?></p>
                    <?php if($f['link']): ?>
                    <a href="<?= $f['link'] ?>"
                       class="inline-flex items-center gap-2 text-fes-red font-display font-700 text-sm uppercase tracking-wider hover:gap-3 transition-all duration-200">
                        Browse Equipment <i class="fas fa-arrow-right text-xs"></i>
                    </a>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>


    <!-- ═══════════════════ HOW IT WORKS ═══════════════════ -->
    <section class="py-24 bg-white">
        <div class="max-w-7xl mx-auto px-6">
            <!-- Heading -->
            <div class="text-center mb-16">
                <span class="accent-line-center"></span>
                <h2 class="font-display font-900 text-gray-900 mb-4" style="font-size: clamp(2rem,4vw,3rem); letter-spacing:-0.01em;">
                    How It Works
                </h2>
                <p class="text-gray-500 max-w-md mx-auto text-base">Three simple steps to get your equipment booked and operations running.</p>
            </div>

            <!-- Steps -->
            <div class="grid md:grid-cols-3 gap-12 relative">
                <?php
                $steps = [
                    ['n'=>'01','icon'=>'fa-user-plus',   'title'=>'Register an Account',
                     'desc'=>'Create your FES account with your farm details and contact information to get started in minutes.'],
                    ['n'=>'02','icon'=>'fa-tractor',     'title'=>'Browse & Book Equipment',
                     'desc'=>'Explore available equipment, check live schedules, and book the resources you need for your farming operations.'],
                    ['n'=>'03','icon'=>'fa-chart-bar',   'title'=>'Track & Monitor',
                     'desc'=>'Monitor equipment usage, receive real-time notifications, and track maintenance schedules from your dashboard.'],
                ];
                foreach($steps as $i => $s): ?>
                <div class="text-center relative">
                    <!-- Connector line (desktop only) -->
                    <?php if($i < 2): ?>
                    <div class="step-connector hidden md:block"></div>
                    <?php endif; ?>

                    <!-- Step number circle -->
                    <div class="relative inline-block mb-6">
                        <div class="w-20 h-20 bg-fes-dark rounded-sm flex items-center justify-center mx-auto relative z-10 shadow-lg">
                            <i class="fas <?= $s['icon'] ?> text-white text-2xl"></i>
                        </div>
                        <span class="absolute -top-3 -right-3 bg-fes-red text-white font-display font-900 text-xs w-7 h-7 rounded-sm flex items-center justify-center z-20">
                            <?= $s['n'] ?>
                        </span>
                    </div>

                    <h3 class="font-display font-800 text-xl text-gray-900 mb-3" style="letter-spacing:-0.01em;">
                        <?= $s['title'] ?>
                    </h3>
                    <p class="text-gray-500 text-sm leading-relaxed max-w-xs mx-auto"><?= $s['desc'] ?></p>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="text-center mt-14">
                <a href="auth/register.php"
                   class="inline-flex items-center gap-3 bg-fes-red hover:bg-red-700 text-white font-display font-800 uppercase tracking-wider px-8 py-4 rounded-sm shadow-lg transition-all duration-300 hover:shadow-xl"
                   style="font-size:0.9rem; letter-spacing:0.1em;">
                    <i class="fas fa-arrow-right text-xs"></i>
                    Create Your Account
                </a>
            </div>
        </div>
    </section>


    <!-- ═══════════════════ WHY FES ═══════════════════ -->
    <section class="py-24 bg-gray-50 grid-bg">
        <div class="max-w-7xl mx-auto px-6">
            <!-- Heading -->
            <div class="text-center mb-16">
                <span class="accent-line-center"></span>
                <h2 class="font-display font-900 text-gray-900 mb-4" style="font-size: clamp(2rem,4vw,3rem); letter-spacing:-0.01em;">
                    Why Choose FES?
                </h2>
                <p class="text-gray-500 max-w-md mx-auto text-base">Built specifically for the demands of modern agricultural operations in Malawi.</p>
            </div>

            <div class="grid lg:grid-cols-2 gap-16 items-center">

                <!-- Benefits list -->
                <div class="space-y-5">
                    <?php
                    $benefits = [
                        ['icon'=>'fa-chart-pie',     'title'=>'Improved Equipment Utilization',
                         'desc'=>'Maximise equipment usage and reduce idle time with smart scheduling and real-time availability tracking.'],
                        ['icon'=>'fa-bolt',          'title'=>'Reduced Equipment Downtime',
                         'desc'=>'Prevent unexpected breakdowns with proactive maintenance monitoring and timely service alerts.'],
                        ['icon'=>'fa-eye',           'title'=>'Transparency & Accountability',
                         'desc'=>'Track equipment usage, maintenance history, and user feedback for complete visibility across your operations.'],
                        ['icon'=>'fa-brain',         'title'=>'Data-Driven Decision Making',
                         'desc'=>'Access comprehensive reports and analytics to optimise resource allocation and improve farm operations.'],
                    ];
                    foreach($benefits as $b): ?>
                    <div class="benefit-item py-4 pr-4 rounded-r-sm">
                        <div class="flex items-start gap-4">
                            <div class="w-10 h-10 bg-fes-red/10 rounded-sm flex items-center justify-center flex-shrink-0 text-fes-red text-sm mt-0.5">
                                <i class="fas <?= $b['icon'] ?>"></i>
                            </div>
                            <div>
                                <h3 class="font-display font-800 text-base text-gray-900 mb-1" style="letter-spacing:-0.01em;">
                                    <?= $b['title'] ?>
                                </h3>
                                <p class="text-gray-500 text-sm leading-relaxed"><?= $b['desc'] ?></p>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Visual stats panel -->
                <div class="bg-fes-dark rounded-sm p-10 grid-bg-dark relative overflow-hidden shadow-2xl">
                    <!-- Red glow -->
                    <div class="absolute top-0 right-0 w-48 h-48 rounded-full opacity-10"
                         style="background: radial-gradient(circle, #D32F2F, transparent); transform: translate(30%,-30%);"></div>

                    <div class="relative z-10">
                        <div class="mb-10">
                            <span class="text-fes-red text-xs font-display font-700 uppercase tracking-[0.2em] block mb-3">Platform Impact</span>
                            <h3 class="font-display font-900 text-white text-2xl leading-tight" style="letter-spacing:-0.01em;">
                                Transforming Farm Equipment Management
                            </h3>
                        </div>

                        <div class="grid grid-cols-2 gap-5">
                            <?php
                            $cards = [
                                ['n'=>'4+',   'l'=>'Core Benefits',     'icon'=>'fa-star',           'col'=>'bg-fes-red'],
                                ['n'=>'80+',  'l'=>'Equipment Units',   'icon'=>'fa-tractor',        'col'=>'bg-mid'],
                                ['n'=>'24/7', 'l'=>'System Access',     'icon'=>'fa-clock',          'col'=>'bg-mid'],
                                ['n'=>'100%', 'l'=>'Web-Based Platform','icon'=>'fa-globe',          'col'=>'bg-mid'],
                            ];
                            foreach($cards as $c): ?>
                            <div class="stat-card <?= $c['col'] ?> rounded-sm p-5 text-white">
                                <i class="fas <?= $c['icon'] ?> text-white/30 text-2xl mb-3 block"></i>
                                <div class="big-number text-3xl text-white"><?= $c['n'] ?></div>
                                <div class="text-xs text-white/60 uppercase tracking-wider mt-1"><?= $c['l'] ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>




    <?php include '../includes/footer.php'; ?>

</body>
</html>