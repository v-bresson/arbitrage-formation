<?php
require_once __DIR__ . '/session-config.php';
session_start();

function require_admin() {
    if (empty($_SESSION['admin_authenticated'])) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Non authentifié']);
        exit;
    }
}
