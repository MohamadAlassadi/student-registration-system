<?php

$allowedOrigins = [
    'http://localhost:5500',
    'http://127.0.0.1:5500',
    'http://localhost:8081',
    'http://127.0.0.1:8081'
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
}

header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/database.php';

session_start();

function login_user(PDO $pdo, array $data): array
{
    $identifier = trim((string) ($data['username'] ?? $data['email'] ?? ''));
    $password = $data['password'] ?? '';

    if ($identifier === '' || $password === '') {
        return [
            'success' => false,
            'status' => 422,
            'message' => 'Username/email and password are required.'
        ];
    }

    $stmt = $pdo->prepare('SELECT id, username, email, password FROM users WHERE username = :username OR email = :email LIMIT 1');
    $stmt->execute([
        ':username' => $identifier,
        ':email' => $identifier
    ]);
    $user = $stmt->fetch();

    if (!$user || !password_verify((string) $password, (string) $user['password'])) {
        return [
            'success' => false,
            'status' => 401,
            'message' => 'Invalid username/email or password.'
        ];
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];

    return [
        'success' => true,
        'status' => 200,
        'message' => 'Login successful.',
        'user' => [
            'id' => (int) $user['id'],
            'username' => $user['username'],
            'email' => $user['email']
        ]
    ];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Only POST requests are allowed.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$data = $_POST;

if (stripos($contentType, 'application/json') !== false) {
    $json = json_decode(file_get_contents('php://input'), true);
    if (!is_array($json)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid JSON request body.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $data = $json;
}

try {
    $result = login_user($pdo, $data);
    http_response_code($result['status'] ?? 200);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    error_log('User login failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'A database error occurred.'
    ], JSON_UNESCAPED_UNICODE);
}
