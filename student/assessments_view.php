<?php
$page_title = "My Assessments";
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
$term = isset($_GET['term']) ? intval($_GET['term']) : '';
$academic_year = isset($_GET['academic_year']) ? intval($_GET['academic_year']) : '';
$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : '';
$type = isset($_GET['type']) ? $_GET['type'] : '';

// Get available years and terms
$years = $conn->query("
    SELECT DISTINCT academic_year as year 
    FROM assessments 
    WHERE student_id = $student_id 
    ORDER BY year DESC
");

// Get student's subjects
$subjects = $conn->query("
    SELECT DISTINCT sub.* 
    FROM assessments a
    JOIN subjects sub ON a.subject_id = sub.subject_id
    WHERE a.student_id = $student_id
    ORDER BY sub.subject_name
");

// Build filter conditions
$where_conditions = ["a.student_id = $student_id"];
if ($term) {
    $where_conditions[] = "a.term = $term";
}
if ($academic_year) {
    $where_conditions[] = "a.academic_year = $academic_year";
}
if ($subject_id) {
    $where_conditions[] = "a.subject_id = $subject_id";
}
if ($type && in_array($type, ['assignment', 'test', 'exam'])) {
    $where_conditions[] = "a.type = '$type'";
}

$where_clause = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get assessments
$assessments = $conn->query("
    SELECT 
        a.*,
        sub.subject_name
    FROM assessments a
    JOIN subjects sub ON a.subject_id = sub.subject_id
    $where_clause
    ORDER BY a.academic_year DESC, a.term DESC, a.type, a.created_at DESC
");

// Get assessment summary
$summary = $conn->query("
    SELECT 
        COUNT(*) as total_assessments,
        AVG((score / max_score) * 100) as overall_average,
        MIN((score / max_score) * 100) as lowest_score,
        MAX((score / max_score) * 100) as highest_score
    FROM assessments 
    WHERE student_id = $student_id
")->fetch_assoc();

// Get subject averages
$subject_averages = $conn->query("
    SELECT 
        sub.subject_name,
        COUNT(*) as count,
        AVG((a.score / a.max_score) * 100) as average_score
    FROM assessments a
    JOIN subjects sub ON a.subject_id = sub.subject_id
    WHERE a.student_id = $student_id
    GROUP BY sub.subject_id
    ORDER BY average_score DESC
");
?>

<div class="row">
    <div class="card">
        <div class="card-header">
            <h3>Assessment Filters</h3>
        </div>
        <form method="GET" action="" class="form-row">
            <div class="form-group">
                <label>Academic Year</label>
                <select name="academic_year" class="form-control" onchange="this.form.submit()">
                    <option value="">All Years</option>
                    <?php while ($year = $years->fetch_assoc()): ?>
                        <option value="<?php echo $year['year']; ?>" 
                            <?php echo $academic_year == $year['year'] ? 'selected' : ''; ?>>
                            <?php echo $year['year']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Term</label>
                <select name="term" class="form-control" onchange="this.form.submit()">
                    <option value="">All Terms</option>
                    <option value="1" <?php echo $term == 1 ? 'selected' : ''; ?>>Term 1</option>
                    <option value="2" <?php echo $term == 2 ? 'selected' : ''; ?>>Term 2</option>
                    <option value="3" <?php echo $term == 3 ? 'selected' : ''; ?>>Term 3</option>
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
            <div class="form-group">
                <label>Type</label>
                <select name="type" class="form-control" onchange="this.form.submit()">
                    <option value="">All Types</option>
                    <option value="assignment" <?php echo $type == 'assignment' ? 'selected' : ''; ?>>Assignment</option>
                    <option value="test" <?php echo $type == 'test' ? 'selected' : ''; ?>>Test</option>
                    <option value="exam" <?php echo $type == 'exam' ? 'selected' : ''; ?>>Exam</option>
                </select>
            </div>
        </form>
    </div>
</div>

<div class="row">
    <!-- Assessment Summary -->
    <div class="card">
        <div class="card-header">
            <h3>Assessment Summary</h3>
        </div>
        <div class="stats-grid">
            <div class="stat-card">
                <h4>Total Assessments</h4>
                <div class="value"><?php echo $summary['total_assessments'] ?? 0; ?></div>
                <div class="trend">Recorded</div>
            </div>
            
            <div class="stat-card">
                <h4>Overall Average</h4>
                <div class="value"><?php echo number_format($summary['overall_average'] ?? 0, 1); ?>%</div>
                <div class="trend <?php echo ($summary['overall_average'] ?? 0) < 50 ? 'negative' : ''; ?>">
                    <?php echo ($summary['overall_average'] ?? 0) < 50 ? 'Needs Improvement' : 'Good'; ?>
                </div>
            </div>
            
            <div class="stat-card">
                <h4>Highest Score</h4>
                <div class="value"><?php echo number_format($summary['highest_score'] ?? 0, 1); ?>%</div>
                <div class="trend">Best Performance</div>
            </div>
            
            <div class="stat-card">
                <h4>Lowest Score</h4>
                <div class="value"><?php echo number_format($summary['lowest_score'] ?? 0, 1); ?>%</div>
                <div class="trend negative">Needs Attention</div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Assessment List -->
    <div class="card" style="flex: 2;">
        <div class="card-header">
            <h3>Assessment Records</h3>
            <div>
                Showing <?php echo $assessments->num_rows; ?> assessments
                <?php 
                if ($academic_year) echo " • Year: $academic_year";
                if ($term) echo " • Term: $term";
                if ($subject_id) {
                    $subject_name = $conn->query("SELECT subject_name FROM subjects WHERE subject_id = $subject_id")->fetch_assoc()['subject_name'];
                    echo " • Subject: " . htmlspecialchars($subject_name);
                }
                if ($type) echo " • Type: " . ucfirst($type);
                ?>
            </div>
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
                        <th>Performance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($assessment = $assessments->fetch_assoc()): 
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
                            <td>
                                <div style="height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden; width: 100px;">
                                    <div style="height: 100%; width: <?php echo min(100, $percentage); ?>%; 
                                              background: <?php 
                                                  echo $percentage >= 70 ? '#48bb78' : 
                                                        ($percentage >= 50 ? '#ed8936' : '#f56565'); 
                                              ?>;">
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    <?php if ($assessments->num_rows == 0): ?>
                        <tr>
                            <td colspan="7" class="text-center">No assessments found with the selected criteria</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Subject Averages -->
    <div class="card" style="flex: 1;">
        <div class="card-header">
            <h3>Subject Performance</h3>
        </div>
        <div style="max-height: 400px; overflow-y: auto;">
            <?php if ($subject_averages->num_rows > 0): ?>
                <?php while ($subject = $subject_averages->fetch_assoc()): ?>
                    <div style="padding: 15px; border-bottom: 1px solid #eee;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                            <strong><?php echo htmlspecialchars($subject['subject_name']); ?></strong>
                            <span class="badge <?php 
                                echo $subject['average_score'] >= 70 ? 'badge-low' : 
                                      ($subject['average_score'] >= 50 ? 'badge-medium' : 'badge-high');
                            ?>">
                                <?php echo number_format($subject['average_score'], 1); ?>%
                            </span>
                        </div>
                        <div style="color: #666; font-size: 12px; margin-bottom: 5px;">
                            <?php echo $subject['count']; ?> assessments
                        </div>
                        <div style="margin-top: 8px; height: 6px; background: #e2e8f0; border-radius: 3px; overflow: hidden;">
                            <div style="height: 100%; width: <?php echo min(100, $subject['average_score']); ?>%; 
                                      background: <?php 
                                          echo $subject['average_score'] >= 70 ? '#48bb78' : 
                                                ($subject['average_score'] >= 50 ? '#ed8936' : '#f56565'); 
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

<?php 
$conn->close();
require_once '../includes/footer.php'; 
?>