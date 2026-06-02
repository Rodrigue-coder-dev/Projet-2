<?php
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    sendResponse(['error' => 'Accès non autorisé'], 403);
}

$db = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

// =====================================================
// GET - Liste des étudiants
// =====================================================
if ($method === 'GET' && !isset($_GET['id'])) {
    $stmt = $db->query("
        SELECT * FROM v_student_summary 
        ORDER BY last_name, first_name
    ");
    sendResponse($stmt->fetchAll());
}

// =====================================================
// GET - Détail d'un étudiant
// =====================================================
elseif ($method === 'GET' && isset($_GET['id'])) {
    $stmt = $db->prepare("
        SELECT * FROM v_student_summary WHERE id = :id
    ");
    $stmt->execute([':id' => $_GET['id']]);
    $student = $stmt->fetch();
    
    if (!$student) {
        sendResponse(['error' => 'Étudiant non trouvé'], 404);
    }
    
    // Récupérer les paiements
    $stmt = $db->prepare("
        SELECT * FROM payments WHERE student_id = :id ORDER BY payment_date DESC
    ");
    $stmt->execute([':id' => $_GET['id']]);
    $student['payments'] = $stmt->fetchAll();
    
    // Récupérer les notes
    $stmt = $db->prepare("
        SELECT g.*, c.name as course_name 
        FROM grades g 
        JOIN courses c ON c.id = g.course_id 
        WHERE g.student_id = :id
    ");
    $stmt->execute([':id' => $_GET['id']]);
    $student['grades'] = $stmt->fetchAll();
    
    sendResponse($student);
}

// =====================================================
// POST - Création étudiant
// =====================================================
elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $required = ['first_name', 'last_name', 'email', 'password', 'phone', 'level_id'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            sendResponse(['error' => "Champ {$field} requis"], 400);
        }
    }
    
    // Vérification email
    $stmt = $db->prepare("SELECT id FROM users WHERE email = :email");
    $stmt->execute([':email' => $input['email']]);
    if ($stmt->fetch()) {
        sendResponse(['error' => 'Email déjà utilisé'], 400);
    }
    
    // Récupération montant inscription
    $stmt = $db->prepare("SELECT price FROM levels WHERE id = :id");
    $stmt->execute([':id' => $input['level_id']]);
    $level = $stmt->fetch();
    $amount = $level ? $level['price'] : 0;
    
    $password_hash = password_hash($input['password'], PASSWORD_BCRYPT);
    
    try {
        $stmt = $db->prepare("CALL sp_register_student(:first_name, :last_name, :email, :password, :phone, :level_id, :amount)");
        $stmt->execute([
            ':first_name' => $input['first_name'],
            ':last_name' => $input['last_name'],
            ':email' => $input['email'],
            ':password' => $password_hash,
            ':phone' => $input['phone'],
            ':level_id' => $input['level_id'],
            ':amount' => $amount
        ]);
        
        $result = $stmt->fetch();
        sendResponse(['success' => true, 'message' => 'Étudiant créé', 'data' => $result], 201);
    } catch(PDOException $e) {
        sendResponse(['error' => 'Erreur création: ' . $e->getMessage()], 500);
    }
}

// =====================================================
// PUT - Mise à jour étudiant
// =====================================================
elseif ($method === 'PUT' && isset($_GET['id'])) {
    $input = json_decode(file_get_contents('php://input'), true);
    $studentId = $_GET['id'];
    
    // Récupérer user_id
    $stmt = $db->prepare("SELECT user_id FROM students WHERE id = :id");
    $stmt->execute([':id' => $studentId]);
    $student = $stmt->fetch();
    
    if (!$student) {
        sendResponse(['error' => 'Étudiant non trouvé'], 404);
    }
    
    // Mise à jour users
    $userUpdates = [];
    $userParams = [':id' => $student['user_id']];
    
    $userFields = ['first_name', 'last_name', 'email', 'phone', 'address'];
    foreach ($userFields as $field) {
        if (isset($input[$field])) {
            $userUpdates[] = "{$field} = :{$field}";
            $userParams[":{$field}"] = $input[$field];
        }
    }
    
    if (!empty($userUpdates)) {
        $sql = "UPDATE users SET " . implode(', ', $userUpdates) . " WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute($userParams);
    }
    
    // Mise à jour students
    $studentUpdates = [];
    $studentParams = [':id' => $studentId];
    
    $studentFields = ['level_id', 'status', 'academic_year', 'date_of_birth', 'place_of_birth'];
    foreach ($studentFields as $field) {
        if (isset($input[$field])) {
            $studentUpdates[] = "{$field} = :{$field}";
            $studentParams[":{$field}"] = $input[$field];
        }
    }
    
    if (!empty($studentUpdates)) {
        $sql = "UPDATE students SET " . implode(', ', $studentUpdates) . " WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute($studentParams);
    }
    
    sendResponse(['success' => true, 'message' => 'Étudiant mis à jour']);
}

// =====================================================
// DELETE - Suppression étudiant
// =====================================================
elseif ($method === 'DELETE' && isset($_GET['id'])) {
    $studentId = $_GET['id'];
    
    $db->beginTransaction();
    try {
        // Récupérer user_id
        $stmt = $db->prepare("SELECT user_id FROM students WHERE id = :id");
        $stmt->execute([':id' => $studentId]);
        $student = $stmt->fetch();
        
        // Supprimer l'étudiant
        $stmt = $db->prepare("DELETE FROM students WHERE id = :id");
        $stmt->execute([':id' => $studentId]);
        
        // Supprimer l'utilisateur
        if ($student) {
            $stmt = $db->prepare("DELETE FROM users WHERE id = :id AND role = 'student'");
            $stmt->execute([':id' => $student['user_id']]);
        }
        
        $db->commit();
        sendResponse(['success' => true, 'message' => 'Étudiant supprimé']);
    } catch(PDOException $e) {
        $db->rollBack();
        sendResponse(['error' => 'Erreur suppression: ' . $e->getMessage()], 500);
    }
}

else {
    sendResponse(['error' => 'Route non trouvée'], 404);
}
?>