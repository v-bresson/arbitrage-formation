<?php
require_once __DIR__ . '/../includes/session-config.php';
session_start();

// ===================================================================
// Compte admin unique stocké dans data/credentials.json, créé lors de
// la première connexion. La gestion multi-comptes viendra plus tard.
// ===================================================================
$CREDENTIALS_FILE = __DIR__ . '/../data/credentials.json';

function qa_credentials_configured($file) {
    return file_exists($file);
}

function qa_read_credentials($file) {
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : null;
}

header('Content-Type: application/json');

if (!is_dir(dirname($CREDENTIALS_FILE))) {
    mkdir(dirname($CREDENTIALS_FILE), 0700, true);
}

$action = $_POST['action'] ?? '';

if ($action === 'status') {
    echo json_encode(['configured' => qa_credentials_configured($CREDENTIALS_FILE)]);
    exit;
}

if ($action === 'setup') {
    if (qa_credentials_configured($CREDENTIALS_FILE)) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Un compte admin est déjà configuré']);
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

    $credentials = [
        'username' => $username,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ];

    $written = file_put_contents($CREDENTIALS_FILE, json_encode($credentials, JSON_PRETTY_PRINT), LOCK_EX);
    if ($written === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => "Impossible d'enregistrer les identifiants"]);
        exit;
    }
    chmod($CREDENTIALS_FILE, 0600);

    $_SESSION['admin_authenticated'] = true;
    $_SESSION['admin_username'] = $username;
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'login') {
    if (!qa_credentials_configured($CREDENTIALS_FILE)) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Aucun compte configuré', 'setup_required' => true]);
        exit;
    }

    $credentials = qa_read_credentials($CREDENTIALS_FILE);
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($credentials && hash_equals($credentials['username'], $username) && password_verify($password, $credentials['password_hash'])) {
        $_SESSION['admin_authenticated'] = true;
        $_SESSION['admin_username'] = $username;
        echo json_encode(['success' => true]);
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
    echo json_encode(['authenticated' => isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Action inconnue']);
