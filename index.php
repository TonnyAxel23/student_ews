<?php
/**
 * Student Performance Prediction & Early Warning System
 * Main Entry Point - Fixed Redirect Version
 */

// Start session
session_start();

// Check if user is logged in
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    // Validate required session variables
    if (!isset($_SESSION['role']) || !isset($_SESSION['user_id'])) {
        // Invalid session, destroy and redirect to login
        session_unset();
        session_destroy();
        header('Location: auth/login.php');
        exit();
    }
    
    // Check session timeout (30 minutes)
    if (isset($_SESSION['last_activity'])) {
        $inactive_timeout = 1800; // 30 minutes
        $current_time = time();
        
        if (($current_time - $_SESSION['last_activity']) > $inactive_timeout) {
            // Session expired
            session_unset();
            session_destroy();
            header('Location: auth/login.php?expired=1');
            exit();
        }
        
        // Update last activity time
        $_SESSION['last_activity'] = $current_time;
    }
    
    // Redirect based on role
    switch ($_SESSION['role']) {
        case 'admin':
            header('Location: admin/dashboard.php');
            break;
        case 'teacher':
            header('Location: teacher/dashboard.php');
            break;
        case 'student':
            header('Location: student/dashboard.php');
            break;
        default:
            // Invalid role, redirect to login
            session_unset();
            session_destroy();
            header('Location: auth/login.php');
    }
    exit();
} else {
    // Not logged in, redirect to login page
    header('Location: auth/login.php');
    exit();
}
?>