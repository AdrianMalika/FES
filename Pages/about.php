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
    <title>About - FES</title>
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

        .noise::after {
            content: '';
            position: absolute;
            inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.04'/%3E%3C/svg%3E");
            pointer-events: none;
            mix-blend-mode: overlay;
            opacity: 0.4;
        }

        .about-hero {
            background: var(--dark);
            clip-path: polygon(0 0, 100% 0, 100% 88%, 0 100%);
            padding-bottom: 8rem;
        }

        .hero-accent {
            position: absolute;
            right: 0; top: 0; bottom: 0;
            width: 42%;
            background: linear-gradient(135deg, transparent 30%, rgba(211,47,47,0.07) 100%);
            pointer-events: none;
        }

        .hero-stripe {
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 3px;
            background: var(--red);
            opacity: 0.7;
        }

        .grid-bg {
            background-image:
                linear-gradient(rgba(211,47,47,0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(211,47,47,0.04) 1px, transparent 1px);
            background-size: 60px 60px;
        }

        .grid-bg-dark {
            background-image:
                linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
            background-size: 60px 60px;
        }

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

        .about-card {
            background: var(--mid);
            border: 1px solid rgba(255,255,255,0.06);
            transition: transform 0.3s ease, border-color 0.3s ease;
        }
        .about-card:hover {
            transform: translateY(-4px);
            border-color: rgba(211,47,47,0.25);
        }

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

        .big-number {
            font-family: 'Barlow Condensed', sans-serif;
            font-weight: 900;
            line-height: 1;
            letter-spacing: -0.02em;
        }

        .benefit-item {
            border-left: 3px solid transparent;
            transition: border-color 0.2s, background 0.2s;
            padding-left: 1.25rem;
        }
        .benefit-item:hover {
            border-left-color: var(--red);
            background: rgba(211,47,47,0.02);
        }

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

        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: #f5f5f5; }
        ::-webkit-scrollbar-thumb { background: var(--red); border-radius: 99px; }
    </style>
</head>

<body class="bg-gray-50 font-body text-gray-900 antialiased">
    <?php include '../includes/header.php'; ?>

    <!-- ═══════════════════ ABOUT HERO ═══════════════════ -->
    <section class="about-hero relative overflow-hidden noise">
        <div class="hero-accent"></div>

        <!-- Ghost tractor bg -->
        <div class="absolute right-0 bottom-0 translate-x-16 opacity-[0.04] pointer-events-none select-none">
            <i class="fas fa-tractor" style="font-size: 520px; color: #D32F2F;"></i>
        </div>

        <div class="max-w-7xl mx-auto px-6 py-28 lg:py-36 relative z-10">
            <div class="max-w-3xl">
                <!-- Eyebrow -->
                <div class="flex items-center gap-3 mb-6">
                    <span class="text-fes-red font-display font-700 text-sm uppercase tracking-[0.2em]">
                        About FES
                    </span>
                    <span class="block h-px w-12 bg-fes-red opacity-60"></span>
                </div>

                <!-- Headline -->
                <h1 class="font-display font-900 text-white leading-none mb-6" style="font-size: clamp(2.8rem,7vw,5.5rem); letter-spacing:-0.01em;">
                    Farming &<br>
                    Engineering<br>
                    <span class="text-fes-red">Services Ltd</span>
                </h1>

                <p class="text-gray-400 leading-relaxed mb-10 max-w-xl text-base">
                    FES is a Malawian agricultural services company dedicated to modernising farming operations.
                    We provide cutting-edge equipment on a rental basis, helping farmers increase productivity,
                    reduce costs, and achieve better harvests across Malawi.
                </p>

                <div class="flex flex-col sm:flex-row gap-4">
                    <a href="auth/register.php"
                       class="inline-flex items-center justify-center gap-3 bg-fes-red hover:bg-red-700 text-white font-display font-700 uppercase tracking-wider px-8 py-4 rounded-sm shadow-lg transition-all duration-300 hover:shadow-xl"
                       style="font-size:0.9rem; letter-spacing:0.1em;">
                        <i class="fas fa-arrow-right text-xs"></i>
                        Get Started
                    </a>
                    <a href="equipment.php"
                       class="inline-flex items-center justify-center gap-3 border-2 border-white/30 text-white hover:border-white/60 hover:bg-white/5 font-display font-700 uppercase tracking-wider px-8 py-4 rounded-sm transition-all duration-300"
                       style="font-size:0.9rem; letter-spacing:0.1em;">
                        <i class="fas fa-tractor text-xs"></i>
                        View Equipment
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

    <!-- ═══════════════════ OUR STORY ═══════════════════ -->
    <section class="py-24 bg-white">
        <div class="max-w-7xl mx-auto px-6">
            <div class="grid lg:grid-cols-2 gap-16 items-center">
                <!-- Text -->
                <div>
                    <span class="accent-line"></span>
                    <h2 class="font-display font-900 text-gray-900 mb-4" style="font-size: clamp(2rem,4vw,3rem); letter-spacing:-0.01em;">
                        Our Story
                    </h2>
                    <div class="text-gray-600 leading-relaxed space-y-4">
                        <p>
                            Farming & Engineering Services Ltd (FES) was founded with a single mission: to make modern
                            farming equipment accessible to all Malawian farmers. We recognised that many farmers struggled
                            with outdated tools, limited resources, and inefficient processes that held back productivity.
                        </p>
                        <p>
                            Our platform bridges this gap by providing an intuitive booking system where farmers can
                            reserve tractors, harvesters, and specialized equipment for land preparation, planting,
                            harvesting, and transportation. Whether you manage a small plot or a large commercial farm,
                            FES ensures you have the right equipment when you need it.
                        </p>
                        <p>
                            Today, we serve hundreds of farmers across multiple regions in Malawi, continuously expanding
                            our fleet and improving our services to meet the growing demands of modern agriculture.
                        </p>
                    </div>
                </div>

                <!-- Visual panel -->
                <div class="bg-fes-dark rounded-sm p-10 grid-bg-dark relative overflow-hidden shadow-2xl">
                    <div class="absolute top-0 right-0 w-48 h-48 rounded-full opacity-10"
                         style="background: radial-gradient(circle, #D32F2F, transparent); transform: translate(30%,-30%);"></div>

                    <div class="relative z-10">
                        <div class="mb-10">
                            <span class="text-fes-red text-xs font-display font-700 uppercase tracking-[0.2em] block mb-3">Our Mission</span>
                            <h3 class="font-display font-900 text-white text-2xl leading-tight" style="letter-spacing:-0.01em;">
                                Empowering Farmers Through Technology
                            </h3>
                        </div>

                        <div class="grid grid-cols-2 gap-5">
                            <?php
                            $cards = [
                                ['n'=>'24/7', 'l'=>'System Access',     'icon'=>'fa-clock',          'col'=>'bg-fes-red'],
                                ['n'=>'100%', 'l'=>'Web-Based Platform','icon'=>'fa-globe',          'col'=>'bg-mid'],
                                ['n'=>'3+',   'l'=>'Service Regions',   'icon'=>'fa-map-marked-alt', 'col'=>'bg-mid'],
                                ['n'=>'10+',  'l'=>'Equipment Types',   'icon'=>'fa-tractor',        'col'=>'bg-mid'],
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

    <!-- ═══════════════════ SERVICES ═══════════════════ -->
    <section class="py-24 bg-gray-50 grid-bg">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center mb-16">
                <span class="accent-line-center"></span>
                <h2 class="font-display font-900 text-gray-900 mb-4" style="font-size: clamp(2rem,4vw,3rem); letter-spacing:-0.01em;">
                    Services We Offer
                </h2>
                <p class="text-gray-500 max-w-xl mx-auto text-base">Comprehensive agricultural equipment services tailored to modern farming needs.</p>
            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-7">
                <?php
                $services = [
                    ['icon'=>'fa-wheat-awn',      'title'=>'Harvesting',
                     'desc'=>'Mechanical harvesting of crops using modern farm equipment to maximise yield and reduce manual labour.'],
                    ['icon'=>'fa-truck-pickup',   'title'=>'Land Preparation',
                     'desc'=>'Tractor-based ploughing, harrowing, and land clearing services to prepare your fields for planting season.'],
                    ['icon'=>'fa-seedling',       'title'=>'Planting',
                     'desc'=>'Mechanical planting and seeding services across farm plots with precision and efficiency.'],
                    ['icon'=>'fa-truck-monster',  'title'=>'Transportation',
                     'desc'=>'Equipment transport and farm produce hauling to get your goods to market safely and on time.'],
                    ['icon'=>'fa-tools',          'title'=>'Equipment Maintenance',
                     'desc'=>'Regular servicing and maintenance to keep our fleet in optimal condition for reliable performance.'],
                    ['icon'=>'fa-headset',        'title'=>'Customer Support',
                     'desc'=>'Dedicated support team available to assist with bookings, equipment queries, and operational guidance.'],
                ];
                foreach($services as $s): ?>
                <div class="feat-card rounded-sm border border-gray-100 p-8 shadow-sm">
                    <div class="feat-icon w-14 h-14 bg-red-50 rounded-sm flex items-center justify-center text-fes-red text-xl mb-6">
                        <i class="fas <?= $s['icon'] ?>"></i>
                    </div>
                    <h3 class="font-display font-800 text-xl text-gray-900 mb-3 leading-tight" style="letter-spacing:-0.01em;">
                        <?= $s['title'] ?>
                    </h3>
                    <p class="text-gray-500 text-sm leading-relaxed"><?= $s['desc'] ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ═══════════════════ WHY CHOOSE FES ═══════════════════ -->
    <section class="py-24 bg-white">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center mb-16">
                <span class="accent-line-center"></span>
                <h2 class="font-display font-900 text-gray-900 mb-4" style="font-size: clamp(2rem,4vw,3rem); letter-spacing:-0.01em;">
                    Why Choose FES?
                </h2>
                <p class="text-gray-500 max-w-md mx-auto text-base">Built specifically for the demands of modern agricultural operations in Malawi.</p>
            </div>

            <div class="grid lg:grid-cols-2 gap-16 items-center">
                <!-- Benefits -->
                <div class="space-y-5">
                    <?php
                    $benefits = [
                        ['icon'=>'fa-chart-pie',  'title'=>'Improved Equipment Utilization',
                         'desc'=>'Maximise equipment usage and reduce idle time with smart scheduling and real-time availability tracking.'],
                        ['icon'=>'fa-bolt',       'title'=>'Reduced Equipment Downtime',
                         'desc'=>'Prevent unexpected breakdowns with proactive maintenance monitoring and timely service alerts.'],
                        ['icon'=>'fa-eye',        'title'=>'Transparency & Accountability',
                         'desc'=>'Track equipment usage, maintenance history, and user feedback for complete visibility across operations.'],
                        ['icon'=>'fa-brain',      'title'=>'Data-Driven Decision Making',
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

                <!-- Team / Contact info -->
                <div class="bg-fes-dark rounded-sm p-10 grid-bg-dark relative overflow-hidden shadow-2xl">
                    <div class="absolute top-0 right-0 w-48 h-48 rounded-full opacity-10"
                         style="background: radial-gradient(circle, #D32F2F, transparent); transform: translate(30%,-30%);"></div>

                    <div class="relative z-10">
                        <div class="mb-10">
                            <span class="text-fes-red text-xs font-display font-700 uppercase tracking-[0.2em] block mb-3">Get In Touch</span>
                            <h3 class="font-display font-900 text-white text-2xl leading-tight" style="letter-spacing:-0.01em;">
                                Contact Information
                            </h3>
                        </div>

                        <div class="space-y-4">
                            <?php
                            $contacts = [
                                ['icon'=>'fa-map-marker-alt', 'label'=>'Location', 'val'=>'Blantyre, Malawi', 'bg'=>'bg-fes-red'],
                                ['icon'=>'fa-envelope',       'label'=>'Email',    'val'=>'info@fes-mw.com',  'bg'=>'bg-mid'],
                                ['icon'=>'fa-phone',          'label'=>'Phone',    'val'=>'+265 1 234 567',   'bg'=>'bg-mid'],
                                ['icon'=>'fa-clock',          'label'=>'Hours',    'val'=>'Mon–Fri: 07:00–18:00', 'bg'=>'bg-mid'],
                            ];
                            foreach($contacts as $c): ?>
                            <div class="flex items-center gap-4 p-4 <?= $c['bg'] ?> rounded-sm">
                                <div class="w-12 h-12 bg-white/10 rounded-sm flex items-center justify-center flex-shrink-0">
                                    <i class="fas <?= $c['icon'] ?> text-white/80 text-lg"></i>
                                </div>
                                <div>
                                    <div class="text-xs text-white/60 uppercase tracking-wider"><?= $c['label'] ?></div>
                                    <div class="text-white font-medium"><?= $c['val'] ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ═══════════════════ CTA ═══════════════════ -->
    <section class="cta-section py-24">
        <div class="max-w-7xl mx-auto px-6 relative z-10">
            <div class="text-center">
                <span class="accent-line-center"></span>
                <h2 class="font-display font-900 text-white mb-4" style="font-size: clamp(2rem,4vw,3rem); letter-spacing:-0.01em;">
                    Ready to Transform Your<br>Farming Operations?
                </h2>
                <p class="text-gray-400 max-w-lg mx-auto text-base mb-10">
                    Join hundreds of Malawian farmers who trust FES for reliable equipment and exceptional service.
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="auth/register.php"
                       class="inline-flex items-center justify-center gap-3 bg-fes-red hover:bg-red-700 text-white font-display font-700 uppercase tracking-wider px-8 py-4 rounded-sm shadow-lg transition-all duration-300 hover:shadow-xl"
                       style="font-size:0.9rem; letter-spacing:0.1em;">
                        <i class="fas fa-arrow-right text-xs"></i>
                        Create Account
                    </a>
                    <a href="index.php#features"
                       class="inline-flex items-center justify-center gap-3 border-2 border-white/30 text-white hover:border-white/60 hover:bg-white/5 font-display font-700 uppercase tracking-wider px-8 py-4 rounded-sm transition-all duration-300"
                       style="font-size:0.9rem; letter-spacing:0.1em;">
                        <i class="fas fa-cogs text-xs"></i>
                        Explore Features
                    </a>
                </div>
            </div>
        </div>
    </section>

    <?php include '../includes/footer.php'; ?>

</body>
</html>
