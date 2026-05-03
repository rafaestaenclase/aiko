<?php
$host = 'localhost';
$db   = 'qr_restaurant';
$user = 'root';
$pass = '';

$pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
]);

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function requireAdminToken(PDO $pdo) {
    $token = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? '';
    if (!$token) jsonResponse(['error' => 'Token requerido'], 401);

    $stmt = $pdo->prepare('SELECT id FROM admin_tokens WHERE token = ?');
    $stmt->execute([hash('sha256', $token)]);
    if (!$stmt->fetch()) jsonResponse(['error' => 'Token inválido'], 403);
}

function getBody(): array {
    return json_decode(file_get_contents('php://input'), true) ?? [];
}