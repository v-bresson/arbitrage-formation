<?php
require_once __DIR__ . '/../includes/session-config.php';
session_start();
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');
$pdo = get_db();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'quizzes') {
    $stmt = $pdo->query('SELECT * FROM quizzes WHERE actif=1 ORDER BY nom');
    $rows = array_map(function ($row) use ($pdo) {
        $countStmt = $pdo->prepare("SELECT COUNT(*) c FROM questions WHERE actif=1 AND (? = '' OR categorie = ?)");
        $countStmt->execute([$row['categorie_filtre'] ?? '', $row['categorie_filtre'] ?? '']);
        return [
            'id' => (int)$row['id'],
            'nom' => $row['nom'],
            'description' => $row['description'],
            'nombre_questions' => (int)$row['nombre_questions'],
            'note_max' => (float)$row['note_max'],
            'seuil_reussite' => (float)$row['seuil_reussite'],
            'questions_disponibles' => (int)$countStmt->fetch()['c'],
        ];
    }, $stmt->fetchAll());
    echo json_encode($rows);
    exit;
}

if ($action === 'start') {
    $quizId = (int)($_POST['quiz_id'] ?? 0);
    $candidat = trim($_POST['candidat'] ?? '');

    if ($candidat === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Merci de renseigner votre nom']);
        exit;
    }

    $stmt = $pdo->prepare('SELECT * FROM quizzes WHERE id=? AND actif=1');
    $stmt->execute([$quizId]);
    $quiz = $stmt->fetch();
    if (!$quiz) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Questionnaire introuvable']);
        exit;
    }

    $qStmt = $pdo->prepare("SELECT * FROM questions WHERE actif=1 AND (? = '' OR categorie = ?) ORDER BY RANDOM() LIMIT ?");
    $qStmt->bindValue(1, $quiz['categorie_filtre'] ?? '');
    $qStmt->bindValue(2, $quiz['categorie_filtre'] ?? '');
    $qStmt->bindValue(3, (int)$quiz['nombre_questions'], PDO::PARAM_INT);
    $qStmt->execute();
    $questions = $qStmt->fetchAll();

    if (count($questions) < 1) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Aucune question disponible pour ce questionnaire']);
        exit;
    }

    // La session stocke les bonnes réponses et le barème : le client ne
    // reçoit jamais les réponses correctes avant la correction.
    $_SESSION['attempt'] = [
        'quiz_id' => $quiz['id'],
        'candidat' => $candidat,
        'note_max' => (float)$quiz['note_max'],
        'seuil_reussite' => (float)$quiz['seuil_reussite'],
        'questions' => array_map(fn($q) => [
            'id' => (int)$q['id'],
            'bonne_reponse' => $q['bonne_reponse'],
            'points' => (int)$q['points'],
        ], $questions),
    ];

    $totalPoints = array_sum(array_column($_SESSION['attempt']['questions'], 'points'));
    $_SESSION['attempt']['total_points'] = $totalPoints;

    echo json_encode([
        'success' => true,
        'quiz' => ['id' => (int)$quiz['id'], 'nom' => $quiz['nom'], 'note_max' => (float)$quiz['note_max']],
        'questions' => array_map(fn($q) => [
            'id' => (int)$q['id'],
            'categorie' => $q['categorie'],
            'enonce' => $q['enonce'],
            'options' => array_filter([
                'a' => $q['option_a'],
                'b' => $q['option_b'],
                'c' => $q['option_c'],
                'd' => $q['option_d'],
            ]),
        ], $questions),
    ]);
    exit;
}

if ($action === 'submit') {
    $attempt = $_SESSION['attempt'] ?? null;
    if (!$attempt) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Aucun questionnaire en cours. Merci de recommencer."]);
        exit;
    }

    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $reponses = $body['reponses'] ?? []; // { question_id: 'a' }

    $earned = 0;
    $detail = [];
    foreach ($attempt['questions'] as $q) {
        $given = strtolower((string)($reponses[$q['id']] ?? ''));
        $correct = $given !== '' && $given === $q['bonne_reponse'];
        if ($correct) $earned += $q['points'];
        $detail[] = ['question_id' => $q['id'], 'donnee' => $given, 'correcte' => $q['bonne_reponse'], 'ok' => $correct];
    }

    $totalPoints = max(1, $attempt['total_points']);
    $noteMax = $attempt['note_max'];
    $note = round(($earned / $totalPoints) * $noteMax, 2);
    $reussi = $note >= $attempt['seuil_reussite'];

    $stmt = $pdo->prepare('INSERT INTO tentatives (quiz_id, candidat, score, note_max, reussi, details) VALUES (?,?,?,?,?,?)');
    $stmt->execute([$attempt['quiz_id'], $attempt['candidat'], $note, $noteMax, $reussi ? 1 : 0, json_encode($detail)]);

    unset($_SESSION['attempt']);

    echo json_encode([
        'success' => true,
        'note' => $note,
        'note_max' => $noteMax,
        'seuil_reussite' => $attempt['seuil_reussite'],
        'reussi' => $reussi,
        'bonnes_reponses' => $earned,
        'total_questions' => count($attempt['questions']),
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Action inconnue']);
