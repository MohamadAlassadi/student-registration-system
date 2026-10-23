<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/database.php';

function register_user(PDO $pdo, array $data): array
{
    $username = trim((string) ($data['username'] ?? ''));
    $email = trim((string) ($data['email'] ?? ''));
    $password = $data['password'] ?? '';

    if ($username === '' || $email === '' || $password === '') {
        return [
            'success' => false,
            'status' => 422,
            'message' => 'Username, email, and password are required.'
        ];
    }

    if (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) {
        return [
            'success' => false,
            'status' => 422,
            'message' => 'Username must be 3 to 50 characters long and contain only letters, numbers, or underscores.'
        ];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [
            'success' => false,
            'status' => 422,
            'message' => 'Invalid email address.'
        ];
    }

    if (strlen((string) $password) < 8) {
        return [
            'success' => false,
            'status' => 422,
            'message' => 'Password must be at least 8 characters long.'
        ];
    }

    $check = $pdo->prepare('SELECT id FROM users WHERE username = :username OR email = :email LIMIT 1');
    $check->execute([
        ':username' => $username,
        ':email' => $email
    ]);

    if ($check->fetch()) {
        return [
            'success' => false,
            'status' => 409,
            'message' => 'Username or email already exists.'
        ];
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare('INSERT INTO users (username, email, password) VALUES (:username, :email, :password)');
    $stmt->execute([
        ':username' => $username,
        ':email' => $email,
        ':password' => $hashedPassword
    ]);

    return [
        'success' => true,
        'status' => 201,
        'message' => 'User registered successfully.',
        'user' => [
            'id' => (int) $pdo->lastInsertId(),
            'username' => $username,
            'email' => $email
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
    $result = register_user($pdo, $data);
    http_response_code($result['status'] ?? 200);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    error_log('User registration failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'A database error occurred.'
    ], JSON_UNESCAPED_UNICODE);
}
