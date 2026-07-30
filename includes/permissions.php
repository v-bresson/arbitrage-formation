<?php
// ===================================================================
// Rôles et permissions personnalisables par section admin.
//
// 4 rôles fixes : candidat, formateur, membre_cra, super_admin. Chaque
// rôle a un groupe de droits par défaut (une "level" par section admin :
// none / read / manage). super_admin a toujours accès total, non
// modifiable. Pour les 3 autres rôles, un Super-Admin peut, depuis la
// fiche d'un utilisateur précis, surcharger la permission d'une section
// sans toucher au groupe de droits du rôle (table user_permissions) :
// la surcharge ne s'applique qu'à cet utilisateur.
// ===================================================================

// Rôles fixes historiques (utilisés comme alias/valeurs de repli) ; la
// liste réelle des rôles disponibles est désormais dynamique, stockée en
// base (table `roles`) et personnalisable depuis l'onglet Rôles de
// l'administration — voir qa_all_roles()/qa_role_label() ci-dessous.
const QA_ROLES = ['candidat', 'formateur', 'membre_cra', 'super_admin'];

const QA_ROLE_LABELS = [
    'candidat' => 'Candidat',
    'formateur' => 'Formateur',
    'membre_cra' => 'Membre CRA',
    'super_admin' => 'Super-Admin',
];

const QA_PERMISSION_SECTIONS = [
    'questions' => 'Banque de questions',
    'quizzes' => 'Questionnaires',
    'attempts' => 'Résultats',
    'users' => 'Comptes utilisateurs',
    'tiles' => 'Tuiles',
    'maintenance' => 'Mise à jour système',
];

const QA_PERMISSION_LEVELS = ['none', 'read', 'manage'];

// Suivi de formation d'un candidat : niveau visé et option de pratique,
// éditables depuis la fiche complète d'un utilisateur (gestion des
// comptes, permission "users").
const QA_NIVEAUX_FORMATION = ['Assistant Arbitre', 'Arbitre Fédéral', 'Arbitre Duel'];
const QA_OPTIONS_PRATIQUE = ['Cible', 'Nat/3D', 'Campagne'];

// Alias de compatibilité : une installation pas encore migrée (voir
// includes/db.php, migration roles_permissions_2026_07) a encore des
// comptes avec l'ancien rôle 'admin'/'user' en base tant que la migration
// n'a pas été lancée volontairement par l'admin. On les traite comme
// super_admin/candidat pour les permissions, afin que ce même admin
// puisse justement se connecter et lancer la migration — sans ça,
// personne ne pourrait plus accéder à l'espace admin pour la déclencher.
function qa_normalize_role($role) {
    if ($role === 'admin') return 'super_admin';
    if ($role === 'user') return 'candidat';
    return $role ?: 'candidat';
}

// Liste des rôles disponibles (4 rôles historiques + rôles personnalisés
// ajoutés depuis l'onglet Rôles de l'administration).
function qa_all_roles($pdo) {
    return $pdo->query('SELECT role_key, label, is_system FROM roles ORDER BY is_system DESC, label')->fetchAll();
}

function qa_role_exists($pdo, $role) {
    $stmt = $pdo->prepare('SELECT 1 FROM roles WHERE role_key = ?');
    $stmt->execute([$role]);
    return (bool)$stmt->fetch();
}

function qa_role_label($pdo, $role) {
    $stmt = $pdo->prepare('SELECT label FROM roles WHERE role_key = ?');
    $stmt->execute([$role]);
    $row = $stmt->fetch();
    return $row ? $row['label'] : (QA_ROLE_LABELS[$role] ?? $role);
}

// Groupe de droits par défaut d'un rôle : super_admin a toujours accès
// total (non stocké, non modifiable) ; les autres rôles (historiques ou
// personnalisés) sont stockés dans la table role_permissions et éditables
// depuis l'onglet Rôles de l'administration.
function qa_role_default_permissions($pdo, $role) {
    $role = qa_normalize_role($role);
    if ($role === 'super_admin') {
        return array_fill_keys(array_keys(QA_PERMISSION_SECTIONS), 'manage');
    }
    $stmt = $pdo->prepare('SELECT section, level FROM role_permissions WHERE role_key = ?');
    $stmt->execute([$role]);
    $perms = array_fill_keys(array_keys(QA_PERMISSION_SECTIONS), 'none');
    foreach ($stmt->fetchAll() as $row) {
        if (isset($perms[$row['section']])) $perms[$row['section']] = $row['level'];
    }
    return $perms;
}

function qa_user_overrides($pdo, $userId) {
    $stmt = $pdo->prepare('SELECT section, level FROM user_permissions WHERE user_id = ?');
    $stmt->execute([$userId]);
    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $out[$row['section']] = $row['level'];
    }
    return $out;
}

