<?php
require_once __DIR__ . '/../includes/session-config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/permissions.php';

header('Content-Type: application/json');

$pdo = get_db();
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$qaAttemptsActions = ['attempts', 'attempt_detail', 'grade_attempt', 'delete_attempt'];
$qaSection = in_array($action, $qaAttemptsActions, true) ? 'attempts' : 'quizzes';
$qaMinLevel = in_array($action, ['save', 'delete', 'grade_attempt', 'delete_attempt'], true) ? 'manage' : 'read';
require_permission($qaSection, $qaMinLevel);

// Un compte Formateur "simple" (sans le rôle Membre CRA ni Super-Admin, qui
// voient toujours l'ensemble) ne doit voir/gérer que les tentatives de ses
// propres candidats assignés (table candidat_formateurs) — pas celles de
// tous les candidats de l'application.
function qa_formateur_scoped_usernames($pdo, $userId, $role) {
    $roles = qa_user_role_keys($pdo, $userId, $role);
    if (!in_array('formateur', $roles, true) || in_array('membre_cra', $roles, true) || in_array('super_admin', $roles, true)) {
        return null; // pas de restriction
    }
    $stmt = $pdo->prepare('SELECT u.username FROM candidat_formateurs cf JOIN users u ON u.id = cf.candidat_id WHERE cf.formateur_id = ?');
    $stmt->execute([$userId]);
    return array_column($stmt->fetchAll(), 'username');
}

$qaScopedUsernames = in_array($action, $qaAttemptsActions, true)
    ? qa_formateur_scoped_usernames($pdo, (int)$_SESSION['user_id'], $_SESSION['role'] ?? 'candidat')
    : null;

function qa_count_available($pdo, $categorie) {
    $countStmt = $pdo->prepare("SELECT COUNT(*) c FROM questions WHERE actif=1 AND (? = '' OR categorie = ?)");
    $countStmt->execute([$categorie ?? '', $categorie ?? '']);
    return (int)$countStmt->fetch()['c'];
}

// Détail (existence + statut actif) des questions d'une sélection manuelle,
// dans l'ordre choisi à la création du QCM Examen.
function qa_manual_questions_detail($pdo, $ids) {
    if (empty($ids)) return [];
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT id, categorie, enonce, actif FROM questions WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    $byId = [];
    foreach ($stmt->fetchAll() as $r) $byId[(int)$r['id']] = $r;
    $detail = [];
    foreach ($ids as $id) {
        $q = $byId[(int)$id] ?? null;
        $detail[] = [
            'id' => (int)$id,
            'categorie' => $q['categorie'] ?? null,
            'enonce' => $q['enonce'] ?? null,
            'disponible' => $q !== null && (bool)$q['actif'],
        ];
    }
    return $detail;
}

