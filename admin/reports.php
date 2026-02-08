<?php
$page_title = "Reports";
require_once '../includes/header.php';
require_once '../config/db.php';

$conn = getDBConnection();

// Set default parameters
$current_month = date('m');
$current_term = ceil($current_month / 4);
$current_year = date('Y');

$term = isset($_GET['term']) ? intval($_GET['term']) : $current_term;
$academic_year = isset($_GET['academic_year']) ? intval($_GET['academic_year']) : $current_year;
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$risk_level = isset($_GET['risk_level']) ? $_GET['risk_level'] : '';

// Get classes for filter
$classes = $conn->query("SELECT * FROM classes ORDER BY level, class_name");

// Build SQL query
$sql = "
    SELECT 
        s.student_id,
        s.full_name,
        s.admission_no,
        c.class_name,
        c.level,
        ps.overall_score,
        ps.attendance_rate,
        ps.average_score,
        ps.risk_level,
        ps.trend,
        ps.generated_on
    FROM students s
    LEFT JOIN classes c ON s.class_id = c.class_id
    LEFT JOIN performance_summary ps ON s.student_id = ps.student_id 
        AND ps.term = $term 
        AND ps.academic_year = $academic_year
";

$where_conditions = [];

if ($class_id > 0) {
    $where_conditions[] = "s.class_id = $class_id";
}

if ($risk_level && in_array($risk_level, ['low', 'medium', 'high'])) {
    $where_conditions[] = "ps.risk_level = '$risk_level'";
}

if (!empty($where_conditions)) {
    $sql .= " WHERE " . implode(" AND ", $where_conditions);
}

$sql .= " ORDER BY ps.overall_score ASC, s.full_name";

$result = $conn->query($sql);

// Get summary statistics
$summary_sql = "
    SELECT 
        COUNT(*) as total_students,
        AVG(ps.overall_score) as avg_overall_score,
        AVG(ps.attendance_rate) as avg_attendance,
        AVG(ps.average_score) as avg_assessment,
        SUM(CASE WHEN ps.risk_level = 'high' THEN 1 ELSE 0 END) as high_risk,
        SUM(CASE WHEN ps.risk_level = 'medium' THEN 1 ELSE 0 END) as medium_risk,
        SUM(CASE WHEN ps.risk_level = 'low' THEN 1 ELSE 0 END) as low_risk
    FROM students s
    LEFT JOIN performance_summary ps ON s.student_id = ps.student_id 
        AND ps.term = $term 
        AND ps.academic_year = $academic_year
";

if ($class_id > 0) {
    $summary_sql .= " WHERE s.class_id = $class_id";
}

$summary_result = $conn->query($summary_sql);
$summary = $summary_result->fetch_assoc();
?>

