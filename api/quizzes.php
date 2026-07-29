<?php
require_once __DIR__ . '/../includes/require_admin.php';
require_once __DIR__ . '/../includes/db.php';

require_admin();
header('Content-Type: application/json');

$pdo = get_db();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function qa_quiz_row_out($row, $pdo) {
    $countStmt = $pdo->prepare("SELECT COUNT(*) c FROM questions WHERE actif=1 AND (? = '' OR categorie = ?)");
    $countStmt->execute([$row['categorie_filtre'] ?? '', $row['categorie_filtre'] ?? '']);
    $available = (int)$countStmt->fetch()['c'];

    return [
        'id' => (int)$row['id'],
        'nom' => $row['nom'],
        'description' => $row['description'],
        'categorie_filtre' => $row['categorie_filtre'],
        'nombre_questions' => (int)$row['nombre_questions'],
        'note_max' => (float)$row['note_max'],
        'seuil_reussite' => (float)$row['seuil_reussite'],
        'actif' => (bool)$row['actif'],
        'questions_disponibles' => $available,
        'created_at' => $row['created_at'],
    ];
}

if ($action === 'list') {
    $stmt = $pdo->query('SELECT * FROM quizzes ORDER BY created_at DESC, id DESC');
    $rows = array_map(fn($r) => qa_quiz_row_out($r, $pdo), $stmt->fetchAll());
    echo json_encode($rows);
    exit;
}

if ($action === 'save') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = $body['id'] ?? null;
    $nom = trim($body['nom'] ?? '');
    $description = trim($body['description'] ?? '');
    $categorieFiltre = trim($body['categorie_filtre'] ?? '');
    $nombreQuestions = max(1, (int)($body['nombre_questions'] ?? 10));
    $noteMax = max(1, (float)($body['note_max'] ?? 20));
    $seuil = max(0, (float)($body['seuil_reussite'] ?? 10));
    $actif = !empty($body['actif']) ? 1 : 0;

    if ($nom === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Le nom du questionnaire est requis']);
        exit;
    }
    if ($seuil > $noteMax) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Le seuil de réussite ne peut pas dépasser la note maximale']);
        exit;
    }

    if ($id) {
        $stmt = $pdo->prepare('UPDATE quizzes SET nom=?, description=?, categorie_filtre=?, nombre_questions=?, note_max=?, seuil_reussite=?, actif=? WHERE id=?');
        $stmt->execute([$nom, $description, $categorieFiltre, $nombreQuestions, $noteMax, $seuil, $actif, $id]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO quizzes (nom, description, categorie_filtre, nombre_questions, note_max, seuil_reussite, actif) VALUES (?,?,?,?,?,?,?)');
        $stmt->execute([$nom, $description, $categorieFiltre, $nombreQuestions, $noteMax, $seuil, $actif]);
        $id = $pdo->lastInsertId();
    }

    $stmt = $pdo->prepare('SELECT * FROM quizzes WHERE id=?');
    $stmt->execute([$id]);
    echo json_encode(['success' => true, 'quiz' => qa_quiz_row_out($stmt->fetch(), $pdo)]);
    exit;
}

if ($action === 'delete') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($body['id'] ?? 0);
    $pdo->prepare('DELETE FROM quizzes WHERE id=?')->execute([$id]);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'attempts') {
    $quizId = (int)($_GET['quiz_id'] ?? 0);
    if ($quizId) {
        $stmt = $pdo->prepare('SELECT * FROM tentatives WHERE quiz_id=? ORDER BY created_at DESC');
        $stmt->execute([$quizId]);
    } else {
        $stmt = $pdo->query('SELECT t.*, q.nom AS quiz_nom FROM tentatives t JOIN quizzes q ON q.id = t.quiz_id ORDER BY t.created_at DESC LIMIT 200');
    }
    $rows = $stmt->fetchAll();
    echo json_encode(array_map(function ($r) {
        return [
            'id' => (int)$r['id'],
            'quiz_id' => (int)$r['quiz_id'],
            'quiz_nom' => $r['quiz_nom'] ?? null,
            'candidat' => $r['candidat'],
            'score' => (float)$r['score'],
            'note_max' => (float)$r['note_max'],
            'reussi' => (bool)$r['reussi'],
            'created_at' => $r['created_at'],
        ];
    }, $rows));
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Action inconnue']);
