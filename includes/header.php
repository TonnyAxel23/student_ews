<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../auth/login.php');
    exit();
}

$user_role = $_SESSION['role'];
$username = $_SESSION['username'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - Student EWS' : 'Student EWS'; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="wrapper">
        <nav class="sidebar">
            <div class="sidebar-header">
                <h3>Student EWS</h3>
                <p><?php echo ucfirst($user_role); ?> Panel</p>
            </div>
            
            <ul class="sidebar-menu">
                <?php if ($user_role == 'admin'): ?>
                    <li><a href="../admin/dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">Dashboard</a></li>
                    <li><a href="../admin/manage_users.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage_users.php' ? 'active' : ''; ?>">Manage Users</a></li>
                    <li><a href="../admin/manage_classes.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage_classes.php' ? 'active' : ''; ?>">Classes</a></li>
                    <li><a href="../admin/manage_subjects.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage_subjects.php' ? 'active' : ''; ?>">Subjects</a></li>
                    <li><a href="../admin/reports.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">Reports</a></li>
                    <li><a href="../admin/interventions.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'interventions.php' ? 'active' : ''; ?>">Interventions</a></li>
                    
                <?php elseif ($user_role == 'teacher'): ?>
                    <li><a href="../teacher/dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">Dashboard</a></li>
                    <li><a href="../teacher/attendance.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'attendance.php' ? 'active' : ''; ?>">Attendance</a></li>
                    <li><a href="../teacher/assessments.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'assessments.php' ? 'active' : ''; ?>">Assessments</a></li>
                    <li><a href="../teacher/students.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'students.php' ? 'active' : ''; ?>">Students</a></li>
                    <li><a href="../teacher/interventions.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'interventions.php' ? 'active' : ''; ?>">Interventions</a></li>
                    
                <?php elseif ($user_role == 'student'): ?>
                    <li><a href="../student/dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">Dashboard</a></li>
                    <li><a href="../student/performance.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'performance.php' ? 'active' : ''; ?>">Performance</a></li>
                    <li><a href="../student/attendance_view.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'attendance_view.php' ? 'active' : ''; ?>">Attendance</a></li>
                    <li><a href="../student/assessments_view.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'assessments_view.php' ? 'active' : ''; ?>">Scores</a></li>
                <?php endif; ?>
                
                <li><a href="../auth/logout.php" class="logout-link">Logout</a></li>
            </ul>
        </nav>

        <div class="main-content">
            <header class="top-nav">
                <div class="nav-left">
                    <button class="sidebar-toggle">☰</button>
                    <h2><?php echo isset($page_title) ? $page_title : 'Dashboard'; ?></h2>
                </div>
                <div class="nav-right">
                    <span class="user-info">Welcome, <?php echo htmlspecialchars($username); ?> (<?php echo ucfirst($user_role); ?>)</span>
                    <a href="../auth/logout.php" class="btn-logout">Logout</a>
                </div>
            </header>

            <main class="content">
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="alert alert-<?php echo $_SESSION['message_type']; ?>">
                        <?php 
                        echo $_SESSION['message'];
                        unset($_SESSION['message']);
                        unset($_SESSION['message_type']);
                        ?>
                    </div>
                <?php endif; ?>