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
    <title>Contact - FES</title>
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

        .contact-hero {
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

        .contact-card {
            background: var(--mid);
            border: 1px solid rgba(255,255,255,0.06);
            transition: transform 0.3s ease, border-color 0.3s ease;
        }
        .contact-card:hover {
            transform: translateY(-4px);
            border-color: rgba(211,47,47,0.25);
        }

        .form-input {
            background: #fff;
            border: 2px solid #e5e7eb;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-input:focus {
            border-color: var(--red);
            box-shadow: 0 0 0 3px rgba(211,47,47,0.1);
            outline: none;
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

    <!-- ═══════════════════ CONTACT HERO ═══════════════════ -->
    <section class="contact-hero relative overflow-hidden noise">
        <div class="hero-accent"></div>

        <!-- Ghost phone bg -->
        <div class="absolute right-0 bottom-0 translate-x-16 opacity-[0.04] pointer-events-none select-none">
            <i class="fas fa-phone-volume" style="font-size: 520px; color: #D32F2F;"></i>
        </div>

        <div class="max-w-7xl mx-auto px-6 py-28 lg:py-36 relative z-10">
            <div class="max-w-2xl">
                <!-- Eyebrow -->
                <div class="flex items-center gap-3 mb-6">
                    <span class="text-fes-red font-display font-700 text-sm uppercase tracking-[0.2em]">
                        Get In Touch
                    </span>
                    <span class="block h-px w-12 bg-fes-red opacity-60"></span>
                </div>

                <!-- Headline -->
                <h1 class="font-display font-900 text-white leading-none mb-6" style="font-size: clamp(2.8rem,7vw,5.5rem); letter-spacing:-0.01em;">
                    Contact<br>
                    <span class="text-fes-red">Us</span>
                </h1>

                <p class="text-gray-400 leading-relaxed max-w-xl text-base">
                    Have questions about our equipment or services? Need help with a booking?
                    Our team is here to assist you. Reach out and we'll respond as quickly as possible.
                </p>
            </div>
        </div>

        <div class="hero-stripe"></div>
    </section>

    <!-- ═══════════════════ CONTACT INFO CARDS ═══════════════════ -->
    <div class="bg-fes-dark grid-bg-dark border-b border-white/5 -mt-1">
        <div class="max-w-7xl mx-auto px-6 py-8 grid grid-cols-2 md:grid-cols-4 divide-x divide-white/10">
            <?php
            $contacts = [
                ['icon'=>'fa-phone',          'label'=>'Phone',         'val'=>'+265 1 234 567'],
                ['icon'=>'fa-envelope',       'label'=>'Email',         'val'=>'info@fes-mw.com'],
                ['icon'=>'fa-map-marker-alt', 'label'=>'Location',      'val'=>'Blantyre, Malawi'],
                ['icon'=>'fa-clock',          'label'=>'Hours',         'val'=>'Mon–Fri: 07:00–18:00'],
            ];
            foreach($contacts as $i => $c): ?>
            <div class="px-6 <?= $i===0?'pl-0':'' ?> flex items-center gap-4">
                <i class="fas <?= $c['icon'] ?> text-fes-red text-lg opacity-70"></i>
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wider mb-0.5"><?= $c['label'] ?></div>
                    <div class="text-white font-medium text-sm"><?= $c['val'] ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ═══════════════════ CONTACT FORM & MAP ═══════════════════ -->
    <section class="py-24 bg-white">
        <div class="max-w-7xl mx-auto px-6">
            <div class="grid lg:grid-cols-2 gap-16">

                <!-- Contact Form -->
                <div>
                    <span class="accent-line"></span>
                    <h2 class="font-display font-900 text-gray-900 mb-4" style="font-size: clamp(2rem,4vw,3rem); letter-spacing:-0.01em;">
                        Send Us a Message
                    </h2>
                    <p class="text-gray-500 mb-10 text-base">Fill out the form below and we'll get back to you within 24 hours.</p>

                    <form method="POST" class="space-y-5">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">First Name *</label>
                                <input type="text" name="first_name" required
                                       class="form-input w-full px-4 py-3 rounded-sm"
                                       placeholder="John">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Last Name *</label>
                                <input type="text" name="last_name" required
                                       class="form-input w-full px-4 py-3 rounded-sm"
                                       placeholder="Doe">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Email Address *</label>
                            <input type="email" name="email" required
                                   class="form-input w-full px-4 py-3 rounded-sm"
                                   placeholder="john@example.com">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Phone Number</label>
                            <input type="tel" name="phone"
                                   class="form-input w-full px-4 py-3 rounded-sm"
                                   placeholder="+265 9 123 4567">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Subject *</label>
                            <select name="subject" required
                                    class="form-input w-full px-4 py-3 rounded-sm">
                                <option value="">Select a subject</option>
                                <option value="booking">Equipment Booking Inquiry</option>
                                <option value="pricing">Pricing & Rates</option>
                                <option value="support">Technical Support</option>
                                <option value="partnership">Partnership Opportunity</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Message *</label>
                            <textarea name="message" required rows="5"
                                      class="form-input w-full px-4 py-3 rounded-sm resize-none"
                                      placeholder="Tell us how we can help you..."></textarea>
                        </div>

                        <button type="submit"
                                class="inline-flex items-center justify-center gap-3 bg-fes-red hover:bg-red-700 text-white font-display font-700 uppercase tracking-wider px-8 py-4 rounded-sm shadow-lg transition-all duration-300 hover:shadow-xl w-full"
                                style="font-size:0.9rem; letter-spacing:0.1em;">
                            <i class="fas fa-paper-plane text-xs"></i>
                            Send Message
                        </button>
                    </form>
                </div>

                <!-- Info Panel -->
                <div class="space-y-6">

                    <!-- Office Location -->
                    <div class="bg-fes-dark rounded-sm p-8 grid-bg-dark relative overflow-hidden shadow-2xl">
                        <div class="absolute top-0 right-0 w-48 h-48 rounded-full opacity-10"
                             style="background: radial-gradient(circle, #D32F2F, transparent); transform: translate(30%,-30%);"></div>

                        <div class="relative z-10">
                            <span class="text-fes-red text-xs font-display font-700 uppercase tracking-[0.2em] block mb-3">Our Office</span>
                            <h3 class="font-display font-900 text-white text-2xl mb-6" style="letter-spacing:-0.01em;">
                                Visit Us
                            </h3>

                            <div class="space-y-4">
                                <div class="flex items-start gap-4 p-4 bg-mid rounded-sm">
                                    <div class="w-10 h-10 bg-fes-red/20 rounded-sm flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-map-marker-alt text-fes-red"></i>
                                    </div>
                                    <div>
                                        <div class="text-white font-medium mb-1">Address</div>
                                        <div class="text-gray-400 text-sm">Plot 123, Patrice Lumumba Road<br>Blantyre, Malawi</div>
                                    </div>
                                </div>

                                <div class="flex items-start gap-4 p-4 bg-mid rounded-sm">
                                    <div class="w-10 h-10 bg-fes-red/20 rounded-sm flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-clock text-fes-red"></i>
                                    </div>
                                    <div>
                                        <div class="text-white font-medium mb-1">Operating Hours</div>
                                        <div class="text-gray-400 text-sm">Monday – Friday: 07:00 – 18:00</div>
                                        <div class="text-gray-400 text-sm">Saturday: 07:00 – 14:00</div>
                                        <div class="text-gray-500 text-sm mt-1">Sunday: Closed</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Contact -->
                    <div class="bg-fes-dark rounded-sm p-8 grid-bg-dark relative overflow-hidden shadow-2xl">
                        <div class="absolute bottom-0 left-0 w-48 h-48 rounded-full opacity-10"
                             style="background: radial-gradient(circle, #D32F2F, transparent); transform: translate(-30%,30%);"></div>

                        <div class="relative z-10">
                            <span class="text-fes-red text-xs font-display font-700 uppercase tracking-[0.2em] block mb-3">Quick Contact</span>
                            <h3 class="font-display font-900 text-white text-2xl mb-6" style="letter-spacing:-0.01em;">
                                Reach Out Directly
                            </h3>

                            <div class="space-y-4">
                                <a href="tel:+2651234567"
                                   class="flex items-center gap-4 p-4 bg-mid rounded-sm hover:bg-white/5 transition-all">
                                    <div class="w-12 h-12 bg-fes-red/20 rounded-sm flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-phone text-fes-red text-lg"></i>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-500 uppercase tracking-wider">Phone</div>
                                        <div class="text-white font-medium">+265 1 234 567</div>
                                    </div>
                                </a>

                                <a href="mailto:info@fes-mw.com"
                                   class="flex items-center gap-4 p-4 bg-mid rounded-sm hover:bg-white/5 transition-all">
                                    <div class="w-12 h-12 bg-fes-red/20 rounded-sm flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-envelope text-fes-red text-lg"></i>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-500 uppercase tracking-wider">Email</div>
                                        <div class="text-white font-medium">info@fes-mw.com</div>
                                    </div>
                                </a>

                                <a href="https://wa.me/2651234567" target="_blank"
                                   class="flex items-center gap-4 p-4 bg-mid rounded-sm hover:bg-white/5 transition-all">
                                    <div class="w-12 h-12 bg-fes-red/20 rounded-sm flex items-center justify-center flex-shrink-0">
                                        <i class="fab fa-whatsapp text-fes-red text-lg"></i>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-500 uppercase tracking-wider">WhatsApp</div>
                                        <div class="text-white font-medium">+265 1 234 567</div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- FAQ Preview -->
                    <div class="bg-fes-dark rounded-sm p-8 grid-bg-dark relative overflow-hidden shadow-2xl">
                        <div class="relative z-10">
                            <span class="text-fes-red text-xs font-display font-700 uppercase tracking-[0.2em] block mb-3">Need Answers?</span>
                            <h3 class="font-display font-900 text-white text-2xl mb-6" style="letter-spacing:-0.01em;">
                                Frequently Asked
                            </h3>

                            <div class="space-y-3">
                                <?php
                                $faqs = [
                                    ['q'=>'How do I book equipment?', 'a'=>'Create an account, browse available equipment, and select your preferred date.'],
                                    ['q'=>'What payment methods are accepted?', 'a'=>'We accept Stripe payments and mobile money transfers.'],
                                    ['q'=>'Can I cancel a booking?', 'a'=>'Yes, cancellations are allowed up to 48 hours before the scheduled date.'],
                                ];
                                foreach($faqs as $faq): ?>
                                <div class="p-4 bg-mid rounded-sm">
                                    <div class="text-white font-semibold text-sm mb-2"><?= $faq['q'] ?></div>
                                    <div class="text-gray-400 text-xs"><?= $faq['a'] ?></div>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <a href="#"
                               class="inline-flex items-center gap-2 text-fes-red font-display font-700 text-sm uppercase tracking-wider mt-4 hover:gap-3 transition-all">
                                View All FAQs <i class="fas fa-arrow-right text-xs"></i>
                            </a>
                        </div>
                    </div>

                </div>

            </div>
        </div>
    </section>

    <!-- ═══════════════════ CTA ═══════════════════ -->
    <section class="py-24 bg-gray-50 grid-bg">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center">
                <span class="accent-line-center"></span>
                <h2 class="font-display font-900 text-gray-900 mb-4" style="font-size: clamp(2rem,4vw,3rem); letter-spacing:-0.01em;">
                    Ready to Get Started?
                </h2>
                <p class="text-gray-500 max-w-lg mx-auto text-base mb-10">
                    Create your account today and start booking equipment for your farming operations.
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="auth/register.php"
                       class="inline-flex items-center justify-center gap-3 bg-fes-red hover:bg-red-700 text-white font-display font-700 uppercase tracking-wider px-8 py-4 rounded-sm shadow-lg transition-all duration-300 hover:shadow-xl"
                       style="font-size:0.9rem; letter-spacing:0.1em;">
                        <i class="fas fa-arrow-right text-xs"></i>
                        Create Account
                    </a>
                    <a href="equipment.php"
                       class="inline-flex items-center justify-center gap-3 border-2 border-gray-300 text-gray-700 hover:border-fes-red hover:text-fes-red font-display font-700 uppercase tracking-wider px-8 py-4 rounded-sm transition-all duration-300"
                       style="font-size:0.9rem; letter-spacing:0.1em;">
                        <i class="fas fa-tractor text-xs"></i>
                        Browse Equipment
                    </a>
                </div>
            </div>
        </div>
    </section>

    <?php include '../includes/footer.php'; ?>

</body>
</html>
