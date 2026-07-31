<?php
require_once __DIR__ . '/../includes/require_user.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/permissions.php';

require_user();
header('Content-Type: application/json');
$pdo = get_db();
$action = $_GET['action'] ?? $_POST['action'] ?? '';
if ($action === 'formateur-stats') {
    require_permission('attempts', 'read');
}

function qa_count_available($pdo, $categorie) {
    $countStmt = $pdo->prepare("SELECT COUNT(*) c FROM questions WHERE actif=1 AND (? = '' OR categorie = ?)");
    $countStmt->execute([$categorie ?? '', $categorie ?? '']);
    return (int)$countStmt->fetch()['c'];
}

// Calcule la disponibilité et le nombre total de questions d'un QCM Examen,
// selon sa méthode de sélection (manuelle, répartition par thématique, ou
// tirage global). Renvoie [nombreQuestionsTotal, disponible, suffisant].
function qa_quiz_availability($pdo, $quiz) {
    if (($quiz['selection_mode'] ?? 'auto') === 'manuel') {
        $ids = json_decode($quiz['questions_manuelles'] ?? '[]', true) ?: [];
        if (empty($ids)) return [0, 0, false];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT COUNT(*) c FROM questions WHERE actif=1 AND id IN ($placeholders)");
        $stmt->execute($ids);
        $available = (int)$stmt->fetch()['c'];
        return [count($ids), $available, $available === count($ids)];
    }

    if (!empty($quiz['repartition'])) {
        $parts = json_decode($quiz['repartition'], true) ?: [];
        $total = 0;
        $available = 0;
        $suffisant = true;
        foreach ($parts as $part) {
            $dispo = qa_count_available($pdo, $part['categorie']);
            $total += $part['nombre_questions'];
            $available += min($dispo, $part['nombre_questions']);
            if ($dispo < $part['nombre_questions']) $suffisant = false;
        }
        return [$total, $available, $suffisant];
    }

    $available = qa_count_available($pdo, $quiz['categorie_filtre'] ?? '');
    return [(int)$quiz['nombre_questions'], $available, $available >= (int)$quiz['nombre_questions']];
}

// Pioche les questions d'un QCM Examen : liste fixe (sélection manuelle,
// mélangée pour ne pas afficher toujours dans le même ordre), par thématique
// si une répartition est définie (chaque thématique tire son propre nombre
// de questions), sinon selon categorie_filtre/nombre_questions.
function qa_draw_questions($pdo, $quiz) {
    if (($quiz['selection_mode'] ?? 'auto') === 'manuel') {
        $ids = json_decode($quiz['questions_manuelles'] ?? '[]', true) ?: [];
        if (empty($ids)) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT * FROM questions WHERE actif=1 AND id IN ($placeholders)");
        $stmt->execute($ids);
        $rows = $stmt->fetchAll();
        shuffle($rows);
        return $rows;
    }

    if (!empty($quiz['repartition'])) {
        $parts = json_decode($quiz['repartition'], true) ?: [];
        $drawn = [];
        foreach ($parts as $part) {
            $qStmt = $pdo->prepare("SELECT * FROM questions WHERE actif=1 AND categorie = ? ORDER BY RAND() LIMIT ?");
            $qStmt->bindValue(1, $part['categorie']);
            $qStmt->bindValue(2, (int)$part['nombre_questions'], PDO::PARAM_INT);
            $qStmt->execute();
            $drawn = array_merge($drawn, $qStmt->fetchAll());
        }
        shuffle($drawn);
        return $drawn;
    }

    $qStmt = $pdo->prepare("SELECT * FROM questions WHERE actif=1 AND (? = '' OR categorie = ?) ORDER BY RAND() LIMIT ?");
    $qStmt->bindValue(1, $quiz['categorie_filtre'] ?? '');
    $qStmt->bindValue(2, $quiz['categorie_filtre'] ?? '');
    $qStmt->bindValue(3, (int)$quiz['nombre_questions'], PDO::PARAM_INT);
    $qStmt->execute();
    return $qStmt->fetchAll();
}

// Détermine si un questionnaire est ouvrable maintenant (fenêtre d'ouverture),
// et renvoie un message explicite sinon.
function qa_check_window($quiz) {
    $now = date('Y-m-d H:i:s');
    if (!empty($quiz['ouverture_debut']) && $now < $quiz['ouverture_debut']) {
        return "Ce questionnaire n'est pas encore ouvert (ouverture le " . $quiz['ouverture_debut'] . ')';
    }
    if (!empty($quiz['ouverture_fin']) && $now > $quiz['ouverture_fin']) {
        return 'Ce questionnaire est fermé (clôturé le ' . $quiz['ouverture_fin'] . ')';
    }
    return null;
}

