<?php
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    sendResponse(['error' => 'Non authentifié'], 401);
}

$db = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'];
$role = $_SESSION['user_role'];

// =====================================================
// GET - Liste des paiements
// =====================================================
if ($method === 'GET' && !isset($_GET['id'])) {
    if ($role === 'admin') {
        $stmt = $db->query("
            SELECT p.*, u.first_name, u.last_name, s.matricule
            FROM payments p
            JOIN students s ON s.id = p.student_id
            JOIN users u ON u.id = s.user_id
            ORDER BY p.payment_date DESC
            LIMIT 100
        ");
    } else {
        $stmt = $db->prepare("
            SELECT * FROM payments 
            WHERE student_id = :student_id 
            ORDER BY payment_date DESC
        ");
        $stmt->execute([':student_id' => $_SESSION['student_id']]);
    }
    sendResponse($stmt->fetchAll());
}

// =====================================================
// GET - Détail paiement
// =====================================================
elseif ($method === 'GET' && isset($_GET['id'])) {
    $stmt = $db->prepare("
        SELECT p.*, u.first_name, u.last_name, s.matricule,
               CONCAT(ru.first_name, ' ', ru.last_name) as recorded_by_name
        FROM payments p
        JOIN students s ON s.id = p.student_id
        JOIN users u ON u.id = s.user_id
        JOIN users ru ON ru.id = p.recorded_by
        WHERE p.id = :id
    ");
    $stmt->execute([':id' => $_GET['id']]);
    $payment = $stmt->fetch();
    
    if (!$payment) {
        sendResponse(['error' => 'Paiement non trouvé'], 404);
    }
    
    sendResponse($payment);
}

// =====================================================
// POST - Création paiement
// =====================================================
elseif ($method === 'POST') {
    if ($role !== 'admin') {
        sendResponse(['error' => 'Seul l\'administrateur peut enregistrer des paiements'], 403);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $required = ['student_id', 'amount', 'payment_method'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            sendResponse(['error' => "Champ {$field} requis"], 400);
        }
    }
    
    // Génération référence unique
    $reference = 'PAY-' . date('YmdHis') . '-' . rand(100, 999);
    
    $db->beginTransaction();
    try {
        $stmt = $db->prepare("
            INSERT INTO payments (student_id, amount, payment_date, payment_method, reference, description, recorded_by)
            VALUES (:student_id, :amount, :payment_date, :payment_method, :reference, :description, :recorded_by)
        ");
        
        $stmt->execute([
            ':student_id' => $input['student_id'],
            ':amount' => $input['amount'],
            ':payment_date' => $input['payment_date'] ?? date('Y-m-d'),
            ':payment_method' => $input['payment_method'],
            ':reference' => $reference,
            ':description' => $input['description'] ?? null,
            ':recorded_by' => $_SESSION['user_id']
        ]);
        
        $paymentId = $db->lastInsertId();
        
        // Vérifier si le solde est nul
        $stmt = $db->prepare("
            SELECT s.inscription_amount - COALESCE(SUM(p.amount), 0) as balance
            FROM students s
            LEFT JOIN payments p ON p.student_id = s.id
            WHERE s.id = :student_id
            GROUP BY s.id
        ");
        $stmt->execute([':student_id' => $input['student_id']]);
        $result = $stmt->fetch();
        
        if ($result && $result['balance'] <= 0) {
            $stmt = $db->prepare("UPDATE students SET inscription_paid = 1 WHERE id = :id");
            $stmt->execute([':id' => $input['student_id']]);
        }
        
        $db->commit();
        sendResponse(['success' => true, 'message' => 'Paiement enregistré', 'id' => $paymentId, 'reference' => $reference], 201);
        
    } catch(PDOException $e) {
        $db->rollBack();
        sendResponse(['error' => 'Erreur: ' . $e->getMessage()], 500);
    }
}

// =====================================================
// GET - Génération facture PDF
// =====================================================
elseif ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'invoice' && isset($_GET['id'])) {
    $paymentId = $_GET['id'];
    
    $stmt = $db->prepare("
        SELECT p.*, s.matricule, u.first_name, u.last_name, u.email, u.phone,
               l.name as formation_name, l.code as formation_code
        FROM payments p
        JOIN students s ON s.id = p.student_id
        JOIN users u ON u.id = s.user_id
        JOIN levels l ON l.id = s.level_id
        WHERE p.id = :id
    ");
    $stmt->execute([':id' => $paymentId]);
    $payment = $stmt->fetch();
    
    if (!$payment) {
        sendResponse(['error' => 'Paiement non trouvé'], 404);
    }
    
    // Génération HTML facture (retournée en JSON pour le front)
    $invoiceHtml = "
    <div style='font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; border: 1px solid #ddd;'>
        <div style='text-align: center; margin-bottom: 30px;'>
            <img src='../public/assets/images/logo.png' alt='LABO ACADEMY' style='max-width: 200px;'>
            <h1 style='color: #2c3e50;'>FACTURE</h1>
            <p style='color: #7f8c8d;'>N° {$payment['reference']}</p>
        </div>
        
        <div style='display: flex; justify-content: space-between; margin-bottom: 30px;'>
            <div>
                <h3 style='color: #2c3e50;'>Émetteur</h3>
                <p><strong>LABO ACADEMY</strong><br>
                Quartier Ange Raphaël, Douala<br>
                Tél: +237 600 000 000<br>
                Email: contact@laboacademy.com</p>
            </div>
            <div>
                <h3 style='color: #2c3e50;'>Client</h3>
                <p><strong>{$payment['first_name']} {$payment['last_name']}</strong><br>
                Matricule: {$payment['matricule']}<br>
                Email: {$payment['email']}<br>
                Tél: {$payment['phone']}</p>
            </div>
        </div>
        
        <table style='width: 100%; border-collapse: collapse; margin-bottom: 30px;'>
            <thead>
                <tr style='background-color: #3498db; color: white;'>
                    <th style='padding: 10px; text-align: left;'>Libellé</th>
                    <th style='padding: 10px; text-align: right;'>Montant</th>
                </tr>
            </thead>
            <tbody>
                <tr style='border-bottom: 1px solid #ddd;'>
                    <td style='padding: 10px;'>Frais de formation - {$payment['formation_name']}</td>
                    <td style='padding: 10px; text-align: right;'>" . number_format($payment['amount'], 0, ',', ' ') . " FCFA</td>
                </tr>
            </tbody>
            <tfoot>
                <tr style='border-top: 2px solid #ddd;'>
                    <td style='padding: 10px;'><strong>TOTAL</strong></td>
                    <td style='padding: 10px; text-align: right;'><strong>" . number_format($payment['amount'], 0, ',', ' ') . " FCFA</strong></td>
                </tr>
            </tfoot>
        </table>
        
        <div style='text-align: center; margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; color: #7f8c8d;'>
            <p>Date: " . date('d/m/Y', strtotime($payment['payment_date'])) . "<br>
            Mode: " . str_replace('_', ' ', $payment['payment_method']) . "<br>
            Merci pour votre confiance !</p>
        </div>
    </div>";
    
    sendResponse(['success' => true, 'html' => $invoiceHtml]);
}

// =====================================================
// POST - Paiement service (étudiant)
// =====================================================
elseif ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'service') {
    if ($role !== 'student') {
        sendResponse(['error' => 'Accès réservé aux étudiants'], 403);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['service_id']) || empty($input['amount'])) {
        sendResponse(['error' => 'Service et montant requis'], 400);
    }
    
    $reference = 'SERV-' . date('YmdHis') . '-' . rand(100, 999);
    
    $stmt = $db->prepare("
        INSERT INTO payments (student_id, amount, payment_date, payment_method, reference, description, recorded_by)
        VALUES (:student_id, :amount, CURDATE(), :method, :reference, :description, :recorded_by)
    ");
    
    $stmt->execute([
        ':student_id' => $_SESSION['student_id'],
        ':amount' => $input['amount'],
        ':method' => $input['payment_method'] ?? 'CASH',
        ':reference' => $reference,
        ':description' => 'Service: ' . ($input['service_name'] ?? 'Service complémentaire'),
        ':recorded_by' => $_SESSION['user_id']
    ]);
    
    sendResponse(['success' => true, 'message' => 'Paiement effectué', 'reference' => $reference]);
}

else {
    sendResponse(['error' => 'Route non trouvée'], 404);
}
?>