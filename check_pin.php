<?php
header('Content-Type: application/json');
$token = $_GET['id'] ?? '';
$pin = $_GET['pin'] ?? '';
$pdo = new PDO(
    'mysql:host=localhost;dbname=u727002967_braceleros_db;charset=utf8mb4',
    'u727002967_wenria',
    'Wenria34!'
);
$stmt = $pdo->prepare("SELECT pin_code, pin_code_plain, pin_active FROM Bracelets WHERE token = ?");
$stmt->execute([$token]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row || !$row['pin_active']) {
    echo json_encode(['ok'=>true]); // pas de PIN ou PIN désactivé
    exit;
}
if ($row && $row['pin_code'] && password_verify($pin, $row['pin_code'])) {
    echo json_encode(['ok'=>true]);
} else {
    echo json_encode(['ok'=>false]);
}