function qa_question_public($q) {
    $out = [
        'id' => (int)$q['id'],
        'categorie' => $q['categorie'],
        'type' => $q['type'],
        'enonce' => $q['enonce'],
        'image' => $q['image'] ?: null,
    ];
    if ($q['type'] !== 'ouverte') {
        $out['options'] = array_filter([
            'a' => $q['options']['a'] ?? null,
            'b' => $q['options']['b'] ?? null,
            'c' => $q['options']['c'] ?? null,
            'd' => $q['options']['d'] ?? null,
            'e' => $q['options']['e'] ?? null,
            'f' => $q['options']['f'] ?? null,
        ]);
    }
    return $out;
}

// Corrige une tentative à partir des réponses fournies et de l'instantané des
// questions figé au démarrage (questions_json). Renvoie [score, totalPoints,
// earnedPoints, bonnesReponses, totalQuestionsNotees, detail].
function qa_grade($questions, $reponses) {
    $earned = 0;
    $totalPoints = 0;
    $bonnesReponses = 0;
    $totalNotees = 0;
    $detail = [];

    foreach ($questions as $q) {
        $given = $reponses[$q['id']] ?? null;

        if ($q['type'] === 'ouverte') {
            $detail[] = ['question_id' => $q['id'], 'type' => 'ouverte', 'reponse_libre' => is_string($given) ? $given : ''];
            continue;
        }

        $totalNotees++;
        $totalPoints += $q['points'];

        $ok = false;
        if ($q['type'] === 'qcm_unique') {
            $givenLetter = strtolower((string)(is_array($given) ? '' : $given));
            $ok = $givenLetter !== '' && $givenLetter === $q['bonne_reponse'];
        } elseif ($q['type'] === 'qcm_multiple') {
            $givenLetters = is_array($given) ? $given : [];
            $givenLetters = array_values(array_unique(array_map('strtolower', array_map('trim', $givenLetters))));
            sort($givenLetters);
            $ok = implode(',', $givenLetters) === $q['bonne_reponse'];
        }

        if ($ok) {
            $earned += $q['points'];
            $bonnesReponses++;
        }

        $detail[] = ['question_id' => $q['id'], 'type' => $q['type'], 'donnee' => $given, 'correcte' => $q['bonne_reponse'], 'ok' => $ok];
    }

    return [$earned, $totalPoints, $bonnesReponses, $totalNotees, $detail];
}

if ($action === 'quizzes') {
    $stmt = $pdo->query('SELECT * FROM quizzes WHERE actif=1 ORDER BY type, nom');
    $rows = array_map(function ($row) use ($pdo) {
        $fermetureMsg = qa_check_window($row);
        [$total, $available, $suffisant] = qa_quiz_availability($pdo, $row);

        return [
            'id' => (int)$row['id'],
            'nom' => $row['nom'],
            'description' => $row['description'],
            'type' => $row['type'],
            'nombre_questions' => $total,
            'note_max' => (float)$row['note_max'],
            'seuil_reussite' => (float)$row['seuil_reussite'],
            'duree_minutes' => $row['duree_minutes'] !== null ? (int)$row['duree_minutes'] : null,
            'ouverture_debut' => $row['ouverture_debut'],
            'ouverture_fin' => $row['ouverture_fin'],
            'tentatives_max' => $row['tentatives_max'] !== null ? (int)$row['tentatives_max'] : null,
            'questions_disponibles' => $available,
            'suffisant' => $suffisant,
            'ferme' => $fermetureMsg,
        ];
    }, $stmt->fetchAll());
    echo json_encode($rows);
    exit;
}

