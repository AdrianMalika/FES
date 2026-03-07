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
    <title>FES - Farm Equipment System</title>
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
                    Smart Farm Equipment Management
                </div>
                <h1 class="text-5xl lg:text-6xl font-black mb-6 leading-tight">
                    Smart Farm Equipment<br>
                    <span class="text-fes-red">Management Made Easy</span>
                </h1>

                <p class="text-lg text-gray-300 leading-relaxed mb-10">
                    FES streamlines equipment booking, monitoring, maintenance, and reporting for agricultural stakeholders. Improve utilization, reduce downtime, and make data-driven decisions.
                </p>
                
                <div class="flex flex-col sm:flex-row gap-5 items-center">
                    <a href="auth/register.php" class="inline-flex items-center justify-center px-10 py-4 bg-fes-red hover:bg-red-700 text-white font-bold rounded-lg shadow-lg transition-all duration-300 text-base uppercase tracking-wide">
                        Get Started
                    </a>

                    <a href="auth/signin.php" class="inline-flex items-center justify-center px-10 py-4 border-2 border-white text-white hover:bg-white hover:bg-opacity-10 font-bold rounded-lg transition-all duration-300 text-base uppercase tracking-wide">
                        Login
                    </a>
                </div>
            </div>

            <!-- Right Side: Icon -->
            <div class="text-center opacity-15">
                <i class="fas fa-tractor text-[300px]"></i>
            </div>
        </div>
    </section>
    
    <!-- Services Section -->
    <section class="py-24 bg-gray-50">
        <div class="max-w-7xl mx-auto text-center mb-16">
            <h2 class="text-4xl font-playfair font-bold text-gray-900 mb-4">Powerful Features for Modern Farming</h2>
            <div class="w-10 h-1 bg-fes-red mx-auto"></div>
        </div>
        
        <div class="max-w-7xl mx-auto grid md:grid-cols-2 lg:grid-cols-3 gap-8">
            <!-- Feature 1 -->
            <div class="bg-white p-10 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 hover:-translate-y-2 border border-gray-100">
                <div class="w-16 h-16 bg-red-50 rounded-full flex items-center justify-center mb-6 text-fes-red text-2xl">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-4">Equipment Booking & Scheduling</h3>
                <p class="text-gray-600 leading-relaxed">Easily browse available equipment, check real-time availability, and book resources for your farming operations with an intuitive scheduling system.</p>
            </div>
            
            <!-- Feature 2 -->
            <div class="bg-white p-10 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 hover:-translate-y-2 border border-gray-100">
                <div class="w-16 h-16 bg-red-50 rounded-full flex items-center justify-center mb-6 text-fes-red text-2xl">
                    <i class="fas fa-tools"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-4">Maintenance Monitoring & Alerts</h3>
                <p class="text-gray-600 leading-relaxed">Track equipment health, receive maintenance alerts, and schedule preventive maintenance to minimize unexpected breakdowns and extend equipment lifespan.</p>
            </div>
            
            <!-- Feature 3 -->
            <div class="bg-white p-10 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 hover:-translate-y-2 border border-gray-100">
                <div class="w-16 h-16 bg-red-50 rounded-full flex items-center justify-center mb-6 text-fes-red text-2xl">
                    <i class="fas fa-chart-line"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-4">Usage Tracking & Reports</h3>
                <p class="text-gray-600 leading-relaxed">Generate comprehensive reports on equipment usage, utilization rates, and operational metrics to make informed decisions and optimize resource allocation.</p>
            </div>

            <!-- Feature 4 -->
            <div class="bg-white p-10 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 hover:-translate-y-2 border border-gray-100">
                <div class="w-16 h-16 bg-red-50 rounded-full flex items-center justify-center mb-6 text-fes-red text-2xl">
                    <i class="fas fa-star"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-4">Ratings & Feedback System</h3>
                <p class="text-gray-600 leading-relaxed">Share experiences and rate equipment quality. Collect feedback from users to continuously improve service quality and equipment performance.</p>
            </div>

            <!-- Feature 5 -->
            <div class="bg-white p-10 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 hover:-translate-y-2 border border-gray-100">
                <div class="w-16 h-16 bg-red-50 rounded-full flex items-center justify-center mb-6 text-fes-red text-2xl">
                    <i class="fas fa-bell"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-4">Notifications & Alerts</h3>
                <p class="text-gray-600 leading-relaxed">Stay informed with real-time notifications about bookings, maintenance schedules, equipment availability, and important system updates.</p>
            </div>

            <!-- Feature 6 -->
            <div class="bg-white p-10 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 hover:-translate-y-2 border border-gray-100">
                <div class="w-16 h-16 bg-red-50 rounded-full flex items-center justify-center mb-6 text-fes-red text-2xl">
                    <i class="fas fa-lock"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-4">Data Security & Privacy</h3>
                <p class="text-gray-600 leading-relaxed">Your farm data is protected with enterprise-grade security measures and compliance with agricultural data protection standards.</p>
            </div>

            <!-- Equipment Catalogue Feature -->
            <div class="bg-white p-10 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 hover:-translate-y-2 border border-gray-100">
                <div class="w-16 h-16 bg-red-50 rounded-full flex items-center justify-center mb-6 text-fes-red text-2xl">
                    <i class="fas fa-tractor"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-4">Equipment Catalogue</h3>
                <p class="text-gray-600 leading-relaxed mb-4">Browse our comprehensive catalogue of farming and engineering equipment available for booking.</p>
                <a href="equipment.php" class="inline-flex items-center px-6 py-3 bg-fes-red hover:bg-red-700 text-white font-bold rounded-lg transition-all duration-300 text-sm">
                    <i class="fas fa-arrow-right mr-2"></i>
                    Browse Equipment
                </a>
        </div>
    </section>
    
    <!-- How It Works Section -->
    <section class="py-24 bg-white">
        <div class="max-w-7xl mx-auto text-center mb-16">
            <h2 class="text-4xl font-playfair font-bold text-gray-900 mb-4">How It Works</h2>
            <div class="w-10 h-1 bg-orange-600 mx-auto"></div>
        </div>

        <div class="max-w-7xl mx-auto grid md:grid-cols-3 gap-12">
            <!-- Step 1 -->
            <div class="text-center">
                <div class="w-20 h-20 bg-fes-dark rounded-full flex items-center justify-center mx-auto mb-6 text-white text-3xl font-bold">
                    1
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-3">Register an Account</h3>
                <p class="text-gray-600 leading-relaxed">Create your FES account with your farm details and contact information to get started.</p>
            </div>
            
            <!-- Step 2 -->
            <div class="text-center">
                <div class="w-20 h-20 bg-fes-dark rounded-full flex items-center justify-center mx-auto mb-6 text-white text-3xl font-bold">
                    2
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-3">Browse & Book Equipment</h3>
                <p class="text-gray-600 leading-relaxed">Explore available equipment, check schedules, and book resources for your farming needs.</p>
            </div>
            
            <!-- Step 3 -->
            <div class="text-center">
                <div class="w-20 h-20 bg-fes-dark rounded-full flex items-center justify-center mx-auto mb-6 text-white text-3xl font-bold">
                    3
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-3">Track & Monitor</h3>
                <p class="text-gray-600 leading-relaxed">Monitor equipment usage, receive notifications, and track maintenance schedules in real-time.</p>
            </div>
        </div>
         
        <div class="text-center mt-12">
            <a href="auth/register.php" class="inline-flex items-center justify-center px-8 py-4 bg-fes-red hover:bg-red-700 text-white font-bold rounded-lg shadow-lg transition-all duration-300 text-base uppercase tracking-wide">
                Create Account
            </a>
        </div>
    </section>

    <!-- Benefits Section -->
    <section class="py-24 bg-gray-50">
        <div class="max-w-7xl mx-auto text-center mb-16">
            <h2 class="text-4xl font-playfair font-bold text-gray-900 mb-4">Why Choose FES?</h2>
            <div class="w-10 h-1 bg-fes-red mx-auto"></div>
        </div>

        <div class="max-w-7xl mx-auto grid lg:grid-cols-2 gap-16 items-center">
            <!-- Benefits List -->
            <div class="space-y-6">
                <div class="flex gap-4 items-start">
                    <div class="w-6 h-6 bg-fes-red rounded-full flex items-center justify-center flex-shrink-0 text-white text-sm font-bold">
                        ✓
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 mb-2">Improved Equipment Utilization</h3>
                        <p class="text-gray-600 leading-relaxed">Maximize equipment usage and reduce idle time with smart scheduling and real-time availability tracking.</p>
                    </div>
                </div>

                <div class="flex gap-4 items-start">
                    <div class="w-6 h-6 bg-fes-red rounded-full flex items-center justify-center flex-shrink-0 text-white text-sm font-bold">
                        ✓
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 mb-2">Reduced Equipment Downtime</h3>
                        <p class="text-gray-600 leading-relaxed">Prevent unexpected breakdowns with proactive maintenance monitoring and timely alerts.</p>
                    </div>
                </div>

                <div class="flex gap-4 items-start">
                    <div class="w-6 h-6 bg-fes-red rounded-full flex items-center justify-center flex-shrink-0 text-white text-sm font-bold">
                        ✓
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 mb-2">Transparency & Accountability</h3>
                        <p class="text-gray-600 leading-relaxed">Track equipment usage, maintenance history, and user feedback for complete visibility and accountability.</p>
                    </div>
                </div>

                <div class="flex gap-4 items-start">
                    <div class="w-6 h-6 bg-fes-red rounded-full flex items-center justify-center flex-shrink-0 text-white text-sm font-bold">
                        ✓
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 mb-2">Data-Driven Decision Making</h3>
                        <p class="text-gray-600 leading-relaxed">Access comprehensive reports and analytics to optimize resource allocation and improve farm operations.</p>
                    </div>
                </div>
            </div>

            <!-- Benefits Visual -->
            <div class="bg-gradient-to-br from-fes-dark to-fes-red rounded-2xl p-16 text-center text-white">
                <div class="text-6xl font-black mb-4">4+</div>
                <div class="text-2xl font-bold mb-2">Core Benefits</div>
                <div class="text-gray-200">Transforming farm equipment management</div>
            </div>
        </div>
    </section>

    <!-- Call to Action Section -->
    <section class="py-24 bg-white">
        <div class="max-w-4xl mx-auto text-center">
            <h2 class="text-4xl font-playfair font-bold text-gray-900 mb-5">Ready to Transform Your Farm Operations?</h2>
            <p class="text-lg text-gray-600 mb-10 leading-relaxed">Join agricultural stakeholders who are already using FES to streamline equipment management, reduce downtime, and make smarter decisions.</p>
            <div class="flex flex-col sm:flex-row gap-5 justify-center">
                <a href="auth/register.php" class="inline-flex items-center justify-center px-10 py-4 bg-fes-red hover:bg-red-700 text-white font-bold rounded-lg shadow-lg transition-all duration-300 text-base uppercase tracking-wide">
                    Get Started Now
                </a>
                <a href="#fes-services" class="inline-flex items-center justify-center px-10 py-4 border-2 border-gray-300 text-gray-700 hover:border-gray-400 hover:bg-gray-50 font-bold rounded-lg transition-all duration-300 text-base uppercase tracking-wide">
                    Learn More
                </a>
            </div>
        </div>
    </section>
    
    <?php include '../includes/footer.php'; ?>
    
</body>
</html>
