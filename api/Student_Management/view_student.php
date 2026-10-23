<?php

require_once __DIR__ . '/../../config/student_api_cors.php';
configureStudentApiCors();
require_once __DIR__ . '/../../config/student_api_auth.php';
requireStudentAuthentication();

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/database.php';


/*
 * Function: getStudentById
 * Purpose: Retrieve a single student by ID.
 */
function getStudentById(PDO $pdo, int $id): array
{
    if ($id <= 0) {
        return [
            'success' => false,
            'message' => 'Invalid student ID.'
        ];
    }

    $sql = "SELECT id, name, email, phone, address,
                   profile_picture, created_at
            FROM students
            WHERE id = :id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    $student = $stmt->fetch();

    if (!$student) {
        return [
            'success' => false,
            'message' => 'Student not found.'
        ];
    }

    return [
        'success' => true,
        'data' => $student
    ];
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Only GET requests are allowed.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if ($id === false || $id === null || $id <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'A valid positive student ID is required.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $result = getStudentById($pdo, $id);

    if (!$result['success']) {
        http_response_code(404);
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    error_log('Get student by ID failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'A database error occurred.'
    ], JSON_UNESCAPED_UNICODE);
}
