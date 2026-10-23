<?php

require_once __DIR__ . '/../../config/student_api_cors.php';
configureStudentApiCors();
require_once __DIR__ . '/../../config/student_api_auth.php';
requireStudentAuthentication();

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/database.php';

function delete_student(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare('DELETE FROM students WHERE id = :id');
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() === 0) {
        return [
            'success' => false,
            'message' => 'Student not found.'
        ];
    }

    return [
        'success' => true,
        'message' => 'Student deleted successfully.'
    ];
}

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Only DELETE requests are allowed.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$rawInput = file_get_contents('php://input');
$data = [];

if (stripos($contentType, 'application/json') !== false) {
    $data = json_decode($rawInput, true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid JSON request body.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
} else {
    parse_str($rawInput, $data);
}

$id = filter_var($_GET['id'] ?? $data['id'] ?? null, FILTER_VALIDATE_INT);
if ($id === false || $id === null || $id <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'A valid positive student ID is required.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $result = delete_student($pdo, $id);

    if (!$result['success']) {
        http_response_code(404);
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    error_log('Delete student failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'A database error occurred.'
    ], JSON_UNESCAPED_UNICODE);
}
