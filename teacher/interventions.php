<?php
$page_title = "Intervention Management";
require_once '../includes/header.php';
require_once '../config/db.php';

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$teacher_id = $conn->query("SELECT teacher_id FROM teachers WHERE user_id = $user_id")->fetch_assoc()['teacher_id'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_intervention'])) {
        $student_id = intval($_POST['student_id']);
        $reason = trim($_POST['reason']);
        $action_taken = trim($_POST['action_taken']);
        $status = $_POST['status'];
        
        $stmt = $conn->prepare("
            INSERT INTO interventions (student_id, teacher_id, reason, action_taken, status) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iisss", $student_id, $teacher_id, $reason, $action_taken, $status);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Intervention added successfully";
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = "Error adding intervention";
            $_SESSION['message_type'] = 'error';
        }
        $stmt->close();
        
        header('Location: interventions.php');
        exit();
    }
    
    if (isset($_POST['update_intervention'])) {
        $intervention_id = intval($_POST['intervention_id']);
        $action_taken = trim($_POST['action_taken']);
        $status = $_POST['status'];
        $notes = trim($_POST['notes'] ?? '');
        
        $stmt = $conn->prepare("
            UPDATE interventions 
            SET action_taken = ?, status = ?, notes = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE intervention_id = ? AND teacher_id = ?
        ");
        $stmt->bind_param("sssii", $action_taken, $status, $notes, $intervention_id, $teacher_id);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Intervention updated successfully";
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = "Error updating intervention";
            $_SESSION['message_type'] = 'error';
        }
        $stmt->close();
        
        header('Location: interventions.php');
        exit();
    }
}

// Get filter parameters
$status = isset($_GET['status']) ? $_GET['status'] : '';
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;

// Build query with filters
$where_conditions = ["i.teacher_id = $teacher_id"];
$params = [];
$types = '';

if ($status && in_array($status, ['pending', 'ongoing', 'completed', 'resolved'])) {
    $where_conditions[] = "i.status = ?";
    $params[] = $status;
    $types .= 's';
}

if ($student_id > 0) {
    $where_conditions[] = "i.student_id = ?";
    $params[] = $student_id;
    $types .= 'i';
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Get interventions
$sql = "
    SELECT 
        i.*,
        s.full_name as student_name,
        s.admission_no,
        c.class_name,
        ps.overall_score,
        ps.risk_level
    FROM interventions i
    JOIN students s ON i.student_id = s.student_id
    LEFT JOIN classes c ON s.class_id = c.class_id
    LEFT JOIN performance_summary ps ON s.student_id = ps.student_id 
        AND ps.term = CEIL(MONTH(CURRENT_DATE()) / 4)
        AND ps.academic_year = YEAR(CURRENT_DATE())
    $where_clause
    ORDER BY 
        CASE i.status 
            WHEN 'pending' THEN 1
            WHEN 'ongoing' THEN 2
            WHEN 'completed' THEN 3
            WHEN 'resolved' THEN 4
        END,
        i.created_at DESC
";

if ($types) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $interventions = $stmt->get_result();
} else {
    $interventions = $conn->query($sql);
}

