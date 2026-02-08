<?php
$page_title = "Assessment Management";
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
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_assessment'])) {
        $subject_id = intval($_POST['subject_id']);
        $student_id = intval($_POST['student_id']);
        $type = $_POST['type'];
        $score = floatval($_POST['score']);
        $max_score = floatval($_POST['max_score']);
        $term = intval($_POST['term']);
        $academic_year = intval($_POST['academic_year']);
        
        // Check if assessment already exists for this student, subject, type, term, and year
        $check = $conn->prepare("
            SELECT assessment_id FROM assessments 
            WHERE student_id = ? 
            AND subject_id = ? 
            AND type = ? 
            AND term = ? 
            AND academic_year = ?
        ");
        $check->bind_param("iisii", $student_id, $subject_id, $type, $term, $academic_year);
        $check->execute();
        $check->store_result();
        
        if ($check->num_rows > 0) {
            $_SESSION['message'] = "Assessment already exists for this student in the selected term";
            $_SESSION['message_type'] = 'error';
        } else {
            $stmt = $conn->prepare("
                INSERT INTO assessments (student_id, subject_id, type, score, max_score, term, academic_year) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("iisddii", $student_id, $subject_id, $type, $score, $max_score, $term, $academic_year);
            
            if ($stmt->execute()) {
                // Trigger performance recalculation
                require_once '../includes/prediction_logic.php';
                calculatePerformanceScore($student_id, $term, $academic_year);
                
                $_SESSION['message'] = "Assessment added successfully";
                $_SESSION['message_type'] = 'success';
            } else {
                $_SESSION['message'] = "Error adding assessment";
                $_SESSION['message_type'] = 'error';
            }
            $stmt->close();
        }
        $check->close();
        
        header("Location: assessments.php?subject_id=$subject_id&student_id=$student_id");
        exit();
    }
    
    if (isset($_POST['update_assessment'])) {
        $assessment_id = intval($_POST['assessment_id']);
        $score = floatval($_POST['score']);
        $max_score = floatval($_POST['max_score']);
        
        // Get student_id and term/year for recalculation
        $assessment_info = $conn->query("
            SELECT student_id, term, academic_year 
            FROM assessments 
            WHERE assessment_id = $assessment_id
        ")->fetch_assoc();
        
        $stmt = $conn->prepare("UPDATE assessments SET score = ?, max_score = ? WHERE assessment_id = ?");
        $stmt->bind_param("ddi", $score, $max_score, $assessment_id);
        
        if ($stmt->execute()) {
            // Trigger performance recalculation
            require_once '../includes/prediction_logic.php';
            calculatePerformanceScore($assessment_info['student_id'], $assessment_info['term'], $assessment_info['academic_year']);
            
            $_SESSION['message'] = "Assessment updated successfully";
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = "Error updating assessment";
            $_SESSION['message_type'] = 'error';
        }
        $stmt->close();
        
        header("Location: assessments.php?subject_id=$subject_id");
        exit();
    }
    
    if (isset($_POST['delete_assessment'])) {
        $assessment_id = intval($_POST['assessment_id']);
        
        // Get student_id and term/year for recalculation
        $assessment_info = $conn->query("
            SELECT student_id, term, academic_year 
            FROM assessments 
            WHERE assessment_id = $assessment_id
        ")->fetch_assoc();
        
        $stmt = $conn->prepare("DELETE FROM assessments WHERE assessment_id = ?");
        $stmt->bind_param("i", $assessment_id);
        
        if ($stmt->execute()) {
            // Trigger performance recalculation
            require_once '../includes/prediction_logic.php';
            calculatePerformanceScore($assessment_info['student_id'], $assessment_info['term'], $assessment_info['academic_year']);
            
            $_SESSION['message'] = "Assessment deleted successfully";
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = "Error deleting assessment";
            $_SESSION['message_type'] = 'error';
        }
        $stmt->close();
        
        header("Location: assessments.php?subject_id=$subject_id");
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

// Get assessments for selected subject and student
$assessments = [];
if ($subject_id) {
    $where_clause = "WHERE a.subject_id = $subject_id";
    if ($student_id) {
        $where_clause .= " AND a.student_id = $student_id";
    }
    
    $assessments = $conn->query("
        SELECT 
            a.*,
            s.full_name,
            s.admission_no
        FROM assessments a
        JOIN students s ON a.student_id = s.student_id
        $where_clause
        ORDER BY a.academic_year DESC, a.term DESC, a.type, s.full_name
    ");
}

// Get current term and year
$current_month = date('m');
$current_term = ceil($current_month / 4);
$current_year = date('Y');
?>

<div class="row">
    <div class="card">
        <div class="card-header">
            <h3>Select Subject and Student</h3>
        </div>
        <form method="GET" action="" class="form-row">
            <div class="form-group">
                <label>Subject</label>
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
            <?php if ($subject_id): ?>
            <div class="form-group">
                <label>Student (Optional)</label>
                <select name="student_id" class="form-control" onchange="this.form.submit()">
                    <option value="">All Students</option>
                    <?php while ($student = $students->fetch_assoc()): ?>
                        <option value="<?php echo $student['student_id']; ?>" 
                            <?php echo $student_id == $student['student_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($student['full_name'] . ' (' . $student['admission_no'] . ')'); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php if ($subject_id): ?>
<!-- Add Assessment Form -->
<div class="row">
    <div class="card">
        <div class="card-header">
            <h3>Add New Assessment</h3>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="subject_id" value="<?php echo $subject_id; ?>">
            
            <div class="form-row">
                <div class="form-group">
                    <label>Student *</label>
                    <select name="student_id" class="form-control" required>
                        <option value="">Select Student</option>
                        <?php 
                        $students->data_seek(0); // Reset pointer
                        while ($student = $students->fetch_assoc()): ?>
                            <option value="<?php echo $student['student_id']; ?>">
                                <?php echo htmlspecialchars($student['full_name'] . ' (' . $student['admission_no'] . ')'); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Assessment Type *</label>
                    <select name="type" class="form-control" required>
                        <option value="assignment">Assignment</option>
                        <option value="test">Test</option>
                        <option value="exam">Exam</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Score *</label>
                    <input type="number" name="score" class="form-control" step="0.01" min="0" required>
                </div>
                <div class="form-group">
                    <label>Maximum Score *</label>
                    <input type="number" name="max_score" class="form-control" step="0.01" min="0" value="100" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Term *</label>
                    <select name="term" class="form-control" required>
                        <option value="1" <?php echo $current_term == 1 ? 'selected' : ''; ?>>Term 1</option>
                        <option value="2" <?php echo $current_term == 2 ? 'selected' : ''; ?>>Term 2</option>
                        <option value="3" <?php echo $current_term == 3 ? 'selected' : ''; ?>>Term 3</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Academic Year *</label>
                    <input type="number" name="academic_year" class="form-control" 
                           value="<?php echo $current_year; ?>" min="2000" max="2030" required>
                </div>
            </div>
            
            <button type="submit" name="add_assessment" class="btn btn-primary">Add Assessment</button>
        </form>
    </div>
</div>

<!-- Assessment List -->
<div class="row">
    <div class="card">
        <div class="card-header">
            <h3>Assessment Records</h3>
            <div>
                <?php 
                $subject_info = $conn->query("
                    SELECT s.subject_name, c.class_name 
                    FROM subjects s 
                    LEFT JOIN classes c ON s.class_id = c.class_id 
                    WHERE s.subject_id = $subject_id
                ")->fetch_assoc();
                echo htmlspecialchars($subject_info['subject_name'] . ' - ' . $subject_info['class_name']);
                ?>
            </div>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Type</th>
                        <th>Score</th>
                        <th>Percentage</th>
                        <th>Term/Year</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($assessment = $assessments->fetch_assoc()): 
                        $percentage = ($assessment['score'] / $assessment['max_score']) * 100;
                    ?>
                        <tr>
                            <td>
                                <?php echo htmlspecialchars($assessment['full_name']); ?>
                                <br><small><?php echo htmlspecialchars($assessment['admission_no']); ?></small>
                            </td>
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
                            <td class="actions">
                                <button type="button" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($assessment)); ?>)" 
                                        class="btn btn-warning btn-sm">Edit</button>
                                <form method="POST" action="" style="display: inline;" 
                                      onsubmit="return confirm('Are you sure you want to delete this assessment?');">
                                    <input type="hidden" name="assessment_id" value="<?php echo $assessment['assessment_id']; ?>">
                                    <button type="submit" name="delete_assessment" class="btn btn-danger btn-sm">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    <?php if ($assessments->num_rows == 0): ?>
                        <tr>
                            <td colspan="7" class="text-center">No assessments found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Assessment</h3>
            <button type="button" class="modal-close" onclick="closeEditModal()">&times;</button>
        </div>
        <form method="POST" action="" id="editForm">
            <input type="hidden" name="assessment_id" id="edit_assessment_id">
            
            <div class="form-group">
                <label>Score *</label>
                <input type="number" name="score" class="form-control" step="0.01" min="0" required id="edit_score">
            </div>
            
            <div class="form-group">
                <label>Maximum Score *</label>
                <input type="number" name="max_score" class="form-control" step="0.01" min="0" required id="edit_max_score">
            </div>
            
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" name="update_assessment" class="btn btn-primary">Update Assessment</button>
                <button type="button" onclick="closeEditModal()" class="btn btn-secondary">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(assessment) {
    document.getElementById('edit_assessment_id').value = assessment.assessment_id;
    document.getElementById('edit_score').value = assessment.score;
    document.getElementById('edit_max_score').value = assessment.max_score;
    
    document.getElementById('editModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('editModal');
    if (event.target == modal) {
        closeEditModal();
    }
}
</script>
<?php else: ?>
<div class="row">
    <div class="card">
        <div class="alert alert-info">
            Please select a subject to manage assessments.
        </div>
    </div>
</div>
<?php endif; ?>

<?php 
$conn->close();
require_once '../includes/footer.php'; 
?>