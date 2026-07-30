<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/permissions.php';

header('Content-Type: application/json');
$qaReadOnlyActions = ['list'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';
require_permission('users', in_array($action, $qaReadOnlyActions, true) ? 'read' : 'manage');

$pdo = get_db();

function qa_user_row_out($pdo, $row) {
    // Normalise un éventuel ancien rôle ('admin'/'user', avant la migration
    // roles_permissions_2026_07) vers son équivalent actuel : le select de
    // rôle et les permissions affichées restent corrects même si la base
    // n'a pas encore été migrée, et le prochain enregistrement de cette
    // fiche corrige la valeur en base au passage.
    $role = qa_normalize_role($row['role']);
    return [
        'id' => (int)$row['id'],
        'username' => $row['username'],
        'role' => $role,
        'role_label' => QA_ROLE_LABELS[$role] ?? $role,
        'actif' => (bool)$row['actif'],
        'nom' => $row['nom'],
        'prenom' => $row['prenom'],
        'email' => $row['email'],
        'numero_licence' => $row['numero_licence'],
        'telephone' => $row['telephone'],
        'club' => $row['club'],
        'created_at' => $row['created_at'],
        'permission_overrides' => qa_user_overrides($pdo, $row['id']),
        'effective_permissions' => qa_effective_permissions($pdo, $row['id'], $role),
    ];
}

function qa_super_admin_count($pdo, $excludeId = null) {
    if ($excludeId) {
        $stmt = $pdo->prepare("SELECT COUNT(*) c FROM users WHERE role IN ('super_admin', 'admin') AND actif=1 AND id != ?");
        $stmt->execute([$excludeId]);
    } else {
        $stmt = $pdo->query("SELECT COUNT(*) c FROM users WHERE role IN ('super_admin', 'admin') AND actif=1");
    }
    return (int)$stmt->fetch()['c'];
}

if ($action === 'list') {
    $stmt = $pdo->query('SELECT * FROM users ORDER BY username');
    $users = array_map(fn($row) => qa_user_row_out($pdo, $row), $stmt->fetchAll());
    echo json_encode([
        'users' => $users,
        'role_labels' => QA_ROLE_LABELS,
        'role_defaults' => array_combine(QA_ROLES, array_map('qa_role_default_permissions', QA_ROLES)),
        'sections' => QA_PERMISSION_SECTIONS,
    ]);
    exit;
}

if ($action === 'save') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = $body['id'] ?? null;
    $username = trim($body['username'] ?? '');
    $password = $body['password'] ?? '';
    $role = in_array($body['role'] ?? '', QA_ROLES, true) ? $body['role'] : 'candidat';
    $actif = !empty($body['actif']) ? 1 : 0;
    $nom = trim($body['nom'] ?? '') ?: null;
    $prenom = trim($body['prenom'] ?? '') ?: null;
    $email = trim($body['email'] ?? '') ?: null;
    $numeroLicence = trim($body['numero_licence'] ?? '') ?: null;
    $telephone = trim($body['telephone'] ?? '') ?: null;
    $club = trim($body['club'] ?? '') ?: null;

    if ($username === '' || strlen($username) < 3) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => "L'identifiant doit contenir au moins 3 caractères"]);
        exit;
    }
    if (!$id && strlen($password) < 8) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Le mot de passe doit contenir au moins 8 caractères']);
        exit;
    }
    if ($password !== '' && strlen($password) < 8) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Le mot de passe doit contenir au moins 8 caractères']);
        exit;
    }

    // Empêche de désactiver ou rétrograder le dernier super-admin actif
    if ($id && ($role !== 'super_admin' || !$actif) && qa_super_admin_count($pdo, $id) === 0) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => "Impossible : il doit rester au moins un Super-Admin actif"]);
        exit;
    }

    $permissionOverrides = is_array($body['permission_overrides'] ?? null) ? $body['permission_overrides'] : [];

    try {
        if ($id) {
            if ($password !== '') {
                $stmt = $pdo->prepare('UPDATE users SET username=?, password_hash=?, role=?, actif=?, nom=?, prenom=?, email=?, numero_licence=?, telephone=?, club=? WHERE id=?');
                $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $role, $actif, $nom, $prenom, $email, $numeroLicence, $telephone, $club, $id]);
            } else {
                $stmt = $pdo->prepare('UPDATE users SET username=?, role=?, actif=?, nom=?, prenom=?, email=?, numero_licence=?, telephone=?, club=? WHERE id=?');
                $stmt->execute([$username, $role, $actif, $nom, $prenom, $email, $numeroLicence, $telephone, $club, $id]);
            }
        } else {
            $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, role, actif, nom, prenom, email, numero_licence, telephone, club) VALUES (?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $role, $actif, $nom, $prenom, $email, $numeroLicence, $telephone, $club]);
            $id = $pdo->lastInsertId();
        }
    } catch (PDOException $e) {
        if ((int)($e->errorInfo[1] ?? 0) === 1054) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => "La base de données n'est pas à jour : un administrateur doit lancer la mise à jour de la base depuis Administration > Mise à jour système avant d'enregistrer une fiche profil complète."]);
            exit;
        }
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Cet identifiant est déjà utilisé']);
        exit;
    }

    // Remplace les surcharges de permissions de cet utilisateur par celles
    // fournies (super_admin n'en a jamais besoin, accès total fixe).
    $pdo->prepare('DELETE FROM user_permissions WHERE user_id = ?')->execute([$id]);
    if ($role !== 'super_admin') {
        $insertOverride = $pdo->prepare('INSERT INTO user_permissions (user_id, section, level) VALUES (?, ?, ?)');
        foreach ($permissionOverrides as $section => $level) {
            if (!array_key_exists($section, QA_PERMISSION_SECTIONS)) continue;
            if (!in_array($level, QA_PERMISSION_LEVELS, true)) continue;
            $insertOverride->execute([$id, $section, $level]);
        }
    }

    $stmt = $pdo->prepare('SELECT * FROM users WHERE id=?');
    $stmt->execute([$id]);
    echo json_encode(['success' => true, 'user' => qa_user_row_out($pdo, $stmt->fetch())]);
    exit;
}

if ($action === 'delete') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($body['id'] ?? 0);

    if (qa_super_admin_count($pdo, $id) === 0) {
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id=?");
        $stmt->execute([$id]);
        $target = $stmt->fetch();
        if ($target && qa_normalize_role($target['role']) === 'super_admin') {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => "Impossible : il doit rester au moins un Super-Admin actif"]);
            exit;
        }
    }

    $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Action inconnue']);
