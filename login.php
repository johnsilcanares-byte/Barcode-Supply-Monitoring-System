<?php
session_start();
$error = "";
// Hardcoded accounts for testing
$accounts = [
    "admin" => ["password" => "admin123", "role" => "admin"],
    "user" => ["password" => "user123", "role" => "user"]
];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    if (isset($accounts[$username]) && $accounts[$username]['password'] === $password) {
        $_SESSION['user_id'] = 1; // just for testing
        $_SESSION['username'] = $username; // Store username for display
        $_SESSION['role'] = $accounts[$username]['role'];
        if ($_SESSION['role'] === 'admin') {
            header("Location: index.php");
        } else {
            header("Location: pos.php");
        }
        exit;
    } else {
        $error = "Invalid username or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login · Supply Inventory System</title>
    <!-- Bootstrap 5 & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz@14..32&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #0f172a;
            position: relative;
            padding: 1rem;
        }

        /* Animated gradient orbs */
        .bg-orb {
            position: fixed;
            width: 70vmax;
            height: 70vmax;
            border-radius: 50%;
            filter: blur(100px);
            opacity: 0.25;
            z-index: 0;
            animation: float 18s infinite alternate ease-in-out;
        }

        .orb-1 {
            background: #4f46e5;
            top: -30vh;
            left: -20vw;
        }

        .orb-2 {
            background: #a855f7;
            bottom: -30vh;
            right: -20vw;
            animation-delay: -5s;
        }

        .orb-3 {
            background: #3b82f6;
            width: 40vmax;
            height: 40vmax;
            bottom: 10vh;
            left: -10vw;
            filter: blur(120px);
            opacity: 0.15;
            animation: float 22s infinite alternate;
        }

        @keyframes float {
            0% { transform: translate(0, 0) scale(1); }
            100% { transform: translate(8%, 6%) scale(1.1); }
        }

        /* Main card */
        .login-wrapper {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 440px;
            margin: 1.5rem;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: 28px;
            padding: 2.5rem 2rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), 0 4px 18px 0 rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: transform 0.2s ease;
        }

        /* Brand area */
        .brand-icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 1.2rem;
            background: linear-gradient(145deg, #4f46e5, #7c3aed);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 28px;
            box-shadow: 0 12px 24px -8px rgba(79, 70, 229, 0.4);
        }

        h1 {
            font-size: 1.7rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            color: #0f172a;
            text-align: center;
            margin-bottom: 0.25rem;
        }

        .welcome-text {
            text-align: center;
            color: #475569;
            font-size: 0.95rem;
            margin-bottom: 1.8rem;
            font-weight: 500;
        }

        /* Error alert */
        .alert-error {
            background: #fef2f2;
            border-left: 5px solid #ef4444;
            color: #b91c1c;
            padding: 0.9rem 1rem;
            border-radius: 12px;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.2s ease;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Floating labels (custom) */
        .input-group-custom {
            margin-bottom: 1.4rem;
            position: relative;
        }

        .input-field {
            width: 100%;
            padding: 1rem 1rem 0.6rem 1rem;
            font-size: 1rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 16px;
            background: #ffffff;
            transition: all 0.2s;
            outline: none;
            color: #0f172a;
            font-weight: 500;
        }

        .input-field:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.15);
        }

        .input-field.error-shake {
            border-color: #ef4444;
            animation: shake 0.3s ease;
        }

        @keyframes shake {
            0%,100% { transform: translateX(0); }
            25% { transform: translateX(-4px); }
            75% { transform: translateX(4px); }
        }

        .floating-label {
            position: absolute;
            left: 1rem;
            top: 0.95rem;
            color: #64748b;
            font-size: 1rem;
            font-weight: 500;
            pointer-events: none;
            transition: 0.15s ease;
            background: transparent;
            padding: 0 4px;
        }

        .input-field:focus ~ .floating-label,
        .input-field:not(:placeholder-shown) ~ .floating-label {
            top: -0.5rem;
            left: 0.9rem;
            font-size: 0.75rem;
            background: #ffffff;
            color: #4f46e5;
            font-weight: 600;
            padding: 0 6px;
        }

        .input-field::placeholder {
            color: transparent;
        }

        /* Password toggle */
        .password-toggle-btn {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #64748b;
            cursor: pointer;
            padding: 6px;
            border-radius: 8px;
            transition: background 0.15s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .password-toggle-btn:hover {
            background: #f1f5f9;
            color: #1e293b;
        }

        /* Login button */
        .btn-login {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            border: none;
            color: white;
            font-weight: 600;
            padding: 0.9rem;
            border-radius: 16px;
            width: 100%;
            font-size: 1.05rem;
            letter-spacing: 0.3px;
            margin-top: 0.5rem;
            margin-bottom: 1.2rem;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 8px 18px -6px rgba(79, 70, 229, 0.5);
            border: 1px solid rgba(255,255,255,0.1);
            position: relative;
            overflow: hidden;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 24px -8px rgba(79, 70, 229, 0.6);
        }

        .btn-login:active {
            transform: translateY(1px);
            box-shadow: 0 4px 12px -4px rgba(79, 70, 229, 0.4);
        }

        .btn-login .spinner-border {
            width: 1.3rem;
            height: 1.3rem;
            border-width: 0.15em;
        }

        /* Demo accounts */
        .demo-section {
            background: #f8fafc;
            border-radius: 18px;
            padding: 1.2rem 1.2rem 0.8rem;
            border: 1px solid #e2e8f0;
            margin-top: 0.8rem;
        }

        .demo-header {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #334155;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-bottom: 1rem;
        }

        .demo-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.6rem 0;
            border-bottom: 1px dashed #cbd5e1;
        }

        .demo-item:last-child {
            border-bottom: none;
        }

        .demo-role {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            color: #1e293b;
        }

        .demo-creds {
            font-family: 'SF Mono', 'Menlo', monospace;
            font-size: 0.8rem;
            color: #475569;
            background: #eef2ff;
            padding: 0.2rem 0.6rem;
            border-radius: 30px;
        }

        .quick-btn {
            background: white;
            border: 1.5px solid #4f46e5;
            color: #4f46e5;
            font-weight: 600;
            font-size: 0.75rem;
            padding: 0.35rem 1rem;
            border-radius: 40px;
            transition: all 0.15s;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }

        .quick-btn:hover {
            background: #4f46e5;
            color: white;
            border-color: #4f46e5;
        }

        /* responsive */
        @media (max-width: 480px) {
            .login-card {
                padding: 1.8rem 1.5rem;
            }
            h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Animated background orbs -->
    <div class="bg-orb orb-1"></div>
    <div class="bg-orb orb-2"></div>
    <div class="bg-orb orb-3"></div>

    <div class="login-wrapper">
        <div class="login-card">
            <!-- Brand -->
            <div class="brand-icon">
                <i class="bi bi-box-seam"></i>
            </div>
            <h1>Supply Inventory</h1>
            <div class="welcome-text">Sign in to continue</div>

            <!-- Error message -->
            <?php if($error): ?>
                <div class="alert-error">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <!-- Login form -->
            <form method="post" id="loginForm" novalidate>
                <!-- Username -->
                <div class="input-group-custom">
                    <input type="text" 
                           class="input-field" 
                           id="username" 
                           name="username" 
                           placeholder=" "
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                           autocomplete="username"
                           required>
                    <label class="floating-label" for="username">
                        <i class="bi bi-person-fill me-1"></i>Username
                    </label>
                </div>

                <!-- Password -->
                <div class="input-group-custom">
                    <input type="password" 
                           class="input-field" 
                           id="password" 
                           name="password" 
                           placeholder=" "
                           autocomplete="current-password"
                           required>
                    <label class="floating-label" for="password">
                        <i class="bi bi-lock-fill me-1"></i>Password
                    </label>
                    <button type="button" class="password-toggle-btn" id="togglePasswordBtn" tabindex="-1" aria-label="Toggle password visibility">
                        <i class="bi bi-eye-slash" id="toggleIcon"></i>
                    </button>
                </div>

                <button type="submit" class="btn-login" id="loginBtn">
                    <span class="btn-text">Sign In</span>
                    <span class="spinner-border spinner-border-sm d-none" role="status" id="loginSpinner"></span>
                </button>
            </form>

            <!-- Demo accounts (quick login) -->
            <div class="demo-section">
                <div class="demo-header">
                    <i class="bi bi-people-fill"></i>
                    <span>Demo access</span>
                </div>
                <div class="demo-item">
                    <div class="demo-role">
                        <i class="bi bi-shield-shaded"></i> Admin
                        <span class="demo-creds">admin / admin123</span>
                    </div>
                    <button class="quick-btn" data-username="admin" data-password="admin123">Fill</button>
                </div>
                <div class="demo-item">
                    <div class="demo-role">
                        <i class="bi bi-person-fill"></i> User
                        <span class="demo-creds">user / user123</span>
                    </div>
                    <button class="quick-btn" data-username="user" data-password="user123">Fill</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function(){
            "use strict";

            const form = document.getElementById('loginForm');
            const usernameInput = document.getElementById('username');
            const passwordInput = document.getElementById('password');
            const toggleBtn = document.getElementById('togglePasswordBtn');
            const toggleIcon = document.getElementById('toggleIcon');
            const loginBtn = document.getElementById('loginBtn');
            const spinner = document.getElementById('loginSpinner');
            const btnText = loginBtn.querySelector('.btn-text');

            // --- Password visibility toggle ---
            toggleBtn.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                toggleIcon.className = type === 'password' ? 'bi bi-eye-slash' : 'bi bi-eye';
            });

            // --- Quick fill demo accounts ---
            document.querySelectorAll('.quick-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const user = this.dataset.username;
                    const pass = this.dataset.password;
                    if (user) usernameInput.value = user;
                    if (pass) passwordInput.value = pass;
                    // trigger floating label update
                    usernameInput.dispatchEvent(new Event('input', { bubbles: true }));
                    passwordInput.dispatchEvent(new Event('input', { bubbles: true }));
                    // subtle feedback
                    this.style.transform = 'scale(0.95)';
                    setTimeout(() => this.style.transform = '', 120);
                });
            });

            // --- Form submission with loading state & validation ---
            form.addEventListener('submit', function(e) {
                let isValid = true;
                
                // Simple validation: shake if empty
                if (!usernameInput.value.trim()) {
                    usernameInput.classList.add('error-shake');
                    setTimeout(() => usernameInput.classList.remove('error-shake'), 400);
                    isValid = false;
                }
                if (!passwordInput.value.trim()) {
                    passwordInput.classList.add('error-shake');
                    setTimeout(() => passwordInput.classList.remove('error-shake'), 400);
                    isValid = false;
                }
                
                if (!isValid) {
                    e.preventDefault();
                    return;
                }

                // Show loading state
                btnText.classList.add('d-none');
                spinner.classList.remove('d-none');
                loginBtn.disabled = true;
                
                // Form will submit normally; we prevent double submission
                setTimeout(() => {
                    // If form is still submitting, keep spinner (handled by page navigation)
                }, 100);
            });

            // Prevent multiple submissions (redundant but safe)
            let submitted = false;
            form.addEventListener('submit', function() {
                if (submitted) e.preventDefault();
                submitted = true;
            });

            // Auto-focus on username
            usernameInput.focus();

            // Enter key to submit
            form.addEventListener('keypress', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    const activeEl = document.activeElement;
                    if (activeEl && (activeEl.id === 'username' || activeEl.id === 'password')) {
                        e.preventDefault();
                        form.dispatchEvent(new Event('submit', { cancelable: true }));
                    }
                }
            });

            // Ensure floating labels update when value set programmatically
            ['input', 'change'].forEach(ev => {
                usernameInput.addEventListener(ev, () => {});
                passwordInput.addEventListener(ev, () => {});
            });

            // Remove error class on input
            [usernameInput, passwordInput].forEach(field => {
                field.addEventListener('input', () => field.classList.remove('error-shake'));
            });

            // If there's an error from PHP, shake fields gently
            <?php if($error): ?>
                usernameInput.classList.add('error-shake');
                passwordInput.classList.add('error-shake');
                setTimeout(() => {
                    usernameInput.classList.remove('error-shake');
                    passwordInput.classList.remove('error-shake');
                }, 500);
            <?php endif; ?>
        })();
    </script>
</body>
</html>