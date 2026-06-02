<?php
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    sendResponse(['error' => 'Non authentifié'], 401);
}

$db = Database::getInstance()->getConnection();
$role = $_SESSION['user_role'];

// =====================================================
// STATS ADMIN DASHBOARD
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $role === 'admin') {
    $stats = [];
    
    // Nombre d'étudiants actifs
    $stmt = $db->query("SELECT COUNT(*) as count FROM students WHERE status = 'ACTIVE'");
    $stats['active_students'] = $stmt->fetch()['count'];
    
    // Nombre de cours actifs
    $stmt = $db->query("SELECT COUNT(*) as count FROM courses WHERE is_active = 1");
    $stats['active_courses'] = $stmt->fetch()['count'];
    
    // Nombre de formations
    $stmt = $db->query("SELECT COUNT(*) as count FROM levels WHERE is_active = 1");
    $stats['total_formations'] = $stmt->fetch()['count'];
    
    // Total paiements année en cours
    $stmt = $db->query("
        SELECT COALESCE(SUM(amount), 0) as total 
        FROM payments 
        WHERE YEAR(payment_date) = YEAR(CURDATE())
    ");
    $stats['total_payments'] = $stmt->fetch()['total'];
    
    // Solde total restant (Version corrigée)
    $stmt = $db->query("
        SELECT COALESCE(SUM(remaining), 0) as total FROM (
            SELECT s.inscription_amount - COALESCE(SUM(p.amount), 0) as remaining
            FROM students s
            LEFT JOIN payments p ON p.student_id = s.id
            GROUP BY s.id, s.inscription_amount
        ) as balances
    ");
    $stats['total_balance'] = $stmt->fetch()['total'];
    
    // Paiements par méthode
    $stmt = $db->query("
        SELECT payment_method, COUNT(*) as count, COALESCE(SUM(amount), 0) as total
        FROM payments
        GROUP BY payment_method
    ");
    $stats['payments_by_method'] = $stmt->fetchAll();
    
    // Paiements récents
    $stmt = $db->query("
        SELECT p.*, u.first_name, u.last_name, s.matricule
        FROM payments p
        JOIN students s ON s.id = p.student_id
        JOIN users u ON u.id = s.user_id
        ORDER BY p.payment_date DESC
        LIMIT 10
    ");
    $stats['recent_payments'] = $stmt->fetchAll();
    
    // Étudiants avec solde restant (Version corrigée)
    $stmt = $db->query("
        SELECT s.id, u.first_name, u.last_name, s.matricule, 
               s.inscription_amount as total_due,
               COALESCE(SUM(p.amount), 0) as total_paid,
               s.inscription_amount - COALESCE(SUM(p.amount), 0) as balance
        FROM students s
        JOIN users u ON u.id = s.user_id
        LEFT JOIN payments p ON p.student_id = s.id
        GROUP BY s.id, u.first_name, u.last_name, s.matricule, s.inscription_amount
        HAVING balance > 0
        ORDER BY balance DESC
        LIMIT 20
    ");
    $stats['students_with_balance'] = $stmt->fetchAll();
    
    sendResponse($stats);
}

// =====================================================
// API pour les formations (levels)
// =====================================================
elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && strpos($_SERVER['REQUEST_URI'], 'levels.php') !== false) {
    $stmt = $db->query("SELECT * FROM levels WHERE is_active = 1 ORDER BY name");
    sendResponse($stmt->fetchAll());
}

// =====================================================
// API pour les emplois du temps
// =====================================================
elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && strpos($_SERVER['REQUEST_URI'], 'timetable.php') !== false) {
    if ($role === 'admin') {
        $stmt = $db->query("
            SELECT t.*, c.name as course_name, c.code as course_code
            FROM timetables t
            JOIN courses c ON c.id = t.course_id
            ORDER BY FIELD(t.day_of_week, 'MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY'), t.start_time
        ");
    } else {
        $stmt = $db->prepare("
            SELECT t.*, c.name as course_name, c.code as course_code
            FROM timetables t
            JOIN courses c ON c.id = t.course_id
            WHERE t.level_id = :level_id
            ORDER BY FIELD(t.day_of_week, 'MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY'), t.start_time
        ");
        $stmt->execute([':level_id' => $_SESSION['level_id']]);
    }
    sendResponse($stmt->fetchAll());
}

// =====================================================
// API pour les notes (grades)
// =====================================================
elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && strpos($_SERVER['REQUEST_URI'], 'grades.php') !== false && !isset($_GET['id'])) {
    if ($role === 'admin') {
        $stmt = $db->query("
            SELECT g.*, 
                   CONCAT(u.first_name, ' ', u.last_name) as student_name,
                   c.name as course_name,
                   ROUND((g.score / g.max_score) * 20, 2) as grade_over_20
            FROM grades g
            JOIN students s ON s.id = g.student_id
            JOIN users u ON u.id = s.user_id
            JOIN courses c ON c.id = g.course_id
            ORDER BY g.created_at DESC
            LIMIT 100
        ");
        sendResponse($stmt->fetchAll());
    } else {
        $stmt = $db->prepare("
            SELECT g.*, c.name as course_name,
                   ROUND((g.score / g.max_score) * 20, 2) as grade_over_20
            FROM grades g
            JOIN courses c ON c.id = g.course_id
            WHERE g.student_id = :student_id
            ORDER BY c.semester, c.name
        ");
        $stmt->execute([':student_id' => $_SESSION['student_id']]);
        sendResponse($stmt->fetchAll());
    }
}

// =====================================================
// STATS STUDENT DASHBOARD
// =====================================================
elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && $role === 'student') {
    $studentId = $_SESSION['student_id'];
    
    // Informations personnelles
    $stmt = $db->prepare("
        SELECT * FROM v_student_summary WHERE id = :id
    ");
    $stmt->execute([':id' => $studentId]);
    $student = $stmt->fetch();
    
    // Cours de l'étudiant
    $stmt = $db->prepare("
        SELECT c.*, u.first_name as teacher_first, u.last_name as teacher_last
        FROM courses c
        JOIN levels l ON l.id = c.level_id
        JOIN students s ON s.level_id = l.id
        JOIN users u ON u.id = c.teacher_id
        WHERE s.id = :student_id AND c.is_active = 1
    ");
    $stmt->execute([':student_id' => $studentId]);
    $courses = $stmt->fetchAll();
    
    // Emploi du temps
    $stmt = $db->prepare("
        SELECT t.*, c.name as course_name, c.code as course_code
        FROM timetables t
        JOIN courses c ON c.id = t.course_id
        WHERE t.level_id = (SELECT level_id FROM students WHERE id = :student_id)
            AND t.academic_year = :academic_year
        ORDER BY FIELD(t.day_of_week, 'MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY'), t.start_time
    ");
    $stmt->execute([
        ':student_id' => $studentId,
        ':academic_year' => date('Y') . '-' . (date('Y') + 1)
    ]);
    $timetable = $stmt->fetchAll();
    
    // Notes et moyennes
    $stmt = $db->prepare("
        SELECT 
            g.course_id,
            c.name as course_name,
            c.credits,
            MAX(CASE WHEN g.grade_type = 'CC' THEN ROUND((g.score / g.max_score) * 20, 2) END) as cc_grade,
            MAX(CASE WHEN g.grade_type = 'TP' THEN ROUND((g.score / g.max_score) * 20, 2) END) as tp_grade,
            MAX(CASE WHEN g.grade_type = 'EXAM' THEN ROUND((g.score / g.max_score) * 20, 2) END) as exam_grade,
            ROUND(AVG(ROUND((g.score / g.max_score) * 20, 2) * g.coefficient), 2) as weighted_average
        FROM grades g
        JOIN courses c ON c.id = g.course_id
        WHERE g.student_id = :student_id AND g.academic_year = :academic_year
        GROUP BY g.course_id, c.name, c.credits
    ");
    $stmt->execute([
        ':student_id' => $studentId,
        ':academic_year' => date('Y') . '-' . (date('Y') + 1)
    ]);
    $grades = $stmt->fetchAll();
    
    // Calcul moyenne générale
    $total_weighted = 0;
    $total_credits = 0;
    foreach ($grades as $grade) {
        $total_weighted += $grade['weighted_average'] * $grade['credits'];
        $total_credits += $grade['credits'];
    }
    $overall_average = $total_credits > 0 ? round($total_weighted / $total_credits, 2) : 0;
    
    // Historique paiements
    $stmt = $db->prepare("
        SELECT * FROM payments 
        WHERE student_id = :student_id 
        ORDER BY payment_date DESC
    ");
    $stmt->execute([':student_id' => $studentId]);
    $payments = $stmt->fetchAll();
    
    // Solde restant
    $remaining = $student['remaining_balance'] ?? 0;
    
    // Documents pédagogiques
    $stmt = $db->prepare("
        SELECT d.*, c.name as course_name
        FROM documents d
        JOIN courses c ON c.id = d.course_id
        WHERE c.level_id = (SELECT level_id FROM students WHERE id = :student_id)
        ORDER BY d.uploaded_at DESC
    ");
    $stmt->execute([':student_id' => $studentId]);
    $documents = $stmt->fetchAll();
    
    sendResponse([
        'student' => $student,
        'courses' => $courses,
        'timetable' => $timetable,
        'grades' => $grades,
        'overall_average' => $overall_average,
        'payments' => $payments,
        'remaining_balance' => $remaining,
        'documents' => $documents
    ]);
}

function sendResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit();
}
?>