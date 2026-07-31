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
    $candidat = $_SESSION['username'];

    // Tentatives déjà réalisées par ce candidat, regroupées par quiz : nombre
    // de tentatives terminées, tentative en cours éventuelle, et statut du
    // dernier résultat publié (pour l'afficher dans le récap du candidat).
    $tStmt = $pdo->prepare("SELECT quiz_id, statut, reussi, resultat_publie, completed_at FROM tentatives WHERE candidat = ? AND quiz_id IS NOT NULL ORDER BY started_at ASC");
    $tStmt->execute([$candidat]);
    $parQuiz = [];
    foreach ($tStmt->fetchAll() as $t) {
        $qid = (int)$t['quiz_id'];
        if (!isset($parQuiz[$qid])) $parQuiz[$qid] = ['count' => 0, 'en_cours' => false, 'dernier_statut' => null];
        if ($t['statut'] === 'en_cours') {
            $parQuiz[$qid]['en_cours'] = true;
            continue;
        }
        $parQuiz[$qid]['count']++;
        if (!$t['resultat_publie']) {
            $parQuiz[$qid]['dernier_statut'] = 'en_attente';
        } else {
            $parQuiz[$qid]['dernier_statut'] = $t['reussi'] === null ? null : ((int)$t['reussi'] === 1 ? 'reussi' : 'non_valide');
        }
    }

    $stmt = $pdo->query('SELECT * FROM quizzes WHERE actif=1 ORDER BY type, nom');
    $rows = array_map(function ($row) use ($pdo, $parQuiz) {
        $fermetureMsg = qa_check_window($row);
        [$total, $available, $suffisant] = qa_quiz_availability($pdo, $row);
        $mine = $parQuiz[(int)$row['id']] ?? ['count' => 0, 'en_cours' => false, 'dernier_statut' => null];

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
            'mes_tentatives' => $mine['count'],
            'tentative_en_cours' => $mine['en_cours'],
            'dernier_resultat' => $mine['dernier_statut'],
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
                // Réponses déjà saisies avant une fermeture de fenêtre / un
                // rechargement : reprises telles quelles côté client pour ne
                // pas les faire disparaître (voir action=save_progress).
                'reponses' => json_decode($enCours['reponses_json'] ?? 'null', true) ?: new stdClass(),
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

// Sauvegarde périodique des réponses en cours de saisie (avant l'envoi
// définitif) : sans ça, fermer l'onglet pendant l'examen fait perdre les
// réponses (seul le minuteur, calculé depuis started_at, survit).
if ($action === 'save_progress') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $tentativeId = (int)($body['tentative_id'] ?? 0);
    $reponses = is_array($body['reponses'] ?? null) ? $body['reponses'] : [];
    $candidat = $_SESSION['username'];

    $stmt = $pdo->prepare("SELECT id FROM tentatives WHERE id=? AND candidat=? AND statut='en_cours'");
    $stmt->execute([$tentativeId, $candidat]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Tentative introuvable ou déjà terminée']);
        exit;
    }

    $stmt = $pdo->prepare('UPDATE tentatives SET reponses_json=? WHERE id=?');
    $stmt->execute([json_encode($reponses), $tentativeId]);
    echo json_encode(['success' => true]);
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

    // Une tentative contenant des questions ouvertes doit être relue et
    // corrigée manuellement avant que le résultat ne soit communiqué au
    // candidat (voir api/quizzes.php, action grade_attempt) ; il en va de
    // même si le questionnaire ne montre jamais le score immédiatement.
    $hasOuverte = false;
    foreach ($questions as $q) {
        if ($q['type'] === 'ouverte') { $hasOuverte = true; break; }
    }
    $resultatPublie = ($tentative['afficher_score'] && !$hasOuverte) ? 1 : 0;

    $stmt = $pdo->prepare("UPDATE tentatives SET statut=?, reponses_json=?, score=?, reussi=?, completed_at=?, details=?, resultat_publie=? WHERE id=?");
    $stmt->execute([$statut, json_encode($reponsesByQid), $note, $reussi, date('Y-m-d H:i:s'), json_encode($detail), $resultatPublie, $tentativeId]);

    $response = [
        'success' => true,
        'afficher_score' => (bool)$tentative['afficher_score'] && !$hasOuverte,
        'expiree' => $late,
        'total_questions' => count($questions),
        'total_questions_notees' => $totalNotees,
    ];

    if ($response['afficher_score']) {
        $response['note'] = $note;
        $response['note_max'] = (float)$tentative['note_max'];
        $response['seuil_reussite'] = (float)$tentative['seuil_reussite'];
        $response['reussi'] = (bool)$reussi;
        $response['bonnes_reponses'] = $bonnes;
    }

    echo json_encode($response);
    exit;
}

// Historique complet des tentatives du candidat connecté (toutes, y
// compris en cours), affiché sous les tuiles de QCM Examen (voir
// quiz.php) avec un bouton de relecture par tentative terminée.
if ($action === 'my-attempts') {
    $candidat = $_SESSION['username'];
    $stmt = $pdo->prepare("SELECT id, quiz_id, quiz_nom, statut, score, note_max, reussi, afficher_score, resultat_publie, started_at, completed_at
        FROM tentatives WHERE candidat = ? ORDER BY started_at DESC");
    $stmt->execute([$candidat]);
    echo json_encode(array_map(function ($r) {
        return [
            'id' => (int)$r['id'],
            'quiz_id' => $r['quiz_id'] !== null ? (int)$r['quiz_id'] : null,
            'quiz_nom' => $r['quiz_nom'],
            'statut' => $r['statut'],
            'score' => $r['score'] !== null ? (float)$r['score'] : null,
            'note_max' => (float)$r['note_max'],
            'reussi' => $r['reussi'] !== null ? (bool)$r['reussi'] : null,
            'afficher_score' => (bool)$r['afficher_score'],
            'resultat_publie' => (bool)$r['resultat_publie'],
            'started_at' => $r['started_at'],
            'completed_at' => $r['completed_at'],
        ];
    }, $stmt->fetchAll()));
    exit;
}

// Relecture d'une tentative terminée par son propre candidat : réponses
// saisies toujours visibles, mais la correction (bonne réponse, points,
// score) n'est révélée que si le résultat a été publié (voir
// resultat_publie, api/quizzes.php action grade_attempt) — sinon on ne
// ferait que reproduire l'écran de résultat en avance sur la correction.
if ($action === 'review') {
    $id = (int)($_GET['id'] ?? 0);
    $candidat = $_SESSION['username'];
    $stmt = $pdo->prepare("SELECT * FROM tentatives WHERE id=? AND candidat=? AND statut != 'en_cours'");
    $stmt->execute([$id, $candidat]);
    $t = $stmt->fetch();
    if (!$t) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Tentative introuvable']);
        exit;
    }

    $questions = json_decode($t['questions_json'], true) ?: [];
    $reponses = json_decode($t['reponses_json'] ?? 'null', true) ?? [];
    $publie = (bool)$t['resultat_publie'];
    $detailByQid = [];
    foreach (json_decode($t['details'] ?? 'null', true) ?? [] as $d) {
        $detailByQid[$d['question_id']] = $d;
    }

    $questionsOut = array_map(function ($q) use ($reponses, $publie, $detailByQid) {
        $out = [
            'id' => $q['id'],
            'categorie' => $q['categorie'],
            'type' => $q['type'],
            'enonce' => $q['enonce'],
            'image' => $q['image'] ?? null,
            'options' => array_filter($q['options'] ?? []),
            'reponse_donnee' => $reponses[$q['id']] ?? ($reponses[(string)$q['id']] ?? null),
        ];
        if ($publie) {
            $d = $detailByQid[$q['id']] ?? [];
            $out['bonne_reponse'] = $q['bonne_reponse'] ?? null;
            $out['points_max'] = (int)$q['points'];
            $out['points_attribues'] = array_key_exists('points', $d) ? (float)$d['points'] : (($q['type'] !== 'ouverte' && ($d['ok'] ?? false)) ? (int)$q['points'] : 0);
        }
        return $out;
    }, $questions);

    echo json_encode([
        'id' => (int)$t['id'],
        'quiz_nom' => $t['quiz_nom'],
        'statut' => $t['statut'],
        'started_at' => $t['started_at'],
        'completed_at' => $t['completed_at'],
        'resultat_publie' => $publie,
        'afficher_score' => (bool)$t['afficher_score'],
        'note_max' => (float)$t['note_max'],
        'seuil_reussite' => (float)$t['seuil_reussite'],
        'score' => ($publie && $t['score'] !== null) ? (float)$t['score'] : null,
        'reussi' => ($publie && $t['reussi'] !== null) ? (bool)$t['reussi'] : null,
        'questions' => $questionsOut,
    ]);
    exit;
}

if ($action === 'my-stats') {
    // Le nombre total de tentatives est visible immédiatement, mais la
    // réussite/moyenne ne compte que les tentatives dont le résultat a été
    // publié (voir resultat_publie, api/quizzes.php action grade_attempt) :
    // tant qu'une question ouverte n'a pas été corrigée manuellement, le
    // candidat ne doit pas voir s'il est reçu ou non.
    $candidat = $_SESSION['username'];
    $stmt = $pdo->prepare("SELECT COUNT(*) total,
        SUM(CASE WHEN resultat_publie = 1 AND reussi = 1 THEN 1 ELSE 0 END) reussies,
        AVG(CASE WHEN resultat_publie = 1 AND afficher_score = 1 AND score IS NOT NULL AND note_max > 0 THEN score / note_max * 100 ELSE NULL END) moyenne,
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
