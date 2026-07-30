<?php
require_once __DIR__ . '/session-config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

function require_user() {
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Non authentifié']);
        exit;
    }
}
