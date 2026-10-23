<?php

function requireStudentAuthentication(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'authenticated' => false,
            'message' => 'Please log in to access student records.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