<div class="row">
    <div class="card">
        <div class="card-header">
            <h3>Report Filters</h3>
        </div>
        <form method="GET" action="">
            <div class="form-row">
                <div class="form-group">
                    <label>Term</label>
                    <select name="term" class="form-control">
                        <option value="1" <?php echo $term == 1 ? 'selected' : ''; ?>>Term 1 (Jan-Apr)</option>
                        <option value="2" <?php echo $term == 2 ? 'selected' : ''; ?>>Term 2 (May-Aug)</option>
                        <option value="3" <?php echo $term == 3 ? 'selected' : ''; ?>>Term 3 (Sep-Dec)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Academic Year</label>
                    <input type="number" name="academic_year" class="form-control" 
                           value="<?php echo $academic_year; ?>" min="2000" max="2030">
                </div>
                <div class="form-group">
                    <label>Class</label>
                    <select name="class_id" class="form-control">
                        <option value="0">All Classes</option>
                        <?php while ($class = $classes->fetch_assoc()): ?>
                            <option value="<?php echo $class['class_id']; ?>" 
                                <?php echo $class_id == $class['class_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['class_name'] . ' (' . ucfirst($class['level']) . ')'); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Risk Level</label>
                    <select name="risk_level" class="form-control">
                        <option value="">All Levels</option>
                        <option value="high" <?php echo $risk_level == 'high' ? 'selected' : ''; ?>>High Risk</option>
                        <option value="medium" <?php echo $risk_level == 'medium' ? 'selected' : ''; ?>>Medium Risk</option>
                        <option value="low" <?php echo $risk_level == 'low' ? 'selected' : ''; ?>>Low Risk</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Generate Report</button>
            <button type="button" onclick="window.print()" class="btn btn-secondary">Print Report</button>
        </form>
    </div>
</div>

<div class="row">
    <!-- Summary Statistics -->
    <div class="card">
        <div class="card-header">
            <h3>Summary Statistics</h3>
            <div>
                Term <?php echo $term; ?>, <?php echo $academic_year; ?>
                <?php if ($class_id > 0): 
                    $class_info = $conn->query("SELECT * FROM classes WHERE class_id = $class_id")->fetch_assoc();
                    echo ' • ' . htmlspecialchars($class_info['class_name']);
                endif; ?>
            </div>
        </div>
        <div class="stats-grid">
            <div class="stat-card">
                <h4>Total Students</h4>
                <div class="value"><?php echo $summary['total_students'] ?? 0; ?></div>
                <div class="trend">Analyzed</div>
            </div>
            
            <div class="stat-card">
                <h4>Average Score</h4>
                <div class="value"><?php echo number_format($summary['avg_overall_score'] ?? 0, 1); ?>%</div>
                <div class="trend <?php echo ($summary['avg_overall_score'] ?? 0) < 50 ? 'negative' : ''; ?>">
                    <?php echo ($summary['avg_overall_score'] ?? 0) < 50 ? 'Below Target' : 'On Target'; ?>
                </div>
            </div>
            
            <div class="stat-card">
                <h4>Attendance Rate</h4>
                <div class="value"><?php echo number_format($summary['avg_attendance'] ?? 0, 1); ?>%</div>
                <div class="trend <?php echo ($summary['avg_attendance'] ?? 0) < 75 ? 'negative' : ''; ?>">
                    <?php echo ($summary['avg_attendance'] ?? 0) < 75 ? 'Below Target' : 'On Target'; ?>
                </div>
            </div>
            
            <div class="stat-card">
                <h4>At Risk</h4>
                <div class="value"><?php echo $summary['high_risk'] ?? 0; ?></div>
                <div class="trend negative">
                    <?php echo $summary['high_risk'] ?? 0; ?> High Risk Students
                </div>
            </div>
        </div>
        
        <div style="margin-top: 20px;">
            <div class="form-row">
                <div style="text-align: center; flex: 1;">
                    <div class="badge badge-low" style="margin-bottom: 5px;">Low Risk</div>
                    <div style="font-size: 24px; font-weight: bold;"><?php echo $summary['low_risk'] ?? 0; ?></div>
                    <div style="color: #666; font-size: 14px;">Students</div>
                </div>
                <div style="text-align: center; flex: 1;">
                    <div class="badge badge-medium" style="margin-bottom: 5px;">Medium Risk</div>
                    <div style="font-size: 24px; font-weight: bold;"><?php echo $summary['medium_risk'] ?? 0; ?></div>
                    <div style="color: #666; font-size: 14px;">Students</div>
                </div>
                <div style="text-align: center; flex: 1;">
                    <div class="badge badge-high" style="margin-bottom: 5px;">High Risk</div>
                    <div style="font-size: 24px; font-weight: bold;"><?php echo $summary['high_risk'] ?? 0; ?></div>
                    <div style="color: #666; font-size: 14px;">Students</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="card">
        <div class="card-header">
            <h3>Detailed Performance Report</h3>
            <div>
                Showing <?php echo $result->num_rows; ?> students
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
                        <th>Assessment Avg</th>
                        <th>Risk Level</th>
                        <th>Trend</th>
                        <th>Last Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($student = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($student['admission_no']); ?></td>
                            <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($student['class_name'] ?? 'N/A'); ?></td>
                            <td><?php echo ucfirst($student['level'] ?? 'N/A'); ?></td>
                            <td>
                                <?php if ($student['overall_score']): ?>
                                    <?php echo number_format($student['overall_score'], 1); ?>%
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($student['attendance_rate']): ?>
                                    <?php echo number_format($student['attendance_rate'], 1); ?>%
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($student['average_score']): ?>
                                    <?php echo number_format($student['average_score'], 1); ?>%
                                <?php else: ?>
                                    N/A
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
                            <td>
                                <?php if ($student['generated_on']): ?>
                                    <?php echo date('M d, Y', strtotime($student['generated_on'])); ?>
                                <?php else: ?>
                                    Never
                                <?php endif; ?>
                            </td>
                            <td class="actions">
                                <a href="../teacher/student_details.php?id=<?php echo $student['student_id']; ?>" class="btn btn-info btn-sm">View</a>
                                <?php if ($student['risk_level'] == 'high'): ?>
                                    <a href="../admin/interventions.php?student_id=<?php echo $student['student_id']; ?>" class="btn btn-warning btn-sm">Intervene</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    <?php if ($result->num_rows == 0): ?>
                        <tr>
                            <td colspan="11" class="text-center">No students found with the selected criteria</td>
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