function qa_quiz_row_out($row, $pdo) {
    $repartition = null;
    $questionsManuelles = null;
    $suffisant = true;

    if (($row['selection_mode'] ?? 'auto') === 'manuel') {
        $ids = json_decode($row['questions_manuelles'] ?? '[]', true) ?: [];
        $questionsManuelles = qa_manual_questions_detail($pdo, $ids);
        $available = count(array_filter($questionsManuelles, fn($q) => $q['disponible']));
        $suffisant = $available === count($ids) && count($ids) > 0;
    } elseif (!empty($row['repartition'])) {
        $parts = json_decode($row['repartition'], true) ?: [];
        $repartition = [];
        $available = 0;
        foreach ($parts as $part) {
            $dispo = qa_count_available($pdo, $part['categorie']);
            $available += min($dispo, $part['nombre_questions']);
            if ($dispo < $part['nombre_questions']) $suffisant = false;
            $repartition[] = [
                'categorie' => $part['categorie'],
                'nombre_questions' => (int)$part['nombre_questions'],
                'disponible' => $dispo,
            ];
        }
    } else {
        $available = qa_count_available($pdo, $row['categorie_filtre'] ?? '');
        $suffisant = $available >= (int)$row['nombre_questions'];
    }

    return [
        'id' => (int)$row['id'],
        'nom' => $row['nom'],
        'description' => $row['description'],
        'type' => 'examen',
        'selection_mode' => $row['selection_mode'] ?? 'auto',
        'categorie_filtre' => $row['categorie_filtre'],
        'nombre_questions' => (int)$row['nombre_questions'],
        'repartition' => $repartition,
        'questions_manuelles' => $questionsManuelles,
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
    $selectionMode = ($body['selection_mode'] ?? '') === 'manuel' ? 'manuel' : 'auto';
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

    // Sélection manuelle : liste d'id de questions choisies une à une dans la
    // banque. Remplace toute logique de tirage aléatoire (repartition/filtre
    // ignorés) et fixe nombre_questions au nombre de questions choisies.
    $questionsManuellesInput = is_array($body['questions_manuelles'] ?? null) ? $body['questions_manuelles'] : [];
    $questionsManuelles = array_values(array_unique(array_map('intval', $questionsManuellesInput)));
    if ($selectionMode === 'manuel') {
        $repartition = [];
        $categorieFiltre = '';
        $nombreQuestions = count($questionsManuelles);
    } else {
        $questionsManuelles = [];
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
        echo json_encode(['success' => false, 'message' => 'Le nom du QCM Examen est requis']);
        exit;
    }
    if ($selectionMode === 'manuel' && count($questionsManuelles) < 1) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Sélectionnez au moins une question dans la banque']);
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
    $questionsManuellesJson = !empty($questionsManuelles) ? json_encode($questionsManuelles) : null;

    if ($id) {
        $stmt = $pdo->prepare('UPDATE quizzes SET nom=?, description=?, selection_mode=?, categorie_filtre=?, nombre_questions=?, repartition=?, questions_manuelles=?, note_max=?, seuil_reussite=?, duree_minutes=?, ouverture_debut=?, ouverture_fin=?, tentatives_max=?, afficher_score=?, actif=? WHERE id=?');
        $stmt->execute([$nom, $description, $selectionMode, $categorieFiltre, $nombreQuestions, $repartitionJson, $questionsManuellesJson, $noteMax, $seuil, $dureeMinutes, $ouvertureDebut, $ouvertureFin, $tentativesMax, $afficherScore, $actif, $id]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO quizzes (nom, description, type, selection_mode, categorie_filtre, nombre_questions, repartition, questions_manuelles, note_max, seuil_reussite, duree_minutes, ouverture_debut, ouverture_fin, tentatives_max, afficher_score, actif) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$nom, $description, 'examen', $selectionMode, $categorieFiltre, $nombreQuestions, $repartitionJson, $questionsManuellesJson, $noteMax, $seuil, $dureeMinutes, $ouvertureDebut, $ouvertureFin, $tentativesMax, $afficherScore, $actif]);
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

// Une tentative a des questions ouvertes si son instantané de questions
// (figé au démarrage, voir api/attempt.php) en contient au moins une.
function qa_attempt_has_ouverte($questionsJson) {
    $questions = json_decode($questionsJson ?? '[]', true) ?: [];
    foreach ($questions as $q) {
        if (($q['type'] ?? '') === 'ouverte') return true;
    }
    return false;
}

function qa_attempt_duration_seconds($r) {
    if (!$r['completed_at']) return null;
    return strtotime($r['completed_at']) - strtotime($r['started_at']);
}

if ($action === 'attempts') {
    $quizId = (int)($_GET['quiz_id'] ?? 0);

    if ($qaScopedUsernames !== null && empty($qaScopedUsernames)) {
        echo json_encode([]);
        exit;
    }

    $where = [];
    $params = [];
    if ($quizId) { $where[] = 'quiz_id = ?'; $params[] = $quizId; }
    if ($qaScopedUsernames !== null) {
        $where[] = 'candidat IN (' . implode(',', array_fill(0, count($qaScopedUsernames), '?')) . ')';
        $params = array_merge($params, $qaScopedUsernames);
    }
    $sql = 'SELECT * FROM tentatives' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY started_at DESC';
    if (!$quizId) $sql .= ' LIMIT 300';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
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
            'resultat_publie' => (bool)$r['resultat_publie'],
            'a_des_questions_ouvertes' => qa_attempt_has_ouverte($r['questions_json']),
            'started_at' => $r['started_at'],
            'completed_at' => $r['completed_at'],
            'duree_secondes' => qa_attempt_duration_seconds($r),
        ];
    }, $rows));
    exit;
}