// Rôles cumulés réellement assignés à ce compte (table user_roles) — un
// compte peut porter plusieurs rôles à la fois (ex. Candidat + Formateur).
// $role sert uniquement de repli si, par accident, le compte n'a encore
// aucune ligne (ne devrait pas arriver : includes/db.php comble ce cas à
// chaque requête).
function qa_user_role_keys($pdo, $userId, $role = null) {
    $stmt = $pdo->prepare('SELECT role_key FROM user_roles WHERE user_id = ?');
    $stmt->execute([$userId]);
    $roles = array_column($stmt->fetchAll(), 'role_key');
    return $roles ?: [qa_normalize_role($role)];
}

// Fusionne, section par section, le niveau le plus élevé parmi tous les
// rôles cumulés de ce compte, puis applique les éventuelles surcharges
// individuelles (super_admin : toujours tout, pas de surcharge possible).
function qa_effective_permissions($pdo, $userId, $role) {
    $roles = qa_user_role_keys($pdo, $userId, $role);
    if (in_array('super_admin', $roles, true)) {
        return qa_role_default_permissions($pdo, 'super_admin');
    }
    $perms = array_fill_keys(array_keys(QA_PERMISSION_SECTIONS), 'none');
    foreach ($roles as $r) {
        foreach (qa_role_default_permissions($pdo, $r) as $section => $level) {
            if (qa_permission_level_rank($level) > qa_permission_level_rank($perms[$section])) {
                $perms[$section] = $level;
            }
        }
    }
    foreach (qa_user_overrides($pdo, $userId) as $section => $level) {
        if (isset($perms[$section])) $perms[$section] = $level;
    }
    return $perms;
}

function qa_permission_level_rank($level) {
    $rank = array_search($level, QA_PERMISSION_LEVELS, true);
    return $rank === false ? 0 : $rank;
}

function qa_has_permission($pdo, $userId, $role, $section, $minLevel) {
    if (in_array('super_admin', qa_user_role_keys($pdo, $userId, $role), true)) return true;
    $perms = qa_effective_permissions($pdo, $userId, $role);
    $have = $perms[$section] ?? 'none';
    return qa_permission_level_rank($have) >= qa_permission_level_rank($minLevel);
}

// Vrai si l'utilisateur a accès à au moins une section admin (donc doit
// voir la tuile Administration sur le dashboard et pouvoir ouvrir
// admin/index.php), même sans être super_admin.
function qa_has_any_admin_access($pdo, $userId, $role) {
    if (in_array('super_admin', qa_user_role_keys($pdo, $userId, $role), true)) return true;
    foreach (qa_effective_permissions($pdo, $userId, $role) as $level) {
        if ($level !== 'none') return true;
    }
    return false;
}

// Tuile « Espace formateur » sur l'accueil : accès à au moins une section
// de suivi des candidats (questions/questionnaires/résultats), sans être
// super_admin (qui a sa propre tuile « Administration », superset).
function qa_has_formateur_access($pdo, $userId, $role) {
    if (in_array('super_admin', qa_user_role_keys($pdo, $userId, $role), true)) return false;
    $perms = qa_effective_permissions($pdo, $userId, $role);
    foreach (['questions', 'quizzes', 'attempts'] as $section) {
        if (($perms[$section] ?? 'none') !== 'none') return true;
    }
    return false;
}

// Tuile « Administration » sur l'accueil : gestion des comptes ou
// maintenance système (super_admin toujours inclus).
function qa_has_pure_admin_access($pdo, $userId, $role) {
    if (in_array('super_admin', qa_user_role_keys($pdo, $userId, $role), true)) return true;
    $perms = qa_effective_permissions($pdo, $userId, $role);
    foreach (['users', 'maintenance'] as $section) {
        if (($perms[$section] ?? 'none') !== 'none') return true;
    }
    return false;
}

// Vrai si ce compte porte le rôle Formateur (utilisé pour la page Espace
// candidat : un compte Formateur y voit la recherche de ses candidats
// assignés à la place de son propre contenu candidat, même s'il est aussi
// candidat — choix exclusif).
function qa_has_formateur_role($pdo, $userId, $role) {
    return in_array('formateur', qa_user_role_keys($pdo, $userId, $role), true);
}

// Garde d'accès pour les endpoints API admin, section par section — à la
// place d'une simple vérification de rôle : coupe court si la session
// n'a pas au moins $minLevel sur $section.
function require_permission($section, $minLevel) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Non authentifié']);
        exit;
    }
    $pdo = get_db();
    $role = $_SESSION['role'] ?? 'candidat';
    if (!qa_has_permission($pdo, (int)$_SESSION['user_id'], $role, $section, $minLevel)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => "Vous n'avez pas les droits nécessaires pour cette action"]);
        exit;
    }
}
