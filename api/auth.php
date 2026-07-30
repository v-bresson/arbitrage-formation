<?php
require_once __DIR__ . '/../includes/session-config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/permissions.php';

// ===================================================================
// Authentification unifiée : un seul point d'entrée (login) pour tous
// les utilisateurs de l'application. Le rôle (candidat/formateur/
// membre_cra/super_admin) est stocké en session ; les permissions
// effectives (groupe de droits du rôle + surcharges individuelles,
// voir includes/permissions.php) déterminent l'accès à l'espace
// d'administration et à ses sections.
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
    $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), 'super_admin']);
    $newId = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT IGNORE INTO user_roles (user_id, role_key) VALUES (?, ?)')->execute([$newId, 'super_admin']);

    $_SESSION['user_id'] = $newId;
    $_SESSION['username'] = $username;
    $_SESSION['role'] = 'super_admin';
    echo json_encode(['success' => true, 'role' => 'super_admin']);
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
        $role = qa_normalize_role($user['role']);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $role;
        echo json_encode(['success' => true, 'role' => $role]);
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
        $role = $_SESSION['role'] ?? 'candidat';
        $profile = [];
        if (qa_column_exists($pdo, 'users', 'nom')) {
            $stmt = $pdo->prepare('SELECT nom, prenom, club, numero_licence FROM users WHERE id = ?');
            $stmt->execute([(int)$_SESSION['user_id']]);
            $profile = $stmt->fetch() ?: [];
        }
        echo json_encode([
            'authenticated' => true,
            'username' => $_SESSION['username'],
            'role' => $role,
            'role_label' => qa_role_label($pdo, $role),
            'roles' => qa_user_role_keys($pdo, (int)$_SESSION['user_id'], $role),
            'permissions' => qa_effective_permissions($pdo, (int)$_SESSION['user_id'], $role),
            'has_admin_access' => qa_has_any_admin_access($pdo, (int)$_SESSION['user_id'], $role),
            'has_formateur_access' => qa_has_formateur_access($pdo, (int)$_SESSION['user_id'], $role),
            'has_pure_admin_access' => qa_has_pure_admin_access($pdo, (int)$_SESSION['user_id'], $role),
            'nom' => $profile['nom'] ?? null,
            'prenom' => $profile['prenom'] ?? null,
            'club' => $profile['club'] ?? null,
            'numero_licence' => $profile['numero_licence'] ?? null,
        ]);
    } else {
        echo json_encode(['authenticated' => false]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Action inconnue']);
