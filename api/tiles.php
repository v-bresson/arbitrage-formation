<?php
require_once __DIR__ . '/../includes/require_user.php';
require_once __DIR__ . '/../includes/db.php';

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
    ];
}

// Liste des tuiles visibles pour l'utilisateur connecté (dashboard).
// Les tuiles réservées à l'admin sont filtrées pour les autres rôles.
if ($action === 'list') {
    $isAdmin = ($_SESSION['role'] ?? '') === 'admin';
    if ($isAdmin) {
        $stmt = $pdo->query('SELECT * FROM tiles WHERE actif=1 ORDER BY ordre, nom');
    } else {
        $stmt = $pdo->query('SELECT * FROM tiles WHERE actif=1 AND admin_uniquement=0 ORDER BY ordre, nom');
    }
    echo json_encode(array_map('qa_tile_row_out', $stmt->fetchAll()));
    exit;
}

// À partir d'ici, gestion des tuiles réservée aux admins.
require_once __DIR__ . '/../includes/require_admin.php';
require_admin();

if ($action === 'list_admin') {
    $stmt = $pdo->query('SELECT * FROM tiles ORDER BY ordre, nom');
    echo json_encode(array_map('qa_tile_row_out', $stmt->fetchAll()));
    exit;
}

if ($action === 'save') {
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

    if ($id) {
        $stmt = $pdo->prepare('UPDATE tiles SET nom=?, description=?, type=?, url=?, icone=?, admin_uniquement=?, ordre=?, actif=? WHERE id=?');
        $stmt->execute([$nom, $description, $type, $type === 'lien' ? $url : null, $icone, $adminUniquement, $ordre, $actif, $id]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO tiles (nom, description, type, url, icone, admin_uniquement, ordre, actif) VALUES (?,?,?,?,?,?,?,?)');
        $stmt->execute([$nom, $description, $type, $type === 'lien' ? $url : null, $icone, $adminUniquement, $ordre, $actif]);
        $id = $pdo->lastInsertId();
    }

    $stmt = $pdo->prepare('SELECT * FROM tiles WHERE id=?');
    $stmt->execute([$id]);
    echo json_encode(['success' => true, 'tile' => qa_tile_row_out($stmt->fetch())]);
    exit;
}

if ($action === 'delete') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($body['id'] ?? 0);
    $pdo->prepare('DELETE FROM tiles WHERE id=?')->execute([$id]);
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Action inconnue']);
