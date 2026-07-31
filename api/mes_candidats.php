<?php
require_once __DIR__ . '/../includes/require_user.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/permissions.php';

require_user();
header('Content-Type: application/json');

$pdo = get_db();
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$userId = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'candidat';
$roles = qa_user_role_keys($pdo, $userId, $role);

// Réservé aux comptes portant le rôle Formateur (recherche de leurs
// candidats assignés, voir candidate.php) ou au Super-Admin (vue de
// dépannage sur l'ensemble des candidats).
if (!in_array('formateur', $roles, true) && !in_array('super_admin', $roles, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => "Vous n'avez pas les droits nécessaires pour cette action"]);
    exit;
}
$isSuperAdmin = in_array('super_admin', $roles, true);

if ($action === 'search') {
    $q = trim($_GET['q'] ?? '');
    $params = [];
    $where = $isSuperAdmin ? '1=1' : 'id IN (SELECT candidat_id FROM candidat_formateurs WHERE formateur_id = ?)';
    if (!$isSuperAdmin) $params[] = $userId;
    if ($q !== '') {
        $where .= " AND (username LIKE ? OR nom LIKE ? OR prenom LIKE ? OR club LIKE ?)";
        $like = '%' . $q . '%';
        array_push($params, $like, $like, $like, $like);
    }
    $stmt = $pdo->prepare("SELECT id, username, nom, prenom, club FROM users WHERE $where ORDER BY nom, prenom, username LIMIT 50");
    $stmt->execute($params);
    echo json_encode(array_map(function ($row) {
        return [
            'id' => (int)$row['id'],
            'username' => $row['username'],
            'nom' => $row['nom'],
            'prenom' => $row['prenom'],
            'club' => $row['club'],
        ];
    }, $stmt->fetchAll()));
    exit;
}

if ($action === 'fiche') {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $u = $stmt->fetch();
    $assignedFormateurIds = $u ? array_column(qa_user_formateurs($pdo, $u['id']), 'id') : [];
    if (!$u || (!$isSuperAdmin && !in_array($userId, $assignedFormateurIds, true))) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Candidat introuvable']);
        exit;
    }

    $statsStmt = $pdo->prepare("SELECT COUNT(*) total,
        SUM(CASE WHEN reussi = 1 THEN 1 ELSE 0 END) reussies,
        AVG(CASE WHEN afficher_score = 1 AND score IS NOT NULL AND note_max > 0 THEN score / note_max * 100 ELSE NULL END) moyenne,
        MAX(completed_at) derniere
        FROM tentatives WHERE candidat = ? AND statut IN ('terminee', 'expiree')");
    $statsStmt->execute([$u['username']]);
    $stats = $statsStmt->fetch();

    echo json_encode([
        'id' => (int)$u['id'],
        'username' => $u['username'],
        'nom' => $u['nom'],
        'prenom' => $u['prenom'],
        'email' => $u['email'],
        'telephone' => $u['telephone'],
        'club' => $u['club'],
        'numero_licence' => $u['numero_licence'],
        'niveau_formation' => $u['niveau_formation'] ?? null,
        'option_pratique' => $u['option_pratique'] ?? null,
        'date_entree_formation' => $u['date_entree_formation'] ?? null,
        'stats' => [
            'total_tentatives' => (int)($stats['total'] ?? 0),
            'reussies' => (int)($stats['reussies'] ?? 0),
            'moyenne_pct' => $stats['moyenne'] !== null ? round((float)$stats['moyenne'], 1) : null,
            'derniere_tentative' => $stats['derniere'],
        ],
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Action inconnue']);
