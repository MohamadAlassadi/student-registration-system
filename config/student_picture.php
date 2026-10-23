<?php

function saveStudentPicture(array $files): array
{
    if (!isset($files['profile_picture'])) {
        return ['success' => true, 'path' => null];
    }

    $file = $files['profile_picture'];
    if (!is_array($file) || !isset($file['error'])) {
        return [
            'success' => false,
            'status' => 400,
            'message' => 'Invalid profile picture upload.'
        ];
    }

    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => true, 'path' => null];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $message = $file['error'] === UPLOAD_ERR_INI_SIZE
            || $file['error'] === UPLOAD_ERR_FORM_SIZE
            ? 'The profile picture must not exceed 5 MB.'
            : 'The profile picture could not be uploaded.';

        return [
            'success' => false,
            'status' => 422,
            'message' => $message
        ];
    }

    if (
        !isset($file['tmp_name'], $file['size'])
        || !is_string($file['tmp_name'])
        || !is_uploaded_file($file['tmp_name'])
        || $file['size'] <= 0
        || $file['size'] > 5 * 1024 * 1024
    ) {
        return [
            'success' => false,
            'status' => 422,
            'message' => 'The profile picture must be a valid image no larger than 5 MB.'
        ];
    }

    $imageInfo = getimagesize($file['tmp_name']);
    $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp'
    ];

    if (
        $imageInfo === false
        || !isset($extensions[$mimeType])
        || ($imageInfo['mime'] ?? null) !== $mimeType
    ) {
        return [
            'success' => false,
            'status' => 422,
            'message' => 'The profile picture must be a JPEG, PNG, GIF, or WebP image.'
        ];
    }

    $uploadDirectory = __DIR__ . '/../uploads/students';
    if (
        !is_dir($uploadDirectory)
        && !mkdir($uploadDirectory, 0755, true)
        && !is_dir($uploadDirectory)
    ) {
        error_log('Unable to create student picture upload directory.');
        return [
            'success' => false,
            'status' => 500,
            'message' => 'The profile picture could not be saved.'
        ];
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mimeType];
    if (!move_uploaded_file($file['tmp_name'], $uploadDirectory . '/' . $filename)) {
        error_log('Unable to move uploaded student picture.');
        return [
            'success' => false,
            'status' => 500,
            'message' => 'The profile picture could not be saved.'
        ];
    }

    return [
        'success' => true,
        'path' => 'uploads/students/' . $filename
    ];
}

function removeStudentPicture(?string $path): bool
{
    if (
        $path === null
        || !preg_match(
            '/\Auploads\/students\/[a-f0-9]{32}\.(?:jpg|png|gif|webp)\z/',
            $path
        )
    ) {
        return true;
    }

    $fullPath = __DIR__ . '/../' . $path;
    return !is_file($fullPath) || unlink($fullPath);
}
