<?php
/**
 * Student Performance Prediction & Early Warning System
 * Main Entry Point
 * 
 * @category   System
 * @package    StudentEWS
 * @author     System Administrator
 * @version    1.0.0
 */

// Enable strict error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 0); // Disable display in production
ini_set('log_errors', 1);
ini_set('error_log', 'system_errors.log');

// Start session with secure settings
session_start([
    'name' => 'StudentEWS_Session',
    'cookie_lifetime' => 86400, // 24 hours
    'cookie_secure' => isset($_SERVER['HTTPS']), // Only send over HTTPS
    'cookie_httponly' => true, // Prevent JavaScript access
    'cookie_samesite' => 'Strict', // CSRF protection
    'use_strict_mode' => true, // Strict session mode
    'use_only_cookies' => true, // Prevent session fixation
    'cookie_domain' => '', // Current domain only
    'cookie_path' => '/',
]);

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline'; img-src 'self' data:;");

// Check if system is installed
if (!file_exists('config/db.php')) {
    // System not installed, redirect to installer
    if (basename($_SERVER['PHP_SELF']) !== 'install.php') {
        header('Location: install.php');
        exit();
    }
    return; // Allow installer to run
}

// Prevent session fixation
if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
}

// Set default timezone
date_default_timezone_set('UTC');

// Authentication check
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    
    // Validate session data
    $required_session_vars = ['user_id', 'username', 'role', 'last_activity'];
    $session_valid = true;
    
    foreach ($required_session_vars as $var) {
        if (!isset($_SESSION[$var])) {
            $session_valid = false;
            break;
        }
    }
    
    // Check session expiration (30 minutes of inactivity)
    if ($session_valid && isset($_SESSION['last_activity'])) {
        $inactive_timeout = 1800; // 30 minutes in seconds
        $current_time = time();
        
        if (($current_time - $_SESSION['last_activity']) > $inactive_timeout) {
            // Session expired
            session_unset();
            session_destroy();
            
            // Set session expired message
            session_start();
            $_SESSION['session_expired'] = true;
            
            header('Location: auth/login.php?expired=1');
            exit();
        }
        
        // Update last activity time
        $_SESSION['last_activity'] = $current_time;
    }
    
    // Check IP consistency (optional, can be disabled on shared networks)
    if ($session_valid && isset($_SESSION['ip_address'])) {
        if ($_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR']) {
            // IP changed - possible session hijacking
            error_log("Possible session hijacking detected for user: " . $_SESSION['username']);
            
            // Destroy session for security
            session_unset();
            session_destroy();
            
            header('Location: auth/login.php?security=1');
            exit();
        }
    } else {
        // Store IP address for future checks
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
    }
    
    // Check user agent consistency
    if ($session_valid && isset($_SESSION['user_agent'])) {
        if ($_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
            // User agent changed - possible session hijacking
            error_log("User agent changed for user: " . $_SESSION['username']);
            
            // For security, require re-login
            session_unset();
            session_destroy();
            
            header('Location: auth/login.php?security=2');
            exit();
        }
    } else {
        // Store user agent for future checks
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
    }
    
    // Redirect based on role with additional security checks
    if ($session_valid && in_array($_SESSION['role'], ['admin', 'teacher', 'student'])) {
        
        // Validate user role path
        $role_paths = [
            'admin' => 'admin/dashboard.php',
            'teacher' => 'teacher/dashboard.php',
            'student' => 'student/dashboard.php'
        ];
        
        $redirect_path = $role_paths[$_SESSION['role']];
        
        // Check if the requested file exists
        if (!file_exists($redirect_path)) {
            // Log error and show maintenance page
            error_log("Dashboard file missing for role: " . $_SESSION['role']);
            showMaintenancePage();
            exit();
        }
        
        // Prevent open redirects
        $redirect_path = filter_var($redirect_path, FILTER_SANITIZE_URL);
        
        // Add cache control headers for dashboard
        header("Cache-Control: no-cache, no-store, must-revalidate");
        header("Pragma: no-cache");
        header("Expires: 0");
        
        // Perform the redirect
        header("Location: " . $redirect_path);
        exit();
        
    } else {
        // Invalid role - security issue
        error_log("Invalid role detected in session: " . ($_SESSION['role'] ?? 'none'));
        
        // Destroy session
        session_unset();
        session_destroy();
        
        header('Location: auth/login.php?error=invalid_role');
        exit();
    }
    
} else {
    // Not logged in - redirect to login page
    
    // Check if already on login page to avoid redirect loop
    $current_page = basename($_SERVER['PHP_SELF']);
    $login_pages = ['login.php', 'install.php', 'index.php'];
    
    if (!in_array($current_page, $login_pages)) {
        // Store requested URL for post-login redirect (optional)
        if ($current_page !== 'index.php') {
            $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        }
        
        // Redirect to login
        header('Location: auth/login.php');
        exit();
    }
    
    // If already on login page, allow it to render
    if ($current_page === 'login.php') {
        return;
    }
    
    // If on index.php and not logged in, redirect to login
    header('Location: auth/login.php');
    exit();
}

