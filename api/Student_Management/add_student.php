
<?php

require_once __DIR__ . '/../../config/student_api_cors.php';
configureStudentApiCors();
require_once __DIR__ . '/../../config/student_api_auth.php';
requireStudentAuthentication();

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/student_picture.php';

function add_student(PDO $pdo, array $data, array $files): array
{
    // 1. Get and clean the input data
    $name = trim($data['name'] ?? '');
    $email = trim($data['email'] ?? '');
    $phone = trim($data['phone'] ?? '');
    $address = trim($data['address'] ?? '');

    // 2. Validate required fields
    if ($name === '' || $email === '' || $phone === '' || $address === '') {
        return [
            'success' => false,
            'message' => 'All fields are required.'
        ];
    }

    // 3. Validate the email address
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [
            'success' => false,
            'message' => 'Invalid email address.'
        ];
    }

    $emailCheck = $pdo->prepare('SELECT id FROM students WHERE email = :email LIMIT 1');
    $emailCheck->execute([':email' => $email]);
    if ($emailCheck->fetch()) {
        return [
            'success' => false,
            'status' => 409,
            'message' => 'A student with this email address already exists.'
        ];
    }

    $picture = saveStudentPicture($files);
    if (!$picture['success']) {
        return $picture;
    }

    try {
        $sql = "INSERT INTO students (name, email, phone, address, profile_picture)
                VALUES (:name, :email, :phone, :address, :profile_picture)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':phone' => $phone,
            ':address' => $address,
            ':profile_picture' => $picture['path']
        ]);
    } catch (PDOException $e) {
        if (!removeStudentPicture($picture['path'])) {
            error_log('Unable to remove student picture after create failure.');
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

    return [
        'success' => true,
        'message' => 'Student created successfully.',
        'student_id' => (int) $pdo->lastInsertId(),
        'profile_picture' => $picture['path']
    ];
}

// Handle the HTTP request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Only POST requests are allowed.'
    ]);

    exit;
}

try {
    $result = add_student($pdo, $_POST, $_FILES);

    if (!$result['success']) {
        http_response_code($result['status'] ?? 422);
    } else {
        http_response_code(201);
    }

    echo json_encode(
        $result,
        JSON_UNESCAPED_UNICODE
    );

} catch (PDOException $e) {
    error_log('Create student failed: ' . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'A database error occurred.'
    ]);
}
