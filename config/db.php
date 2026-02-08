<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'student_ews');

// Create connection
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    return $conn;
}

// Create database and tables if not exists
function initializeDatabase() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    // Create database
    $sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    if (!$conn->query($sql)) {
        die("Error creating database: " . $conn->error);
    }
    
    $conn->select_db(DB_NAME);
    
    // Create tables
    $tables = [
        "CREATE TABLE IF NOT EXISTS users (
            user_id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('admin', 'teacher', 'student') NOT NULL,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS classes (
            class_id INT AUTO_INCREMENT PRIMARY KEY,
            class_name VARCHAR(50) NOT NULL,
            level ENUM('junior', 'senior') NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS students (
            student_id INT AUTO_INCREMENT PRIMARY KEY,
            admission_no VARCHAR(20) UNIQUE NOT NULL,
            user_id INT UNIQUE,
            full_name VARCHAR(100) NOT NULL,
            gender ENUM('male', 'female', 'other') NOT NULL,
            level ENUM('junior', 'senior') NOT NULL,
            class_id INT,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
            FOREIGN KEY (class_id) REFERENCES classes(class_id) ON DELETE SET NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS teachers (
            teacher_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNIQUE,
            full_name VARCHAR(100) NOT NULL,
            email VARCHAR(100),
            phone VARCHAR(20),
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS subjects (
            subject_id INT AUTO_INCREMENT PRIMARY KEY,
            subject_name VARCHAR(100) NOT NULL,
            level ENUM('junior', 'senior') NOT NULL,
            teacher_id INT,
            class_id INT,
            FOREIGN KEY (teacher_id) REFERENCES teachers(teacher_id) ON DELETE SET NULL,
            FOREIGN KEY (class_id) REFERENCES classes(class_id) ON DELETE SET NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS attendance (
            attendance_id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            subject_id INT NOT NULL,
            date DATE NOT NULL,
            status ENUM('present', 'absent') DEFAULT 'absent',
            FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
            FOREIGN KEY (subject_id) REFERENCES subjects(subject_id) ON DELETE CASCADE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_attendance (student_id, subject_id, date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS assessments (
            assessment_id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            subject_id INT NOT NULL,
            type ENUM('assignment', 'test', 'exam') NOT NULL,
            score DECIMAL(5,2) NOT NULL,
            max_score DECIMAL(5,2) DEFAULT 100.00,
            term INT NOT NULL,
            academic_year YEAR NOT NULL,
            FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
            FOREIGN KEY (subject_id) REFERENCES subjects(subject_id) ON DELETE CASCADE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS performance_summary (
            performance_id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            term INT NOT NULL,
            academic_year YEAR NOT NULL,
            average_score DECIMAL(5,2) DEFAULT 0.00,
            attendance_rate DECIMAL(5,2) DEFAULT 0.00,
            overall_score DECIMAL(5,2) DEFAULT 0.00,
            risk_level ENUM('low', 'medium', 'high') DEFAULT 'low',
            last_term_score DECIMAL(5,2) DEFAULT 0.00,
            trend ENUM('improving', 'stable', 'declining') DEFAULT 'stable',
            generated_on TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
            UNIQUE KEY unique_summary (student_id, term, academic_year)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS interventions (
            intervention_id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            teacher_id INT NOT NULL,
            reason TEXT NOT NULL,
            action_taken TEXT NOT NULL,
            status ENUM('pending', 'ongoing', 'completed', 'resolved') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
            FOREIGN KEY (teacher_id) REFERENCES teachers(teacher_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];
    
    foreach ($tables as $table) {
        if (!$conn->query($table)) {
            die("Error creating table: " . $conn->error);
        }
    }
    
    // Insert default admin user if not exists
    $checkAdmin = $conn->query("SELECT * FROM users WHERE username = 'admin'");
    
    if ($checkAdmin->num_rows == 0) {
        $password = password_hash('admin123', PASSWORD_DEFAULT);
        $conn->query("INSERT INTO users (username, password_hash, role) VALUES ('admin', '$password', 'admin')");
        
        // Insert sample teacher
        $password = password_hash('teacher123', PASSWORD_DEFAULT);
        $conn->query("INSERT INTO users (username, password_hash, role) VALUES ('teacher', '$password', 'teacher')");
        $teacher_user_id = $conn->insert_id;
        $conn->query("INSERT INTO teachers (user_id, full_name, email, phone) VALUES ($teacher_user_id, 'John Doe', 'teacher@school.edu', '1234567890')");
        
        // Insert sample student
        $password = password_hash('student123', PASSWORD_DEFAULT);
        $conn->query("INSERT INTO users (username, password_hash, role) VALUES ('student', '$password', 'student')");
        $student_user_id = $conn->insert_id;
        
        // Insert sample class
        $conn->query("INSERT IGNORE INTO classes (class_name, level) VALUES ('JSS 1', 'junior')");
        $class_id = $conn->insert_id;
        
        $conn->query("INSERT IGNORE INTO students (admission_no, user_id, full_name, gender, level, class_id) 
                     VALUES ('STD001', $student_user_id, 'Jane Smith', 'female', 'junior', $class_id)");
        
        // Insert sample subjects
        $conn->query("INSERT IGNORE INTO subjects (subject_name, level, teacher_id, class_id) VALUES 
                     ('Mathematics', 'junior', 1, $class_id),
                     ('English Language', 'junior', 1, $class_id),
                     ('Basic Science', 'junior', 1, $class_id)");
    }
    
    $conn->close();
}

// Initialize database on first run
initializeDatabase();
?>