<?php
require_once __DIR__ . '/../includes/require_admin.php';
require_once __DIR__ . '/../includes/db.php';

require_admin();
header('Content-Type: application/json');

$pdo = get_db();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function qa_quiz_pool_where($type) {
    // Un questionnaire d'entraînement ne pioche jamais parmi les questions
    // réservées à l'examen. Un questionnaire d'examen pioche dans toute la banque.
    return $type === 'entrainement' ? 'AND examen_uniquement = 0' : '';
}

function qa_count_available($pdo, $poolWhere, $categorie) {
    $countStmt = $pdo->prepare("SELECT COUNT(*) c FROM questions WHERE actif=1 $poolWhere AND (? = '' OR categorie = ?)");
    $countStmt->execute([$categorie ?? '', $categorie ?? '']);
    return (int)$countStmt->fetch()['c'];
}

function qa_quiz_row_out($row, $pdo) {
    $poolWhere = qa_quiz_pool_where($row['type']);
    $repartition = null;
    $suffisant = true;

    if (!empty($row['repartition'])) {
        $parts = json_decode($row['repartition'], true) ?: [];
        $repartition = [];
        $available = 0;
        foreach ($parts as $part) {
            $dispo = qa_count_available($pdo, $poolWhere, $part['categorie']);
            $available += min($dispo, $part['nombre_questions']);
            if ($dispo < $part['nombre_questions']) $suffisant = false;
            $repartition[] = [
                'categorie' => $part['categorie'],
                'nombre_questions' => (int)$part['nombre_questions'],
                'disponible' => $dispo,
            ];
        }
    } else {
        $available = qa_count_available($pdo, $poolWhere, $row['categorie_filtre'] ?? '');
        $suffisant = $available >= (int)$row['nombre_questions'];
    }

    return [
        'id' => (int)$row['id'],
        'nom' => $row['nom'],
        'description' => $row['description'],
        'type' => $row['type'],
        'categorie_filtre' => $row['categorie_filtre'],
        'nombre_questions' => (int)$row['nombre_questions'],
        'repartition' => $repartition,
        'note_max' => (float)$row['note_max'],
        'seuil_reussite' => (float)$row['seuil_reussite'],
        'duree_minutes' => $row['duree_minutes'] !== null ? (int)$row['duree_minutes'] : null,
        'ouverture_debut' => $row['ouverture_debut'],
        'ouverture_fin' => $row['ouverture_fin'],
        'tentatives_max' => $row['tentatives_max'] !== null ? (int)$row['tentatives_max'] : null,
        'afficher_score' => (bool)$row['afficher_score'],
        'actif' => (bool)$row['actif'],
        'questions_disponibles' => $available,
        'suffisant' => $suffisant,
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
    $type = in_array($body['type'] ?? '', ['entrainement', 'examen'], true) ? $body['type'] : 'entrainement';
    $categorieFiltre = trim($body['categorie_filtre'] ?? '');
    $nombreQuestions = max(1, (int)($body['nombre_questions'] ?? 10));
    $noteMax = max(1, (float)($body['note_max'] ?? 20));

    // Répartition par thématique : liste [{categorie, nombre_questions}, ...].
    // Quand elle est fournie et non vide, elle remplace categorie_filtre et
    // nombre_questions (recalculé comme la somme des thématiques).
    $repartitionInput = is_array($body['repartition'] ?? null) ? $body['repartition'] : [];
    $repartition = [];
    foreach ($repartitionInput as $part) {
        $cat = trim($part['categorie'] ?? '');
        $n = max(1, (int)($part['nombre_questions'] ?? 0));
        if ($cat === '') continue;
        $repartition[] = ['categorie' => $cat, 'nombre_questions' => $n];
    }
    if (!empty($repartition)) {
        $categorieFiltre = '';
        $nombreQuestions = array_sum(array_column($repartition, 'nombre_questions'));
    }
    $seuil = max(0, (float)($body['seuil_reussite'] ?? 10));
    $dureeMinutes = isset($body['duree_minutes']) && $body['duree_minutes'] !== '' ? max(1, (int)$body['duree_minutes']) : null;
    $ouvertureDebut = trim($body['ouverture_debut'] ?? '') ?: null;
    $ouvertureFin = trim($body['ouverture_fin'] ?? '') ?: null;
    $tentativesMax = isset($body['tentatives_max']) && $body['tentatives_max'] !== '' ? max(1, (int)$body['tentatives_max']) : null;
    $afficherScore = !empty($body['afficher_score']) ? 1 : 0;
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
    if ($ouvertureDebut && $ouvertureFin && $ouvertureDebut >= $ouvertureFin) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => "La date d'ouverture doit être antérieure à la date de fermeture"]);
        exit;
    }

    $repartitionJson = !empty($repartition) ? json_encode($repartition) : null;

    if ($id) {
        $stmt = $pdo->prepare('UPDATE quizzes SET nom=?, description=?, type=?, categorie_filtre=?, nombre_questions=?, repartition=?, note_max=?, seuil_reussite=?, duree_minutes=?, ouverture_debut=?, ouverture_fin=?, tentatives_max=?, afficher_score=?, actif=? WHERE id=?');
        $stmt->execute([$nom, $description, $type, $categorieFiltre, $nombreQuestions, $repartitionJson, $noteMax, $seuil, $dureeMinutes, $ouvertureDebut, $ouvertureFin, $tentativesMax, $afficherScore, $actif, $id]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO quizzes (nom, description, type, categorie_filtre, nombre_questions, repartition, note_max, seuil_reussite, duree_minutes, ouverture_debut, ouverture_fin, tentatives_max, afficher_score, actif) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$nom, $description, $type, $categorieFiltre, $nombreQuestions, $repartitionJson, $noteMax, $seuil, $dureeMinutes, $ouvertureDebut, $ouvertureFin, $tentativesMax, $afficherScore, $actif]);
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
    // Les tentatives passées sont conservées (quiz_id passe à NULL, voir schéma)
    $pdo->prepare('DELETE FROM quizzes WHERE id=?')->execute([$id]);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'attempts') {
    $quizId = (int)($_GET['quiz_id'] ?? 0);
    if ($quizId) {
        $stmt = $pdo->prepare('SELECT * FROM tentatives WHERE quiz_id=? ORDER BY started_at DESC');
        $stmt->execute([$quizId]);
    } else {
        $stmt = $pdo->query('SELECT * FROM tentatives ORDER BY started_at DESC LIMIT 300');
    }
    $rows = $stmt->fetchAll();
    echo json_encode(array_map(function ($r) {
        return [
            'id' => (int)$r['id'],
            'quiz_id' => $r['quiz_id'] !== null ? (int)$r['quiz_id'] : null,
            'quiz_nom' => $r['quiz_nom'],
            'quiz_type' => $r['quiz_type'],
            'candidat' => $r['candidat'],
            'statut' => $r['statut'],
            'score' => $r['score'] !== null ? (float)$r['score'] : null,
            'note_max' => (float)$r['note_max'],
            'reussi' => $r['reussi'] !== null ? (bool)$r['reussi'] : null,
            'afficher_score' => (bool)$r['afficher_score'],
            'started_at' => $r['started_at'],
            'completed_at' => $r['completed_at'],
        ];
    }, $rows));
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Action inconnue']);