if ($action === 'start') {
    $quizId = (int)($_POST['quiz_id'] ?? 0);
    $candidat = $_SESSION['username'];

    $stmt = $pdo->prepare('SELECT * FROM quizzes WHERE id=? AND actif=1');
    $stmt->execute([$quizId]);
    $quiz = $stmt->fetch();
    if (!$quiz) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Questionnaire introuvable']);
        exit;
    }

    $fermetureMsg = qa_check_window($quiz);
    if ($fermetureMsg) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => $fermetureMsg]);
        exit;
    }

    // Reprise d'une tentative en cours (même candidat, même questionnaire)
    $stmt = $pdo->prepare("SELECT * FROM tentatives WHERE quiz_id=? AND candidat=? AND statut='en_cours' ORDER BY started_at DESC LIMIT 1");
    $stmt->execute([$quizId, $candidat]);
    $enCours = $stmt->fetch();

    if ($enCours) {
        $expired = false;
        if ($enCours['duree_minutes']) {
            $deadline = strtotime($enCours['started_at']) + $enCours['duree_minutes'] * 60;
            $expired = time() > $deadline;
        }

        if (!$expired) {
            $questions = json_decode($enCours['questions_json'], true);
            echo json_encode([
                'success' => true,
                'tentative_id' => (int)$enCours['id'],
                'quiz' => ['id' => (int)$quiz['id'], 'nom' => $quiz['nom'], 'type' => $quiz['type'], 'note_max' => (float)$quiz['note_max']],
                'started_at' => $enCours['started_at'],
                'duree_minutes' => $enCours['duree_minutes'] !== null ? (int)$enCours['duree_minutes'] : null,
                'questions' => array_map('qa_question_public', $questions),
            ]);
            exit;
        }

        // Temps écoulé : on clôture la tentative abandonnée avant d'en autoriser une nouvelle
        $questions = json_decode($enCours['questions_json'], true);
        $reponses = json_decode($enCours['reponses_json'] ?? 'null', true) ?? [];
        [$earned, $totalPoints, $bonnes, $totalNotees, $detail] = qa_grade($questions, $reponses);
        $note = $totalPoints > 0 ? round(($earned / $totalPoints) * $enCours['note_max'], 2) : null;
        $reussi = $note !== null ? ($note >= $enCours['seuil_reussite'] ? 1 : 0) : null;
        $stmt = $pdo->prepare("UPDATE tentatives SET statut='expiree', score=?, reussi=?, completed_at=?, details=? WHERE id=?");
        $stmt->execute([$note, $reussi, date('Y-m-d H:i:s'), json_encode($detail), $enCours['id']]);
    }

    // Vérification du nombre de tentatives maximum (tentatives terminées ou expirées)
    if ($quiz['tentatives_max']) {
        $stmt = $pdo->prepare("SELECT COUNT(*) c FROM tentatives WHERE quiz_id=? AND candidat=? AND statut IN ('terminee','expiree')");
        $stmt->execute([$quizId, $candidat]);
        $used = (int)$stmt->fetch()['c'];
        if ($used >= $quiz['tentatives_max']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => "Nombre maximum de tentatives atteint pour ce questionnaire (" . $quiz['tentatives_max'] . ')']);
            exit;
        }
    }

    [, , $suffisant] = qa_quiz_availability($pdo, $quiz);
    if (!$suffisant) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => "Il n'y a pas assez de questions disponibles (globalement ou dans une thématique) pour ce questionnaire"]);
        exit;
    }

    $drawn = qa_draw_questions($pdo, $quiz);

    if (count($drawn) < 1) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Aucune question disponible pour ce questionnaire']);
        exit;
    }

    // Instantané figé des questions (énoncé, options, bonne réponse, points) :
    // sert à la fois à masquer les corrections au candidat et à conserver un
    // archivage fiable même si la banque de questions change ensuite.
    $questionsSnapshot = array_map(fn($q) => [
        'id' => (int)$q['id'],
        'categorie' => $q['categorie'],
        'type' => $q['type'],
        'enonce' => $q['enonce'],
        'image' => $q['image'],
        'options' => ['a' => $q['option_a'], 'b' => $q['option_b'], 'c' => $q['option_c'], 'd' => $q['option_d'], 'e' => $q['option_e'], 'f' => $q['option_f']],
        'bonne_reponse' => $q['bonne_reponse'],
        'points' => (int)$q['points'],
    ], $drawn);

    $startedAt = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare('INSERT INTO tentatives (quiz_id, quiz_nom, quiz_type, candidat, statut, questions_json, note_max, seuil_reussite, duree_minutes, afficher_score, started_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
    $stmt->execute([
        $quiz['id'], $quiz['nom'], $quiz['type'], $candidat, 'en_cours',
        json_encode($questionsSnapshot), $quiz['note_max'], $quiz['seuil_reussite'],
        $quiz['duree_minutes'], $quiz['afficher_score'], $startedAt,
    ]);
    $tentativeId = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'tentative_id' => (int)$tentativeId,
        'quiz' => ['id' => (int)$quiz['id'], 'nom' => $quiz['nom'], 'type' => $quiz['type'], 'note_max' => (float)$quiz['note_max']],
        'started_at' => $startedAt,
        'duree_minutes' => $quiz['duree_minutes'] !== null ? (int)$quiz['duree_minutes'] : null,
        'questions' => array_map('qa_question_public', $questionsSnapshot),
    ]);
    exit;
}

