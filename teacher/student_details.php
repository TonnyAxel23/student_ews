<?php
$page_title = "Student Details";
require_once '../includes/header.php';
require_once '../config/db.php';
require_once '../includes/prediction_logic.php';

if (!isset($_GET['id'])) {
    header('Location: students.php');
    exit();
}

$student_id = intval($_GET['id']);
$conn = getDBConnection();

// Get student details
$student = $conn->query("
    SELECT s.*, c.class_name, c.level 
    FROM students s 
    LEFT JOIN classes c ON s.class_id = c.class_id 
    WHERE s.student_id = $student_id
")->fetch_assoc();

if (!$student) {
    header('Location: students.php');
    exit();
}

// Calculate performance
$performance = calculatePerformanceScore($student_id);

// Get performance history
$history = getPerformanceHistory($student_id);

// Get recent assessments
$assessments = $conn->query("
    SELECT 
        a.*,
        sub.subject_name
    FROM assessments a
    JOIN subjects sub ON a.subject_id = sub.subject_id
    WHERE a.student_id = $student_id
    ORDER BY a.academic_year DESC, a.term DESC, a.created_at DESC
    LIMIT 10
");

// Get attendance summary
$attendance_summary = $conn->query("
    SELECT 
        sub.subject_name,
        COUNT(*) as total_days,
        SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_days
    FROM attendance a
    JOIN subjects sub ON a.subject_id = sub.subject_id
    WHERE a.student_id = $student_id
      AND YEAR(a.date) = YEAR(CURRENT_DATE())
    GROUP BY a.subject_id
    ORDER BY sub.subject_name
");

// Get interventions
$interventions = $conn->query("
    SELECT 
        i.*,
        t.full_name as teacher_name
    FROM interventions i
    JOIN teachers t ON i.teacher_id = t.teacher_id
    WHERE i.student_id = $student_id
    ORDER BY i.created_at DESC
");
?>

<div class="row">
    <div class="card">
        <div class="card-header">
            <h3>Student Information</h3>
            <a href="students.php" class="btn btn-secondary">Back to List</a>
        </div>
        <div class="form-row">
            <div style="flex: 2;">
                <h4><?php echo htmlspecialchars($student['full_name']); ?></h4>
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
    <!-- Performance Summary -->
    <div class="card" style="flex: 2;">
        <div class="card-header">
            <h3>Performance Summary</h3>
            <div>
                Term <?php echo $performance['term']; ?>, <?php echo $performance['academic_year']; ?>
            </div>
        </div>
        <div class="stats-grid">
            <div class="stat-card">
                <h4>Attendance Rate</h4>
                <div class="value"><?php echo number_format($performance['attendance_rate'], 1); ?>%</div>
                <div class="trend <?php echo $performance['attendance_rate'] < 75 ? 'negative' : ''; ?>">
                    <?php echo $performance['attendance_rate'] < 75 ? 'Below Target' : 'On Target'; ?>
                </div>
            </div>
            
            <div class="stat-card">
                <h4>Assessment Average</h4>
                <div class="value"><?php echo number_format($performance['average_score'], 1); ?>%</div>
                <div class="trend <?php echo $performance['average_score'] < 50 ? 'negative' : ''; ?>">
                    <?php echo $performance['average_score'] < 50 ? 'Below Target' : 'On Target'; ?>
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
        </div>
        
        <?php if (!empty($performance['warning_triggers'])): ?>
            <div style="margin-top: 20px; padding: 15px; background: #fed7d7; border-radius: 5px;">
                <h4 style="color: #c53030; margin-bottom: 10px;">⚠️ Early Warning Triggers</h4>
                <ul style="color: #744210; margin: 0; padding-left: 20px;">
                    <?php foreach ($performance['warning_triggers'] as $trigger): ?>
                        <li><?php echo htmlspecialchars($trigger); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <!-- Performance Chart -->
        <div style="margin-top: 30px;">
            <h4>Performance History</h4>
            <div class="chart-container">
                <canvas id="performanceChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Recent Assessments -->
    <div class="card" style="flex: 1;">
        <div class="card-header">
            <h3>Recent Assessments</h3>
        </div>
        <div style="max-height: 400px; overflow-y: auto;">
            <?php if ($assessments->num_rows > 0): ?>
                <?php while ($assessment = $assessments->fetch_assoc()): 
                    $percentage = ($assessment['score'] / $assessment['max_score']) * 100;
                ?>
                    <div style="padding: 15px; border-bottom: 1px solid #eee;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <strong><?php echo htmlspecialchars($assessment['subject_name']); ?></strong>
                            <span class="badge <?php 
                                echo $percentage >= 70 ? 'badge-low' : 
                                      ($percentage >= 50 ? 'badge-medium' : 'badge-high');
                            ?>">
                                <?php echo number_format($percentage, 1); ?>%
                            </span>
                        </div>
                        <div style="color: #666; font-size: 12px; margin-bottom: 5px;">
                            <?php echo ucfirst($assessment['type']); ?> • 
                            Term <?php echo $assessment['term']; ?>, <?php echo $assessment['academic_year']; ?>
                        </div>
                        <div style="font-size: 13px; color: #555;">
                            Score: <?php echo $assessment['score']; ?>/<?php echo $assessment['max_score']; ?>
                        </div>
                        <div style="font-size: 11px; color: #999; margin-top: 5px;">
                            <?php echo date('M d, Y', strtotime($assessment['created_at'])); ?>
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
    <!-- Attendance Summary -->
    <div class="card" style="flex: 2;">
        <div class="card-header">
            <h3>Attendance Summary (Current Year)</h3>
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
                    </tr>
                </thead>
                <tbody>
                    <?php while ($attendance = $attendance_summary->fetch_assoc()): 
                        $absent = $attendance['total_days'] - $attendance['present_days'];
                        $rate = $attendance['total_days'] > 0 
                            ? round(($attendance['present_days'] / $attendance['total_days']) * 100, 1) 
                            : 0;
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($attendance['subject_name']); ?></td>
                            <td><?php echo $attendance['total_days']; ?></td>
                            <td><?php echo $attendance['present_days']; ?></td>
                            <td><?php echo $absent; ?></td>
                            <td>
                                <span class="badge <?php 
                                    echo $rate >= 75 ? 'badge-low' : 
                                          ($rate >= 50 ? 'badge-medium' : 'badge-high');
                                ?>">
                                    <?php echo $rate; ?>%
                                </span>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    <?php if ($attendance_summary->num_rows == 0): ?>
                        <tr>
                            <td colspan="5" class="text-center">No attendance records found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Interventions -->
    <div class="card" style="flex: 1;">
        <div class="card-header">
            <h3>Interventions</h3>
            <a href="interventions.php?student_id=<?php echo $student_id; ?>" class="btn btn-sm btn-primary">Manage</a>
        </div>
        <div style="max-height: 400px; overflow-y: auto;">
            <?php if ($interventions->num_rows > 0): ?>
                <?php while ($intervention = $interventions->fetch_assoc()): ?>
                    <div style="padding: 15px; border-bottom: 1px solid #eee;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <span class="badge badge-<?php echo $intervention['status']; ?>">
                                <?php echo ucfirst($intervention['status']); ?>
                            </span>
                            <small><?php echo date('M d', strtotime($intervention['created_at'])); ?></small>
                        </div>
                        <div style="color: #666; font-size: 12px; margin-bottom: 5px;">
                            By: <?php echo htmlspecialchars($intervention['teacher_name']); ?>
                        </div>
                        <div style="font-size: 13px; color: #555; margin-bottom: 5px;">
                            <?php echo substr($intervention['reason'], 0, 50); ?>...
                        </div>
                        <div style="font-size: 12px; color: #888;">
                            Action: <?php echo substr($intervention['action_taken'], 0, 50); ?>...
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="padding: 20px; text-align: center; color: #666;">
                    No interventions yet
                    <br>
                    <a href="interventions.php?student_id=<?php echo $student_id; ?>" class="btn btn-warning btn-sm mt-3">
                        Create Intervention
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Prepare chart data from history
const historyData = <?php echo json_encode($history); ?>;
const labels = [];
const scores = [];
const attendance = [];

historyData.forEach(item => {
    labels.push(`Term ${item.term}, ${item.academic_year}`);
    scores.push(item.overall_score);
    attendance.push(item.attendance_rate);
});

// Performance Chart
const ctx = document.getElementById('performanceChart').getContext('2d');
const performanceChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: labels,
        datasets: [
            {
                label: 'Overall Score',
                data: scores,
                borderColor: '#667eea',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            },
            {
                label: 'Attendance Rate',
                data: attendance,
                borderColor: '#48bb78',
                backgroundColor: 'rgba(72, 187, 120, 0.1)',
                borderWidth: 2,
                fill: false,
                tension: 0.4
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                max: 100,
                ticks: {
                    callback: function(value) {
                        return value + '%';
                    }
                }
            }
        },
        plugins: {
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return `${context.dataset.label}: ${context.parsed.y}%`;
                    }
                }
            }
        }
    }
});
</script>

<?php 
$conn->close();
require_once '../includes/footer.php'; 
?>