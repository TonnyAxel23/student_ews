<?php
$page_title = "Student Management";
require_once '../includes/header.php';
require_once '../config/db.php';

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Get teacher's subjects and classes
$teacher_classes = $conn->query("
    SELECT DISTINCT c.* 
    FROM classes c
    JOIN subjects s ON c.class_id = s.class_id
    WHERE s.teacher_id = (SELECT teacher_id FROM teachers WHERE user_id = $user_id)
    ORDER BY c.level, c.class_name
");

$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : null;
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Get students based on filters
$where_conditions = [];
if ($class_id) {
    $where_conditions[] = "s.class_id = $class_id";
}

if ($filter == 'at_risk') {
    $where_conditions[] = "ps.risk_level IN ('medium', 'high')";
} elseif ($filter == 'high_risk') {
    $where_conditions[] = "ps.risk_level = 'high'";
}

$where_clause = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";

$students = $conn->query("
    SELECT 
        s.*,
        c.class_name,
        ps.overall_score,
        ps.attendance_rate,
        ps.average_score,
        ps.risk_level,
        ps.trend,
        (SELECT COUNT(*) FROM attendance a WHERE a.student_id = s.student_id AND a.status = 'present') as total_present,
        (SELECT COUNT(*) FROM attendance a WHERE a.student_id = s.student_id) as total_attendance,
        (SELECT AVG((score / max_score) * 100) FROM assessments WHERE student_id = s.student_id) as overall_avg_score
    FROM students s
    LEFT JOIN classes c ON s.class_id = c.class_id
    LEFT JOIN performance_summary ps ON s.student_id = ps.student_id 
        AND ps.term = CEIL(MONTH(CURRENT_DATE()) / 4)
        AND ps.academic_year = YEAR(CURRENT_DATE())
    $where_clause
    ORDER BY s.full_name
");
?>

<div class="row">
    <div class="card">
        <div class="card-header">
            <h3>Student Filters</h3>
        </div>
        <form method="GET" action="">
            <div class="form-row">
                <div class="form-group">
                    <label>Class</label>
                    <select name="class_id" class="form-control" onchange="this.form.submit()">
                        <option value="">All Classes</option>
                        <?php while ($class = $teacher_classes->fetch_assoc()): ?>
                            <option value="<?php echo $class['class_id']; ?>" 
                                <?php echo $class_id == $class['class_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['class_name'] . ' (' . ucfirst($class['level']) . ')'); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Filter by Risk</label>
                    <select name="filter" class="form-control" onchange="this.form.submit()">
                        <option value="all" <?php echo $filter == 'all' ? 'selected' : ''; ?>>All Students</option>
                        <option value="at_risk" <?php echo $filter == 'at_risk' ? 'selected' : ''; ?>>At Risk (Medium & High)</option>
                        <option value="high_risk" <?php echo $filter == 'high_risk' ? 'selected' : ''; ?>>High Risk Only</option>
                    </select>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="row">
    <div class="card">
        <div class="card-header">
            <h3>Student List</h3>
            <div>
                <?php 
                $total = $students->num_rows;
                echo "Showing $total students";
                if ($class_id) {
                    $class_name = $conn->query("SELECT class_name FROM classes WHERE class_id = $class_id")->fetch_assoc()['class_name'];
                    echo " in " . htmlspecialchars($class_name);
                }
                ?>
            </div>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Admission No</th>
                        <th>Student Name</th>
                        <th>Class</th>
                        <th>Level</th>
                        <th>Overall Score</th>
                        <th>Attendance</th>
                        <th>Avg Score</th>
                        <th>Risk Level</th>
                        <th>Trend</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($student = $students->fetch_assoc()): 
                        $attendance_percentage = $student['total_attendance'] > 0 
                            ? round(($student['total_present'] / $student['total_attendance']) * 100, 1) 
                            : 0;
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($student['admission_no']); ?></td>
                            <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($student['class_name']); ?></td>
                            <td>
                                <span class="badge <?php echo $student['level'] == 'junior' ? 'badge-info' : 'badge-warning'; ?>">
                                    <?php echo ucfirst($student['level']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($student['overall_score']): ?>
                                    <?php echo number_format($student['overall_score'], 1); ?>%
                                <?php else: ?>
                                    <span class="badge">Not Calculated</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($student['total_attendance'] > 0): ?>
                                    <span class="badge <?php 
                                        echo $attendance_percentage >= 75 ? 'badge-low' : 
                                              ($attendance_percentage >= 50 ? 'badge-medium' : 'badge-high');
                                    ?>">
                                        <?php echo $attendance_percentage; ?>%
                                    </span>
                                    <br>
                                    <small><?php echo $student['total_present']; ?>/<?php echo $student['total_attendance']; ?></small>
                                <?php else: ?>
                                    <span class="badge">No Data</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($student['overall_avg_score']): ?>
                                    <?php echo number_format($student['overall_avg_score'], 1); ?>%
                                <?php else: ?>
                                    <span class="badge">No Data</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($student['risk_level']): ?>
                                    <span class="badge badge-<?php echo $student['risk_level']; ?>">
                                        <?php echo ucfirst($student['risk_level']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge">Not Calculated</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($student['trend']): ?>
                                    <?php echo ucfirst($student['trend']); ?>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td class="actions">
                                <a href="student_details.php?id=<?php echo $student['student_id']; ?>" class="btn btn-info btn-sm">View Details</a>
                                <?php if ($student['risk_level'] == 'high'): ?>
                                    <a href="interventions.php?student_id=<?php echo $student['student_id']; ?>" class="btn btn-warning btn-sm">Intervene</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    <?php if ($students->num_rows == 0): ?>
                        <tr>
                            <td colspan="10" class="text-center">No students found with the selected criteria</td>
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