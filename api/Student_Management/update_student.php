<?php

require_once __DIR__ . '/../../config/student_api_cors.php';
configureStudentApiCors();
require_once __DIR__ . '/../../config/student_api_auth.php';
requireStudentAuthentication();

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/student_picture.php';

function update_student(PDO $pdo, int $id, array $data, array $files): array
{
    $check = $pdo->prepare('SELECT profile_picture FROM students WHERE id = :id');
    $check->execute([':id' => $id]);
    $student = $check->fetch();

    if (!$student) {
        return [
            'success' => false,
            'message' => 'Student not found.'
        ];
    }

    $columns = [
        'name' => 'name',
        'email' => 'email',
        'phone' => 'phone',
        'address' => 'address'
    ];
    $setClauses = [];
    $params = [':id' => $id];

    foreach ($columns as $field => $column) {
        if (!array_key_exists($field, $data)) {
            continue;
        }

        if (!is_string($data[$field]) || trim($data[$field]) === '') {
            return [
                'success' => false,
                'message' => ucfirst($field) . ' cannot be empty.'
            ];
        }

        $value = trim($data[$field]);
        if ($field === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'message' => 'Invalid email address.'
            ];
        }

        if ($field === 'email') {
            $emailCheck = $pdo->prepare(
                'SELECT id FROM students WHERE email = :email AND id <> :id LIMIT 1'
            );
            $emailCheck->execute([
                ':email' => $value,
                ':id' => $id
            ]);
            if ($emailCheck->fetch()) {
                return [
                    'success' => false,
                    'status' => 409,
                    'message' => 'A student with this email address already exists.'
                ];
            }
        }

        $setClauses[] = "{$column} = :{$field}";
        $params[":{$field}"] = $value;
    }

    $picture = saveStudentPicture($files);
    if (!$picture['success']) {
        return $picture;
    }

    if ($picture['path'] !== null) {
        $setClauses[] = 'profile_picture = :profile_picture';
        $params[':profile_picture'] = $picture['path'];
    }

    if ($setClauses === []) {
        return [
            'success' => false,
            'message' => 'Provide at least one field or a profile picture to update.'
        ];
    }

    try {
        $sql = 'UPDATE students SET ' . implode(', ', $setClauses) . ' WHERE id = :id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    } catch (PDOException $e) {
        if (!removeStudentPicture($picture['path'])) {
            error_log('Unable to remove student picture after update failure.');
        }

        if (($e->errorInfo[1] ?? null) === 1062) {
            return [
                'success' => false,
                'status' => 409,
                'message' => 'A student with this email address already exists.'
            ];
        }

        throw $e;
    }

    if (
        $picture['path'] !== null
        && !removeStudentPicture($student['profile_picture'])
    ) {
        error_log('Unable to remove the previous student picture.');
    }

    return [
        'success' => true,
        'message' => 'Student updated successfully.',
        'profile_picture' => $picture['path'] ?? $student['profile_picture']
    ];
}

if (!in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'POST'], true)) {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Only PUT or POST requests are allowed.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

if (stripos($contentType, 'multipart/form-data') !== false) {
    if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'PHP does not process multipart/form-data for PUT requests. In Postman, use POST with Body > form-data.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $data = $_POST;
    if ($data === [] && $_FILES === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        http_response_code(413);
        echo json_encode([
            'success' => false,
            'message' => 'The request body is empty or exceeds the PHP upload limits. Check post_max_size and upload_max_filesize.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
} elseif (stripos($contentType, 'application/json') !== false) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid JSON request body.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = $_POST;
} else {
    $rawInput = file_get_contents('php://input');
    parse_str($rawInput, $data);
}

$id = filter_var($data['id'] ?? $_GET['id'] ?? null, FILTER_VALIDATE_INT);
if ($id === false || $id === null || $id <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'A valid positive student ID is required.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $result = update_student($pdo, $id, $data, $_FILES);

    if (!$result['success']) {
        http_response_code(
            $result['status']
            ?? ($result['message'] === 'Student not found.' ? 404 : 422)
        );
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    error_log('Update student failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'A database error occurred.'
    ], JSON_UNESCAPED_UNICODE);
}
