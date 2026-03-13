<?php
session_start();
require_once '../../includes/database.php';

$error = '';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? 'customer';
    switch($role) {
        case 'admin':
            header("Location: ../admin/dashboard.php");
            exit();
        case 'operator':
            header("Location: ../operator/dashboard.php");
            exit();
        default:
            header("Location: ../customer/dashboard.php");
            exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validation
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $conn = getDBConnection();
        $stmt = null;
        
        try {
            // Get user by email
            $stmt = $conn->prepare("SELECT user_id, name, email, password_hash, role FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $error = 'Invalid email or password.';
            } else {
                $user = $result->fetch_assoc();
                
                // Verify password
                if (!password_verify($password, $user['password_hash'])) {
                    $error = 'Invalid email or password.';
                } else {
                    // Login successful - create session
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['name'] = $user['name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    
                    // Redirect based on role
                    switch($user['role']) {
                        case 'admin':
                            header("Location: ../../pages/admin/dashboard.php");
                            exit();
                        case 'operator':
                            header("Location: ../../pages/operator/dashboard.php");
                            exit();
                        default:
                            header("Location: ../customer/dashboard.php");
                            exit();
                    }
                }
            }
            
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            $error = 'An error occurred during login. Please try again.';
        }
        
        if ($stmt !== null) {
            $stmt->close();
        }
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - FES</title>
    <link rel="icon" type="image/png" href="../../assets/images/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;600;700;800;900&family=Barlow:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        * { font-family: 'Barlow', sans-serif; }
        h1, h2, h3, h4, .display { font-family: 'Barlow Condensed', sans-serif; }

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
    style="margin: 0; padding: 0; background-color: #f5f5f5; height: 100vh; display: flex; align-items: center; justify-content: center;">

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
                <p style="margin: 0 0 40px 0; font-size: 14px; color: #757575;">Enter your credentials to continue</p>

                <?php if (!empty($error)): ?>
                    <div style="padding: 12px 16px; background-color: #FFEBEE; border-left: 4px solid #D32F2F; border-radius: 4px; margin-bottom: 20px;">
                        <p style="margin: 0; color: #C62828; font-size: 14px; font-weight: 500;"><?php echo htmlspecialchars($error); ?></p>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    
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
                    <button type="submit"
                        style="width: 100%; padding: 16px; background-color: #424242; color: #ffffff; border: none; border-radius: 6px; font-size: 16px; font-weight: 600; cursor: pointer; transition: background-color 0.3s; margin-bottom: 25px;"
                        onmouseover="this.style.backgroundColor='#303030'"
                        onmouseout="this.style.backgroundColor='#424242'">Sign In</button>
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

</body>

</html>

