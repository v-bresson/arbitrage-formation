<?php
require_once __DIR__ . '/../includes/require_user.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/permissions.php';

require_user();
header('Content-Type: application/json');

$pdo = get_db();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function qa_tile_row_out($row) {
    return [
        'id' => (int)$row['id'],
        'nom' => $row['nom'],
        'description' => $row['description'],
        'type' => $row['type'],
        'url' => $row['url'],
        'icone' => $row['icone'],
        'admin_uniquement' => (bool)$row['admin_uniquement'],
        'ordre' => (int)$row['ordre'],
        'actif' => (bool)$row['actif'],
        'scope' => $row['scope'] ?? 'candidat',
    ];
}

$requestedScope = $_GET['scope'] ?? $_POST['scope'] ?? 'candidat';
$scope = in_array($requestedScope, ['candidat', 'accueil'], true) ? $requestedScope : 'candidat';

// Liste des tuiles visibles pour l'utilisateur connecté (dashboard ou
// Espace candidat, selon $scope). Les tuiles réservées à l'admin sont
// filtrées pour les autres rôles.
// Tant que la migration tiles_scope_accueil_2026_07 n'a pas été lancée par
// un admin (voir includes/db.php), la colonne scope n'existe pas encore :
// on filtre alors sans elle, pour ne pas casser l'affichage des tuiles
// existantes (toutes considérées "candidat" avant cette migration).
$hasScopeColumn = qa_column_exists($pdo, 'tiles', 'scope');

if ($action === 'list') {
    $role = $_SESSION['role'] ?? 'candidat';
    $isAdmin = qa_has_any_admin_access($pdo, (int)$_SESSION['user_id'], $role);
    $scopeSql = $hasScopeColumn ? ' AND scope=?' : '';
    $params = $hasScopeColumn ? [$scope] : [];
    if ($isAdmin) {
        $stmt = $pdo->prepare("SELECT * FROM tiles WHERE actif=1{$scopeSql} ORDER BY ordre, nom");
    } else {
        $stmt = $pdo->prepare("SELECT * FROM tiles WHERE actif=1 AND admin_uniquement=0{$scopeSql} ORDER BY ordre, nom");
    }
    $stmt->execute($params);
    echo json_encode(array_map('qa_tile_row_out', $hasScopeColumn || $scope === 'candidat' ? $stmt->fetchAll() : []));
    exit;
}

// À partir d'ici, gestion des tuiles (réservée par défaut au Super-Admin,
// voir includes/permissions.php).
if ($action === 'list_admin') {
    require_permission('tiles', 'read');
    if ($hasScopeColumn) {
        $stmt = $pdo->prepare('SELECT * FROM tiles WHERE scope=? ORDER BY ordre, nom');
        $stmt->execute([$scope]);
        echo json_encode(array_map('qa_tile_row_out', $stmt->fetchAll()));
    } else {
        $stmt = $pdo->query('SELECT * FROM tiles ORDER BY ordre, nom');
        echo json_encode(array_map('qa_tile_row_out', $scope === 'candidat' ? $stmt->fetchAll() : []));
    }
    exit;
}

if ($action === 'save') {
    require_permission('tiles', 'manage');
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = $body['id'] ?? null;
    $nom = trim($body['nom'] ?? '');
    $description = trim($body['description'] ?? '');
    $type = in_array($body['type'] ?? '', ['questionnaire', 'lien'], true) ? $body['type'] : 'lien';
    $url = trim($body['url'] ?? '');
    $icone = trim($body['icone'] ?? '') ?: 'info';
    $adminUniquement = !empty($body['admin_uniquement']) ? 1 : 0;
    $ordre = (int)($body['ordre'] ?? 0);
    $actif = !empty($body['actif']) ? 1 : 0;
    $requestedTileScope = $body['scope'] ?? 'candidat';
    $scope = in_array($requestedTileScope, ['candidat', 'accueil'], true) ? $requestedTileScope : 'candidat';

    if ($nom === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Le nom de la tuile est requis']);
        exit;
    }
    if ($type === 'lien' && $url === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => "L'URL est requise pour une tuile de type lien"]);
        exit;
    }

    try {
        if ($id) {
            $stmt = $pdo->prepare('UPDATE tiles SET nom=?, description=?, type=?, url=?, icone=?, admin_uniquement=?, ordre=?, actif=?, scope=? WHERE id=?');
            $stmt->execute([$nom, $description, $type, $type === 'lien' ? $url : null, $icone, $adminUniquement, $ordre, $actif, $scope, $id]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO tiles (nom, description, type, url, icone, admin_uniquement, ordre, actif, scope) VALUES (?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$nom, $description, $type, $type === 'lien' ? $url : null, $icone, $adminUniquement, $ordre, $actif, $scope]);
            $id = $pdo->lastInsertId();
        }
    } catch (PDOException $e) {
        if ((int)($e->errorInfo[1] ?? 0) === 1054) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => "La base de données n'est pas à jour : un administrateur doit lancer la mise à jour de la base depuis Administration > Mise à jour système avant d'enregistrer une tuile."]);
            exit;
        }
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => "Erreur lors de l'enregistrement de la tuile"]);
        exit;
    }

    $stmt = $pdo->prepare('SELECT * FROM tiles WHERE id=?');
    $stmt->execute([$id]);
    echo json_encode(['success' => true, 'tile' => qa_tile_row_out($stmt->fetch())]);
    exit;
}

if ($action === 'delete') {
    require_permission('tiles', 'manage');
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($body['id'] ?? 0);
    try {
        $pdo->prepare('DELETE FROM tiles WHERE id=?')->execute([$id]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression de la tuile']);
        exit;
    }
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Action inconnue']);
