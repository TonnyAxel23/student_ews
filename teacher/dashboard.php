<?php
$page_title = "Teacher Dashboard";
require_once '../includes/header.php';
require_once '../config/db.php';
require_once '../includes/prediction_logic.php';

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Get teacher details
$teacher = $conn->query("
    SELECT t.* FROM teachers t 
    WHERE t.user_id = $user_id
")->fetch_assoc();

$teacher_id = $teacher['teacher_id'];

// Get teacher's subjects and classes
$teacher_subjects = $conn->query("
    SELECT 
        s.*, 
        c.class_name,
        (SELECT COUNT(DISTINCT st.student_id) 
         FROM students st 
         WHERE st.class_id = s.class_id) as student_count
    FROM subjects s 
    LEFT JOIN classes c ON s.class_id = c.class_id 
    WHERE s.teacher_id = $teacher_id
    ORDER BY s.level, s.subject_name
");

// Get students needing attention
$attention_students = $conn->query("
    SELECT 
        s.student_id,
        s.full_name,
        s.admission_no,
        c.class_name,
        ps.overall_score,
        ps.attendance_rate,
        ps.average_score,
        ps.risk_level,
        ps.trend
    FROM students s
    LEFT JOIN classes c ON s.class_id = c.class_id
    LEFT JOIN performance_summary ps ON s.student_id = ps.student_id 
        AND ps.term = CEIL(MONTH(CURRENT_DATE()) / 4)
        AND ps.academic_year = YEAR(CURRENT_DATE())
    WHERE s.class_id IN (
        SELECT class_id FROM subjects WHERE teacher_id = $teacher_id
    )
    AND ps.risk_level IN ('medium', 'high')
    ORDER BY ps.risk_level DESC, ps.overall_score ASC
    LIMIT 10
");

// Get upcoming assessments
$current_month = date('m');
$current_term = ceil($current_month / 4);
$current_year = date('Y');

$recent_assessments = $conn->query("
    SELECT 
        a.*,
        s.full_name,
        sub.subject_name
    FROM assessments a
    JOIN students s ON a.student_id = s.student_id
    JOIN subjects sub ON a.subject_id = sub.subject_id
    WHERE sub.teacher_id = $teacher_id
      AND a.term = $current_term
      AND a.academic_year = $current_year
    ORDER BY a.created_at DESC
    LIMIT 5
");
?>

<div class="row">
    <div class="stats-grid">
        <div class="stat-card">
            <h4>My Subjects</h4>
            <div class="value"><?php echo $teacher_subjects->num_rows; ?></div>
            <div class="trend">Assigned</div>
        </div>
        
        <div class="stat-card">
            <h4>Total Students</h4>
            <div class="value">
                <?php
                $total_students = $conn->query("
                    SELECT COUNT(DISTINCT s.student_id) as count
                    FROM students s
                    JOIN subjects sub ON s.class_id = sub.class_id
                    WHERE sub.teacher_id = $teacher_id
                ")->fetch_assoc()['count'];
                echo $total_students;
                ?>
            </div>
            <div class="trend">In My Classes</div>
        </div>
        
        <div class="stat-card">
            <h4>At Risk</h4>
            <div class="value">
                <?php
                $at_risk = $conn->query("
                    SELECT COUNT(DISTINCT s.student_id) as count
                    FROM students s
                    LEFT JOIN performance_summary ps ON s.student_id = ps.student_id 
                        AND ps.term = CEIL(MONTH(CURRENT_DATE()) / 4)
                        AND ps.academic_year = YEAR(CURRENT_DATE())
                    WHERE s.class_id IN (
                        SELECT class_id FROM subjects WHERE teacher_id = $teacher_id
                    )
                    AND ps.risk_level IN ('medium', 'high')
                ")->fetch_assoc()['count'];
                echo $at_risk;
                ?>
            </div>
            <div class="trend negative">Need Attention</div>
        </div>
        
        <div class="stat-card">
            <h4>Pending Interventions</h4>
            <div class="value">
                <?php
                $pending = $conn->query("
                    SELECT COUNT(*) as count 
                    FROM interventions 
                    WHERE teacher_id = $teacher_id 
                    AND status IN ('pending', 'ongoing')
                ")->fetch_assoc()['count'];
                echo $pending;
                ?>
            </div>
            <div class="trend negative">Action Required</div>
        </div>
    </div>
</div>

<div class="row">
    <!-- My Subjects -->
    <div class="card" style="flex: 2;">
        <div class="card-header">
            <h3>My Subjects & Classes</h3>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Level</th>
                        <th>Class</th>
                        <th>Students</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($subject = $teacher_subjects->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                            <td>
                                <span class="badge <?php echo $subject['level'] == 'junior' ? 'badge-info' : 'badge-warning'; ?>">
                                    <?php echo ucfirst($subject['level']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($subject['class_name'] ?? 'All Classes'); ?></td>
                            <td><?php echo $subject['student_count'] ?? 0; ?> students</td>
                            <td class="actions">
                                <a href="attendance.php?subject_id=<?php echo $subject['subject_id']; ?>" class="btn btn-primary btn-sm">Attendance</a>
                                <a href="assessments.php?subject_id=<?php echo $subject['subject_id']; ?>" class="btn btn-success btn-sm">Scores</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    <?php if ($teacher_subjects->num_rows == 0): ?>
                        <tr>
                            <td colspan="5" class="text-center">No subjects assigned yet</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Assessments -->
    <div class="card" style="flex: 1;">
        <div class="card-header">
            <h3>Recent Assessments</h3>
            <a href="assessments.php" class="btn btn-sm btn-primary">View All</a>
        </div>
        <div style="max-height: 400px; overflow-y: auto;">
            <?php if ($recent_assessments->num_rows > 0): ?>
                <?php while ($assessment = $recent_assessments->fetch_assoc()): ?>
                    <div style="padding: 15px; border-bottom: 1px solid #eee;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <strong><?php echo htmlspecialchars($assessment['full_name']); ?></strong>
                            <span><?php echo number_format(($assessment['score'] / $assessment['max_score']) * 100, 1); ?>%</span>
                        </div>
                        <div style="color: #666; font-size: 12px; margin-bottom: 5px;">
                            <?php echo ucfirst($assessment['type']); ?> • 
                            <?php echo htmlspecialchars($assessment['subject_name']); ?>
                        </div>
                        <div style="font-size: 13px; color: #555;">
                            Score: <?php echo $assessment['score']; ?>/<?php echo $assessment['max_score']; ?>
                        </div>
                        <div style="font-size: 11px; color: #999; margin-top: 5px;">
                            Term <?php echo $assessment['term']; ?>, <?php echo $assessment['academic_year']; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="padding: 20px; text-align: center; color: #666;">
                    No assessments recorded yet
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row">
    <!-- Students Needing Attention -->
    <div class="card">
        <div class="card-header">
            <h3>Students Needing Attention</h3>
            <a href="students.php?filter=at_risk" class="btn btn-sm btn-warning">View All</a>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Student Name</th>
                        <th>Class</th>
                        <th>Overall Score</th>
                        <th>Attendance</th>
                        <th>Avg Score</th>
                        <th>Risk Level</th>
                        <th>Trend</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($student = $attention_students->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <?php echo htmlspecialchars($student['full_name']); ?>
                                <br><small><?php echo htmlspecialchars($student['admission_no']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($student['class_name']); ?></td>
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
                                    N/A
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
                                <a href="student_details.php?id=<?php echo $student['student_id']; ?>" class="btn btn-info btn-sm">View</a>
                                <a href="interventions.php?student_id=<?php echo $student['student_id']; ?>" class="btn btn-warning btn-sm">Intervene</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    <?php if ($attention_students->num_rows == 0): ?>
                        <tr>
                            <td colspan="8" class="text-center">No students need immediate attention</td>
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