<?php

require_once __DIR__ . '/../../config/student_api_cors.php';
configureStudentApiCors();

header('Content-Type: application/json; charset=utf-8');

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Only POST or GET requests are allowed.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

http_response_code(200);
echo json_encode([
    'success' => true,
    'message' => 'Logout successful.'
], JSON_UNESCAPED_UNICODE);
