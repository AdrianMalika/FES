<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FES - Farming & Engineering Solutions</title>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        @media (max-width: 768px) {
            #fes-hero {
                padding: 70px 16px !important;
            }

            #fes-hero-grid {
                grid-template-columns: 1fr !important;
                gap: 30px !important;
                text-align: left !important;
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
        }
    </style>
</head>

<body style="margin: 0; padding: 0; font-family: Georgia, 'Times New Roman', serif; background-color: #f5f5f5; color: #424242;">
    
    <?php include '../includes/header.php'; ?>
    
    <!-- Hero Section -->
    <section id="fes-hero" style="background-color: #424242; color: #ffffff; padding: 100px 50px; min-height: 500px; display: flex; align-items: center; justify-content: center; position: relative;">
        <div id="fes-hero-grid" style="max-width: 1200px; width: 100%; display: grid; grid-template-columns: 1.2fr 1fr; gap: 80px; align-items: center;">
            <!-- Left Side: Content -->
            <div>

                <div style="text-transform: uppercase; color: #D32F2F; font-size: 14px; font-weight: 700; margin-bottom: 20px; letter-spacing: 2px;">
                    Agricultural Engineering Solutions
                </div>
                <h1 id="fes-hero-title" style="margin: 0 0 25px 0; font-size: 56px; font-weight: 900; line-height: 1.1; letter-spacing: -1px;">
                    Precision Equipment.<br>
                    <span style="color: #D32F2F;">Measured Results.</span>
                </h1>

                <p style="margin: 0 0 40px 0; font-size: 18px; line-height: 1.7; color: #cccccc; font-weight: 300;">
                    Manage your equipment fleet, scheduling, and logistics with the FES digital ecosystem. Reduce downtime and maximize project ROI.
                </p>
                
                <div id="fes-hero-actions" style="display: flex; gap: 20px; align-items: center;">
                    <a href="auth/register.php" style="display: inline-block; text-decoration: none; background-color: #D32F2F; color: #ffffff; padding: 16px 40px; border-radius: 6px; font-weight: 700; font-size: 15px; transition: all 0.3s; text-transform: uppercase; letter-spacing: 1px; box-shadow: 0 4px 6px rgba(0,0,0,0.2);" onmouseover="this.style.backgroundColor='#B71C1C'; this.style.transform='translateY(-2px)'" onmouseout="this.style.backgroundColor='#D32F2F'; this.style.transform='translateY(0)'">
                        Get Started
                    </a>

                    <a href="#services" style="display: inline-flex; align-items: center; text-decoration: none; color: #ffffff; font-weight: 500; font-size: 15px; transition: color 0.3s; text-transform: uppercase; letter-spacing: 1px; border: 1px solid #ffffff; padding: 15px 35px; border-radius: 6px;" onmouseover="this.style.backgroundColor='#ffffff'; this.style.color='#424242'" onmouseout="this.style.backgroundColor='transparent'; this.style.color='#ffffff'">
                        Learn More
                    </a>
                </div>
            </div>

            <!-- Right Side: Featured Image/Icon -->
             <div style="text-align: center; opacity: 0.8;">
                 <i class="fas fa-tractor" style="font-size: 300px; color: #ffffff; opacity: 0.1;"></i>
            </div>
        </div>
    </section>
    
    <!-- Services Section -->
    <section id="fes-services" style="padding: 90px 50px; background-color: #f5f5f5;">
        <div style="max-width: 1200px; margin: 0 auto;">
            <div style="text-align: center; margin-bottom: 60px;">
                <h2 style="margin: 0 0 15px 0; font-size: 36px; font-weight: 700; color: #212121;">Our Solutions</h2>
                <div style="width: 40px; height: 4px; background-color: #D32F2F; margin: 0 auto;"></div>
            </div>
            
            <div id="fes-services-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 30px;">
                <!-- Service 1 -->
                <div style="background: #ffffff; padding: 40px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); transition: all 0.3s;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 12px 24px rgba(0,0,0,0.1)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.05)'">
                    <div style="width: 60px; height: 60px; background-color: #ffebee; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 25px;">
                        <i class="fas fa-tools" style="font-size: 24px; color: #D32F2F;"></i>
                    </div>

                    <h3 style="margin: 0 0 15px 0; font-size: 20px; font-weight: 700; color: #212121;">Equipment Rental</h3>
                    <p style="margin: 0 0 25px 0; font-size: 15px; color: #757575; line-height: 1.6;">Access premium tractors, harvesters, and machinery on-demand with flexible rental terms.</p>
                </div>
                
                <!-- Service 2 -->
                <div style="background: #ffffff; padding: 40px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); transition: all 0.3s;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 12px 24px rgba(0,0,0,0.1)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.05)'">
                    <div style="width: 60px; height: 60px; background-color: #ffebee; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 25px;">
                        <i class="fas fa-file-contract" style="font-size: 24px; color: #D32F2F;"></i>
                    </div>

                    <h3 style="margin: 0 0 15px 0; font-size: 20px; font-weight: 700; color: #212121;">Contracting Services</h3>
                    <p style="margin: 0 0 25px 0; font-size: 15px; color: #757575; line-height: 1.6;">Professional land preparation, planting, and harvesting services by certified operators.</p>
                </div>
                
                <!-- Service 3 -->
                <div style="background: #ffffff; padding: 40px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); transition: all 0.3s;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 12px 24px rgba(0,0,0,0.1)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.05)'">
                    <div style="width: 60px; height: 60px; background-color: #ffebee; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 25px;">
                        <i class="fas fa-chart-line" style="font-size: 24px; color: #D32F2F;"></i>
                    </div>

                    <h3 style="margin: 0 0 15px 0; font-size: 20px; font-weight: 700; color: #212121;">Agrilab Analysis</h3>
                    <p style="margin: 0 0 25px 0; font-size: 15px; color: #757575; line-height: 1.6;">Expert agricultural consulting and data analytics to optimize yields and resource allocation.</p>
                </div>
            </div>
        </div>
    </section>
    
    <!-- CTA / How it Works Section -->
    <section id="fes-how" style="padding: 90px 50px; background-color: #ffffff;">
        <div style="max-width: 1200px; margin: 0 auto; text-align: center;">
            <h2 style="margin: 0 0 15px 0; font-size: 36px; font-weight: 700; color: #212121;">How It Works</h2>
             <div style="width: 40px; height: 4px; background-color: #D32F2F; margin: 0 auto 60px auto;"></div>

            <div id="fes-how-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 50px;">
                <!-- Step 1 -->
                <div style="text-align: center;">
                    <div style="width: 80px; height: 80px; background-color: #424242; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 25px auto; color: #ffffff; font-size: 24px; font-weight: 700;">1</div>
                    <h3 style="margin: 0 0 10px 0; font-size: 20px; font-weight: 700; color: #212121;">Browse Inventory</h3>
                    <p style="margin: 0; font-size: 15px; color: #757575; line-height: 1.6;">Search from our extensive catalog of professional-grade machinery.</p>
                </div>
                
                <!-- Step 2 -->
                <div style="text-align: center;">
                    <div style="width: 80px; height: 80px; background-color: #424242; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 25px auto; color: #ffffff; font-size: 24px; font-weight: 700;">2</div>
                    <h3 style="margin: 0 0 10px 0; font-size: 20px; font-weight: 700; color: #212121;">Reserve & Confirm</h3>
                    <p style="margin: 0; font-size: 15px; color: #757575; line-height: 1.6;">Select your dates and receive professional project confirmation.</p>
                </div>
                
                <!-- Step 3 -->
                <div style="text-align: center;">
                    <div style="width: 80px; height: 80px; background-color: #424242; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 25px auto; color: #ffffff; font-size: 24px; font-weight: 700;">3</div>
                    <h3 style="margin: 0 0 10px 0; font-size: 20px; font-weight: 700; color: #212121;">Deploy & Execute</h3>
                    <p style="margin: 0; font-size: 15px; color: #757575; line-height: 1.6;">Monitor your fleet and optimize project execution in real-time.</p>
                </div>
            </div>
             
             <div style="margin-top: 60px;">
                <a href="auth/register.php" style="display: inline-block; text-decoration: none; background-color: #D32F2F; color: #ffffff; padding: 18px 45px; border-radius: 6px; font-weight: 700; font-size: 15px; transition: all 0.3s; text-transform: uppercase; letter-spacing: 1px; box-shadow: 0 4px 6px rgba(211, 47, 47, 0.2);" onmouseover="this.style.backgroundColor='#B71C1C'; this.style.transform='translateY(-2px)'" onmouseout="this.style.backgroundColor='#D32F2F'; this.style.transform='translateY(0)'">
                    Create Account
                </a>
             </div>
        </div>
    </section>
    
    <?php include '../includes/footer.php'; ?>
    
</body>
</html>