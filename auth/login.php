<?php
/**
 * Student Performance Prediction & Early Warning System
 * Login Page - Enhanced Version
 */

// Start secure session
session_start([
    'name' => 'StudentEWS_Session',
    'cookie_lifetime' => 86400,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'use_strict_mode' => true,
    'use_only_cookies' => true,
]);

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

// Check if already logged in
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: ../index.php');
    exit();
}

// Handle login attempts
$error = '';
$success_message = '';
$login_attempts = $_SESSION['login_attempts'] ?? 0;
$lockout_time = $_SESSION['lockout_time'] ?? 0;

// Check if account is locked out
if ($login_attempts >= 5 && time() < $lockout_time) {
    $remaining_time = ceil(($lockout_time - time()) / 60);
    $error = "Too many failed attempts. Please try again in $remaining_time minutes.";
} else {
    // Reset attempts if lockout period has passed
    if (time() > $lockout_time) {
        $_SESSION['login_attempts'] = 0;
        $login_attempts = 0;
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        require_once '../config/db.php';
        
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        
        // Input validation
        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password';
        } elseif (strlen($username) > 50 || strlen($password) > 100) {
            $error = 'Invalid input length';
        } else {
            $conn = getDBConnection();
            
            // Prepare statement with timeout
            $stmt = $conn->prepare("SELECT user_id, username, password_hash, role, status FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 1) {
                $user = $result->fetch_assoc();
                
                // Check if account is active
                if ($user['status'] !== 'active') {
                    $error = 'Account is inactive. Please contact administrator.';
                } elseif (password_verify($password, $user['password_hash'])) {
                    // Login successful
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['logged_in'] = true;
                    $_SESSION['last_activity'] = time();
                    $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
                    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
                    
                    // Reset login attempts on success
                    $_SESSION['login_attempts'] = 0;
                    
                    // Log successful login
                    error_log("Successful login: " . $username . " from IP: " . $_SERVER['REMOTE_ADDR']);
                    
                    // Redirect based on role with delay for animation
                    $redirect_url = '../index.php';
                    switch ($user['role']) {
                        case 'admin':
                            $redirect_url = '../admin/dashboard.php';
                            break;
                        case 'teacher':
                            $redirect_url = '../teacher/dashboard.php';
                            break;
                        case 'student':
                            $redirect_url = '../student/dashboard.php';
                            break;
                    }
                    
                    // Set success message for UI feedback
                    $success_message = 'Login successful! Redirecting...';
                    
                    // Store redirect in session for JavaScript
                    $_SESSION['redirect_url'] = $redirect_url;
                    
                } else {
                    // Invalid password
                    $login_attempts++;
                    $_SESSION['login_attempts'] = $login_attempts;
                    
                    if ($login_attempts >= 5) {
                        $_SESSION['lockout_time'] = time() + 900; // 15 minutes lockout
                        $error = "Too many failed attempts. Account locked for 15 minutes.";
                    } else {
                        $remaining_attempts = 5 - $login_attempts;
                        $error = "Invalid username or password. $remaining_attempts attempts remaining.";
                    }
                    
                    // Log failed attempt
                    error_log("Failed login attempt for username: " . $username . " from IP: " . $_SERVER['REMOTE_ADDR']);
                }
            } else {
                // User not found
                $login_attempts++;
                $_SESSION['login_attempts'] = $login_attempts;
                
                if ($login_attempts >= 5) {
                    $_SESSION['lockout_time'] = time() + 900;
                    $error = "Too many failed attempts. Account locked for 15 minutes.";
                } else {
                    $remaining_attempts = 5 - $login_attempts;
                    $error = "Invalid username or password. $remaining_attempts attempts remaining.";
                }
            }
            
            $stmt->close();
            $conn->close();
        }
    }
}

