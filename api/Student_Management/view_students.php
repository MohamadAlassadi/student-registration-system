<?php

require_once __DIR__ . '/../../config/student_api_cors.php';
configureStudentApiCors();
require_once __DIR__ . '/../../config/student_api_auth.php';
requireStudentAuthentication();

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/database.php';

function view_students(PDO $pdo, int $page, string $search): array
{
    $perPage = 10;
    $offset = ($page - 1) * $perPage;
    $searchPattern = '%' . $search . '%';
    $where = '';
    $params = [];

    if ($search !== '') {
        $where = ' WHERE name LIKE :name_search OR email LIKE :email_search OR phone LIKE :phone_search';
        $params = [
            ':name_search' => $searchPattern,
            ':email_search' => $searchPattern,
            ':phone_search' => $searchPattern
        ];
    }

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM students' . $where);
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $sql = "SELECT id, name, email, phone, address,
                   profile_picture, created_at
            FROM students" . $where . "
            ORDER BY id DESC
            LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $name => $value) {
        $stmt->bindValue($name, $value, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $students = $stmt->fetchAll();

    return [
        'success' => true,
        'count' => count($students),
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => (int) ceil($total / $perPage),
        'has_previous' => $page > 1,
        'has_next' => $offset + count($students) < $total,
        'search' => $search,
        'data' => $students
    ];
}

// Handle the HTTP request
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Only GET requests are allowed.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
    $search = $_GET['search'] ?? '';

    if (
        $page === false
        || $page < 1
        || $page > intdiv(PHP_INT_MAX, 10) + 1
        || !is_string($search)
    ) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Page must be a valid positive integer and search must be a string.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $result = view_students($pdo, $page, trim($search));
    http_response_code(200);

    echo json_encode(
        $result,
        JSON_UNESCAPED_UNICODE
    );
} catch (PDOException $e) {
    error_log('Student operation failed: ' . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'A database error occurred.'
    ], JSON_UNESCAPED_UNICODE);
}