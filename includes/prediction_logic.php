<?php
function calculatePerformanceScore($student_id, $term = null, $academic_year = null) {
    require_once '../config/db.php';
    $conn = getDBConnection();
    
    // Set default term and year if not provided
    if ($term === null) {
        $current_month = date('m');
        $term = ceil($current_month / 4);
    }
    
    if ($academic_year === null) {
        $academic_year = date('Y');
    }
    
    // Get student details
    $student = $conn->query("
        SELECT s.*, c.class_name 
        FROM students s 
        LEFT JOIN classes c ON s.class_id = c.class_id 
        WHERE s.student_id = $student_id
    ")->fetch_assoc();
    
    if (!$student) {
        return false;
    }
    
    // Calculate attendance rate for current term
    $attendance = $conn->query("
        SELECT 
            COUNT(*) as total_days,
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days
        FROM attendance 
        WHERE student_id = $student_id
          AND YEAR(date) = $academic_year
          AND MONTH(date) BETWEEN (($term-1)*4+1) AND ($term*4)
    ")->fetch_assoc();
    
    $attendance_rate = ($attendance['total_days'] > 0) 
        ? ($attendance['present_days'] / $attendance['total_days']) * 100 
        : 0;
    
    // Calculate assessment average for current term
    $assessments = $conn->query("
        SELECT 
            AVG((score / max_score) * 100) as avg_score,
            COUNT(*) as total_assessments
        FROM assessments 
        WHERE student_id = $student_id
          AND term = $term
          AND academic_year = $academic_year
    ")->fetch_assoc();
    
    $assessment_avg = $assessments['avg_score'] ?? 0;
    $total_assessments = $assessments['total_assessments'] ?? 0;
    
    // Get previous term performance for trend calculation
    $previous_term = $term > 1 ? $term - 1 : 4;
    $previous_year = $term > 1 ? $academic_year : $academic_year - 1;
    
    $prev_performance = $conn->query("
        SELECT overall_score 
        FROM performance_summary 
        WHERE student_id = $student_id 
          AND term = $previous_term 
          AND academic_year = $previous_year
        LIMIT 1
    ");
    
    $trend_score = 0;
    $prev_score = 0;
    if ($prev_performance->num_rows > 0) {
        $prev_score = $prev_performance->fetch_assoc()['overall_score'];
        if ($assessment_avg > $prev_score) {
            $trend_score = 10; // Improving
        } elseif ($assessment_avg < $prev_score - 10) {
            $trend_score = -10; // Declining significantly
        } elseif ($assessment_avg < $prev_score) {
            $trend_score = -5; // Declining slightly
        }
    } else {
        // If no previous term data, assume stable
        $trend_score = 0;
    }
    
    // Apply weights (Attendance: 30%, Assessment: 60%, Trend: 10%)
    $weighted_attendance = $attendance_rate * 0.3;
    $weighted_assessment = $assessment_avg * 0.6;
    $weighted_trend = $trend_score;
    
    $overall_score = $weighted_attendance + $weighted_assessment + $weighted_trend;
    
    // Ensure score is between 0 and 100
    $overall_score = max(0, min(100, $overall_score));
    
    // Adjust for attendance threshold (if attendance < 75%, penalize)
    if ($attendance_rate < 75) {
        $overall_score = $overall_score * 0.8;
    }
    
    // Adjust for insufficient assessments
    if ($total_assessments < 3) {
        $overall_score = $overall_score * 0.9;
    }
    
    // Determine risk level
    if ($overall_score >= 70) {
        $risk_level = 'low';
    } elseif ($overall_score >= 50) {
        $risk_level = 'medium';
    } else {
        $risk_level = 'high';
    }
    
    // Determine trend
    $trend = 'stable';
    if ($trend_score > 5) {
        $trend = 'improving';
    } elseif ($trend_score < -5) {
        $trend = 'declining';
    }
    
    // Check for early warning triggers
    $warning_triggers = [];
    
    if ($attendance_rate < 75) {
        $warning_triggers[] = "Low attendance ($attendance_rate%)";
    }
    
    if ($assessment_avg < 50) {
        $warning_triggers[] = "Low assessment average ($assessment_avg%)";
    }
    
    if ($total_assessments < 2) {
        $warning_triggers[] = "Insufficient assessments ($total_assessments)";
    }
    
    // Insert or update performance summary
    $stmt = $conn->prepare("
        INSERT INTO performance_summary 
        (student_id, term, academic_year, average_score, attendance_rate, overall_score, risk_level, last_term_score, trend)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        average_score = VALUES(average_score),
        attendance_rate = VALUES(attendance_rate),
        overall_score = VALUES(overall_score),
        risk_level = VALUES(risk_level),
        last_term_score = VALUES(last_term_score),
        trend = VALUES(trend),
        generated_on = CURRENT_TIMESTAMP
    ");
    
    $stmt->bind_param("iiidddsss", 
        $student_id, 
        $term, 
        $academic_year,
        $assessment_avg,
        $attendance_rate,
        $overall_score,
        $risk_level,
        $prev_score,
        $trend
    );
    
    $stmt->execute();
    $stmt->close();
    
    $result = [
        'student_id' => $student_id,
        'student_name' => $student['full_name'],
        'class_name' => $student['class_name'],
        'term' => $term,
        'academic_year' => $academic_year,
        'average_score' => round($assessment_avg, 2),
        'attendance_rate' => round($attendance_rate, 2),
        'overall_score' => round($overall_score, 2),
        'risk_level' => $risk_level,
        'trend' => $trend,
        'warning_triggers' => $warning_triggers,
        'last_calculated' => date('Y-m-d H:i:s')
    ];
    
    // If high risk, create intervention record if not exists
    if ($risk_level == 'high' && !empty($warning_triggers)) {
        $existing_intervention = $conn->query("
            SELECT * FROM interventions 
            WHERE student_id = $student_id 
            AND status IN ('pending', 'ongoing')
            LIMIT 1
        ");
        
        if ($existing_intervention->num_rows == 0) {
            // Get teacher for this student's class
            $teacher = $conn->query("
                SELECT t.teacher_id 
                FROM teachers t 
                JOIN subjects s ON t.teacher_id = s.teacher_id 
                WHERE s.class_id = {$student['class_id']} 
                LIMIT 1
            ")->fetch_assoc();
            
            if ($teacher) {
                $reason = implode(', ', $warning_triggers);
                $conn->query("
                    INSERT INTO interventions (student_id, teacher_id, reason, action_taken, status)
                    VALUES ($student_id, {$teacher['teacher_id']}, '$reason', 'Awaiting action', 'pending')
                ");
            }
        }
    }
    
    $conn->close();
    
    return $result;
}

function generateEarlyWarnings($term = null, $academic_year = null) {
    require_once '../config/db.php';
    $conn = getDBConnection();
    
    if ($term === null) {
        $current_month = date('m');
        $term = ceil($current_month / 4);
    }
    
    if ($academic_year === null) {
        $academic_year = date('Y');
    }
    
    // Get all active students
    $students = $conn->query("
        SELECT s.student_id, s.full_name, s.admission_no, c.class_name
        FROM students s 
        LEFT JOIN classes c ON s.class_id = c.class_id 
        WHERE s.student_id IS NOT NULL
    ");
    
    $warnings = [];
    $performance_data = [];
    
    while ($student = $students->fetch_assoc()) {
        $performance = calculatePerformanceScore($student['student_id'], $term, $academic_year);
        
        if ($performance && $performance['risk_level'] == 'high') {
            $warnings[] = [
                'student_id' => $student['student_id'],
                'student_name' => $student['full_name'],
                'admission_no' => $student['admission_no'],
                'class_name' => $student['class_name'],
                'overall_score' => $performance['overall_score'],
                'attendance_rate' => $performance['attendance_rate'],
                'average_score' => $performance['average_score'],
                'risk_level' => $performance['risk_level'],
                'warning_triggers' => $performance['warning_triggers']
            ];
        }
        
        $performance_data[] = $performance;
    }
    
    $conn->close();
    
    return [
        'warnings' => $warnings,
        'total_warnings' => count($warnings),
        'performance_data' => $performance_data,
        'term' => $term,
        'academic_year' => $academic_year
    ];
}

function getPerformanceHistory($student_id) {
    require_once '../config/db.php';
    $conn = getDBConnection();
    
    $history = $conn->query("
        SELECT * FROM performance_summary 
        WHERE student_id = $student_id 
        ORDER BY academic_year DESC, term DESC
    ");
    
    $data = [];
    while ($row = $history->fetch_assoc()) {
        $data[] = $row;
    }
    
    $conn->close();
    return $data;
}

function getClassPerformanceReport($class_id, $term = null, $academic_year = null) {
    require_once '../config/db.php';
    $conn = getDBConnection();
    
    if ($term === null) {
        $current_month = date('m');
        $term = ceil($current_month / 4);
    }
    
    if ($academic_year === null) {
        $academic_year = date('Y');
    }
    
    $report = $conn->query("
        SELECT 
            s.student_id,
            s.full_name,
            s.admission_no,
            ps.overall_score,
            ps.attendance_rate,
            ps.average_score,
            ps.risk_level,
            ps.trend,
            COUNT(DISTINCT a.assessment_id) as total_assessments
        FROM students s
        LEFT JOIN performance_summary ps ON s.student_id = ps.student_id 
            AND ps.term = $term 
            AND ps.academic_year = $academic_year
        LEFT JOIN assessments a ON s.student_id = a.student_id 
            AND a.term = $term 
            AND a.academic_year = $academic_year
        WHERE s.class_id = $class_id
        GROUP BY s.student_id
        ORDER BY ps.overall_score DESC
    ");
    
    $data = [];
    while ($row = $report->fetch_assoc()) {
        $data[] = $row;
    }
    
    $conn->close();
    return $data;
}
?>