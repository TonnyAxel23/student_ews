<?php
$page_title = "Manage Users";
require_once '../includes/header.php';
require_once '../config/db.php';

$conn = getDBConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_user'])) {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $role = $_POST['role'];
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        
        // Check if username exists
        $check = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
        $check->bind_param("s", $username);
        $check->execute();
        $check->store_result();
        
        if ($check->num_rows > 0) {
            $_SESSION['message'] = "Username already exists";
            $_SESSION['message_type'] = 'error';
        } else {
            // Insert user
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $username, $password_hash, $role);
            
            if ($stmt->execute()) {
                $user_id = $stmt->insert_id;
                
                // Insert role-specific data
                if ($role == 'student') {
                    $admission_no = trim($_POST['admission_no']);
                    $gender = $_POST['gender'];
                    $level = $_POST['level'];
                    $class_id = $_POST['class_id'];
                    
                    $stmt2 = $conn->prepare("INSERT INTO students (admission_no, user_id, full_name, gender, level, class_id) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt2->bind_param("sisssi", $admission_no, $user_id, $full_name, $gender, $level, $class_id);
                    $stmt2->execute();
                    $stmt2->close();
                    
                } elseif ($role == 'teacher') {
                    $stmt2 = $conn->prepare("INSERT INTO teachers (user_id, full_name, email, phone) VALUES (?, ?, ?, ?)");
                    $stmt2->bind_param("isss", $user_id, $full_name, $email, $phone);
                    $stmt2->execute();
                    $stmt2->close();
                }
                
                $_SESSION['message'] = "User added successfully";
                $_SESSION['message_type'] = 'success';
            } else {
                $_SESSION['message'] = "Error adding user";
                $_SESSION['message_type'] = 'error';
            }
            $stmt->close();
        }
        $check->close();
        
        header('Location: manage_users.php');
        exit();
    }
    
    if (isset($_POST['delete_user'])) {
        $user_id = intval($_POST['user_id']);
        
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "User deleted successfully";
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = "Error deleting user";
            $_SESSION['message_type'] = 'error';
        }
        $stmt->close();
        
        header('Location: manage_users.php');
        exit();
    }
    
    if (isset($_POST['toggle_status'])) {
        $user_id = intval($_POST['user_id']);
        
        $stmt = $conn->prepare("UPDATE users SET status = IF(status = 'active', 'inactive', 'active') WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
        
        header('Location: manage_users.php');
        exit();
    }
}

// Get users with role-specific data
$users = $conn->query("
    SELECT 
        u.*,
        s.full_name as student_name,
        s.admission_no,
        s.gender,
        s.level,
        s.class_id,
        c.class_name,
        t.full_name as teacher_name,
        t.email,
        t.phone
    FROM users u
    LEFT JOIN students s ON u.user_id = s.user_id
    LEFT JOIN classes c ON s.class_id = c.class_id
    LEFT JOIN teachers t ON u.user_id = t.user_id
    ORDER BY u.role, u.created_at DESC
");

// Get classes for dropdown
$classes = $conn->query("SELECT * FROM classes ORDER BY level, class_name");
?>

<div class="row">
    <div class="card">
        <div class="card-header">
            <h3>Add New User</h3>
        </div>
        <form method="POST" action="">
            <div class="form-row">
                <div class="form-group">
                    <label>Username *</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Password *</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Role *</label>
                    <select name="role" class="form-control" required onchange="toggleRoleFields(this.value)">
                        <option value="">Select Role</option>
                        <option value="admin">Admin</option>
                        <option value="teacher">Teacher</option>
                        <option value="student">Student</option>
                    </select>
                </div>
            </div>
            
            <!-- Common Fields -->
            <div class="form-group">
                <label>Full Name *</label>
                <input type="text" name="full_name" class="form-control" required>
            </div>
            
            <!-- Teacher Specific Fields -->
            <div id="teacherFields" style="display: none;">
                <div class="form-row">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="phone" class="form-control">
                    </div>
                </div>
            </div>
            
            <!-- Student Specific Fields -->
            <div id="studentFields" style="display: none;">
                <div class="form-row">
                    <div class="form-group">
                        <label>Admission Number *</label>
                        <input type="text" name="admission_no" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Gender *</label>
                        <select name="gender" class="form-control">
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Level *</label>
                        <select name="level" class="form-control">
                            <option value="junior">Junior Secondary</option>
                            <option value="senior">Senior Secondary</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Class</label>
                        <select name="class_id" class="form-control">
                            <option value="">Select Class</option>
                            <?php while ($class = $classes->fetch_assoc()): ?>
                                <option value="<?php echo $class['class_id']; ?>">
                                    <?php echo htmlspecialchars($class['class_name'] . ' (' . ucfirst($class['level']) . ')'); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <button type="submit" name="add_user" class="btn btn-primary">Add User</button>
        </form>
    </div>
</div>

<div class="row">
    <div class="card">
        <div class="card-header">
            <h3>User List</h3>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Role</th>
                        <th>Details</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($user = $users->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $user['user_id']; ?></td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td>
                                <?php 
                                if ($user['role'] == 'student') {
                                    echo htmlspecialchars($user['student_name']);
                                } elseif ($user['role'] == 'teacher') {
                                    echo htmlspecialchars($user['teacher_name']);
                                } else {
                                    echo 'Admin';
                                }
                                ?>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $user['role'] == 'admin' ? 'high' : ($user['role'] == 'teacher' ? 'medium' : 'low'); ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($user['role'] == 'student'): ?>
                                    <small>
                                        <?php echo htmlspecialchars($user['admission_no']); ?><br>
                                        <?php echo ucfirst($user['gender']); ?> • 
                                        <?php echo ucfirst($user['level']); ?><br>
                                        <?php echo htmlspecialchars($user['class_name'] ?? 'No Class'); ?>
                                    </small>
                                <?php elseif ($user['role'] == 'teacher'): ?>
                                    <small>
                                        <?php echo htmlspecialchars($user['email']); ?><br>
                                        <?php echo htmlspecialchars($user['phone']); ?>
                                    </small>
                                <?php else: ?>
                                    <small>System Administrator</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" action="" style="display: inline;">
                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                    <button type="submit" name="toggle_status" class="btn btn-sm <?php echo $user['status'] == 'active' ? 'btn-success' : 'btn-danger'; ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </button>
                                </form>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                            <td class="actions">
                                <?php if ($user['role'] != 'admin'): ?>
                                    <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                        <button type="submit" name="delete_user" class="btn btn-danger btn-sm">Delete</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    <?php if ($users->num_rows == 0): ?>
                        <tr>
                            <td colspan="8" class="text-center">No users found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function toggleRoleFields(role) {
    document.getElementById('teacherFields').style.display = 'none';
    document.getElementById('studentFields').style.display = 'none';
    
    if (role === 'teacher') {
        document.getElementById('teacherFields').style.display = 'block';
    } else if (role === 'student') {
        document.getElementById('studentFields').style.display = 'block';
    }
}
</script>

<?php 
$conn->close();
require_once '../includes/footer.php'; 
?>