<?php
$page_title = "Manage Subjects";
require_once '../includes/header.php';
require_once '../config/db.php';

$conn = getDBConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_subject'])) {
        $subject_name = trim($_POST['subject_name']);
        $level = $_POST['level'];
        $teacher_id = !empty($_POST['teacher_id']) ? intval($_POST['teacher_id']) : null;
        $class_id = !empty($_POST['class_id']) ? intval($_POST['class_id']) : null;
        
        $stmt = $conn->prepare("INSERT INTO subjects (subject_name, level, teacher_id, class_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssii", $subject_name, $level, $teacher_id, $class_id);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Subject added successfully";
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = "Error adding subject";
            $_SESSION['message_type'] = 'error';
        }
        $stmt->close();
        
        header('Location: manage_subjects.php');
        exit();
    }
    
    if (isset($_POST['edit_subject'])) {
        $subject_id = intval($_POST['subject_id']);
        $subject_name = trim($_POST['subject_name']);
        $level = $_POST['level'];
        $teacher_id = !empty($_POST['teacher_id']) ? intval($_POST['teacher_id']) : null;
        $class_id = !empty($_POST['class_id']) ? intval($_POST['class_id']) : null;
        
        $stmt = $conn->prepare("UPDATE subjects SET subject_name = ?, level = ?, teacher_id = ?, class_id = ? WHERE subject_id = ?");
        $stmt->bind_param("ssiii", $subject_name, $level, $teacher_id, $class_id, $subject_id);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Subject updated successfully";
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = "Error updating subject";
            $_SESSION['message_type'] = 'error';
        }
        $stmt->close();
        
        header('Location: manage_subjects.php');
        exit();
    }
    
    if (isset($_POST['delete_subject'])) {
        $subject_id = intval($_POST['subject_id']);
        
        $stmt = $conn->prepare("DELETE FROM subjects WHERE subject_id = ?");
        $stmt->bind_param("i", $subject_id);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Subject deleted successfully";
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = "Error deleting subject";
            $_SESSION['message_type'] = 'error';
        }
        $stmt->close();
        
        header('Location: manage_subjects.php');
        exit();
    }
}

// Get all subjects with teacher and class info
$subjects = $conn->query("
    SELECT 
        s.*,
        t.full_name as teacher_name,
        c.class_name,
        (SELECT COUNT(*) FROM assessments WHERE subject_id = s.subject_id) as assessment_count
    FROM subjects s
    LEFT JOIN teachers t ON s.teacher_id = t.teacher_id
    LEFT JOIN classes c ON s.class_id = c.class_id
    ORDER BY s.level, s.subject_name
");

// Get teachers and classes for dropdowns
$teachers = $conn->query("SELECT * FROM teachers ORDER BY full_name");
$classes = $conn->query("SELECT * FROM classes ORDER BY level, class_name");

// Get subject for editing if requested
$edit_subject = null;
if (isset($_GET['edit'])) {
    $subject_id = intval($_GET['edit']);
    $result = $conn->query("SELECT * FROM subjects WHERE subject_id = $subject_id");
    if ($result->num_rows > 0) {
        $edit_subject = $result->fetch_assoc();
    }
}
?>

<div class="row">
    <div class="card">
        <div class="card-header">
            <h3><?php echo $edit_subject ? 'Edit Subject' : 'Add New Subject'; ?></h3>
        </div>
        <form method="POST" action="">
            <?php if ($edit_subject): ?>
                <input type="hidden" name="subject_id" value="<?php echo $edit_subject['subject_id']; ?>">
            <?php endif; ?>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Subject Name *</label>
                    <input type="text" name="subject_name" class="form-control" 
                           value="<?php echo $edit_subject ? htmlspecialchars($edit_subject['subject_name']) : ''; ?>" required>
                </div>
                <div class="form-group">
                    <label>Level *</label>
                    <select name="level" class="form-control" required>
                        <option value="junior" <?php echo ($edit_subject && $edit_subject['level'] == 'junior') ? 'selected' : ''; ?>>Junior Secondary</option>
                        <option value="senior" <?php echo ($edit_subject && $edit_subject['level'] == 'senior') ? 'selected' : ''; ?>>Senior Secondary</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Teacher</label>
                    <select name="teacher_id" class="form-control">
                        <option value="">Select Teacher</option>
                        <?php while ($teacher = $teachers->fetch_assoc()): ?>
                            <option value="<?php echo $teacher['teacher_id']; ?>" 
                                <?php echo ($edit_subject && $edit_subject['teacher_id'] == $teacher['teacher_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($teacher['full_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Class</label>
                    <select name="class_id" class="form-control">
                        <option value="">Select Class (Optional)</option>
                        <?php while ($class = $classes->fetch_assoc()): ?>
                            <option value="<?php echo $class['class_id']; ?>" 
                                <?php echo ($edit_subject && $edit_subject['class_id'] == $class['class_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['class_name'] . ' (' . ucfirst($class['level']) . ')'); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
            
            <button type="submit" name="<?php echo $edit_subject ? 'edit_subject' : 'add_subject'; ?>" class="btn btn-primary">
                <?php echo $edit_subject ? 'Update Subject' : 'Add Subject'; ?>
            </button>
            <?php if ($edit_subject): ?>
                <a href="manage_subjects.php" class="btn btn-secondary">Cancel</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="row">
    <div class="card">
        <div class="card-header">
            <h3>Subject List</h3>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Subject Name</th>
                        <th>Level</th>
                        <th>Teacher</th>
                        <th>Class</th>
                        <th>Assessments</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($subject = $subjects->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $subject['subject_id']; ?></td>
                            <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                            <td>
                                <span class="badge <?php echo $subject['level'] == 'junior' ? 'badge-info' : 'badge-warning'; ?>">
                                    <?php echo ucfirst($subject['level']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($subject['teacher_name'] ?? 'Unassigned'); ?></td>
                            <td><?php echo htmlspecialchars($subject['class_name'] ?? 'All Classes'); ?></td>
                            <td><?php echo $subject['assessment_count']; ?></td>
                            <td class="actions">
                                <a href="?edit=<?php echo $subject['subject_id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                                <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this subject?');">
                                    <input type="hidden" name="subject_id" value="<?php echo $subject['subject_id']; ?>">
                                    <button type="submit" name="delete_subject" class="btn btn-danger btn-sm">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    <?php if ($subjects->num_rows == 0): ?>
                        <tr>
                            <td colspan="7" class="text-center">No subjects found</td>
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