<?php
require_once '../config/database.php';

$db = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

function sendResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit();
}

// =====================================================
// LOGIN
// =====================================================
if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'login') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['email']) || !isset($input['password'])) {
        sendResponse(['error' => 'Email et mot de passe requis'], 400);
    }
    
    $stmt = $db->prepare("
        SELECT u.*, 
               s.id as student_id, s.matricule, s.level_id, s.status as student_status,
               l.name as level_name
        FROM users u
        LEFT JOIN students s ON u.id = s.user_id
        LEFT JOIN levels l ON l.id = s.level_id
        WHERE u.email = :email AND u.is_active = 1
    ");
    
    $stmt->execute([':email' => $input['email']]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($input['password'], $user['password'])) {
        sendResponse(['error' => 'Email ou mot de passe incorrect'], 401);
    }
    
    // Régénération session ID
    session_regenerate_id(true);
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_code'] = $user['code_unique'];
    
    if ($user['role'] === 'student' && isset($user['student_id'])) {
        $_SESSION['student_id'] = $user['student_id'];
        $_SESSION['matricule'] = $user['matricule'];
        $_SESSION['level_id'] = $user['level_id'];
    }
    
    sendResponse([
        'success' => true,
        'user' => [
            'id' => $user['id'],
            'name' => $user['first_name'] . ' ' . $user['last_name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'code_unique' => $user['code_unique'],
            'profile_image' => $user['profile_image']
        ]
    ]);
}

// =====================================================
// INSCRIPTION
// =====================================================
elseif ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'register') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $required = ['first_name', 'last_name', 'email', 'password', 'phone', 'level_id'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            sendResponse(['error' => "Champ {$field} requis"], 400);
        }
    }
    
    // Vérification email existant
    $stmt = $db->prepare("SELECT id FROM users WHERE email = :email");
    $stmt->execute([':email' => $input['email']]);
    if ($stmt->fetch()) {
        sendResponse(['error' => 'Cet email est déjà utilisé'], 400);
    }
    
    // Récupération prix formation
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
        
        sendResponse([
            'success' => true,
            'message' => 'Inscription réussie',
            'matricule' => $result['matricule'],
            'code_unique' => $result['code_unique']
        ]);
    } catch(PDOException $e) {
        sendResponse(['error' => 'Erreur lors de l\'inscription: ' . $e->getMessage()], 500);
    }
}

// =====================================================
// MOT DE PASSE OUBLIÉ - Demande reset
// =====================================================
elseif ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'forgot-password') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['email'])) {
        sendResponse(['error' => 'Email requis'], 400);
    }
    
    $stmt = $db->prepare("SELECT id, first_name, last_name FROM users WHERE email = :email");
    $stmt->execute([':email' => $input['email']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        sendResponse(['error' => 'Aucun compte associé à cet email'], 404);
    }
    
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    $stmt = $db->prepare("UPDATE users SET reset_token = :token, reset_expires = :expires WHERE id = :id");
    $stmt->execute([':token' => $token, ':expires' => $expires, ':id' => $user['id']]);
    
    // En environnement réel, envoyer email
    sendResponse([
        'success' => true,
        'message' => 'Email de réinitialisation envoyé',
        'reset_token' => $token // Pour test seulement
    ]);
}

// =====================================================
// RÉINITIALISATION MOT DE PASSE
// =====================================================
elseif ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'reset-password') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['token']) || empty($input['new_password'])) {
        sendResponse(['error' => 'Token et nouveau mot de passe requis'], 400);
    }
    
    $stmt = $db->prepare("
        SELECT id FROM users 
        WHERE reset_token = :token AND reset_expires > NOW() AND is_active = 1
    ");
    $stmt->execute([':token' => $input['token']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        sendResponse(['error' => 'Token invalide ou expiré'], 400);
    }
    
    $new_password = password_hash($input['new_password'], PASSWORD_BCRYPT);
    
    $stmt = $db->prepare("
        UPDATE users 
        SET password = :password, reset_token = NULL, reset_expires = NULL 
        WHERE id = :id
    ");
    $stmt->execute([':password' => $new_password, ':id' => $user['id']]);
    
    sendResponse(['success' => true, 'message' => 'Mot de passe réinitialisé avec succès']);
}

// =====================================================
// LOGOUT
// =====================================================
elseif ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'logout') {
    $_SESSION = [];
    session_destroy();
    sendResponse(['success' => true, 'message' => 'Déconnexion réussie']);
}

// =====================================================
// CHECK SESSION
// =====================================================
elseif ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'check') {
    if (isset($_SESSION['user_id'])) {
        sendResponse([
            'authenticated' => true,
            'user' => [
                'id' => $_SESSION['user_id'],
                'name' => $_SESSION['user_name'],
                'email' => $_SESSION['user_email'],
                'role' => $_SESSION['user_role'],
                'code_unique' => $_SESSION['user_code'] ?? null
            ]
        ]);
    } else {
        sendResponse(['authenticated' => false], 401);
    }
}

// =====================================================
// MISE À JOUR PROFIL
// =====================================================
elseif ($method === 'PUT' && isset($_GET['action']) && $_GET['action'] === 'profile') {
    if (!isset($_SESSION['user_id'])) {
        sendResponse(['error' => 'Non authentifié'], 401);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'];
    
    $updateFields = [];
    $params = [':id' => $userId];
    
    $allowedFields = ['first_name', 'last_name', 'phone', 'address'];
    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            $updateFields[] = "{$field} = :{$field}";
            $params[":{$field}"] = $input[$field];
        }
    }
    
    if (!empty($updateFields)) {
        $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
    }
    
    // Mise à jour de la session
    if (isset($input['first_name']) && isset($input['last_name'])) {
        $_SESSION['user_name'] = $input['first_name'] . ' ' . $input['last_name'];
    }
    
    sendResponse(['success' => true, 'message' => 'Profil mis à jour']);
}

else {
    sendResponse(['error' => 'Route non trouvée'], 404);
}
?>