<?php
$page_title = "Manage Classes";
require_once '../includes/header.php';
require_once '../config/db.php';

$conn = getDBConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_class'])) {
        $class_name = trim($_POST['class_name']);
        $level = $_POST['level'];
        
        $stmt = $conn->prepare("INSERT INTO classes (class_name, level) VALUES (?, ?)");
        $stmt->bind_param("ss", $class_name, $level);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Class added successfully";
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = "Error adding class";
            $_SESSION['message_type'] = 'error';
        }
        $stmt->close();
        
        header('Location: manage_classes.php');
        exit();
    }
    
    if (isset($_POST['edit_class'])) {
        $class_id = intval($_POST['class_id']);
        $class_name = trim($_POST['class_name']);
        $level = $_POST['level'];
        
        $stmt = $conn->prepare("UPDATE classes SET class_name = ?, level = ? WHERE class_id = ?");
        $stmt->bind_param("ssi", $class_name, $level, $class_id);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Class updated successfully";
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = "Error updating class";
            $_SESSION['message_type'] = 'error';
        }
        $stmt->close();
        
        header('Location: manage_classes.php');
        exit();
    }
    
    if (isset($_POST['delete_class'])) {
        $class_id = intval($_POST['class_id']);
        
        // Check if class has students
        $check = $conn->query("SELECT COUNT(*) as count FROM students WHERE class_id = $class_id");
        $has_students = $check->fetch_assoc()['count'] > 0;
        
        if ($has_students) {
            $_SESSION['message'] = "Cannot delete class with students. Please reassign students first.";
            $_SESSION['message_type'] = 'error';
        } else {
            $stmt = $conn->prepare("DELETE FROM classes WHERE class_id = ?");
            $stmt->bind_param("i", $class_id);
            
            if ($stmt->execute()) {
                $_SESSION['message'] = "Class deleted successfully";
                $_SESSION['message_type'] = 'success';
            } else {
                $_SESSION['message'] = "Error deleting class";
                $_SESSION['message_type'] = 'error';
            }
            $stmt->close();
        }
        
        header('Location: manage_classes.php');
        exit();
    }
}

// Get all classes with student count
$classes = $conn->query("
    SELECT 
        c.*,
        COUNT(s.student_id) as student_count
    FROM classes c
    LEFT JOIN students s ON c.class_id = s.class_id
    GROUP BY c.class_id
    ORDER BY c.level, c.class_name
");

// Get class for editing if requested
$edit_class = null;
if (isset($_GET['edit'])) {
    $class_id = intval($_GET['edit']);
    $result = $conn->query("SELECT * FROM classes WHERE class_id = $class_id");
    if ($result->num_rows > 0) {
        $edit_class = $result->fetch_assoc();
    }
}
?>

<div class="row">
    <div class="card">
        <div class="card-header">
            <h3><?php echo $edit_class ? 'Edit Class' : 'Add New Class'; ?></h3>
        </div>
        <form method="POST" action="">
            <?php if ($edit_class): ?>
                <input type="hidden" name="class_id" value="<?php echo $edit_class['class_id']; ?>">
            <?php endif; ?>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Class Name *</label>
                    <input type="text" name="class_name" class="form-control" 
                           value="<?php echo $edit_class ? htmlspecialchars($edit_class['class_name']) : ''; ?>" required>
                </div>
                <div class="form-group">
                    <label>Level *</label>
                    <select name="level" class="form-control" required>
                        <option value="junior" <?php echo ($edit_class && $edit_class['level'] == 'junior') ? 'selected' : ''; ?>>Junior Secondary</option>
                        <option value="senior" <?php echo ($edit_class && $edit_class['level'] == 'senior') ? 'selected' : ''; ?>>Senior Secondary</option>
                    </select>
                </div>
            </div>
            
            <button type="submit" name="<?php echo $edit_class ? 'edit_class' : 'add_class'; ?>" class="btn btn-primary">
                <?php echo $edit_class ? 'Update Class' : 'Add Class'; ?>
            </button>
            <?php if ($edit_class): ?>
                <a href="manage_classes.php" class="btn btn-secondary">Cancel</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="row">
    <div class="card">
        <div class="card-header">
            <h3>Class List</h3>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Class Name</th>
                        <th>Level</th>
                        <th>Students</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($class = $classes->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $class['class_id']; ?></td>
                            <td><?php echo htmlspecialchars($class['class_name']); ?></td>
                            <td>
                                <span class="badge <?php echo $class['level'] == 'junior' ? 'badge-info' : 'badge-warning'; ?>">
                                    <?php echo ucfirst($class['level']); ?>
                                </span>
                            </td>
                            <td><?php echo $class['student_count']; ?> students</td>
                            <td><?php echo date('M d, Y', strtotime($class['created_at'])); ?></td>
                            <td class="actions">
                                <a href="?edit=<?php echo $class['class_id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                                <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this class?');">
                                    <input type="hidden" name="class_id" value="<?php echo $class['class_id']; ?>">
                                    <button type="submit" name="delete_class" class="btn btn-danger btn-sm">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    <?php if ($classes->num_rows == 0): ?>
                        <tr>
                            <td colspan="6" class="text-center">No classes found</td>
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