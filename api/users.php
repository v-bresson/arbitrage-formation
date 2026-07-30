<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/permissions.php';

header('Content-Type: application/json');
$qaReadOnlyActions = ['list'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';
require_permission('users', in_array($action, $qaReadOnlyActions, true) ? 'read' : 'manage');

$pdo = get_db();

// Ordre de préséance des rôles connus pour déterminer le "rôle principal"
// (colonne users.role, conservée pour l'affichage et la compatibilité)
// quand un compte cumule plusieurs rôles cochés. Un rôle personnalisé non
// listé ici est traité au même rang que "formateur".
const QA_ROLE_RANK = ['candidat' => 0, 'formateur' => 1, 'membre_cra' => 2, 'super_admin' => 3];

function qa_primary_role($roles) {
    $best = $roles[0] ?? 'candidat';
    foreach ($roles as $r) {
        if ((QA_ROLE_RANK[$r] ?? 1) > (QA_ROLE_RANK[$best] ?? 1)) $best = $r;
    }
    return $best;
}

function qa_user_row_out($pdo, $row) {
    // Normalise un éventuel ancien rôle ('admin'/'user', avant la migration
    // roles_permissions_2026_07) vers son équivalent actuel : le select de
    // rôle et les permissions affichées restent corrects même si la base
    // n'a pas encore été migrée, et le prochain enregistrement de cette
    // fiche corrige la valeur en base au passage.
    $role = qa_normalize_role($row['role']);
    $roles = qa_user_role_keys($pdo, $row['id'], $role);
    return [
        'id' => (int)$row['id'],
        'username' => $row['username'],
        'role' => $role,
        'role_label' => qa_role_label($pdo, $role),
        'roles' => $roles,
        'role_labels' => array_map(fn($r) => qa_role_label($pdo, $r), $roles),
        'actif' => (bool)$row['actif'],
        'nom' => $row['nom'],
        'prenom' => $row['prenom'],
        'email' => $row['email'],
        'numero_licence' => $row['numero_licence'],
        'telephone' => $row['telephone'],
        'club' => $row['club'],
        'niveau_formation' => $row['niveau_formation'] ?? null,
        'option_pratique' => $row['option_pratique'] ?? null,
        'formateur_referent_id' => isset($row['formateur_referent_id']) && $row['formateur_referent_id'] !== null ? (int)$row['formateur_referent_id'] : null,
        'date_entree_formation' => $row['date_entree_formation'] ?? null,
        'created_at' => $row['created_at'],
        'permission_overrides' => qa_user_overrides($pdo, $row['id']),
        'effective_permissions' => qa_effective_permissions($pdo, $row['id'], $role),
    ];
}

function qa_super_admin_count($pdo, $excludeId = null) {
    if ($excludeId) {
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT ur.user_id) c FROM user_roles ur
            JOIN users u ON u.id = ur.user_id
            WHERE ur.role_key = 'super_admin' AND u.actif = 1 AND ur.user_id != ?");
        $stmt->execute([$excludeId]);
    } else {
        $stmt = $pdo->query("SELECT COUNT(DISTINCT ur.user_id) c FROM user_roles ur
            JOIN users u ON u.id = ur.user_id
            WHERE ur.role_key = 'super_admin' AND u.actif = 1");
    }
    return (int)$stmt->fetch()['c'];
}

if ($action === 'list') {
    $stmt = $pdo->query('SELECT * FROM users ORDER BY username');
    $users = array_map(fn($row) => qa_user_row_out($pdo, $row), $stmt->fetchAll());
    $roles = qa_all_roles($pdo);
    $roleKeys = array_column($roles, 'role_key');
    $roleLabels = array_combine($roleKeys, array_column($roles, 'label'));
    echo json_encode([
        'users' => $users,
        'roles' => $roles,
        'role_labels' => $roleLabels,
        'role_defaults' => array_combine($roleKeys, array_map(fn($r) => qa_role_default_permissions($pdo, $r), $roleKeys)),
        'sections' => QA_PERMISSION_SECTIONS,
    ]);
    exit;
}

if ($action === 'save') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = $body['id'] ?? null;
    $username = trim($body['username'] ?? '');
    $password = $body['password'] ?? '';
    $requestedRoles = is_array($body['roles'] ?? null) ? array_values(array_unique($body['roles'])) : [];
    $roles = array_values(array_filter($requestedRoles, fn($r) => qa_role_exists($pdo, $r)));
    if (!$roles) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Au moins un rôle doit être sélectionné']);
        exit;
    }
    $role = qa_primary_role($roles);
    $actif = !empty($body['actif']) ? 1 : 0;
    $nom = trim($body['nom'] ?? '') ?: null;
    $prenom = trim($body['prenom'] ?? '') ?: null;
    $email = trim($body['email'] ?? '') ?: null;
    $numeroLicence = trim($body['numero_licence'] ?? '') ?: null;
    $telephone = trim($body['telephone'] ?? '') ?: null;
    $club = trim($body['club'] ?? '') ?: null;
    $niveauFormation = in_array($body['niveau_formation'] ?? '', QA_NIVEAUX_FORMATION, true) ? $body['niveau_formation'] : null;
    $optionPratique = in_array($body['option_pratique'] ?? '', QA_OPTIONS_PRATIQUE, true) ? $body['option_pratique'] : null;
    $formateurReferentId = !empty($body['formateur_referent_id']) ? (int)$body['formateur_referent_id'] : null;
    $dateEntreeFormation = preg_match('/^\d{4}-\d{2}-\d{2}$/', $body['date_entree_formation'] ?? '') ? $body['date_entree_formation'] : null;

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

    // Empêche de désactiver ou retirer le rôle Super-Admin du dernier
    // compte qui le porte encore activement.
    if ($id && (!in_array('super_admin', $roles, true) || !$actif) && qa_super_admin_count($pdo, $id) === 0) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => "Impossible : il doit rester au moins un Super-Admin actif"]);
        exit;
    }

    $permissionOverrides = is_array($body['permission_overrides'] ?? null) ? $body['permission_overrides'] : [];

    try {
        if ($id) {
            if ($password !== '') {
                $stmt = $pdo->prepare('UPDATE users SET username=?, password_hash=?, role=?, actif=?, nom=?, prenom=?, email=?, numero_licence=?, telephone=?, club=?, niveau_formation=?, option_pratique=?, formateur_referent_id=?, date_entree_formation=? WHERE id=?');
                $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $role, $actif, $nom, $prenom, $email, $numeroLicence, $telephone, $club, $niveauFormation, $optionPratique, $formateurReferentId, $dateEntreeFormation, $id]);
            } else {
                $stmt = $pdo->prepare('UPDATE users SET username=?, role=?, actif=?, nom=?, prenom=?, email=?, numero_licence=?, telephone=?, club=?, niveau_formation=?, option_pratique=?, formateur_referent_id=?, date_entree_formation=? WHERE id=?');
                $stmt->execute([$username, $role, $actif, $nom, $prenom, $email, $numeroLicence, $telephone, $club, $niveauFormation, $optionPratique, $formateurReferentId, $dateEntreeFormation, $id]);
            }
        } else {
            $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, role, actif, nom, prenom, email, numero_licence, telephone, club, niveau_formation, option_pratique, formateur_referent_id, date_entree_formation) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $role, $actif, $nom, $prenom, $email, $numeroLicence, $telephone, $club, $niveauFormation, $optionPratique, $formateurReferentId, $dateEntreeFormation]);
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

    // Remplace les rôles cumulés de ce compte par ceux cochés dans le
    // formulaire (voir includes/permissions.php pour le calcul des
    // permissions effectives à partir de plusieurs rôles).
    $pdo->prepare('DELETE FROM user_roles WHERE user_id = ?')->execute([$id]);
    $insertRole = $pdo->prepare('INSERT IGNORE INTO user_roles (user_id, role_key) VALUES (?, ?)');
    foreach ($roles as $r) {
        $insertRole->execute([$id, $r]);
    }

    // Remplace les surcharges de permissions de cet utilisateur par celles
    // fournies (super_admin n'en a jamais besoin, accès total fixe). La
    // fenêtre d'édition n'expose plus ce réglage (retiré pour une
    // implémentation future) : tant que la clé permission_overrides n'est
    // pas envoyée, on ne touche pas aux surcharges existantes.
    if (array_key_exists('permission_overrides', $body)) {
        $pdo->prepare('DELETE FROM user_permissions WHERE user_id = ?')->execute([$id]);
        if (!in_array('super_admin', $roles, true)) {
            $insertOverride = $pdo->prepare('INSERT INTO user_permissions (user_id, section, level) VALUES (?, ?, ?)');
            foreach ($permissionOverrides as $section => $level) {
                if (!array_key_exists($section, QA_PERMISSION_SECTIONS)) continue;
                if (!in_array($level, QA_PERMISSION_LEVELS, true)) continue;
                $insertOverride->execute([$id, $section, $level]);
            }
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
        $stmt = $pdo->prepare("SELECT 1 FROM user_roles WHERE user_id = ? AND role_key = 'super_admin'");
        $stmt->execute([$id]);
        if ($stmt->fetch()) {
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
