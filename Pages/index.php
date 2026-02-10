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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Inter:wght@400;500;600&family=Poppins:wght@600;700&display=swap" rel="stylesheet">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #F8F6F1;
            color: #2C2C2C;
            line-height: 1.6;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Playfair Display', serif;
            font-weight: 700;
        }

        .btn-primary, button[type="submit"], .cta-button {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
        }

        /* Hero Section */
        #fes-hero {
            background: linear-gradient(135deg, #1B4332 0%, #2C5F4F 100%);
            color: #ffffff;
            padding: 100px 50px;
            min-height: 600px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        #fes-hero::before {
            content: '';
            position: absolute;
            top: 0;
            right: -100px;
            width: 400px;
            height: 400px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            z-index: 0;
        }

        #fes-hero-grid {
            max-width: 1200px;
            width: 100%;
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 80px;
            align-items: center;
            position: relative;
            z-index: 1;
        }

        #fes-hero-title {
            margin: 0 0 25px 0;
            font-size: 56px;
            font-weight: 900;
            line-height: 1.1;
            letter-spacing: -1px;
        }

        .hero-accent {
            color: #D4623B;
        }

        #fes-hero-subtitle {
            margin: 0 0 20px 0;
            text-transform: uppercase;
            color: #D4623B;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 2px;
        }

        #fes-hero-description {
            margin: 0 0 40px 0;
            font-size: 18px;
            line-height: 1.7;
            color: #E8E4DC;
            font-weight: 300;
        }

        #fes-hero-actions {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .btn-hero {
            display: inline-block;
            text-decoration: none;
            padding: 16px 40px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 15px;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 1px;
            border: none;
            cursor: pointer;
        }

        .btn-primary-hero {
            background-color: #D4623B;
            color: #ffffff;
            box-shadow: 0 4px 6px rgba(212, 98, 59, 0.2);
        }

        .btn-primary-hero:hover {
            background-color: #B84D2A;
            transform: translateY(-2px);
        }

        .btn-secondary-hero {
            background-color: transparent;
            color: #ffffff;
            border: 1px solid #ffffff;
        }

        .btn-secondary-hero:hover {
            background-color: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
        }

        #fes-hero-icon {
            text-align: center;
            opacity: 0.15;
        }

        /* Services Section */
        #fes-services {
            padding: 90px 50px;
            background-color: #ffffff;
        }

        #fes-services-header {
            text-align: center;
            margin-bottom: 60px;
        }

        #fes-services-header h2 {
            margin: 0 0 15px 0;
            font-size: 36px;
            font-weight: 700;
            color: #2C2C2C;
        }

        .section-divider {
            width: 40px;
            height: 4px;
            background-color: #D4623B;
            margin: 0 auto;
        }

        #fes-services-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .service-card {
            background: #ffffff;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
            border: 1px solid #E8E4DC;
        }

        .service-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.1);
        }

        .service-icon {
            width: 60px;
            height: 60px;
            background-color: #1B4332;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 25px;
            color: #ffffff;
            font-size: 24px;
        }

        .service-card h3 {
            margin: 0 0 15px 0;
            font-size: 20px;
            font-weight: 700;
            color: #2C2C2C;
        }

        .service-card p {
            margin: 0;
            font-size: 15px;
            color: #6B5B45;
            line-height: 1.6;
        }

        /* How It Works Section */
        #fes-how {
            padding: 90px 50px;
            background: linear-gradient(135deg, #1B4332 0%, #2C5F4F 100%);
            color: #ffffff;
        }

        #fes-how-header {
            text-align: center;
            margin-bottom: 60px;
        }

        #fes-how-header h2 {
            margin: 0 0 15px 0;
            font-size: 36px;
            font-weight: 700;
            color: #ffffff;
        }

        #fes-how-header .section-divider {
            background-color: #D4623B;
        }

        #fes-how-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 50px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .how-step {
            text-align: center;
        }

        .step-number {
            width: 80px;
            height: 80px;
            background-color: #D4623B;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px auto;
            color: #ffffff;
            font-size: 32px;
            font-weight: 700;
        }

        .how-step h3 {
            margin: 0 0 10px 0;
            font-size: 20px;
            font-weight: 700;
            color: #ffffff;
        }

        .how-step p {
            margin: 0;
            font-size: 15px;
            color: #E8E4DC;
            line-height: 1.6;
        }

        #fes-how-cta {
            margin-top: 60px;
            text-align: center;
        }

        /* Benefits Section */
        #fes-benefits {
            padding: 90px 50px;
            background-color: #ffffff;
        }

        #fes-benefits-header {
            text-align: center;
            margin-bottom: 60px;
        }

        #fes-benefits-header h2 {
            margin: 0 0 15px 0;
            font-size: 36px;
            font-weight: 700;
            color: #2C2C2C;
        }

        #fes-benefits-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            max-width: 1200px;
            margin: 0 auto;
            align-items: center;
        }

        .benefits-list {
            space-y: 24px;
        }

        .benefit-item {
            display: flex;
            gap: 16px;
            margin-bottom: 24px;
        }

        .benefit-check {
            width: 24px;
            height: 24px;
            background-color: #1B4332;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            flex-shrink: 0;
            font-size: 14px;
        }

        .benefit-text h3 {
            margin: 0 0 4px 0;
            font-size: 16px;
            font-weight: 700;
            color: #2C2C2C;
        }

        .benefit-text p {
            margin: 0;
            font-size: 14px;
            color: #6B5B45;
            line-height: 1.5;
        }

        .benefits-visual {
            background: linear-gradient(135deg, #1B4332 0%, #D4623B 100%);
            border-radius: 12px;
            padding: 60px;
            text-align: center;
            color: #ffffff;
            min-height: 300px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .benefits-visual-number {
            font-size: 64px;
            font-weight: 700;
            margin-bottom: 16px;
        }

        .benefits-visual-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .benefits-visual-desc {
            font-size: 16px;
            color: #E8E4DC;
        }

        /* CTA Section */
        #fes-cta {
            padding: 90px 50px;
            background: linear-gradient(135deg, #1B4332 0%, #2C5F4F 100%);
            color: #ffffff;
            text-align: center;
        }

        #fes-cta-content {
            max-width: 800px;
            margin: 0 auto;
        }

        #fes-cta h2 {
            margin: 0 0 20px 0;
            font-size: 36px;
            font-weight: 700;
        }

        #fes-cta p {
            margin: 0 0 40px 0;
            font-size: 18px;
            color: #E8E4DC;
            line-height: 1.7;
        }

        #fes-cta-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            #fes-hero {
                padding: 70px 16px !important;
            }

            #fes-hero-grid {
                grid-template-columns: 1fr !important;
                gap: 30px !important;
            }

            #fes-hero-title {
                font-size: 38px !important;
            }

            #fes-hero-actions {
                flex-direction: column !important;
                align-items: stretch !important;
            }

            #fes-services {
                padding: 60px 16px !important;
            }

            #fes-services-grid {
                grid-template-columns: 1fr !important;
            }

            #fes-how {
                padding: 60px 16px !important;
            }

            #fes-how-grid {
                grid-template-columns: 1fr !important;
                gap: 30px !important;
            }

            #fes-benefits {
                padding: 60px 16px !important;
            }

            #fes-benefits-grid {
                grid-template-columns: 1fr !important;
                gap: 30px !important;
            }

            #fes-cta {
                padding: 60px 16px !important;
            }

            #fes-cta h2 {
                font-size: 28px !important;
            }

            #fes-cta-buttons {
                flex-direction: column !important;
            }

            .btn-hero {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>

<body>
    
    <?php include '../includes/header.php'; ?>
    
    <!-- Hero Section -->
    <section id="fes-hero">
        <div id="fes-hero-grid">
            <!-- Left Side: Content -->
            <div>
                <div id="fes-hero-subtitle">
                    Smart Farm Equipment Management
                </div>
                <h1 id="fes-hero-title">
                    Smart Farm Equipment<br>
                    <span class="hero-accent">Management Made Easy</span>
                </h1>

                <p id="fes-hero-description">
                    FES streamlines equipment booking, monitoring, maintenance, and reporting for agricultural stakeholders. Improve utilization, reduce downtime, and make data-driven decisions.
                </p>
                
                <div id="fes-hero-actions">
                    <a href="auth/register.php" class="btn-hero btn-primary-hero">
                        Get Started
                    </a>

                    <a href="auth/signin.php" class="btn-hero btn-secondary-hero">
                        Login
                    </a>
                </div>
            </div>

            <!-- Right Side: Icon -->
            <div id="fes-hero-icon">
                <i class="fas fa-tractor" style="font-size: 300px;"></i>
            </div>
        </div>
    </section>
    
    <!-- Key Features Section -->
    <section id="fes-services">
        <div id="fes-services-header">
            <h2>Powerful Features for Modern Farming</h2>
            <div class="section-divider"></div>
        </div>
        
        <div id="fes-services-grid">
            <!-- Feature 1 -->
            <div class="service-card">
                <div class="service-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <h3>Equipment Booking & Scheduling</h3>
                <p>Easily browse available equipment, check real-time availability, and book resources for your farming operations with an intuitive scheduling system.</p>
            </div>
            
            <!-- Feature 2 -->
            <div class="service-card">
                <div class="service-icon">
                    <i class="fas fa-tools"></i>
                </div>
                <h3>Maintenance Monitoring & Alerts</h3>
                <p>Track equipment health, receive maintenance alerts, and schedule preventive maintenance to minimize unexpected breakdowns and extend equipment lifespan.</p>
            </div>
            
            <!-- Feature 3 -->
            <div class="service-card">
                <div class="service-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <h3>Usage Tracking & Reports</h3>
                <p>Generate comprehensive reports on equipment usage, utilization rates, and operational metrics to make informed decisions and optimize resource allocation.</p>
            </div>

            <!-- Feature 4 -->
            <div class="service-card">
                <div class="service-icon">
                    <i class="fas fa-star"></i>
                </div>
                <h3>Ratings & Feedback System</h3>
                <p>Share experiences and rate equipment quality. Collect feedback from users to continuously improve service quality and equipment performance.</p>
            </div>

            <!-- Feature 5 -->
            <div class="service-card">
                <div class="service-icon">
                    <i class="fas fa-bell"></i>
                </div>
                <h3>Notifications & Alerts</h3>
                <p>Stay informed with real-time notifications about bookings, maintenance schedules, equipment availability, and important system updates.</p>
            </div>

            <!-- Feature 6 -->
            <div class="service-card">
                <div class="service-icon">
                    <i class="fas fa-lock"></i>
                </div>
                <h3>Data Security & Privacy</h3>
                <p>Your farm data is protected with enterprise-grade security measures and compliance with agricultural data protection standards.</p>
            </div>
        </div>
    </section>
    
    <!-- How It Works Section -->
    <section id="fes-how">
        <div id="fes-how-header">
            <h2>How It Works</h2>
            <div class="section-divider"></div>
        </div>

        <div id="fes-how-grid">
            <!-- Step 1 -->
            <div class="how-step">
                <div class="step-number">1</div>
                <h3>Register an Account</h3>
                <p>Create your FES account with your farm details and contact information to get started.</p>
            </div>
            
            <!-- Step 2 -->
            <div class="how-step">
                <div class="step-number">2</div>
                <h3>Browse & Book Equipment</h3>
                <p>Explore available equipment, check schedules, and book resources for your farming needs.</p>
            </div>
            
            <!-- Step 3 -->
            <div class="how-step">
                <div class="step-number">3</div>
                <h3>Track & Monitor</h3>
                <p>Monitor equipment usage, receive notifications, and track maintenance schedules in real-time.</p>
            </div>

            <!-- Step 4 -->
            <div class="how-step">
                <div class="step-number">4</div>
                <h3>View Reports & Feedback</h3>
                <p>Access detailed reports, provide feedback, and make data-driven decisions for your farm.</p>
            </div>
        </div>
         
        <div id="fes-how-cta">
            <a href="auth/register.php" class="btn-hero btn-primary-hero">
                Create Account
            </a>
        </div>
    </section>

    <!-- Benefits Section -->
    <section id="fes-benefits">
        <div id="fes-benefits-header">
            <h2>Why Choose FES?</h2>
            <div class="section-divider"></div>
        </div>

        <div id="fes-benefits-grid">
            <!-- Benefits List -->
            <div class="benefits-list">
                <div class="benefit-item">
                    <div class="benefit-check">✓</div>
                    <div class="benefit-text">
                        <h3>Improved Equipment Utilization</h3>
                        <p>Maximize equipment usage and reduce idle time with smart scheduling and real-time availability tracking.</p>
                    </div>
                </div>

                <div class="benefit-item">
                    <div class="benefit-check">✓</div>
                    <div class="benefit-text">
                        <h3>Reduced Equipment Downtime</h3>
                        <p>Prevent unexpected breakdowns with proactive maintenance monitoring and timely alerts.</p>
                    </div>
                </div>

                <div class="benefit-item">
                    <div class="benefit-check">✓</div>
                    <div class="benefit-text">
                        <h3>Transparency & Accountability</h3>
                        <p>Track equipment usage, maintenance history, and user feedback for complete visibility and accountability.</p>
                    </div>
                </div>

                <div class="benefit-item">
                    <div class="benefit-check">✓</div>
                    <div class="benefit-text">
                        <h3>Data-Driven Decision Making</h3>
                        <p>Access comprehensive reports and analytics to optimize resource allocation and improve farm operations.</p>
                    </div>
                </div>
            </div>

            <!-- Benefits Visual -->
            <div class="benefits-visual">
                <div class="benefits-visual-number">4+</div>
                <div class="benefits-visual-title">Core Benefits</div>
                <div class="benefits-visual-desc">Transforming farm equipment management</div>
            </div>
        </div>
    </section>

    <!-- Call to Action Section -->
    <section id="fes-cta">
        <div id="fes-cta-content">
            <h2>Ready to Transform Your Farm Operations?</h2>
            <p>Join agricultural stakeholders who are already using FES to streamline equipment management, reduce downtime, and make smarter decisions.</p>
            <div id="fes-cta-buttons">
                <a href="auth/register.php" class="btn-hero btn-primary-hero">
                    Get Started Now
                </a>
                <a href="#fes-services" class="btn-hero btn-secondary-hero">
                    Learn More
                </a>
            </div>
        </div>
    </section>
    
    <?php include '../includes/footer.php'; ?>
    
</body>
</html>
