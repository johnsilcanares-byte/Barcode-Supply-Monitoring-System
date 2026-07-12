<?php
session_start();
require_once 'config/db.php';

$error = "";
$success = "";
$active_form = $_GET['form'] ?? 'login';

// Handle Signup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signup'])) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = trim($_POST['role'] ?? 'user');
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    
    $errors = [];
    
    if (empty($username) || strlen($username) < 3) {
        $errors[] = "Username must be at least 3 characters";
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required";
    }
    
    if (empty($password) || strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    if (empty($errors)) {
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $check_stmt->bind_param("ss", $username, $email);
        $check_stmt->execute();
        $check_stmt->store_result();
        
        if ($check_stmt->num_rows > 0) {
            $error = "Username or email already exists";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert_stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
            $insert_stmt->bind_param("ssss", $username, $email, $hashed_password, $role);
            
            if ($insert_stmt->execute()) {
                $success = "Account created successfully! Please login.";
                $active_form = 'login';
                $_POST = [];
            } else {
                $error = "Failed to create account";
            }
            $insert_stmt->close();
        }
        $check_stmt->close();
    } else {
        $error = implode(", ", $errors);
    }
}

// Handle Login - FIXED to match your working logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if (!empty($username) && !empty($password)) {
        // FIRST: Check in admins table
        $adminQuery = "SELECT id, username, password FROM admins WHERE username = ? LIMIT 1";
        $adminStmt = $conn->prepare($adminQuery);
        $adminStmt->bind_param("s", $username);
        $adminStmt->execute();
        $adminResult = $adminStmt->get_result();
        
        if ($adminResult->num_rows === 1) {
            $admin = $adminResult->fetch_assoc();
            
            if (password_verify($password, $admin['password'])) {
                $_SESSION['user_id'] = $admin['id'];
                $_SESSION['username'] = $admin['username'];
                $_SESSION['is_admin'] = true;
                $_SESSION['role'] = 'admin';
                
                $adminStmt->close();
                $conn->close();
                
                $loginSuccess = true;
                $redirectRole = 'admin';
            } else {
                $error = "Invalid username or password.";
            }
        } else {
            $adminStmt->close();
            
            // SECOND: Check in users table
            $userQuery = "SELECT id, username, password, role FROM users WHERE username = ? LIMIT 1";
            $userStmt = $conn->prepare($userQuery);
            $userStmt->bind_param("s", $username);
            $userStmt->execute();
            $userResult = $userStmt->get_result();
            
            if ($userResult->num_rows === 1) {
                $user = $userResult->fetch_assoc();
                
                if (password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['is_admin'] = false;
                    $_SESSION['role'] = $user['role'];
                    
                    $loginSuccess = true;
                    $redirectRole = $user['role'];
                } else {
                    $error = "Invalid username or password.";
                }
            } else {
                $error = "Account not found. Please sign up first.";
            }
            $userStmt->close();
        }
    } else {
        $error = "Please enter username and password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes, maximum-scale=1.0">
    <title>DEBESMSCAT · Supply Inventory System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300..800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Plus Jakarta Sans', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            padding: 1rem;
            overflow-x: hidden;
        }

        /* Animated Background */
        .bg-image {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -2;
        }

        .bg-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            display: block;
            filter: brightness(1.1) contrast(1.05);
            animation: slowZoom 20s ease-in-out infinite;
        }

        @keyframes slowZoom {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .bg-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            background: linear-gradient(135deg, 
                rgba(0, 20, 10, 0.4) 0%, 
                rgba(0, 40, 20, 0.3) 50%,
                rgba(0, 60, 30, 0.35) 100%);
        }

        /* Floating Particles */
        .particle {
            position: fixed;
            width: 4px;
            height: 4px;
            background: rgba(46, 156, 94, 0.5);
            border-radius: 50%;
            pointer-events: none;
            z-index: -1;
            animation: floatParticle 15s infinite linear;
        }

        @keyframes floatParticle {
            0% {
                transform: translateY(100vh) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 0.5;
            }
            90% {
                opacity: 0.5;
            }
            100% {
                transform: translateY(-100vh) rotate(360deg);
                opacity: 0;
            }
        }

        .login-wrapper {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 520px;
            margin: 1rem;
            animation: fadeInUp 0.8s cubic-bezier(0.2, 0.9, 0.4, 1.1);
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(40px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-card {
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border-radius: 48px;
            padding: 2.5rem 2rem;
            box-shadow: 0 30px 60px -20px rgba(0, 0, 0, 0.4),
                        0 0 0 1px rgba(46, 156, 94, 0.2),
                        inset 0 1px 0 rgba(255,255,255,0.6);
            border: 1px solid rgba(255, 255, 255, 0.5);
            transition: all 0.4s cubic-bezier(0.2, 0.9, 0.4, 1.1);
        }

        .login-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 40px 70px -25px rgba(0, 0, 0, 0.5),
                        0 0 0 1px rgba(46, 156, 94, 0.3),
                        inset 0 1px 0 rgba(255,255,255,0.7);
            background: rgba(255, 255, 255, 0.96);
        }

        .logo-container {
            display: flex;
            justify-content: center;
            margin-bottom: 1.5rem;
            position: relative;
        }

        .logo-img {
            width: 100px;
            height: 100px;
            object-fit: contain;
            filter: drop-shadow(0 10px 25px rgba(0, 40, 10, 0.3));
            transition: all 0.3s ease;
            animation: logoPulse 3s ease-in-out infinite;
            border-radius: 50%;
        }

        @keyframes logoPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .logo-img:hover {
            transform: scale(1.08);
            filter: drop-shadow(0 15px 30px rgba(0, 50, 15, 0.4));
        }

        h1 {
            font-size: 1.8rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            text-align: center;
            margin-bottom: 0.25rem;
        }

        .title-main {
            background: linear-gradient(135deg, #0a4a25, #1a7a42, #2e9c5e);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .title-sub {
            font-size: 0.9rem;
            font-weight: 500;
            color: #2a5e3a;
            letter-spacing: 1px;
        }

        .welcome-text {
            text-align: center;
            color: #2a5e3a;
            font-size: 0.85rem;
            margin-bottom: 2rem;
            font-weight: 500;
            letter-spacing: 0.5px;
            opacity: 0.9;
        }

        /* Alert animations */
        .alert-error, .alert-success {
            padding: 0.9rem 1.2rem;
            border-radius: 28px;
            font-size: 0.9rem;
            margin-bottom: 1.8rem;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            animation: slideAlert 0.4s cubic-bezier(0.2, 0.9, 0.4, 1.1);
        }

        .alert-error {
            background: rgba(220, 83, 79, 0.12);
            border-left: 4px solid #d9534f;
            color: #a94442;
            border: 1px solid rgba(220, 80, 70, 0.3);
        }

        .alert-success {
            background: rgba(92, 184, 92, 0.12);
            border-left: 4px solid #5cb85c;
            color: #3c763d;
            border: 1px solid rgba(80, 180, 80, 0.3);
        }

        @keyframes slideAlert {
            from { opacity: 0; transform: translateY(-15px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Input fields - Enhanced */
        .input-group-custom {
            margin-bottom: 1.8rem;
            position: relative;
        }

        .input-field {
            width: 100%;
            padding: 1.1rem 1rem 0.6rem 1rem;
            font-size: 1rem;
            border: 2px solid rgba(60, 140, 85, 0.2);
            border-radius: 32px;
            background: rgba(255, 255, 255, 0.95);
            transition: all 0.3s ease;
            outline: none;
            color: #0b331b;
            font-weight: 500;
        }

        .input-field:focus {
            border-color: #2e9c5e;
            box-shadow: 0 0 0 5px rgba(46, 156, 94, 0.12);
            background: #ffffff;
            transform: translateY(-2px);
        }

        .input-field:focus ~ .floating-label,
        .input-field:not(:placeholder-shown) ~ .floating-label {
            top: -0.6rem;
            left: 1rem;
            font-size: 0.7rem;
            background: white;
            color: #1a7a42;
            font-weight: 700;
            border-radius: 30px;
            padding: 0 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .floating-label {
            position: absolute;
            left: 1.1rem;
            top: 0.95rem;
            color: #5a8a6a;
            font-size: 0.95rem;
            font-weight: 500;
            pointer-events: none;
            transition: 0.25s cubic-bezier(0.2, 0.9, 0.4, 1.1);
            background: transparent;
            padding: 0 5px;
        }

        .input-field::placeholder {
            color: transparent;
        }

        /* Password toggle - Enhanced */
        .password-toggle-btn {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(60, 140, 80, 0.08);
            border: none;
            color: #1e6b3b;
            cursor: pointer;
            padding: 10px;
            border-radius: 50%;
            transition: 0.25s;
            display: flex;
            z-index: 11;
        }

        .password-toggle-btn:hover {
            background: rgba(46, 156, 94, 0.2);
            color: #0a4622;
            transform: translateY(-50%) scale(1.05);
        }

        /* Role Select - Enhanced */
        .role-select {
            width: 100%;
            padding: 1.1rem 1rem 0.6rem 1rem;
            font-size: 1rem;
            border: 2px solid rgba(60, 140, 85, 0.2);
            border-radius: 32px;
            background: rgba(255, 255, 255, 0.95);
            color: #0b331b;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .role-select:focus {
            border-color: #2e9c5e;
            box-shadow: 0 0 0 5px rgba(46, 156, 94, 0.12);
            outline: none;
        }

        /* Button - Enhanced */
        .btn-submit {
            background: linear-gradient(135deg, #1a7a42, #2e9c5e, #3bb56a);
            background-size: 200% 200%;
            border: none;
            color: white;
            font-weight: 700;
            padding: 1rem;
            border-radius: 48px;
            width: 100%;
            font-size: 1.05rem;
            letter-spacing: 0.5px;
            margin-top: 0.5rem;
            transition: all 0.35s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            box-shadow: 0 8px 25px -8px rgba(30, 130, 70, 0.5);
            border: 1px solid rgba(255,255,255,0.3);
            animation: gradientShift 4s ease infinite;
            position: relative;
            overflow: hidden;
        }

        .btn-submit::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255,255,255,0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .btn-submit:active::before {
            width: 300px;
            height: 300px;
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px -10px #1e6b3f;
            gap: 15px;
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        /* Form Footer */
        .form-footer {
            text-align: center;
            margin-top: 1.8rem;
            padding-top: 1.8rem;
            border-top: 1px solid rgba(60, 140, 85, 0.15);
        }

        .toggle-form-link {
            background: none;
            border: none;
            color: #1a7a42;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.95rem;
            padding: 0.5rem 1rem;
            border-radius: 40px;
        }

        .toggle-form-link:hover {
            color: #2e9c5e;
            background: rgba(46, 156, 94, 0.1);
            transform: translateX(3px);
        }

        .form-hidden {
            display: none;
        }

        /* Responsive Design */
        @media (max-width: 640px) {
            .login-card {
                padding: 2rem 1.5rem;
                border-radius: 36px;
            }
            
            h1 {
                font-size: 1.5rem;
            }
            
            .title-sub {
                font-size: 0.75rem;
            }
            
            .logo-img {
                width: 80px;
                height: 80px;
            }
            
            .btn-submit {
                font-size: 0.95rem;
                padding: 0.9rem;
            }
        }

        @media (max-width: 480px) {
            .login-card {
                padding: 1.8rem 1.2rem;
                border-radius: 32px;
            }
            
            .input-field {
                font-size: 0.9rem;
            }
            
            .floating-label {
                font-size: 0.85rem;
            }
        }

        /* Modal - Enhanced */
        .modal-content {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border: 1px solid #2e9c5e;
            border-radius: 40px;
            box-shadow: 0 30px 60px rgba(0,0,0,0.2);
        }
        
        .modal-header {
            border-bottom: none;
        }
        
        .modal-footer {
            border-top: none;
        }
        
        /* Loading spinner */
        .spinner-border {
            width: 2.5rem;
            height: 2.5rem;
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: rgba(46, 156, 94, 0.1);
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: rgba(46, 156, 94, 0.5);
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: rgba(46, 156, 94, 0.7);
        }
        
        /* Focus visible for accessibility */
        .input-field:focus-visible,
        .role-select:focus-visible,
        .btn-submit:focus-visible {
            outline: 2px solid #2e9c5e;
            outline-offset: 2px;
        }
    </style>
</head>
<body>

    <!-- Background -->
    <div class="bg-image">
        <img src="image/bg-image.jpeg" alt="DEBESMSCAT Campus Background">
    </div>
    <div class="bg-overlay"></div>
    
    <!-- Floating Particles -->
    <script>
        for(let i = 0; i < 30; i++) {
            const particle = document.createElement('div');
            particle.classList.add('particle');
            particle.style.left = Math.random() * 100 + '%';
            particle.style.animationDelay = Math.random() * 20 + 's';
            particle.style.animationDuration = 10 + Math.random() * 20 + 's';
            particle.style.width = (2 + Math.random() * 6) + 'px';
            particle.style.height = particle.style.width;
            document.body.appendChild(particle);
        }
    </script>

    <div class="login-wrapper">
        <div class="login-card">
            
            <div class="logo-container">
                <img src="image/logo.jpg" alt="DEBESMSCAT Logo" class="logo-img">
            </div>

            <h1>
                <span class="title-main">DEBESMSCAT</span><br>
                <span class="title-sub">Supply Inventory System</span>
            </h1>
            <div class="welcome-text">✦ Smart Tracking · Smart Inventory Management ✦</div>

            <?php if(isset($error) && $error): ?>
                <div class="alert-error">
                    <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <?php if(isset($success) && $success): ?>
                <div class="alert-success">
                    <i class="bi bi-check-circle-fill fs-5"></i>
                    <span><?= htmlspecialchars($success) ?></span>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <div id="loginFormContainer" class="form-container <?= $active_form === 'login' ? '' : 'form-hidden' ?>">
                <form method="post" action="">
                    <div class="input-group-custom">
                        <input type="text" class="input-field" id="login_username" name="username" placeholder=" " 
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                        <label class="floating-label" for="login_username"><i class="bi bi-person-fill me-1"></i>Username</label>
                    </div>

                    <div class="input-group-custom">
                        <input type="password" class="input-field" id="login_password" name="password" placeholder=" ">
                        <label class="floating-label" for="login_password"><i class="bi bi-lock-fill me-1"></i>Password</label>
                        <button type="button" class="password-toggle-btn toggle-login-pwd">
                            <i class="bi bi-eye-slash"></i>
                        </button>
                    </div>

                    <button type="submit" name="login" class="btn-submit">
                        <i class="bi bi-box-arrow-in-right"></i> Sign In
                    </button>
                </form>
                <div class="form-footer">
                    <span style="color: #5a8a6a;">New to the system?</span>
                    <button type="button" class="toggle-form-link" onclick="switchToSignup()">
                        Create Account <i class="bi bi-arrow-right"></i>
                    </button>
                </div>
            </div>

            <!-- Signup Form -->
            <div id="signupFormContainer" class="form-container <?= $active_form === 'signup' ? '' : 'form-hidden' ?>">
                <form method="post" action="">
                    <div class="input-group-custom">
                        <input type="text" class="input-field" id="signup_username" name="username" placeholder=" " 
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                        <label class="floating-label" for="signup_username"><i class="bi bi-person-fill me-1"></i>Username</label>
                    </div>

                    <div class="input-group-custom">
                        <input type="email" class="input-field" id="signup_email" name="email" placeholder=" "
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                        <label class="floating-label" for="signup_email"><i class="bi bi-envelope-fill me-1"></i>Email</label>
                    </div>

                    <div class="input-group-custom">
                        <select class="role-select" id="signup_role" name="role">
                            <option value="user">User</option>
                        
                        </select>
                        <label class="floating-label" for="signup_role" style="top: -0.6rem; left: 1rem; font-size: 0.7rem; background: white; padding: 0 10px;">
                            <i class="bi bi-briefcase-fill me-1"></i>Role
                        </label>
                    </div>

                    <div class="input-group-custom">
                        <input type="password" class="input-field" id="signup_password" name="password" placeholder=" ">
                        <label class="floating-label" for="signup_password"><i class="bi bi-lock-fill me-1"></i>Password</label>
                        <button type="button" class="password-toggle-btn toggle-signup-pwd">
                            <i class="bi bi-eye-slash"></i>
                        </button>
                    </div>

                    <div class="input-group-custom">
                        <input type="password" class="input-field" id="signup_confirm_password" name="confirm_password" placeholder=" ">
                        <label class="floating-label" for="signup_confirm_password"><i class="bi bi-shield-lock-fill me-1"></i>Confirm Password</label>
                        <button type="button" class="password-toggle-btn toggle-confirm-pwd">
                            <i class="bi bi-eye-slash"></i>
                        </button>
                    </div>

                    <button type="submit" name="signup" class="btn-submit">
                        <i class="bi bi-person-plus"></i> Create Account
                    </button>
                </form>
                <div class="form-footer">
                    <span style="color: #5a8a6a;">Already have an account?</span>
                    <button type="button" class="toggle-form-link" onclick="switchToLogin()">
                        <i class="bi bi-arrow-left"></i> Sign In
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Login Success Modal -->
    <div class="modal fade" id="loginModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title w-100 text-center" style="color: #1b7a44;">
                        <i class="bi bi-check-circle-fill me-2" style="color: #2e9c5e;"></i>Welcome to DEBESMSCAT SIS
                    </h5>
                </div>
                <div class="modal-body text-center py-4">
                    <div class="mb-3">
                        <i class="bi bi-person-circle" style="font-size: 4rem; color: #2e9c5e;"></i>
                    </div>
                    <p class="mb-2 fs-5 fw-semibold">Welcome, <span id="modalUsername" style="color: #1a7a42;"></span>!</p>
                    <p class="text-secondary" id="modalRoleText">Loading your dashboard...</p>
                    <div class="spinner-border text-success mt-3" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function switchToSignup() {
            document.getElementById('loginFormContainer').classList.add('form-hidden');
            document.getElementById('signupFormContainer').classList.remove('form-hidden');
            const url = new URL(window.location.href);
            url.searchParams.set('form', 'signup');
            window.history.pushState({}, '', url);
            // Clear any existing alerts
            const alerts = document.querySelectorAll('.alert-error, .alert-success');
            alerts.forEach(alert => alert.remove());
        }
        
        function switchToLogin() {
            document.getElementById('signupFormContainer').classList.add('form-hidden');
            document.getElementById('loginFormContainer').classList.remove('form-hidden');
            const url = new URL(window.location.href);
            url.searchParams.set('form', 'login');
            window.history.pushState({}, '', url);
            // Clear any existing alerts
            const alerts = document.querySelectorAll('.alert-error, .alert-success');
            alerts.forEach(alert => alert.remove());
        }
        
        // Password toggle functionality
        const toggleButtons = document.querySelectorAll('.password-toggle-btn');
        toggleButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                const input = this.parentElement.querySelector('input');
                const icon = this.querySelector('i');
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('bi-eye-slash');
                    icon.classList.add('bi-eye');
                } else {
                    input.type = 'password';
                    icon.classList.remove('bi-eye');
                    icon.classList.add('bi-eye-slash');
                }
            });
        });
        
        // Add ripple effect to buttons
        const buttons = document.querySelectorAll('.btn-submit');
        buttons.forEach(button => {
            button.addEventListener('click', function(e) {
                const rect = button.getBoundingClientRect();
                const ripple = document.createElement('span');
                ripple.style.position = 'absolute';
                ripple.style.borderRadius = '50%';
                ripple.style.backgroundColor = 'rgba(255,255,255,0.5)';
                ripple.style.width = '10px';
                ripple.style.height = '10px';
                ripple.style.transform = 'translate(-50%, -50%)';
                ripple.style.left = (e.clientX - rect.left) + 'px';
                ripple.style.top = (e.clientY - rect.top) + 'px';
                ripple.style.animation = 'ripple 0.6s linear';
                button.style.position = 'relative';
                button.style.overflow = 'hidden';
                button.appendChild(ripple);
                
                setTimeout(() => ripple.remove(), 600);
            });
        });
        
        // Add ripple animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes ripple {
                0% {
                    width: 0px;
                    height: 0px;
                    opacity: 0.5;
                }
                100% {
                    width: 500px;
                    height: 500px;
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
        
        <?php if(isset($loginSuccess) && $loginSuccess): ?>
            (function showModalAndRedirect() {
                const modalEl = document.getElementById('loginModal');
                const modal = new bootstrap.Modal(modalEl);
                const usernameSpan = document.getElementById('modalUsername');
                const roleText = document.getElementById('modalRoleText');
                
                const username = <?= json_encode($_SESSION['username'] ?? '') ?>;
                const role = <?= json_encode($redirectRole ?? '') ?>;
                
                usernameSpan.textContent = username;
                
                if (role === 'admin') {
                    roleText.innerHTML = '<i class="bi bi-shield-lock-fill"></i> Redirecting to Admin Control Panel...';
                    setTimeout(() => {
                        window.location.href = 'dashboard.php';
                    }, 1500);
                } else {
                    roleText.innerHTML = '<i class="bi bi-box-seam-fill"></i> Redirecting to Inventory Dashboard...';
                    setTimeout(() => {
                        window.location.href = 'pos.php';
                    }, 1500);
                }
                
                modal.show();
            })();
        <?php endif; ?>
        
        // Form validation for signup
        const signupForm = document.querySelector('#signupFormContainer form');
        if (signupForm) {
            signupForm.addEventListener('submit', function(e) {
                const password = document.getElementById('signup_password').value;
                const confirm = document.getElementById('signup_confirm_password').value;
                const username = document.getElementById('signup_username').value;
                const email = document.getElementById('signup_email').value;
                
                if (username.length < 3) {
                    e.preventDefault();
                    alert('Username must be at least 3 characters long!');
                    return false;
                }
                
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(email)) {
                    e.preventDefault();
                    alert('Please enter a valid email address!');
                    return false;
                }
                
                if (password.length < 6) {
                    e.preventDefault();
                    alert('Password must be at least 6 characters long!');
                    return false;
                }
                
                if (password !== confirm) {
                    e.preventDefault();
                    alert('Passwords do not match!');
                    return false;
                }
            });
        }
    </script>
</body>
</html>