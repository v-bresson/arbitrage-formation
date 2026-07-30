<?php
require_once __DIR__ . '/../includes/session-config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/db.php';

// ===================================================================
// Authentification unifiée : un seul point d'entrée (login) pour tous
// les utilisateurs de l'application. Le rôle ('admin' ou 'user') est
// stocké en session et détermine l'accès à l'espace d'administration
// et aux tuiles réservées.
// ===================================================================

header('Content-Type: application/json');
$pdo = get_db();
$action = $_POST['action'] ?? '';

function qa_users_count($pdo) {
    return (int)$pdo->query('SELECT COUNT(*) c FROM users')->fetch()['c'];
}

if ($action === 'status') {
    echo json_encode(['configured' => qa_users_count($pdo) > 0]);
    exit;
}

if ($action === 'setup') {
    if (qa_users_count($pdo) > 0) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Un compte est déjà configuré']);
        exit;
    }

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || strlen($username) < 3) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => "L'identifiant doit contenir au moins 3 caractères"]);
        exit;
    }
    if (strlen($password) < 8) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Le mot de passe doit contenir au moins 8 caractères']);
        exit;
    }

    $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, role, actif) VALUES (?, ?, ?, 1)');
    $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), 'admin']);

    $_SESSION['user_id'] = (int)$pdo->lastInsertId();
    $_SESSION['username'] = $username;
    $_SESSION['role'] = 'admin';
    echo json_encode(['success' => true, 'role' => 'admin']);
    exit;
}

if ($action === 'login') {
    if (qa_users_count($pdo) === 0) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Aucun compte configuré', 'setup_required' => true]);
        exit;
    }

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? AND actif = 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        echo json_encode(['success' => true, 'role' => $user['role']]);
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Identifiant ou mot de passe incorrect']);
    }
    exit;
}

if ($action === 'logout') {
    $_SESSION = [];
    session_destroy();
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'check') {
    if (!empty($_SESSION['user_id'])) {
        echo json_encode([
            'authenticated' => true,
            'username' => $_SESSION['username'],
            'role' => $_SESSION['role'],
        ]);
    } else {
        echo json_encode(['authenticated' => false]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Action inconnue']);