/**
 * Display maintenance page when system files are missing
 */
function showMaintenancePage() {
    http_response_code(503);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>System Maintenance - Student EWS</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                justify-content: center;
                align-items: center;
                color: #333;
            }
            
            .maintenance-container {
                background: white;
                border-radius: 10px;
                box-shadow: 0 10px 40px rgba(0,0,0,0.1);
                width: 90%;
                max-width: 500px;
                padding: 40px;
                text-align: center;
            }
            
            .maintenance-icon {
                font-size: 64px;
                color: #667eea;
                margin-bottom: 20px;
            }
            
            h1 {
                color: #333;
                font-size: 24px;
                margin-bottom: 15px;
            }
            
            p {
                color: #666;
                line-height: 1.6;
                margin-bottom: 20px;
            }
            
            .status-code {
                display: inline-block;
                padding: 5px 15px;
                background: #f7fafc;
                border-radius: 20px;
                font-family: monospace;
                color: #667eea;
                margin-bottom: 20px;
            }
            
            .btn {
                display: inline-block;
                padding: 12px 24px;
                background: #667eea;
                color: white;
                text-decoration: none;
                border-radius: 5px;
                margin: 5px;
                transition: background 0.3s;
            }
            
            .btn:hover {
                background: #5a67d8;
            }
            
            .btn-secondary {
                background: #718096;
            }
            
            .btn-secondary:hover {
                background: #4a5568;
            }
            
            .contact-info {
                margin-top: 30px;
                padding-top: 20px;
                border-top: 1px solid #e2e8f0;
                font-size: 14px;
                color: #718096;
            }
            
            .contact-info strong {
                color: #4a5568;
            }
        </style>
    </head>
    <body>
        <div class="maintenance-container">
            <div class="maintenance-icon">⚠️</div>
            <h1>System Maintenance</h1>
            <div class="status-code">Status: 503 Service Unavailable</div>
            <p>We're currently performing maintenance on the Student Performance Prediction System. Some features may be temporarily unavailable.</p>
            <p>Our team is working to restore full functionality as soon as possible. Thank you for your patience.</p>
            
            <div style="margin-top: 30px;">
                <a href="auth/login.php" class="btn">Return to Login</a>
                <a href="javascript:location.reload()" class="btn btn-secondary">Refresh Page</a>
            </div>
            
            <div class="contact-info">
                <p>For urgent assistance, please contact your system administrator.</p>
                <p><strong>Expected Duration:</strong> 30 minutes</p>
                <p><strong>Last Updated:</strong> <?php echo date('F j, Y, g:i a'); ?></p>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}

/**
 * Log security events
 */
function logSecurityEvent($event_type, $user_id = null, $details = '') {
    $log_file = 'security_logs.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    $log_entry = sprintf(
        "[%s] [%s] IP: %s | User: %s | Details: %s | Agent: %s\n",
        $timestamp,
        $event_type,
        $ip_address,
        $user_id ?? 'guest',
        $details,
        substr($user_agent, 0, 100)
    );
    
    // Append to log file
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

/**
 * Validate session token (for future CSRF protection implementation)
 */
function validateSessionToken($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Generate CSRF token (for future implementation)
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['csrf_token'];
}