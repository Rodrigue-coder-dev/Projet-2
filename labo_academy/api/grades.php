<?php
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    sendResponse(['error' => 'Accès non autorisé'], 403);
}

$db = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

// GET - Liste des notes
if ($method === 'GET') {
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
}

// POST - Création note
elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $required = ['student_id', 'course_id', 'grade_type', 'score', 'max_score', 'coefficient', 'semester'];
    foreach ($required as $field) {
        if (!isset($input[$field])) {
            sendResponse(['error' => "Champ {$field} requis"], 400);
        }
    }
    
    $stmt = $db->prepare("
        INSERT INTO grades (student_id, course_id, grade_type, score, max_score, coefficient, graded_by, academic_year, semester)
        VALUES (:student_id, :course_id, :grade_type, :score, :max_score, :coefficient, :graded_by, :academic_year, :semester)
    ");
    
    $stmt->execute([
        ':student_id' => $input['student_id'],
        ':course_id' => $input['course_id'],
        ':grade_type' => $input['grade_type'],
        ':score' => $input['score'],
        ':max_score' => $input['max_score'],
        ':coefficient' => $input['coefficient'],
        ':graded_by' => $_SESSION['user_id'],
        ':academic_year' => date('Y') . '-' . (date('Y') + 1),
        ':semester' => $input['semester']
    ]);
    
    sendResponse(['success' => true, 'message' => 'Note enregistrée', 'id' => $db->lastInsertId()], 201);
}

// DELETE - Suppression note
elseif ($method === 'DELETE' && isset($_GET['id'])) {
    $stmt = $db->prepare("DELETE FROM grades WHERE id = :id");
    $stmt->execute([':id' => $_GET['id']]);
    sendResponse(['success' => true, 'message' => 'Note supprimée']);
}

function sendResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit();
}
?>