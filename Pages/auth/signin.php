<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - FES</title>
    <link rel="icon" type="image/png" href="../../assets/images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        @media (max-width: 768px) {
            body {
                height: auto !important;
                min-height: 100vh !important;
                align-items: stretch !important;
                justify-content: flex-start !important;
                padding: 16px 0 !important;
            }

            #fes-auth-shell {
                height: auto !important;
                flex-direction: column !important;
                width: 100% !important;
            }

            #fes-auth-left,
            #fes-auth-right {
                padding: 28px 18px !important;
            }

            #fes-auth-left {
                justify-content: flex-start !important;
                gap: 18px !important;
            }

            #fes-role-tabs {
                flex-wrap: wrap !important;
            }

            #fes-role-tabs button {
                flex: 1 1 120px !important;
                padding: 10px 12px !important;
            }

            #fes-auth-left h1 {
                font-size: 34px !important;
            }
        }
    </style>
</head>

<body
    style="margin: 0; padding: 0; font-family: Georgia, 'Times New Roman', serif; background-color: #f5f5f5; height: 100vh; display: flex; align-items: center; justify-content: center;">

    <div id="fes-auth-shell"
        style="display: flex; width: 90%; max-width: 1200px; height: 600px; background-color: #ffffff; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.15); overflow: hidden;">

        <!-- Left Panel -->
        <div id="fes-auth-left"
            style="flex: 1; background-color: #424242; color: #ffffff; padding: 60px 50px; display: flex; flex-direction: column; justify-content: space-between; position: relative;">
            <div>
                <h2 style="margin: 0 0 20px 0; font-size: 28px; font-weight: 300;">Hello!</h2>
                <h1 style="margin: 0 0 30px 0; font-size: 48px; font-weight: 700; line-height: 1.2;">Have a<br>GOOD DAY
                </h1>
                <p style="margin: 0; font-size: 16px; color: #cccccc; line-height: 1.6;">Get access to premium farming
                    equipment and expert engineering services today.</p>
            </div>

            <!-- Icon -->
            <div style="opacity: 0.3;">
                <i class="fas fa-tractor" style="font-size: 60px;"></i>
            </div>
        </div>

        <!-- Right Panel - Sign In Form -->
        <div id="fes-auth-right" style="flex: 1; padding: 60px 50px; display: flex; flex-direction: column; justify-content: center;">
            <div style="max-width: 400px; width: 100%;">
                <h2 style="margin: 0 0 10px 0; font-size: 32px; font-weight: 700; color: #212121;">Sign in</h2>
                <p style="margin: 0 0 40px 0; font-size: 14px; color: #757575;">Choose your account type and enter your
                    credentials</p>

                <!-- Account Type Tabs -->
                <div id="fes-role-tabs"
                    style="display: flex; gap: 10px; margin-bottom: 30px; background-color: #f5f5f5; padding: 5px; border-radius: 8px;">
                    <button type="button" id="tab-customer" onclick="switchRole('customer')"
                        style="flex: 1; padding: 12px 20px; background-color: #D32F2F; color: #ffffff; border: none; border-radius: 6px; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.3s;">Customer</button>
                    <button type="button" id="tab-operator" onclick="switchRole('operator')"
                        style="flex: 1; padding: 12px 20px; background-color: transparent; color: #757575; border: none; border-radius: 6px; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.3s;">Operator</button>
                    <button type="button" id="tab-admin" onclick="switchRole('admin')"
                        style="flex: 1; padding: 12px 20px; background-color: transparent; color: #757575; border: none; border-radius: 6px; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.3s;">Admin</button>
                </div>

                <form method="POST" action="">
                    <!-- Hidden field to track selected role -->
                    <input type="hidden" name="role" id="selected-role" value="customer">
                    
                    <!-- Email -->
                    <div style="margin-bottom: 20px;">
                        <label
                            style="display: block; margin-bottom: 8px; font-size: 14px; font-weight: 500; color: #212121;">Email</label>
                        <input type="email" name="email" placeholder="Enter your email" required
                            style="width: 100%; padding: 14px 16px; border: 1px solid #e0e0e0; border-radius: 6px; font-size: 14px; color: #212121; box-sizing: border-box; transition: border-color 0.3s;"
                            onfocus="this.style.borderColor='#D32F2F'" onblur="this.style.borderColor='#e0e0e0'">
                    </div>

                    <!-- Password -->
                    <div style="margin-bottom: 30px;">
                        <label
                            style="display: block; margin-bottom: 8px; font-size: 14px; font-weight: 500; color: #212121;">Password</label>
                        <input type="password" name="password" placeholder="Enter your password" required
                            style="width: 100%; padding: 14px 16px; border: 1px solid #e0e0e0; border-radius: 6px; font-size: 14px; color: #212121; box-sizing: border-box; transition: border-color 0.3s;"
                            onfocus="this.style.borderColor='#D32F2F'" onblur="this.style.borderColor='#e0e0e0'">
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" id="login-button"
                        style="width: 100%; padding: 16px; background-color: #424242; color: #ffffff; border: none; border-radius: 6px; font-size: 16px; font-weight: 600; cursor: pointer; transition: background-color 0.3s; margin-bottom: 25px;"
                        onmouseover="this.style.backgroundColor='#303030'"
                        onmouseout="this.style.backgroundColor='#424242'">Login as Customer</button>
                </form>

                <!-- Sign Up Link -->
                <p style="text-align: center; font-size: 14px; color: #757575; margin: 0;">
                    Don't have an account?
                    <a href="register.php" style="color: #D32F2F; text-decoration: none; font-weight: 500;">Create
                        Account</a>
                </p>
            </div>
        </div>

    </div>

    <script>
        function switchRole(role) {
            // Reset all tabs
            document.getElementById('tab-customer').style.backgroundColor = 'transparent';
            document.getElementById('tab-customer').style.color = '#757575';
            document.getElementById('tab-operator').style.backgroundColor = 'transparent';
            document.getElementById('tab-operator').style.color = '#757575';
            document.getElementById('tab-admin').style.backgroundColor = 'transparent';
            document.getElementById('tab-admin').style.color = '#757575';

            // Activate selected tab
            const activeTab = document.getElementById('tab-' + role);
            activeTab.style.backgroundColor = '#D32F2F';
            activeTab.style.color = '#ffffff';

            // Update hidden input
            document.getElementById('selected-role').value = role;

            // Update button text
            const roleName = role.charAt(0).toUpperCase() + role.slice(1);
            document.getElementById('login-button').textContent = 'Login as ' + roleName;
        }
    </script>

</body>

</html>