<?php
$page_title = "My Performance";
require_once '../includes/header.php';
require_once '../config/db.php';
require_once '../includes/prediction_logic.php';

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

// Get performance history
$history = getPerformanceHistory($student_id);

// Get current performance
$current_performance = calculatePerformanceScore($student_id);

// Get all assessments grouped by term
$assessments_by_term = $conn->query("
    SELECT 
        a.term,
        a.academic_year,
        COUNT(*) as total_assessments,
        AVG((a.score / a.max_score) * 100) as avg_score,
        MIN((a.score / a.max_score) * 100) as min_score,
        MAX((a.score / a.max_score) * 100) as max_score
    FROM assessments a
    WHERE a.student_id = $student_id
    GROUP BY a.academic_year, a.term
    ORDER BY a.academic_year DESC, a.term DESC
");

// Get subject-wise performance
$subject_performance = $conn->query("
    SELECT 
        sub.subject_name,
        AVG((a.score / a.max_score) * 100) as avg_score,
        COUNT(a.assessment_id) as assessment_count,
        MIN((a.score / a.max_score) * 100) as min_score,
        MAX((a.score / a.max_score) * 100) as max_score
    FROM assessments a
    JOIN subjects sub ON a.subject_id = sub.subject_id
    WHERE a.student_id = $student_id
    GROUP BY sub.subject_id
    ORDER BY avg_score DESC
");
?>

<div class="row">
    <div class="card">
        <div class="card-header">
            <h3>Performance Overview</h3>
            <div>
                Term <?php echo $current_performance['term']; ?>, <?php echo $current_performance['academic_year']; ?>
            </div>
        </div>
        <div class="stats-grid">
            <div class="stat-card">
                <h4>Current Score</h4>
                <div class="value"><?php echo number_format($current_performance['overall_score'], 1); ?>%</div>
                <div class="trend <?php echo $current_performance['overall_score'] < 50 ? 'negative' : ''; ?>">
                    <?php echo $current_performance['overall_score'] < 50 ? 'Needs Improvement' : 'Good'; ?>
                </div>
            </div>
            
            <div class="stat-card">
                <h4>Attendance</h4>
                <div class="value"><?php echo number_format($current_performance['attendance_rate'], 1); ?>%</div>
                <div class="trend <?php echo $current_performance['attendance_rate'] < 75 ? 'negative' : ''; ?>">
                    <?php echo $current_performance['attendance_rate'] < 75 ? 'Below Target' : 'On Target'; ?>
                </div>
            </div>
            
            <div class="stat-card">
                <h4>Assessment Avg</h4>
                <div class="value"><?php echo number_format($current_performance['average_score'], 1); ?>%</div>
                <div class="trend <?php echo $current_performance['average_score'] < 50 ? 'negative' : ''; ?>">
                    <?php echo $current_performance['average_score'] < 50 ? 'Below Target' : 'On Target'; ?>
                </div>
            </div>
            
            <div class="stat-card">
                <h4>Risk Level</h4>
                <div class="value" style="font-size: 24px;">
                    <?php echo ucfirst($current_performance['risk_level']); ?>
                </div>
                <div class="trend <?php echo $current_performance['risk_level'] == 'high' ? 'negative' : ''; ?>">
                    <?php echo $current_performance['risk_level'] == 'high' ? 'Needs Attention' : 'Good'; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Performance History Chart -->
    <div class="card" style="flex: 2;">
        <div class="card-header">
            <h3>Performance History</h3>
        </div>
        <div class="chart-container">
            <canvas id="performanceChart"></canvas>
        </div>
    </div>

    <!-- Term-wise Summary -->
    <div class="card" style="flex: 1;">
        <div class="card-header">
            <h3>Term-wise Performance</h3>
        </div>
        <div style="max-height: 400px; overflow-y: auto;">
            <?php if ($assessments_by_term->num_rows > 0): ?>
                <?php while ($term_data = $assessments_by_term->fetch_assoc()): 
                    $term_performance = $conn->query("
                        SELECT overall_score, risk_level 
                        FROM performance_summary 
                        WHERE student_id = $student_id 
                          AND term = {$term_data['term']} 
                          AND academic_year = {$term_data['academic_year']}
                    ")->fetch_assoc();
                ?>
                    <div style="padding: 15px; border-bottom: 1px solid #eee;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                            <strong>Term <?php echo $term_data['term']; ?>, <?php echo $term_data['academic_year']; ?></strong>
                            <?php if ($term_performance): ?>
                                <span class="badge badge-<?php echo $term_performance['risk_level']; ?>">
                                    <?php echo number_format($term_performance['overall_score'], 1); ?>%
                                </span>
                            <?php endif; ?>
                        </div>
                        <div style="color: #666; font-size: 12px; margin-bottom: 5px;">
                            <?php echo $term_data['total_assessments']; ?> assessments
                        </div>
                        <div style="font-size: 13px; color: #555;">
                            Average: <?php echo number_format($term_data['avg_score'], 1); ?>%
                            <br>
                            Range: <?php echo number_format($term_data['min_score'], 1); ?>% - 
                            <?php echo number_format($term_data['max_score'], 1); ?>%
                        </div>
                        <?php if ($term_performance): ?>
                            <div style="font-size: 11px; color: #999; margin-top: 5px;">
                                Risk: <?php echo ucfirst($term_performance['risk_level']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="padding: 20px; text-align: center; color: #666;">
                    No term data available
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row">
    <!-- Subject Performance -->
    <div class="card">
        <div class="card-header">
            <h3>Subject Performance</h3>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Average Score</th>
                        <th>Assessments</th>
                        <th>Range</th>
                        <th>Performance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($subject = $subject_performance->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                            <td>
                                <span class="badge <?php 
                                    echo $subject['avg_score'] >= 70 ? 'badge-low' : 
                                          ($subject['avg_score'] >= 50 ? 'badge-medium' : 'badge-high');
                                ?>">
                                    <?php echo number_format($subject['avg_score'], 1); ?>%
                                </span>
                            </td>
                            <td><?php echo $subject['assessment_count']; ?></td>
                            <td>
                                <?php echo number_format($subject['min_score'], 1); ?>% - 
                                <?php echo number_format($subject['max_score'], 1); ?>%
                            </td>
                            <td>
                                <div style="height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden; width: 100px;">
                                    <div style="height: 100%; width: <?php echo min(100, $subject['avg_score']); ?>%; 
                                              background: <?php 
                                                  echo $subject['avg_score'] >= 70 ? '#48bb78' : 
                                                        ($subject['avg_score'] >= 50 ? '#ed8936' : '#f56565'); 
                                              ?>;">
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    <?php if ($subject_performance->num_rows == 0): ?>
                        <tr>
                            <td colspan="5" class="text-center">No subject performance data available</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Prepare chart data from history
const historyData = <?php echo json_encode($history); ?>;
const labels = [];
const overallScores = [];
const attendanceRates = [];
const assessmentAvgs = [];

historyData.forEach(item => {
    labels.push(`Term ${item.term}, ${item.academic_year}`);
    overallScores.push(item.overall_score);
    attendanceRates.push(item.attendance_rate);
    assessmentAvgs.push(item.average_score);
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
                data: overallScores,
                borderColor: '#667eea',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4
            },
            {
                label: 'Attendance Rate',
                data: attendanceRates,
                borderColor: '#48bb78',
                backgroundColor: 'rgba(72, 187, 120, 0.1)',
                borderWidth: 2,
                fill: false,
                tension: 0.4
            },
            {
                label: 'Assessment Average',
                data: assessmentAvgs,
                borderColor: '#ed8936',
                backgroundColor: 'rgba(237, 137, 54, 0.1)',
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
            },
            legend: {
                position: 'bottom',
                labels: {
                    padding: 20,
                    usePointStyle: true
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