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
// candidats assignés, voir formateur.php), Membre CRA (vue de l'ensemble
// des candidats de la région) ou Super-Admin (recherche et fiche en
// lecture seule de n'importe quel candidat, voir candidate.php).
if (!in_array('formateur', $roles, true) && !in_array('membre_cra', $roles, true) && !in_array('super_admin', $roles, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => "Vous n'avez pas les droits nécessaires pour cette action"]);
    exit;
}
// Membre CRA et Super-Admin voient l'ensemble des candidats ; un Formateur
// reste borné à ses seuls candidats assignés (candidat_formateurs).
$seeAllCandidats = in_array('membre_cra', $roles, true) || in_array('super_admin', $roles, true);

if ($action === 'search') {
    $q = trim($_GET['q'] ?? '');
    $params = [];
    $where = $seeAllCandidats ? '1=1' : 'id IN (SELECT candidat_id FROM candidat_formateurs WHERE formateur_id = ?)';
    if (!$seeAllCandidats) $params[] = $userId;
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
    if (!$u || (!$seeAllCandidats && !in_array($userId, $assignedFormateurIds, true))) {
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

// Liste complète des candidats visibles (bornée aux candidats assignés pour
// un Formateur simple) — alimente la tuile "Liste des candidats" de
// formateur.php (formateur-candidats.php), en lecture seule.
if ($action === 'list') {
    $params = [];
    $sql = "SELECT DISTINCT u.* FROM users u JOIN user_roles ur ON ur.user_id = u.id AND ur.role_key = 'candidat'";
    if (!$seeAllCandidats) {
        $sql .= ' JOIN candidat_formateurs cf ON cf.candidat_id = u.id AND cf.formateur_id = ?';
        $params[] = $userId;
    }
    $sql .= ' ORDER BY u.nom, u.prenom, u.username';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode(array_map(function ($row) use ($pdo) {
        return [
            'id' => (int)$row['id'],
            'username' => $row['username'],
            'nom' => $row['nom'],
            'prenom' => $row['prenom'],
            'club' => $row['club'],
            'niveau_formation' => $row['niveau_formation'] ?? null,
            'option_pratique' => $row['option_pratique'] ?? null,
            'formateurs' => qa_user_formateurs($pdo, $row['id']),
            'actif' => (bool)$row['actif'],
        ];
    }, $stmt->fetchAll()));
    exit;
}

// Chiffres pour les tuiles de l'Espace formateur : répartition des
// candidats visibles par niveau de formation, et nombre de tentatives
// terminées en attente de correction/publication (même logique que
// l'onglet Résultats de l'administration, voir admin/app.js « à corriger »).
if ($action === 'dashboard_stats') {
    $params = [];
    $sql = "SELECT u.niveau_formation, u.username FROM users u JOIN user_roles ur ON ur.user_id = u.id AND ur.role_key = 'candidat'";
    if (!$seeAllCandidats) {
        $sql .= ' JOIN candidat_formateurs cf ON cf.candidat_id = u.id AND cf.formateur_id = ?';
        $params[] = $userId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $counts = ['Assistant Arbitre' => 0, 'Arbitre Fédéral' => 0, 'Arbitre Duel' => 0];
    $usernames = [];
    foreach ($rows as $row) {
        if (isset($counts[$row['niveau_formation']])) $counts[$row['niveau_formation']]++;
        $usernames[] = $row['username'];
    }

    $aCorriger = 0;
    if ($usernames && qa_has_permission($pdo, $userId, $role, 'attempts', 'read')) {
        $placeholders = implode(',', array_fill(0, count($usernames), '?'));
        $stmt2 = $pdo->prepare("SELECT COUNT(*) c FROM tentatives WHERE statut != 'en_cours' AND resultat_publie = 0 AND candidat IN ($placeholders)");
        $stmt2->execute($usernames);
        $aCorriger = (int)$stmt2->fetch()['c'];
    }

    echo json_encode([
        'candidats_total' => count($rows),
        'candidats_assistant' => $counts['Assistant Arbitre'],
        'candidats_federal' => $counts['Arbitre Fédéral'],
        'candidats_duel' => $counts['Arbitre Duel'],
        'a_corriger' => $aCorriger,
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Action inconnue']);
