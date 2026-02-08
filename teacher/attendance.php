<?php
$page_title = "Attendance Management";
require_once '../includes/header.php';
require_once '../config/db.php';

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Get teacher's subjects
$subjects = $conn->query("
    SELECT s.*, c.class_name 
    FROM subjects s 
    LEFT JOIN classes c ON s.class_id = c.class_id 
    WHERE s.teacher_id = (SELECT teacher_id FROM teachers WHERE user_id = $user_id)
    ORDER BY s.subject_name
");

$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : null;
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['take_attendance'])) {
        $subject_id = intval($_POST['subject_id']);
        $attendance_date = $_POST['date'];
        
        foreach ($_POST['attendance'] as $student_id => $status) {
            // Check if attendance already exists
            $check = $conn->prepare("SELECT attendance_id FROM attendance WHERE student_id = ? AND subject_id = ? AND date = ?");
            $check->bind_param("iis", $student_id, $subject_id, $attendance_date);
            $check->execute();
            $check->store_result();
            
            if ($check->num_rows > 0) {
                // Update existing
                $stmt = $conn->prepare("UPDATE attendance SET status = ? WHERE student_id = ? AND subject_id = ? AND date = ?");
                $stmt->bind_param("siis", $status, $student_id, $subject_id, $attendance_date);
            } else {
                // Insert new
                $stmt = $conn->prepare("INSERT INTO attendance (student_id, subject_id, date, status) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("iiss", $student_id, $subject_id, $attendance_date, $status);
            }
            $stmt->execute();
            $stmt->close();
            $check->close();
        }
        
        $_SESSION['message'] = "Attendance saved successfully";
        $_SESSION['message_type'] = 'success';
        
        // Redirect to show updated attendance
        header("Location: attendance.php?subject_id=$subject_id&date=$attendance_date");
        exit();
    }
}

// Get students for selected subject
$students = [];
if ($subject_id) {
    // Get class ID from subject
    $subject_info = $conn->query("SELECT class_id FROM subjects WHERE subject_id = $subject_id")->fetch_assoc();
    $class_id = $subject_info['class_id'];
    
    if ($class_id) {
        $students = $conn->query("
            SELECT s.* 
            FROM students s 
            WHERE s.class_id = $class_id 
            ORDER BY s.full_name
        ");
    }
}

// Get existing attendance for the date
$existing_attendance = [];
if ($subject_id) {
    $result = $conn->query("
        SELECT student_id, status 
        FROM attendance 
        WHERE subject_id = $subject_id 
        AND date = '$date'
    ");
    
    while ($row = $result->fetch_assoc()) {
        $existing_attendance[$row['student_id']] = $row['status'];
    }
}
?>

<div class="row">
    <div class="card">
        <div class="card-header">
            <h3>Take Attendance</h3>
        </div>
        <form method="GET" action="" class="form-row">
            <div class="form-group">
                <label>Select Subject</label>
                <select name="subject_id" class="form-control" onchange="this.form.submit()">
                    <option value="">Select Subject</option>
                    <?php while ($subject = $subjects->fetch_assoc()): ?>
                        <option value="<?php echo $subject['subject_id']; ?>" 
                            <?php echo $subject_id == $subject['subject_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($subject['subject_name'] . ' - ' . $subject['class_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Date</label>
                <input type="date" name="date" class="form-control" value="<?php echo $date; ?>" 
                       onchange="this.form.submit()">
            </div>
        </form>
    </div>
</div>

<?php if ($subject_id && $students->num_rows > 0): ?>
<div class="row">
    <div class="card">
        <div class="card-header">
            <h3>Attendance for <?php 
                $subject_info = $conn->query("
                    SELECT s.subject_name, c.class_name 
                    FROM subjects s 
                    LEFT JOIN classes c ON s.class_id = c.class_id 
                    WHERE s.subject_id = $subject_id
                ")->fetch_assoc();
                echo htmlspecialchars($subject_info['subject_name'] . ' - ' . $subject_info['class_name']);
            ?></h3>
            <div><?php echo date('F j, Y', strtotime($date)); ?></div>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="subject_id" value="<?php echo $subject_id; ?>">
            <input type="hidden" name="date" value="<?php echo $date; ?>">
            
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Student Name</th>
                            <th>Attendance Status</th>
                            <th>Current Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($student = $students->fetch_assoc()): 
                            $current_status = $existing_attendance[$student['student_id']] ?? 'absent';
                            $attendance_rate = $conn->query("
                                SELECT 
                                    COUNT(*) as total_days,
                                    SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days
                                FROM attendance 
                                WHERE student_id = {$student['student_id']}
                                AND subject_id = $subject_id
                                AND YEAR(date) = YEAR('$date')
                                AND MONTH(date) = MONTH('$date')
                            ")->fetch_assoc();
                            
                            $rate = $attendance_rate['total_days'] > 0 
                                ? round(($attendance_rate['present_days'] / $attendance_rate['total_days']) * 100, 1) 
                                : 0;
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student['admission_no']); ?></td>
                                <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                <td>
                                    <div style="display: flex; gap: 15px; align-items: center;">
                                        <label style="display: flex; align-items: center; gap: 5px;">
                                            <input type="radio" name="attendance[<?php echo $student['student_id']; ?>]" 
                                                   value="present" required 
                                                   <?php echo $current_status == 'present' ? 'checked' : ''; ?>>
                                            Present
                                        </label>
                                        <label style="display: flex; align-items: center; gap: 5px;">
                                            <input type="radio" name="attendance[<?php echo $student['student_id']; ?>]" 
                                                   value="absent" 
                                                   <?php echo $current_status == 'absent' ? 'checked' : ''; ?>>
                                            Absent
                                        </label>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($attendance_rate['total_days'] > 0): ?>
                                        <span class="badge <?php echo $rate >= 75 ? 'badge-low' : ($rate >= 50 ? 'badge-medium' : 'badge-high'); ?>">
                                            <?php echo $rate; ?>% Attendance
                                        </span>
                                        <br>
                                        <small><?php echo $attendance_rate['present_days']; ?> present / <?php echo $attendance_rate['total_days']; ?> days</small>
                                    <?php else: ?>
                                        <span class="badge">No data</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <div style="padding: 20px; text-align: center; border-top: 1px solid #eee;">
                <button type="submit" name="take_attendance" class="btn btn-primary btn-lg">
                    Save Attendance
                </button>
            </div>
        </form>
    </div>
</div>
<?php elseif ($subject_id && $students->num_rows == 0): ?>
<div class="row">
    <div class="card">
        <div class="alert alert-warning">
            No students found in this class. Please contact the administrator.
        </div>
    </div>
</div>
<?php elseif (!$subject_id): ?>
<div class="row">
    <div class="card">
        <div class="alert alert-info">
            Please select a subject and date to take attendance.
        </div>
    </div>
</div>
<?php endif; ?>

<?php 
$conn->close();
require_once '../includes/footer.php'; 
?>