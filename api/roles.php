<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/permissions.php';

// ===================================================================
// Gestion des rôles et de leurs permissions par défaut (onglet Rôles de
// l'administration, réservé à la permission "users" comme le reste de
// la gestion des comptes). super_admin est toujours affiché mais son
// accès total n'est jamais modifiable ni stocké ici (voir
// includes/permissions.php, qa_role_default_permissions).
// ===================================================================

header('Content-Type: application/json');
$qaReadOnlyActions = ['list'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';
require_permission('users', in_array($action, $qaReadOnlyActions, true) ? 'read' : 'manage');

$pdo = get_db();

function qa_role_slug($label) {
    $slug = strtolower(trim($label));
    $slug = preg_replace('/[^a-z0-9]+/u', '_', $slug);
    return trim($slug, '_');
}

if ($action === 'list') {
    $roles = qa_all_roles($pdo);
    $out = array_map(function ($role) use ($pdo) {
        return [
            'role_key' => $role['role_key'],
            'label' => $role['label'],
            'is_system' => (bool)$role['is_system'],
            'permissions' => qa_role_default_permissions($pdo, $role['role_key']),
        ];
    }, $roles);
    echo json_encode(['roles' => $out, 'sections' => QA_PERMISSION_SECTIONS]);
    exit;
}

if ($action === 'save') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $roleKey = trim($body['role_key'] ?? '');
    $label = trim($body['label'] ?? '');
    $permissions = is_array($body['permissions'] ?? null) ? $body['permissions'] : [];
    $isNew = !$roleKey;

    if ($label === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Le nom du rôle est requis']);
        exit;
    }

    if ($isNew) {
        $roleKey = qa_role_slug($label);
        if ($roleKey === '' || in_array($roleKey, ['admin', 'user'], true)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Nom de rôle invalide']);
            exit;
        }
        if (qa_role_exists($pdo, $roleKey)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Un rôle avec un nom équivalent existe déjà']);
            exit;
        }
        $pdo->prepare('INSERT INTO roles (role_key, label, is_system) VALUES (?, ?, 0)')->execute([$roleKey, $label]);
    } else {
        $stmt = $pdo->prepare('SELECT * FROM roles WHERE role_key = ?');
        $stmt->execute([$roleKey]);
        $existing = $stmt->fetch();
        if (!$existing) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Rôle introuvable']);
            exit;
        }
        if ($roleKey === 'super_admin') {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => "Le rôle Super-Admin a toujours un accès total : il n'est pas modifiable"]);
            exit;
        }
        // Le nom des rôles historiques (candidat/formateur/membre CRA) reste
        // fixe pour ne pas casser les alias/libellés utilisés ailleurs dans
        // l'application ; seules leurs permissions sont modifiables.
        if (!$existing['is_system']) {
            $pdo->prepare('UPDATE roles SET label = ? WHERE role_key = ?')->execute([$label, $roleKey]);
        }
    }

    if ($roleKey !== 'super_admin') {
        $pdo->prepare('DELETE FROM role_permissions WHERE role_key = ?')->execute([$roleKey]);
        $insert = $pdo->prepare('INSERT INTO role_permissions (role_key, section, level) VALUES (?, ?, ?)');
        foreach ($permissions as $section => $level) {
            if (!array_key_exists($section, QA_PERMISSION_SECTIONS)) continue;
            if (!in_array($level, QA_PERMISSION_LEVELS, true)) continue;
            $insert->execute([$roleKey, $section, $level]);
        }
    }

    $stmt = $pdo->prepare('SELECT role_key, label, is_system FROM roles WHERE role_key = ?');
    $stmt->execute([$roleKey]);
    $saved = $stmt->fetch();
    echo json_encode([
        'success' => true,
        'role' => [
            'role_key' => $saved['role_key'],
            'label' => $saved['label'],
            'is_system' => (bool)$saved['is_system'],
            'permissions' => qa_role_default_permissions($pdo, $roleKey),
        ],
    ]);
    exit;
}

if ($action === 'delete') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $roleKey = trim($body['role_key'] ?? '');

    $stmt = $pdo->prepare('SELECT * FROM roles WHERE role_key = ?');
    $stmt->execute([$roleKey]);
    $role = $stmt->fetch();
    if (!$role) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Rôle introuvable']);
        exit;
    }
    if ($role['is_system']) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Ce rôle est un rôle système et ne peut pas être supprimé']);
        exit;
    }
    $stmt = $pdo->prepare('SELECT COUNT(*) c FROM user_roles WHERE role_key = ?');
    $stmt->execute([$roleKey]);
    if ((int)$stmt->fetch()['c'] > 0) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Impossible : des comptes utilisent encore ce rôle']);
        exit;
    }

    $pdo->prepare('DELETE FROM role_permissions WHERE role_key = ?')->execute([$roleKey]);
    $pdo->prepare('DELETE FROM roles WHERE role_key = ?')->execute([$roleKey]);
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Action inconnue']);
