<?php
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    sendResponse(['error' => 'Non authentifié'], 401);
}

$db = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

// GET - Liste des formations
if ($method === 'GET') {
    $stmt = $db->query("SELECT * FROM levels WHERE is_active = 1 ORDER BY name");
    sendResponse($stmt->fetchAll());
}

function sendResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit();
}
?>