// Détail complet d'une tentative pour la relecture/correction : chaque
// question avec son énoncé, ses options, la réponse donnée, la bonne
// réponse et les points actuellement attribués (voir details, mis à jour
// par grade_attempt ci-dessous).
if ($action === 'attempt_detail') {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT * FROM tentatives WHERE id=?');
    $stmt->execute([$id]);
    $t = $stmt->fetch();
    if (!$t || ($qaScopedUsernames !== null && !in_array($t['candidat'], $qaScopedUsernames, true))) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Tentative introuvable']);
        exit;
    }

    $questions = json_decode($t['questions_json'], true) ?: [];
    $reponses = json_decode($t['reponses_json'] ?? 'null', true) ?? [];
    $detailByQid = [];
    foreach (json_decode($t['details'] ?? 'null', true) ?? [] as $d) {
        $detailByQid[$d['question_id']] = $d;
    }

    $questionsOut = array_map(function ($q) use ($reponses, $detailByQid) {
        $d = $detailByQid[$q['id']] ?? [];
        return [
            'id' => $q['id'],
            'categorie' => $q['categorie'],
            'type' => $q['type'],
            'enonce' => $q['enonce'],
            'image' => $q['image'] ?? null,
            'options' => $q['options'] ?? null,
            'bonne_reponse' => $q['bonne_reponse'] ?? null,
            'points_max' => (int)$q['points'],
            'reponse_donnee' => $reponses[$q['id']] ?? ($reponses[(string)$q['id']] ?? null),
            'points_attribues' => array_key_exists('points', $d) ? (float)$d['points'] : (($q['type'] !== 'ouverte' && ($d['ok'] ?? false)) ? (int)$q['points'] : 0),
        ];
    }, $questions);

    echo json_encode([
        'id' => (int)$t['id'],
        'quiz_nom' => $t['quiz_nom'],
        'candidat' => $t['candidat'],
        'statut' => $t['statut'],
        'note_max' => (float)$t['note_max'],
        'seuil_reussite' => (float)$t['seuil_reussite'],
        'score' => $t['score'] !== null ? (float)$t['score'] : null,
        'reussi' => $t['reussi'] !== null ? (bool)$t['reussi'] : null,
        'afficher_score' => (bool)$t['afficher_score'],
        'resultat_publie' => (bool)$t['resultat_publie'],
        'started_at' => $t['started_at'],
        'completed_at' => $t['completed_at'],
        'questions' => $questionsOut,
    ]);
    exit;
}

// Enregistre la correction manuelle d'une tentative : points attribués par
// question (obligatoire pour les questions ouvertes, modifiable aussi pour
// les QCM en cas de contestation), recalcule la note totale et la
// réussite, et publie ou non le résultat au candidat.
if ($action === 'grade_attempt') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($body['id'] ?? 0);
    $corrections = is_array($body['corrections'] ?? null) ? $body['corrections'] : [];
    $publier = !empty($body['publier']);

    $stmt = $pdo->prepare('SELECT * FROM tentatives WHERE id=?');
    $stmt->execute([$id]);
    $t = $stmt->fetch();
    if (!$t || ($qaScopedUsernames !== null && !in_array($t['candidat'], $qaScopedUsernames, true))) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Tentative introuvable']);
        exit;
    }

    $questions = json_decode($t['questions_json'], true) ?: [];
    $pointsByQid = [];
    foreach ($corrections as $c) {
        $pointsByQid[(int)($c['question_id'] ?? 0)] = max(0, (float)($c['points'] ?? 0));
    }

    $reponses = json_decode($t['reponses_json'] ?? 'null', true) ?? [];
    $earned = 0;
    $totalPoints = 0;
    $detail = [];
    foreach ($questions as $q) {
        $totalPoints += (int)$q['points'];
        $points = $pointsByQid[$q['id']] ?? 0;
        $points = min($points, (int)$q['points']);
        $earned += $points;
        $entry = ['question_id' => $q['id'], 'type' => $q['type'], 'points' => $points];
        if ($q['type'] === 'ouverte') {
            $entry['reponse_libre'] = is_string($reponses[$q['id']] ?? null) ? $reponses[$q['id']] : '';
        } else {
            $entry['donnee'] = $reponses[$q['id']] ?? null;
            $entry['correcte'] = $q['bonne_reponse'];
        }
        $detail[] = $entry;
    }

    $note = $totalPoints > 0 ? round(($earned / $totalPoints) * $t['note_max'], 2) : null;
    $reussi = $note !== null ? ($note >= $t['seuil_reussite'] ? 1 : 0) : null;

    $stmt = $pdo->prepare('UPDATE tentatives SET score=?, reussi=?, details=?, resultat_publie=? WHERE id=?');
    $stmt->execute([$note, $reussi, json_encode($detail), $publier ? 1 : 0, $id]);

    echo json_encode(['success' => true, 'score' => $note, 'reussi' => (bool)$reussi, 'resultat_publie' => $publier]);
    exit;
}

if ($action === 'delete_attempt') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($body['id'] ?? 0);

    if ($qaScopedUsernames !== null) {
        $stmt = $pdo->prepare('SELECT candidat FROM tentatives WHERE id=?');
        $stmt->execute([$id]);
        $candidat = $stmt->fetchColumn();
        if ($candidat === false || !in_array($candidat, $qaScopedUsernames, true)) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Tentative introuvable']);
            exit;
        }
    }

    $pdo->prepare('DELETE FROM tentatives WHERE id=?')->execute([$id]);
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Action inconnue']);