// Check for session expired or security redirect
if (isset($_GET['expired'])) {
    $error = 'Your session has expired. Please login again.';
} elseif (isset($_GET['security'])) {
    $error = 'Security verification failed. Please login again.';
} elseif (isset($_GET['logout'])) {
    $success_message = 'You have been successfully logged out.';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Student Performance Prediction System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Enhanced Login Styles */
        .login-page {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #4a5568 100%);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .login-page::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="%23ffffff" fill-opacity="0.05" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,112C672,96,768,96,864,112C960,128,1056,160,1152,160C1248,160,1344,128,1392,112L1440,96L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>');
            background-size: cover;
            opacity: 0.5;
        }

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 420px;
            padding: 40px;
            position: relative;
            z-index: 1;
            transform: translateY(0);
            animation: slideUp 0.6s ease-out;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
            position: relative;
        }

        .login-header::after {
            content: '';
            display: block;
            width: 60px;
            height: 4px;
            background: linear-gradient(to right, #667eea, #764ba2);
            margin: 15px auto;
            border-radius: 2px;
        }

        .system-logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 32px;
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .login-header h1 {
            color: #2d3748;
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 8px;
            line-height: 1.3;
        }

        .login-header h2 {
            color: #4a5568;
            font-size: 18px;
            font-weight: 500;
            margin-bottom: 5px;
        }

        .login-header .subtitle {
            color: #718096;
            font-size: 14px;
            font-weight: 400;
        }

        .login-form {
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #4a5568;
            font-weight: 600;
            font-size: 14px;
            transition: color 0.3s;
        }

        .form-group label i {
            margin-right: 8px;
            width: 16px;
        }

        .form-group.focused label {
            color: #667eea;
        }

        .input-with-icon {
            position: relative;
        }

        .input-with-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
            transition: color 0.3s;
            z-index: 2;
        }

        .input-with-icon input {
            padding-left: 45px;
            transition: all 0.3s;
        }

        .input-with-icon input:focus + i {
            color: #667eea;
        }

        .input-with-icon input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-control {
            width: 100%;
            padding: 14px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s;
            background: white;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #a0aec0;
            cursor: pointer;
            padding: 5px;
            z-index: 2;
        }

        .password-toggle:hover {
            color: #667eea;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 15px 24px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s;
            width: 100%;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn i {
            font-size: 18px;
        }

        .login-footer {
            margin-top: 30px;
            padding-top: 25px;
            border-top: 1px solid #e2e8f0;
            text-align: center;
        }

        .demo-accounts {
            background: #f7fafc;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }

        .demo-accounts h4 {
            color: #4a5568;
            font-size: 15px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .demo-accounts p {
            color: #718096;
            margin: 8px 0;
            font-size: 13px;
            line-height: 1.5;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .demo-accounts p i {
            width: 16px;
            color: #667eea;
        }

        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px;
            border: 1px solid transparent;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.5s ease-out;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .alert-error {
            background: linear-gradient(135deg, #fed7d7, #feb2b2);
            border-color: #fc8181;
            color: #c53030;
        }

        .alert-success {
            background: linear-gradient(135deg, #c6f6d5, #9ae6b4);
            border-color: #68d391;
            color: #22543d;
        }

        .alert i {
            font-size: 18px;
        }

        .system-info {
            position: absolute;
            bottom: 20px;
            left: 0;
            right: 0;
            text-align: center;
            color: rgba(255, 255, 255, 0.7);
            font-size: 12px;
        }

        .system-info a {
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            margin: 0 10px;
        }

        .system-info a:hover {
            text-decoration: underline;
        }

        /* Loading animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsive design */
        @media (max-width: 480px) {
            .login-container {
                padding: 30px 25px;
                margin: 15px;
            }

            .login-header h1 {
                font-size: 20px;
            }

            .login-header h2 {
                font-size: 16px;
            }

            .system-logo {
                width: 70px;
                height: 70px;
                font-size: 28px;
            }
        }

        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            .login-container {
                background: rgba(26, 32, 44, 0.95);
                color: #e2e8f0;
            }

            .login-header h1 {
                color: #f7fafc;
            }

            .login-header h2 {
                color: #e2e8f0;
            }

            .form-group label {
                color: #e2e8f0;
            }

            .form-control {
                background: #2d3748;
                border-color: #4a5568;
                color: #e2e8f0;
            }

            .demo-accounts {
                background: #2d3748;
            }

            .demo-accounts h4 {
                color: #e2e8f0;
            }

            .demo-accounts p {
                color: #a0aec0;
            }
        }

        /* Accessibility improvements */
        .form-control:focus {
            outline: 2px solid #667eea;
            outline-offset: 2px;
        }

        .btn:focus {
            outline: 2px solid #667eea;
            outline-offset: 2px;
        }

        /* Password strength indicator */
        .password-strength {
            height: 4px;
            background: #e2e8f0;
            border-radius: 2px;
            margin-top: 8px;
            overflow: hidden;
        }

        .password-strength-meter {
            height: 100%;
            width: 0;
            transition: width 0.3s, background 0.3s;
        }
    </style>
</head>
<body class="login-page">
    <div class="login-container">
        <div class="system-logo">
            <i class="fas fa-graduation-cap"></i>
        </div>
        
        <div class="login-header">
            <h1>Student Performance Prediction System</h1>
            <h2>Early Warning & Analytics Platform</h2>
            <div class="subtitle">Sign in to your account</div>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($success_message); ?></span>
            </div>
        <?php endif; ?>
        
        <div class="login-form">
            <form method="POST" action="" id="loginForm" autocomplete="on">
                <div class="form-group">
                    <label for="username">
                        <i class="fas fa-user"></i> Username
                    </label>
                    <div class="input-with-icon">
                        <input type="text" 
                               id="username" 
                               name="username" 
                               class="form-control" 
                               required 
                               placeholder="Enter your username"
                               autocomplete="username"
                               <?php echo isset($_SESSION['login_attempts']) && $_SESSION['login_attempts'] >= 5 ? 'disabled' : ''; ?>
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                        <i class="fas fa-user"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock"></i> Password
                    </label>
                    <div class="input-with-icon">
                        <input type="password" 
                               id="password" 
                               name="password" 
                               class="form-control" 
                               required 
                               placeholder="Enter your password"
                               autocomplete="current-password"
                               <?php echo isset($_SESSION['login_attempts']) && $_SESSION['login_attempts'] >= 5 ? 'disabled' : ''; ?>>
                        <i class="fas fa-lock"></i>
                        <button type="button" class="password-toggle" id="togglePassword">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="password-strength">
                        <div class="password-strength-meter" id="passwordStrength"></div>
                    </div>
                </div>
                
                <button type="submit" 
                        class="btn btn-primary" 
                        id="loginButton"
                        <?php echo isset($_SESSION['login_attempts']) && $_SESSION['login_attempts'] >= 5 ? 'disabled' : ''; ?>>
                    <i class="fas fa-sign-in-alt"></i>
                    <span id="buttonText">Login to Dashboard</span>
                    <div class="loading" id="loadingSpinner" style="display: none;"></div>
                </button>
            </form>
        </div>
        
        <div class="login-footer">
            <div class="demo-accounts">
                <h4><i class="fas fa-key"></i> Demo Accounts</h4>
                <p><i class="fas fa-user-shield"></i> Admin: <strong>admin</strong> / <strong>admin123</strong></p>
                <p><i class="fas fa-chalkboard-teacher"></i> Teacher: <strong>teacher</strong> / <strong>teacher123</strong></p>
                <p><i class="fas fa-user-graduate"></i> Student: <strong>student</strong> / <strong>student123</strong></p>
            </div>
            
            <div style="margin-top: 20px; font-size: 13px; color: #718096;">
                <p><i class="fas fa-info-circle"></i> Need help? Contact your system administrator</p>
            </div>
        </div>
    </div>

    <div class="system-info">
        <span>© <?php echo date('Y'); ?> Student Performance Prediction System v1.0.0</span>
        <span>|</span>
        <a href="javascript:void(0)" onclick="showSystemInfo()">System Info</a>
        <span>|</span>
        <a href="javascript:void(0)" onclick="toggleTheme()"><i class="fas fa-moon"></i> Theme</a>
    </div>

    <script>
        // Password visibility toggle
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        const passwordIcon = togglePassword.querySelector('i');
        
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            passwordIcon.className = type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
        });

        // Form submission animation
        const loginForm = document.getElementById('loginForm');
        const loginButton = document.getElementById('loginButton');
        const buttonText = document.getElementById('buttonText');
        const loadingSpinner = document.getElementById('loadingSpinner');
        
        loginForm.addEventListener('submit', function(e) {
            const lockoutTime = <?php echo isset($_SESSION['lockout_time']) ? $_SESSION['lockout_time'] : 0; ?>;
            const currentTime = Math.floor(Date.now() / 1000);
            
            if (lockoutTime > currentTime) {
                e.preventDefault();
                alert('Account is temporarily locked. Please try again later.');
                return;
            }
            
            // Show loading animation
            buttonText.textContent = 'Authenticating...';
            loadingSpinner.style.display = 'inline-block';
            loginButton.disabled = true;
            loginButton.style.opacity = '0.8';
        });

        // Auto-focus on username field
        document.getElementById('username').focus();

        // Add focus effects
        const inputs = document.querySelectorAll('.form-control');
        inputs.forEach(input => {
            const formGroup = input.closest('.form-group');
            
            input.addEventListener('focus', () => {
                formGroup.classList.add('focused');
            });
            
            input.addEventListener('blur', () => {
                if (!input.value) {
                    formGroup.classList.remove('focused');
                }
            });
            
            // Add initial state
            if (input.value) {
                formGroup.classList.add('focused');
            }
        });

        // Password strength indicator
        const passwordStrength = document.getElementById('passwordStrength');
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            
            if (password.length >= 8) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            const width = strength * 25;
            let color = '#f56565'; // red
            
            if (strength >= 3) color = '#ed8936'; // orange
            if (strength >= 4) color = '#48bb78'; // green
            
            passwordStrength.style.width = width + '%';
            passwordStrength.style.background = color;
        });

        // Theme toggle
        function toggleTheme() {
            const body = document.body;
            const currentTheme = body.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            body.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            
            const themeIcon = document.querySelector('.system-info a:last-child i');
            themeIcon.className = newTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        }

        // Load saved theme
        const savedTheme = localStorage.getItem('theme') || (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        document.body.setAttribute('data-theme', savedTheme);
        
        const themeIcon = document.querySelector('.system-info a:last-child i');
        themeIcon.className = savedTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';

        // System info modal
        function showSystemInfo() {
            const info = `
                <div style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 1000;">
                    <div style="background: white; padding: 30px; border-radius: 15px; max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto;">
                        <h3 style="color: #2d3748; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-info-circle" style="color: #667eea;"></i>
                            System Information
                        </h3>
                        <div style="line-height: 1.6; color: #4a5568;">
                            <p><strong>System:</strong> Student Performance Prediction & Early Warning System</p>
                            <p><strong>Version:</strong> 1.0.0</p>
                            <p><strong>Environment:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></p>
                            <p><strong>PHP Version:</strong> <?php echo phpversion(); ?></p>
                            <p><strong>Server IP:</strong> <?php echo $_SERVER['SERVER_ADDR'] ?? 'Unknown'; ?></p>
                            <p><strong>Client IP:</strong> <?php echo $_SERVER['REMOTE_ADDR'] ?? 'Unknown'; ?></p>
                            <p><strong>User Agent:</strong> <?php echo $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'; ?></p>
                        </div>
                        <div style="margin-top: 25px; text-align: center;">
                            <button onclick="this.closest('div[style*=\"position: fixed\"]').remove()" style="padding: 10px 20px; background: #667eea; color: white; border: none; border-radius: 5px; cursor: pointer;">
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', info);
        }

        // Handle success redirect
        <?php if ($success_message && isset($_SESSION['redirect_url'])): ?>
        setTimeout(function() {
            window.location.href = '<?php echo $_SESSION["redirect_url"]; ?>';
        }, 1500);
        <?php endif; ?>

        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl+Enter to submit form
            if (e.ctrlKey && e.key === 'Enter') {
                e.preventDefault();
                loginForm.requestSubmit();
            }
            
            // Escape to clear form
            if (e.key === 'Escape') {
                loginForm.reset();
                inputs.forEach(input => {
                    const formGroup = input.closest('.form-group');
                    formGroup.classList.remove('focused');
                });
            }
        });

        // Animated background elements
        function createFloatingElement() {
            const element = document.createElement('div');
            const size = Math.random() * 20 + 10;
            const colors = ['rgba(102, 126, 234, 0.1)', 'rgba(118, 75, 162, 0.1)', 'rgba(74, 85, 104, 0.1)'];
            
            element.style.cssText = `
                position: absolute;
                width: ${size}px;
                height: ${size}px;
                background: ${colors[Math.floor(Math.random() * colors.length)]};
                border-radius: 50%;
                pointer-events: none;
                z-index: 0;
                animation: float ${Math.random() * 20 + 10}s linear infinite;
            `;
            
            element.style.left = Math.random() * 100 + 'vw';
            element.style.top = Math.random() * 100 + 'vh';
            
            document.querySelector('.login-page').appendChild(element);
            
            setTimeout(() => element.remove(), 20000);
        }

        // Create floating elements
        for (let i = 0; i < 15; i++) {
            setTimeout(createFloatingElement, i * 1000);
            setInterval(createFloatingElement, 20000);
        }

        // Add floating animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes float {
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
                    transform: translateY(-100px) rotate(360deg);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>