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
    return in_array($role, QA_ROLES, true) ? $role : 'candidat';
}

function qa_role_default_permissions($role) {
    $role = qa_normalize_role($role);
    if ($role === 'super_admin') {
        return array_fill_keys(array_keys(QA_PERMISSION_SECTIONS), 'manage');
    }
    if ($role === 'formateur') {
        return [
            'questions' => 'manage',
            'quizzes' => 'manage',
            'attempts' => 'read',
            'users' => 'none',
            'tiles' => 'none',
            'maintenance' => 'none',
        ];
    }
    if ($role === 'membre_cra') {
        return [
            'questions' => 'read',
            'quizzes' => 'read',
            'attempts' => 'manage',
            'users' => 'none',
            'tiles' => 'none',
            'maintenance' => 'none',
        ];
    }
    // candidat
    return array_fill_keys(array_keys(QA_PERMISSION_SECTIONS), 'none');
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

// Fusionne le groupe de droits du rôle avec les éventuelles surcharges
// individuelles de cet utilisateur (super_admin : toujours tout, pas de
// surcharge possible).
function qa_effective_permissions($pdo, $userId, $role) {
    $role = qa_normalize_role($role);
    if ($role === 'super_admin') return qa_role_default_permissions('super_admin');
    $perms = qa_role_default_permissions($role);
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
    if (qa_normalize_role($role) === 'super_admin') return true;
    $perms = qa_effective_permissions($pdo, $userId, $role);
    $have = $perms[$section] ?? 'none';
    return qa_permission_level_rank($have) >= qa_permission_level_rank($minLevel);
}

// Vrai si l'utilisateur a accès à au moins une section admin (donc doit
// voir la tuile Administration sur le dashboard et pouvoir ouvrir
// admin/index.php), même sans être super_admin.
function qa_has_any_admin_access($pdo, $userId, $role) {
    if (qa_normalize_role($role) === 'super_admin') return true;
    foreach (qa_effective_permissions($pdo, $userId, $role) as $level) {
        if ($level !== 'none') return true;
    }
    return false;
}

// Tuile « Espace formateur » sur l'accueil : accès à au moins une section
// de suivi des candidats (questions/questionnaires/résultats), sans être
// super_admin (qui a sa propre tuile « Administration », superset).
function qa_has_formateur_access($pdo, $userId, $role) {
    $role = qa_normalize_role($role);
    if ($role === 'super_admin') return false;
    $perms = qa_effective_permissions($pdo, $userId, $role);
    foreach (['questions', 'quizzes', 'attempts'] as $section) {
        if (($perms[$section] ?? 'none') !== 'none') return true;
    }
    return false;
}

// Tuile « Administration » sur l'accueil : gestion des comptes ou
// maintenance système (super_admin toujours inclus).
function qa_has_pure_admin_access($pdo, $userId, $role) {
    $role = qa_normalize_role($role);
    if ($role === 'super_admin') return true;
    $perms = qa_effective_permissions($pdo, $userId, $role);
    foreach (['users', 'maintenance'] as $section) {
        if (($perms[$section] ?? 'none') !== 'none') return true;
    }
    return false;
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
