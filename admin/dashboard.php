<?php
$page_title = "Admin Dashboard";
require_once '../includes/header.php';
require_once '../config/db.php';
require_once '../includes/prediction_logic.php';

$conn = getDBConnection();

// Get statistics
$total_students = $conn->query("SELECT COUNT(*) as count FROM students")->fetch_assoc()['count'];
$total_teachers = $conn->query("SELECT COUNT(*) as count FROM teachers")->fetch_assoc()['count'];
$total_classes = $conn->query("SELECT COUNT(*) as count FROM classes")->fetch_assoc()['count'];

// Get risk distribution
$risk_distribution = $conn->query("
    SELECT 
        risk_level,
        COUNT(*) as count,
        ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM performance_summary), 1) as percentage
    FROM performance_summary 
    WHERE term = CEIL(MONTH(CURRENT_DATE()) / 4)
    GROUP BY risk_level
    ORDER BY 
        CASE risk_level 
            WHEN 'high' THEN 1
            WHEN 'medium' THEN 2
            WHEN 'low' THEN 3
        END
");

$risk_data = [];
while ($row = $risk_distribution->fetch_assoc()) {
    $risk_data[$row['risk_level']] = $row;
}

// Get recent interventions
$recent_interventions = $conn->query("
    SELECT 
        i.*,
        s.full_name as student_name,
        s.admission_no,
        t.full_name as teacher_name,
        c.class_name
    FROM interventions i
    JOIN students s ON i.student_id = s.student_id
    JOIN teachers t ON i.teacher_id = t.teacher_id
    LEFT JOIN classes c ON s.class_id = c.class_id
    ORDER BY i.created_at DESC
    LIMIT 10
");

// Generate early warnings if requested
if (isset($_GET['generate_warnings'])) {
    $warnings = generateEarlyWarnings();
    $_SESSION['message'] = "Generated " . $warnings['total_warnings'] . " early warnings";
    $_SESSION['message_type'] = 'success';
    header('Location: dashboard.php');
    exit();
}
?>

<div class="row">
    <div class="stats-grid">
        <div class="stat-card">
            <h4>Total Students</h4>
            <div class="value"><?php echo $total_students; ?></div>
            <div class="trend">Registered</div>
        </div>
        
        <div class="stat-card">
            <h4>Total Teachers</h4>
            <div class="value"><?php echo $total_teachers; ?></div>
            <div class="trend">Active Staff</div>
        </div>
        
        <div class="stat-card">
            <h4>Total Classes</h4>
            <div class="value"><?php echo $total_classes; ?></div>
            <div class="trend">Junior & Senior</div>
        </div>
        
        <div class="stat-card">
            <h4>High Risk</h4>
            <div class="value"><?php echo $risk_data['high']['count'] ?? 0; ?></div>
            <div class="trend negative">Need Intervention</div>
        </div>
    </div>
</div>

<div class="row">
    <div class="card">
        <div class="card-header">
            <h3>System Actions</h3>
            <div>
                <a href="?generate_warnings" class="btn btn-warning">Generate Early Warnings</a>
                <a href="reports.php" class="btn btn-info">View Reports</a>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <p>Generate early warning alerts for all students based on current performance data.</p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Risk Distribution -->
    <div class="card" style="flex: 2;">
        <div class="card-header">
            <h3>Risk Level Distribution (Current Term)</h3>
        </div>
        <div class="chart-container">
            <canvas id="riskChart"></canvas>
        </div>
        <div style="display: flex; justify-content: space-around; margin-top: 20px;">
            <div style="text-align: center;">
                <div class="badge badge-low">Low Risk</div>
                <div style="font-size: 24px; font-weight: bold;"><?php echo $risk_data['low']['count'] ?? 0; ?></div>
                <div style="color: #666; font-size: 14px;"><?php echo $risk_data['low']['percentage'] ?? 0; ?>%</div>
            </div>
            <div style="text-align: center;">
                <div class="badge badge-medium">Medium Risk</div>
                <div style="font-size: 24px; font-weight: bold;"><?php echo $risk_data['medium']['count'] ?? 0; ?></div>
                <div style="color: #666; font-size: 14px;"><?php echo $risk_data['medium']['percentage'] ?? 0; ?>%</div>
            </div>
            <div style="text-align: center;">
                <div class="badge badge-high">High Risk</div>
                <div style="font-size: 24px; font-weight: bold;"><?php echo $risk_data['high']['count'] ?? 0; ?></div>
                <div style="color: #666; font-size: 14px;"><?php echo $risk_data['high']['percentage'] ?? 0; ?>%</div>
            </div>
        </div>
    </div>

    <!-- Recent Interventions -->
    <div class="card" style="flex: 1;">
        <div class="card-header">
            <h3>Recent Interventions</h3>
            <a href="interventions.php" class="btn btn-sm btn-primary">View All</a>
        </div>
        <div style="max-height: 400px; overflow-y: auto;">
            <?php if ($recent_interventions->num_rows > 0): ?>
                <?php while ($intervention = $recent_interventions->fetch_assoc()): ?>
                    <div style="padding: 15px; border-bottom: 1px solid #eee;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <strong><?php echo htmlspecialchars($intervention['student_name']); ?></strong>
                            <span class="badge badge-<?php echo $intervention['status']; ?>">
                                <?php echo ucfirst($intervention['status']); ?>
                            </span>
                        </div>
                        <div style="color: #666; font-size: 12px; margin-bottom: 5px;">
                            <?php echo htmlspecialchars($intervention['class_name']); ?> • 
                            <?php echo htmlspecialchars($intervention['admission_no']); ?>
                        </div>
                        <div style="font-size: 13px; color: #555;">
                            <?php echo substr($intervention['reason'], 0, 50); ?>...
                        </div>
                        <div style="font-size: 11px; color: #999; margin-top: 5px;">
                            <?php echo date('M d, Y', strtotime($intervention['created_at'])); ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="padding: 20px; text-align: center; color: #666;">
                    No interventions yet
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Risk Distribution Chart
const riskCtx = document.getElementById('riskChart').getContext('2d');
const riskChart = new Chart(riskCtx, {
    type: 'doughnut',
    data: {
        labels: ['Low Risk', 'Medium Risk', 'High Risk'],
        datasets: [{
            data: [
                <?php echo $risk_data['low']['count'] ?? 0; ?>,
                <?php echo $risk_data['medium']['count'] ?? 0; ?>,
                <?php echo $risk_data['high']['count'] ?? 0; ?>
            ],
            backgroundColor: [
                '#48bb78',
                '#ed8936',
                '#f56565'
            ],
            borderWidth: 1,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '70%',
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 20,
                    usePointStyle: true
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        let label = context.label || '';
                        if (label) {
                            label += ': ';
                        }
                        label += context.parsed + ' students';
                        return label;
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