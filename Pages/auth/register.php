<?php
session_start();
require_once '../../includes/database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validation
    if (empty($fullname) || empty($email) || empty($password)) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        $conn = getDBConnection();
        $stmt = null;
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Check if email already exists
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                throw new Exception('An account with this email already exists.');
            }
            
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert new user
            $stmt = $conn->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'customer')");
            $stmt->bind_param("sss", $fullname, $email, $hashed_password);
            
            if (!$stmt->execute()) {
                throw new Exception('An error occurred creating your account.');
            }
            
            // Get the newly created user_id
            $user_id = $conn->insert_id;
            
            // Create customer record
            $stmt = $conn->prepare("INSERT INTO customers (user_id) VALUES (?)");
            $stmt->bind_param("i", $user_id);
            
            if (!$stmt->execute()) {
                throw new Exception('An error occurred setting up your customer profile.');
            }
            
            // Commit transaction
            $conn->commit();
            
            $success = 'Account created successfully! Redirecting to sign in...';
            // Redirect after 2 seconds
            header("refresh:2;url=signin.php");
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error = $e->getMessage();
        }
        
        // Clean up resources
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
    <title>Create Account - FES</title>
    <link rel="icon" type="image/png" href="../../assets/images/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;600;700;800;900&family=Barlow:wght@300;400;500;600&display=swap" rel="stylesheet">

    <style>
        * { font-family: 'Barlow', sans-serif; }
        h1, h2, h3, h4, .display { font-family: 'Barlow Condensed', sans-serif; }

        @media (max-width: 768px) {
            body {
                align-items: stretch !important;
                justify-content: flex-start !important;
                padding: 16px 0 !important;
            }

            #fes-auth-shell {
                min-height: 0 !important;
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
        }
    </style>
</head>


<body
    style="margin: 0; padding: 0; background-color: #f5f5f5; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px 0;">

    <div id="fes-auth-shell"
        style="display: flex; width: 90%; max-width: 1200px; min-height: 650px; background-color: #ffffff; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.15); overflow: hidden;">

        <!-- Left Panel -->
        <div id="fes-auth-left"
            style="flex: 1; background-color: #424242; color: #ffffff; padding: 60px 50px; display: flex; flex-direction: column; justify-content: space-between; position: relative;">
            <div>
                <h2 style="margin: 0 0 15px 0; font-size: 24px; font-weight: 300; letter-spacing: 1px;">START YOUR JOURNEY</h2>
                <br>
                <p style="margin: 0; font-size: 15px; color: #cccccc; line-height: 1.7;">Access cutting-edge agricultural equipment and professional engineering solutions tailored for modern farming.</p>
            </div>

            <!-- Icon -->
            <div style="opacity: 0.3;">
                <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
            </div>
        </div>

        <!-- Right Panel - Create Account Form -->
        <div id="fes-auth-right" style="flex: 1; padding: 60px 50px; display: flex; flex-direction: column; justify-content: center;">
            <div style="max-width: 450px; width: 100%;">
                <h2 style="margin: 0 0 10px 0; font-size: 32px; font-weight: 700; color: #212121;">Create Account</h2>
                <p style="margin: 0 0 30px 0; font-size: 14px; color: #757575;">Please fill in your details to get
                    started</p>

                <?php if (!empty($error)): ?>
                    <div style="padding: 12px 16px; background-color: #FFEBEE; border-left: 4px solid #D32F2F; border-radius: 4px; margin-bottom: 20px;">
                        <p style="margin: 0; color: #C62828; font-size: 14px; font-weight: 500;"><?php echo htmlspecialchars($error); ?></p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div style="padding: 12px 16px; background-color: #E8F5E9; border-left: 4px solid #4CAF50; border-radius: 4px; margin-bottom: 20px;">
                        <p style="margin: 0; color: #2E7D32; font-size: 14px; font-weight: 500;"><?php echo htmlspecialchars($success); ?></p>
                    </div>
                <?php endif; ?>


                <form method="POST" action="">
                    <!-- Full Name -->
                    <div style="margin-bottom: 18px;">
                        <label
                            style="display: block; margin-bottom: 8px; font-size: 14px; font-weight: 500; color: #212121;">Full
                            Name</label>
                        <input type="text" name="fullname" placeholder="Enter your full name" required
                            style="width: 100%; padding: 14px 16px; border: 1px solid #e0e0e0; border-radius: 6px; font-size: 14px; color: #212121; box-sizing: border-box; transition: border-color 0.3s;"
                            onfocus="this.style.borderColor='#D32F2F'" onblur="this.style.borderColor='#e0e0e0'">
                    </div>

                    <!-- Email -->
                    <div style="margin-bottom: 18px;">
                        <label
                            style="display: block; margin-bottom: 8px; font-size: 14px; font-weight: 500; color: #212121;">Email
                            Address</label>
                        <input type="email" name="email" placeholder="Enter your email" required
                            style="width: 100%; padding: 14px 16px; border: 1px solid #e0e0e0; border-radius: 6px; font-size: 14px; color: #212121; box-sizing: border-box; transition: border-color 0.3s;"
                            onfocus="this.style.borderColor='#D32F2F'" onblur="this.style.borderColor='#e0e0e0'">
                    </div>

                    <!-- Password -->
                    <div style="margin-bottom: 30px;">
                        <label
                            style="display: block; margin-bottom: 8px; font-size: 14px; font-weight: 500; color: #212121;">Password</label>
                        <input type="password" name="password" placeholder="Create password" required
                            style="width: 100%; padding: 14px 16px; border: 1px solid #e0e0e0; border-radius: 6px; font-size: 14px; color: #212121; box-sizing: border-box; transition: border-color 0.3s;"
                            onfocus="this.style.borderColor='#D32F2F'" onblur="this.style.borderColor='#e0e0e0'">
                    </div>

                    <!-- Submit Button -->
                    <button type="submit"
                        style="width: 100%; padding: 16px; background-color: #424242; color: #ffffff; border: none; border-radius: 6px; font-size: 16px; font-weight: 600; cursor: pointer; transition: background-color 0.3s; margin-bottom: 20px;"
                        onmouseover="this.style.backgroundColor='#303030'"
                        onmouseout="this.style.backgroundColor='#424242'">Create Account</button>
                </form>

                <!-- Sign In Link -->
                <p style="text-align: center; font-size: 14px; color: #757575; margin: 0;">
                    Already have an account?
                    <a href="signin.php" style="color: #D32F2F; text-decoration: none; font-weight: 500;">Sign In</a>
                </p>
            </div>
        </div>

    </div>

</body> 

</html>
