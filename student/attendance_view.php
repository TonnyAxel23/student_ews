<?php
$page_title = "My Attendance";
require_once '../includes/header.php';
require_once '../config/db.php';

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Get student details
$student = $conn->query("
    SELECT s.* FROM students s WHERE s.user_id = $user_id
")->fetch_assoc();

if (!$student) {
    header('Location: ../auth/logout.php');
    exit();
}

$student_id = $student['student_id'];

// Set filter parameters
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : null;

// Get available years
$years = $conn->query("
    SELECT DISTINCT YEAR(date) as year 
    FROM attendance 
    WHERE student_id = $student_id 
    ORDER BY year DESC
");

// Get student's subjects
$subjects = $conn->query("
    SELECT DISTINCT sub.* 
    FROM attendance a
    JOIN subjects sub ON a.subject_id = sub.subject_id
    WHERE a.student_id = $student_id
    ORDER BY sub.subject_name
");

// Get attendance summary
$attendance_summary = $conn->query("
    SELECT 
        sub.subject_name,
        COUNT(*) as total_days,
        SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_days,
        SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_days
    FROM attendance a
    JOIN subjects sub ON a.subject_id = sub.subject_id
    WHERE a.student_id = $student_id
      AND YEAR(a.date) = $year
      " . ($subject_id ? "AND a.subject_id = $subject_id" : "") . "
    GROUP BY a.subject_id
    ORDER BY sub.subject_name
");

// Get monthly attendance
$monthly_attendance = $conn->query("
    SELECT 
        DATE_FORMAT(date, '%Y-%m') as month,
        COUNT(*) as total_days,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days
    FROM attendance 
    WHERE student_id = $student_id
    GROUP BY DATE_FORMAT(date, '%Y-%m')
    ORDER BY month DESC
    LIMIT 12
");

// Get daily attendance for selected month
$daily_attendance = $conn->query("
    SELECT 
        a.*,
        sub.subject_name
    FROM attendance a
    JOIN subjects sub ON a.subject_id = sub.subject_id
    WHERE a.student_id = $student_id
      AND YEAR(a.date) = $year
      AND MONTH(a.date) = $month
      " . ($subject_id ? "AND a.subject_id = $subject_id" : "") . "
    ORDER BY a.date DESC, sub.subject_name
");
?>

<div class="row">
    <div class="card">
        <div class="card-header">
            <h3>Attendance Filters</h3>
        </div>
        <form method="GET" action="" class="form-row">
            <div class="form-group">
                <label>Year</label>
                <select name="year" class="form-control" onchange="this.form.submit()">
                    <?php while ($year_data = $years->fetch_assoc()): ?>
                        <option value="<?php echo $year_data['year']; ?>" 
                            <?php echo $year == $year_data['year'] ? 'selected' : ''; ?>>
                            <?php echo $year_data['year']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Month</label>
                <select name="month" class="form-control" onchange="this.form.submit()">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?php echo $m; ?>" <?php echo $month == $m ? 'selected' : ''; ?>>
                            <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Subject</label>
                <select name="subject_id" class="form-control" onchange="this.form.submit()">
                    <option value="">All Subjects</option>
                    <?php while ($subject = $subjects->fetch_assoc()): ?>
                        <option value="<?php echo $subject['subject_id']; ?>" 
                            <?php echo $subject_id == $subject['subject_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($subject['subject_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<div class="row">
    <!-- Attendance Summary -->
    <div class="card" style="flex: 2;">
        <div class="card-header">
            <h3>Attendance Summary - <?php echo date('F Y', mktime(0, 0, 0, $month, 1, $year)); ?></h3>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Total Days</th>
                        <th>Present</th>
                        <th>Absent</th>
                        <th>Attendance Rate</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_days = 0;
                    $total_present = 0;
                    $total_absent = 0;
                    ?>
                    <?php while ($attendance = $attendance_summary->fetch_assoc()): 
                        $rate = $attendance['total_days'] > 0 
                            ? round(($attendance['present_days'] / $attendance['total_days']) * 100, 1) 
                            : 0;
                        
                        $total_days += $attendance['total_days'];
                        $total_present += $attendance['present_days'];
                        $total_absent += $attendance['absent_days'];
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($attendance['subject_name']); ?></td>
                            <td><?php echo $attendance['total_days']; ?></td>
                            <td><?php echo $attendance['present_days']; ?></td>
                            <td><?php echo $attendance['absent_days']; ?></td>
                            <td>
                                <span class="badge <?php 
                                    echo $rate >= 75 ? 'badge-low' : 
                                          ($rate >= 50 ? 'badge-medium' : 'badge-high');
                                ?>">
                                    <?php echo $rate; ?>%
                                </span>
                            </td>
                            <td>
                                <?php if ($rate >= 75): ?>
                                    <span style="color: #48bb78;">✓ Good</span>
                                <?php elseif ($rate >= 50): ?>
                                    <span style="color: #ed8936;">⚠️ Fair</span>
                                <?php else: ?>
                                    <span style="color: #f56565;">✗ Poor</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    <?php if ($attendance_summary->num_rows == 0): ?>
                        <tr>
                            <td colspan="6" class="text-center">No attendance records found for the selected period</td>
                        </tr>
                    <?php else: 
                        $overall_rate = $total_days > 0 ? round(($total_present / $total_days) * 100, 1) : 0;
                    ?>
                        <tr style="background: #f8fafc; font-weight: bold;">
                            <td>Total</td>
                            <td><?php echo $total_days; ?></td>
                            <td><?php echo $total_present; ?></td>
                            <td><?php echo $total_absent; ?></td>
                            <td>
                                <span class="badge <?php 
                                    echo $overall_rate >= 75 ? 'badge-low' : 
                                          ($overall_rate >= 50 ? 'badge-medium' : 'badge-high');
                                ?>">
                                    <?php echo $overall_rate; ?>%
                                </span>
                            </td>
                            <td>
                                <?php if ($overall_rate >= 75): ?>
                                    <span style="color: #48bb78;">✓ Good Attendance</span>
                                <?php elseif ($overall_rate >= 50): ?>
                                    <span style="color: #ed8936;">⚠️ Needs Improvement</span>
                                <?php else: ?>
                                    <span style="color: #f56565;">✗ Poor Attendance</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Monthly Overview -->
    <div class="card" style="flex: 1;">
        <div class="card-header">
            <h3>Monthly Overview</h3>
        </div>
        <div style="max-height: 400px; overflow-y: auto;">
            <?php if ($monthly_attendance->num_rows > 0): ?>
                <?php while ($month_data = $monthly_attendance->fetch_assoc()): 
                    $rate = $month_data['total_days'] > 0 
                        ? round(($month_data['present_days'] / $month_data['total_days']) * 100, 1) 
                        : 0;
                    list($y, $m) = explode('-', $month_data['month']);
                ?>
                    <div style="padding: 15px; border-bottom: 1px solid #eee;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                            <strong><?php echo date('F Y', mktime(0, 0, 0, $m, 1, $y)); ?></strong>
                            <span class="badge <?php 
                                echo $rate >= 75 ? 'badge-low' : 
                                      ($rate >= 50 ? 'badge-medium' : 'badge-high');
                            ?>">
                                <?php echo $rate; ?>%
                            </span>
                        </div>
                        <div style="color: #666; font-size: 12px; margin-bottom: 5px;">
                            <?php echo $month_data['present_days']; ?> present / <?php echo $month_data['total_days']; ?> days
                        </div>
                        <div style="margin-top: 8px; height: 6px; background: #e2e8f0; border-radius: 3px; overflow: hidden;">
                            <div style="height: 100%; width: <?php echo min(100, $rate); ?>%; 
                                      background: <?php 
                                          echo $rate >= 75 ? '#48bb78' : 
                                                ($rate >= 50 ? '#ed8936' : '#f56565'); 
                                      ?>;">
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="padding: 20px; text-align: center; color: #666;">
                    No monthly attendance data available
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row">
    <!-- Daily Attendance Details -->
    <div class="card">
        <div class="card-header">
            <h3>Daily Attendance Details</h3>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th>Day</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($attendance = $daily_attendance->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($attendance['date'])); ?></td>
                            <td><?php echo htmlspecialchars($attendance['subject_name']); ?></td>
                            <td>
                                <?php if ($attendance['status'] == 'present'): ?>
                                    <span class="badge badge-low">Present</span>
                                <?php else: ?>
                                    <span class="badge badge-high">Absent</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('l', strtotime($attendance['date'])); ?></td>
                        </tr>
                    <?php endwhile; ?>
                    <?php if ($daily_attendance->num_rows == 0): ?>
                        <tr>
                            <td colspan="4" class="text-center">No daily attendance records found for the selected period</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php 
$conn->close();
require_once '../includes/footer.php'; 
?>