<?php
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    sendResponse(['error' => 'Accès non autorisé'], 403);
}

$db = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

// =====================================================
// GET - Liste des cours
// =====================================================
if ($method === 'GET' && !isset($_GET['id'])) {
    $sql = "
        SELECT c.*, l.name as level_name, 
               CONCAT(u.first_name, ' ', u.last_name) as teacher_name
        FROM courses c
        JOIN levels l ON l.id = c.level_id
        JOIN users u ON u.id = c.teacher_id
        ORDER BY c.name
    ";
    $stmt = $db->query($sql);
    sendResponse($stmt->fetchAll());
}

// =====================================================
// GET - Détail d'un cours
// =====================================================
elseif ($method === 'GET' && isset($_GET['id'])) {
    $stmt = $db->prepare("
        SELECT c.*, l.name as level_name, l.code as level_code,
               CONCAT(u.first_name, ' ', u.last_name) as teacher_name
        FROM courses c
        JOIN levels l ON l.id = c.level_id
        JOIN users u ON u.id = c.teacher_id
        WHERE c.id = :id
    ");
    $stmt->execute([':id' => $_GET['id']]);
    $course = $stmt->fetch();
    
    if (!$course) {
        sendResponse(['error' => 'Cours non trouvé'], 404);
    }
    
    sendResponse($course);
}

// =====================================================
// POST - Création cours
// =====================================================
elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $required = ['code', 'name', 'credits', 'semester', 'level_id', 'teacher_id'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            sendResponse(['error' => "Champ {$field} requis"], 400);
        }
    }
    
    $stmt = $db->prepare("
        INSERT INTO courses (code, name, description, credits, semester, level_id, teacher_id)
        VALUES (:code, :name, :description, :credits, :semester, :level_id, :teacher_id)
    ");
    
    $stmt->execute([
        ':code' => $input['code'],
        ':name' => $input['name'],
        ':description' => $input['description'] ?? '',
        ':credits' => $input['credits'],
        ':semester' => $input['semester'],
        ':level_id' => $input['level_id'],
        ':teacher_id' => $input['teacher_id']
    ]);
    
    sendResponse(['success' => true, 'message' => 'Cours créé', 'id' => $db->lastInsertId()], 201);
}

// =====================================================
// PUT - Mise à jour cours
// =====================================================
elseif ($method === 'PUT' && isset($_GET['id'])) {
    $input = json_decode(file_get_contents('php://input'), true);
    $courseId = $_GET['id'];
    
    $fields = [];
    $params = [':id' => $courseId];
    
    $allowed = ['code', 'name', 'description', 'credits', 'semester', 'level_id', 'teacher_id', 'is_active'];
    foreach ($allowed as $field) {
        if (isset($input[$field])) {
            $fields[] = "{$field} = :{$field}";
            $params[":{$field}"] = $input[$field];
        }
    }
    
    if (empty($fields)) {
        sendResponse(['error' => 'Aucune donnée à mettre à jour'], 400);
    }
    
    $sql = "UPDATE courses SET " . implode(', ', $fields) . " WHERE id = :id";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    sendResponse(['success' => true, 'message' => 'Cours mis à jour']);
}

// =====================================================
// DELETE - Suppression cours
// =====================================================
elseif ($method === 'DELETE' && isset($_GET['id'])) {
    $stmt = $db->prepare("DELETE FROM courses WHERE id = :id");
    $stmt->execute([':id' => $_GET['id']]);
    sendResponse(['success' => true, 'message' => 'Cours supprimé']);
}

else {
    sendResponse(['error' => 'Route non trouvée'], 404);
}
?>