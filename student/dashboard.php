<?php
$page_title = "Student Dashboard";
require_once '../includes/header.php';
require_once '../config/db.php';
require_once '../includes/prediction_logic.php';

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Get student details
$student = $conn->query("
    SELECT s.*, c.class_name, c.level 
    FROM students s 
    LEFT JOIN classes c ON s.class_id = c.class_id 
    WHERE s.user_id = $user_id
")->fetch_assoc();

if (!$student) {
    header('Location: ../auth/logout.php');
    exit();
}

$student_id = $student['student_id'];

// Calculate current performance
$performance = calculatePerformanceScore($student_id);

// Get recent assessments
$recent_assessments = $conn->query("
    SELECT 
        a.*,
        sub.subject_name
    FROM assessments a
    JOIN subjects sub ON a.subject_id = sub.subject_id
    WHERE a.student_id = $student_id
    ORDER BY a.created_at DESC
    LIMIT 5
");

// Get attendance summary
$attendance_summary = $conn->query("
    SELECT 
        COUNT(*) as total_days,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days
    FROM attendance 
    WHERE student_id = $student_id
      AND YEAR(date) = YEAR(CURRENT_DATE())
");

$attendance_data = $attendance_summary->fetch_assoc();
$attendance_rate = $attendance_data['total_days'] > 0 
    ? round(($attendance_data['present_days'] / $attendance_data['total_days']) * 100, 1) 
    : 0;

// Get subject-wise performance
$subject_performance = $conn->query("
    SELECT 
        sub.subject_name,
        AVG((a.score / a.max_score) * 100) as avg_score,
        COUNT(a.assessment_id) as assessment_count
    FROM assessments a
    JOIN subjects sub ON a.subject_id = sub.subject_id
    WHERE a.student_id = $student_id
    GROUP BY sub.subject_id
    ORDER BY avg_score DESC
    LIMIT 5
");
?>

<div class="row">
    <!-- Student Info Card -->
    <div class="card">
        <div class="card-header">
            <h3>Welcome, <?php echo htmlspecialchars($student['full_name']); ?></h3>
        </div>
        <div class="form-row">
            <div style="flex: 2;">
                <table style="width: 100%;">
                    <tr>
                        <td style="width: 150px; padding: 8px 0; color: #666;">Admission No:</td>
                        <td style="padding: 8px 0;"><?php echo htmlspecialchars($student['admission_no']); ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #666;">Class:</td>
                        <td style="padding: 8px 0;"><?php echo htmlspecialchars($student['class_name']); ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #666;">Level:</td>
                        <td style="padding: 8px 0;">
                            <span class="badge <?php echo $student['level'] == 'junior' ? 'badge-info' : 'badge-warning'; ?>">
                                <?php echo ucfirst($student['level']); ?> Secondary
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #666;">Gender:</td>
                        <td style="padding: 8px 0;"><?php echo ucfirst($student['gender']); ?></td>
                    </tr>
                </table>
            </div>
            <div style="flex: 1; text-align: center;">
                <div style="font-size: 36px; font-weight: bold; color: #2d3748; margin-bottom: 5px;">
                    <?php echo number_format($performance['overall_score'], 1); ?>%
                </div>
                <div style="margin-bottom: 10px;">
                    <span class="badge badge-<?php echo $performance['risk_level']; ?>" style="font-size: 16px;">
                        <?php echo ucfirst($performance['risk_level']); ?> Risk
                    </span>
                </div>
                <div style="color: #666; font-size: 14px;">
                    Overall Performance Score
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Performance Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <h4>Attendance Rate</h4>
            <div class="value"><?php echo $attendance_rate; ?>%</div>
            <div class="trend <?php echo $attendance_rate < 75 ? 'negative' : ''; ?>">
                <?php echo $attendance_rate < 75 ? 'Needs Improvement' : 'Good'; ?>
            </div>
        </div>
        
        <div class="stat-card">
            <h4>Assessment Average</h4>
            <div class="value"><?php echo number_format($performance['average_score'], 1); ?>%</div>
            <div class="trend <?php echo $performance['average_score'] < 50 ? 'negative' : ''; ?>">
                <?php echo $performance['average_score'] < 50 ? 'Needs Improvement' : 'Good'; ?>
            </div>
        </div>
        
        <div class="stat-card">
            <h4>Performance Trend</h4>
            <div class="value" style="font-size: 24px;">
                <?php echo ucfirst($performance['trend']); ?>
            </div>
            <div class="trend <?php echo $performance['trend'] == 'declining' ? 'negative' : ''; ?>">
                <?php echo $performance['trend'] == 'improving' ? 'Improving' : 
                          ($performance['trend'] == 'declining' ? 'Declining' : 'Stable'); ?>
            </div>
        </div>
        
        <div class="stat-card">
            <h4>Total Assessments</h4>
            <div class="value">
                <?php
                $total_assessments = $conn->query("
                    SELECT COUNT(*) as count FROM assessments WHERE student_id = $student_id
                ")->fetch_assoc()['count'];
                echo $total_assessments;
                ?>
            </div>
            <div class="trend">Recorded</div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Recent Assessments -->
    <div class="card" style="flex: 2;">
        <div class="card-header">
            <h3>Recent Assessments</h3>
            <a href="assessments_view.php" class="btn btn-sm btn-primary">View All</a>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Type</th>
                        <th>Score</th>
                        <th>Percentage</th>
                        <th>Term/Year</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($assessment = $recent_assessments->fetch_assoc()): 
                        $percentage = ($assessment['score'] / $assessment['max_score']) * 100;
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($assessment['subject_name']); ?></td>
                            <td>
                                <span class="badge <?php 
                                    echo $assessment['type'] == 'exam' ? 'badge-high' : 
                                          ($assessment['type'] == 'test' ? 'badge-medium' : 'badge-low');
                                ?>">
                                    <?php echo ucfirst($assessment['type']); ?>
                                </span>
                            </td>
                            <td><?php echo $assessment['score']; ?>/<?php echo $assessment['max_score']; ?></td>
                            <td>
                                <span class="badge <?php 
                                    echo $percentage >= 70 ? 'badge-low' : 
                                          ($percentage >= 50 ? 'badge-medium' : 'badge-high');
                                ?>">
                                    <?php echo number_format($percentage, 1); ?>%
                                </span>
                            </td>
                            <td>Term <?php echo $assessment['term']; ?>, <?php echo $assessment['academic_year']; ?></td>
                            <td><?php echo date('M d, Y', strtotime($assessment['created_at'])); ?></td>
                        </tr>
                    <?php endwhile; ?>
                    <?php if ($recent_assessments->num_rows == 0): ?>
                        <tr>
                            <td colspan="6" class="text-center">No assessments recorded yet</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Subject Performance -->
    <div class="card" style="flex: 1;">
        <div class="card-header">
            <h3>Subject Performance</h3>
        </div>
        <div style="max-height: 400px; overflow-y: auto;">
            <?php if ($subject_performance->num_rows > 0): ?>
                <?php while ($subject = $subject_performance->fetch_assoc()): ?>
                    <div style="padding: 15px; border-bottom: 1px solid #eee;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                            <strong><?php echo htmlspecialchars($subject['subject_name']); ?></strong>
                            <span class="badge <?php 
                                echo $subject['avg_score'] >= 70 ? 'badge-low' : 
                                      ($subject['avg_score'] >= 50 ? 'badge-medium' : 'badge-high');
                            ?>">
                                <?php echo number_format($subject['avg_score'], 1); ?>%
                            </span>
                        </div>
                        <div style="color: #666; font-size: 12px;">
                            <?php echo $subject['assessment_count']; ?> assessments
                        </div>
                        <div style="margin-top: 8px; height: 6px; background: #e2e8f0; border-radius: 3px; overflow: hidden;">
                            <div style="height: 100%; width: <?php echo min(100, $subject['avg_score']); ?>%; 
                                      background: <?php 
                                          echo $subject['avg_score'] >= 70 ? '#48bb78' : 
                                                ($subject['avg_score'] >= 50 ? '#ed8936' : '#f56565'); 
                                      ?>;">
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="padding: 20px; text-align: center; color: #666;">
                    No subject performance data available
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!empty($performance['warning_triggers'])): ?>
<div class="row">
    <div class="card">
        <div class="card-header">
            <h3 style="color: #c53030;">⚠️ Attention Required</h3>
        </div>
        <div style="padding: 20px;">
            <p>The following areas need your attention:</p>
            <ul style="color: #744210; margin: 15px 0; padding-left: 20px;">
                <?php foreach ($performance['warning_triggers'] as $trigger): ?>
                    <li><?php echo htmlspecialchars($trigger); ?></li>
                <?php endforeach; ?>
            </ul>
            <div style="background: #feebc8; padding: 15px; border-radius: 5px; margin-top: 15px;">
                <strong>Recommendations:</strong>
                <ul style="margin: 10px 0 0 20px;">
                    <li>Improve your attendance by coming to class regularly</li>
                    <li>Focus on completing all assignments and tests</li>
                    <li>Seek help from your teachers if you're struggling</li>
                    <li>Review your study habits and create a study schedule</li>
                </ul>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php 
$conn->close();
require_once '../includes/footer.php'; 
?>