if ($action === 'submit') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $tentativeId = (int)($body['tentative_id'] ?? 0);
    $reponses = $body['reponses'] ?? [];

    $stmt = $pdo->prepare("SELECT * FROM tentatives WHERE id=? AND statut='en_cours'");
    $stmt->execute([$tentativeId]);
    $tentative = $stmt->fetch();

    if (!$tentative) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Tentative introuvable ou déjà terminée. Merci de recommencer.']);
        exit;
    }

    $questions = json_decode($tentative['questions_json'], true);

    // Normalise les clés de réponses (JSON envoie les id de question en string)
    $reponsesNorm = [];
    foreach ($reponses as $qid => $val) {
        $reponsesNorm[(int)$qid] = $val;
    }
    $reponsesByQid = [];
    foreach ($questions as $q) {
        $reponsesByQid[$q['id']] = $reponsesNorm[$q['id']] ?? null;
    }

    $late = false;
    if ($tentative['duree_minutes']) {
        $deadline = strtotime($tentative['started_at']) + $tentative['duree_minutes'] * 60;
        $late = time() > $deadline + 5; // petite marge réseau
    }

    [$earned, $totalPoints, $bonnes, $totalNotees, $detail] = qa_grade($questions, $reponsesByQid);
    $note = $totalPoints > 0 ? round(($earned / $totalPoints) * $tentative['note_max'], 2) : null;
    $reussi = $note !== null ? ($note >= $tentative['seuil_reussite'] ? 1 : 0) : null;
    $statut = $late ? 'expiree' : 'terminee';

    $stmt = $pdo->prepare("UPDATE tentatives SET statut=?, reponses_json=?, score=?, reussi=?, completed_at=?, details=? WHERE id=?");
    $stmt->execute([$statut, json_encode($reponsesByQid), $note, $reussi, date('Y-m-d H:i:s'), json_encode($detail), $tentativeId]);

    $response = [
        'success' => true,
        'afficher_score' => (bool)$tentative['afficher_score'],
        'expiree' => $late,
        'total_questions' => count($questions),
        'total_questions_notees' => $totalNotees,
    ];

    if ($tentative['afficher_score']) {
        $response['note'] = $note;
        $response['note_max'] = (float)$tentative['note_max'];
        $response['seuil_reussite'] = (float)$tentative['seuil_reussite'];
        $response['reussi'] = (bool)$reussi;
        $response['bonnes_reponses'] = $bonnes;
    }

    echo json_encode($response);
    exit;
}

if ($action === 'my-stats') {
    $candidat = $_SESSION['username'];
    $stmt = $pdo->prepare("SELECT COUNT(*) total,
        SUM(CASE WHEN reussi = 1 THEN 1 ELSE 0 END) reussies,
        AVG(CASE WHEN afficher_score = 1 AND score IS NOT NULL AND note_max > 0 THEN score / note_max * 100 ELSE NULL END) moyenne,
        MAX(completed_at) derniere
        FROM tentatives WHERE candidat = ? AND statut IN ('terminee', 'expiree')");
    $stmt->execute([$candidat]);
    $row = $stmt->fetch();
    echo json_encode([
        'total_tentatives' => (int)($row['total'] ?? 0),
        'reussies' => (int)($row['reussies'] ?? 0),
        'moyenne_pct' => $row['moyenne'] !== null ? round((float)$row['moyenne'], 1) : null,
        'derniere_tentative' => $row['derniere'],
    ]);
    exit;
}

if ($action === 'formateur-stats') {
    $stmt = $pdo->query("SELECT COUNT(DISTINCT candidat) candidats,
        COUNT(*) total,
        SUM(CASE WHEN reussi = 1 THEN 1 ELSE 0 END) reussies,
        MAX(completed_at) derniere
        FROM tentatives WHERE statut IN ('terminee', 'expiree')");
    $row = $stmt->fetch();
    echo json_encode([
        'candidats' => (int)($row['candidats'] ?? 0),
        'total_tentatives' => (int)($row['total'] ?? 0),
        'reussies' => (int)($row['reussies'] ?? 0),
        'derniere_tentative' => $row['derniere'],
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Action inconnue']);
