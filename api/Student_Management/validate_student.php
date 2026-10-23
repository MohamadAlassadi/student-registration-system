<?php

require_once __DIR__ . '/../../config/student_api_cors.php';
configureStudentApiCors();
require_once __DIR__ . '/../../config/student_api_auth.php';
requireStudentAuthentication();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Only POST requests are allowed.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid validation request.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

foreach (['name', 'email', 'phone', 'address'] as $field) {
    if (!isset($data[$field]) || !is_string($data[$field]) || trim($data[$field]) === '') {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Please fill in all required fields.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!filter_var(trim($data['email']), FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Enter a valid email address.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(200);
echo json_encode([
    'success' => true,
    'message' => 'All required fields are valid.'
], JSON_UNESCAPED_UNICODE);