// Get teacher's students for dropdowns
$teacher_students = $conn->query("
    SELECT DISTINCT s.* 
    FROM students s
    JOIN subjects sub ON s.class_id = sub.class_id
    WHERE sub.teacher_id = $teacher_id
    ORDER BY s.full_name
");
?>

<div class="row">
    <!-- Add Intervention Form -->
    <div class="card">
        <div class="card-header">
            <h3>Add New Intervention</h3>
        </div>
        <form method="POST" action="">
            <div class="form-row">
                <div class="form-group">
                    <label>Student *</label>
                    <select name="student_id" class="form-control" required>
                        <option value="">Select Student</option>
                        <?php while ($student = $teacher_students->fetch_assoc()): 
                            $student_perf = $conn->query("
                                SELECT risk_level FROM performance_summary 
                                WHERE student_id = {$student['student_id']}
                                ORDER BY generated_on DESC LIMIT 1
                            ")->fetch_assoc();
                        ?>
                            <option value="<?php echo $student['student_id']; ?>">
                                <?php echo htmlspecialchars($student['full_name'] . ' (' . $student['admission_no'] . ')'); ?>
                                <?php if ($student_perf && $student_perf['risk_level']): ?>
                                    - <?php echo ucfirst($student_perf['risk_level']); ?> Risk
                                <?php endif; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Initial Status *</label>
                    <select name="status" class="form-control" required>
                        <option value="pending">Pending</option>
                        <option value="ongoing">Ongoing</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label>Reason for Intervention *</label>
                <textarea name="reason" class="form-control" rows="3" required 
                          placeholder="Describe why this intervention is needed..."></textarea>
            </div>
            
            <div class="form-group">
                <label>Initial Action Taken *</label>
                <textarea name="action_taken" class="form-control" rows="3" required 
                          placeholder="Describe what action has been taken or will be taken..."></textarea>
            </div>
            
            <button type="submit" name="add_intervention" class="btn btn-primary">Add Intervention</button>
        </form>
    </div>
</div>

<div class="row">
    <!-- Filter Options -->
    <div class="card">
        <div class="card-header">
            <h3>Filter Interventions</h3>
        </div>
        <form method="GET" action="" class="form-row">
            <div class="form-group">
                <label>Status</label>
                <select name="status" class="form-control">
                    <option value="">All Status</option>
                    <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="ongoing" <?php echo $status == 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                    <option value="completed" <?php echo $status == 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="resolved" <?php echo $status == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                </select>
            </div>
            <div class="form-group">
                <label>Student</label>
                <select name="student_id" class="form-control">
                    <option value="0">All Students</option>
                    <?php 
                    $teacher_students->data_seek(0); // Reset pointer
                    while ($student = $teacher_students->fetch_assoc()): ?>
                        <option value="<?php echo $student['student_id']; ?>" 
                            <?php echo $student_id == $student['student_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($student['full_name'] . ' (' . $student['admission_no'] . ')'); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group" style="align-self: flex-end;">
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="interventions.php" class="btn btn-secondary">Clear</a>
            </div>
        </form>
    </div>
</div>

<div class="row">
    <div class="card">
        <div class="card-header">
            <h3>My Interventions</h3>
            <div>
                <?php 
                $total = $interventions->num_rows;
                echo "Showing $total interventions";
                if ($status) echo " - " . ucfirst($status);
                ?>
            </div>
        </div>
        <div class="table-container">
            <?php if ($interventions->num_rows > 0): ?>
                <?php while ($intervention = $interventions->fetch_assoc()): ?>
                    <div class="card" style="margin-bottom: 15px;">
                        <div style="padding: 15px;">
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                                <div>
                                    <h4 style="margin: 0 0 5px 0;">
                                        <?php echo htmlspecialchars($intervention['student_name']); ?>
                                        <small style="color: #666;">(<?php echo htmlspecialchars($intervention['admission_no']); ?>)</small>
                                    </h4>
                                    <div style="color: #666; font-size: 14px;">
                                        <?php echo htmlspecialchars($intervention['class_name']); ?>
                                        <?php if ($intervention['overall_score']): ?>
                                            • Overall Score: <?php echo number_format($intervention['overall_score'], 1); ?>%
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <span class="badge badge-<?php echo $intervention['status']; ?>" style="margin-bottom: 5px;">
                                        <?php echo ucfirst($intervention['status']); ?>
                                    </span>
                                    <?php if ($intervention['risk_level']): ?>
                                        <div style="margin-top: 5px;">
                                            <span class="badge badge-<?php echo $intervention['risk_level']; ?>">
                                                <?php echo ucfirst($intervention['risk_level']); ?> Risk
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div style="margin-bottom: 15px;">
                                <strong>Reason:</strong>
                                <p style="margin: 5px 0; color: #555; padding: 10px; background: #f8f9fa; border-radius: 5px;">
                                    <?php echo nl2br(htmlspecialchars($intervention['reason'])); ?>
                                </p>
                            </div>
                            
                            <div style="margin-bottom: 15px;">
                                <strong>Action Taken:</strong>
                                <p style="margin: 5px 0; color: #555; padding: 10px; background: #f8f9fa; border-radius: 5px;">
                                    <?php echo nl2br(htmlspecialchars($intervention['action_taken'])); ?>
                                </p>
                            </div>
                            
                            <?php if (!empty($intervention['notes'])): ?>
                                <div style="margin-bottom: 15px;">
                                    <strong>Notes:</strong>
                                    <p style="margin: 5px 0; color: #555; padding: 10px; background: #f8f9fa; border-radius: 5px;">
                                        <?php echo nl2br(htmlspecialchars($intervention['notes'])); ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                            
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">
                                <div style="font-size: 12px; color: #999;">
                                    Created: <?php echo date('M d, Y', strtotime($intervention['created_at'])); ?>
                                    <?php if ($intervention['updated_at'] != $intervention['created_at']): ?>
                                        <br>Updated: <?php echo date('M d, Y H:i', strtotime($intervention['updated_at'])); ?>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <button type="button" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($intervention)); ?>)" 
                                            class="btn btn-warning btn-sm">Edit</button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="padding: 40px; text-align: center; color: #666;">
                    <p>No interventions found</p>
                    <p>Create an intervention using the form above or interventions will be created automatically for high-risk students.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Intervention</h3>
            <button type="button" class="modal-close" onclick="closeEditModal()">&times;</button>
        </div>
        <form method="POST" action="" id="editForm">
            <input type="hidden" name="intervention_id" id="edit_intervention_id">
            
            <div class="form-group">
                <label>Status *</label>
                <select name="status" class="form-control" required id="edit_status">
                    <option value="pending">Pending</option>
                    <option value="ongoing">Ongoing</option>
                    <option value="completed">Completed</option>
                    <option value="resolved">Resolved</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Action Taken *</label>
                <textarea name="action_taken" class="form-control" rows="4" required id="edit_action_taken"></textarea>
            </div>
            
            <div class="form-group">
                <label>Additional Notes</label>
                <textarea name="notes" class="form-control" rows="3" id="edit_notes" 
                          placeholder="Add any additional notes or observations..."></textarea>
            </div>
            
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" name="update_intervention" class="btn btn-primary">Update Intervention</button>
                <button type="button" onclick="closeEditModal()" class="btn btn-secondary">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(intervention) {
    document.getElementById('edit_intervention_id').value = intervention.intervention_id;
    document.getElementById('edit_status').value = intervention.status;
    document.getElementById('edit_action_taken').value = intervention.action_taken;
    document.getElementById('edit_notes').value = intervention.notes || '';
    
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

<?php 
if (isset($stmt)) $stmt->close();
$conn->close();
require_once '../includes/footer.php'; 
?>