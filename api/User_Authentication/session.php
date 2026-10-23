<?php

require_once __DIR__ . '/../../config/student_api_cors.php';
configureStudentApiCors();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Only GET requests are allowed.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

session_start();

$authenticated = isset($_SESSION['user_id']);
echo json_encode([
    'success' => true,
    'authenticated' => $authenticated,
    'user' => $authenticated
        ? [
            'id' => (int) $_SESSION['user_id'],
            'username' => (string) ($_SESSION['username'] ?? ''),
            'email' => (string) ($_SESSION['email'] ?? '')
        ]
        : null
], JSON_UNESCAPED_UNICODE);
