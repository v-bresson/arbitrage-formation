<?php
require_once __DIR__ . '/../includes/require_admin.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/xlsx_reader.php';

require_admin();
header('Content-Type: application/json');

$pdo = get_db();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function qa_question_row_out($row) {
    return [
        'id' => (int)$row['id'],
        'categorie' => $row['categorie'],
        'enonce' => $row['enonce'],
        'options' => [
            'a' => $row['option_a'],
            'b' => $row['option_b'],
            'c' => $row['option_c'],
            'd' => $row['option_d'],
        ],
        'bonne_reponse' => $row['bonne_reponse'],
        'points' => (int)$row['points'],
        'actif' => (bool)$row['actif'],
        'created_at' => $row['created_at'],
    ];
}

if ($action === 'list') {
    $stmt = $pdo->query('SELECT * FROM questions ORDER BY created_at DESC, id DESC');
    $rows = array_map('qa_question_row_out', $stmt->fetchAll());
    echo json_encode($rows);
    exit;
}

if ($action === 'categories') {
    $stmt = $pdo->query('SELECT DISTINCT categorie FROM questions ORDER BY categorie');
    echo json_encode(array_column($stmt->fetchAll(), 'categorie'));
    exit;
}

if ($action === 'save') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = $body['id'] ?? null;
    $categorie = trim($body['categorie'] ?? '') ?: 'Général';
    $enonce = trim($body['enonce'] ?? '');
    $options = $body['options'] ?? [];
    $a = trim($options['a'] ?? '');
    $b = trim($options['b'] ?? '');
    $c = trim($options['c'] ?? '');
    $d = trim($options['d'] ?? '');
    $bonne = strtolower(trim($body['bonne_reponse'] ?? ''));
    $points = max(1, (int)($body['points'] ?? 1));
    $actif = !empty($body['actif']) ? 1 : 0;

    if ($enonce === '' || $a === '' || $b === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => "L'énoncé et au moins les réponses A et B sont requis"]);
        exit;
    }
    if (!in_array($bonne, ['a', 'b', 'c', 'd'], true)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'La bonne réponse doit être a, b, c ou d']);
        exit;
    }
    if (($bonne === 'c' && $c === '') || ($bonne === 'd' && $d === '')) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => "La réponse choisie comme correcte n'a pas de texte"]);
        exit;
    }

    if ($id) {
        $stmt = $pdo->prepare('UPDATE questions SET categorie=?, enonce=?, option_a=?, option_b=?, option_c=?, option_d=?, bonne_reponse=?, points=?, actif=? WHERE id=?');
        $stmt->execute([$categorie, $enonce, $a, $b, $c ?: null, $d ?: null, $bonne, $points, $actif, $id]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO questions (categorie, enonce, option_a, option_b, option_c, option_d, bonne_reponse, points, actif) VALUES (?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$categorie, $enonce, $a, $b, $c ?: null, $d ?: null, $bonne, $points, $actif]);
        $id = $pdo->lastInsertId();
    }

    $stmt = $pdo->prepare('SELECT * FROM questions WHERE id=?');
    $stmt->execute([$id]);
    echo json_encode(['success' => true, 'question' => qa_question_row_out($stmt->fetch())]);
    exit;
}

if ($action === 'delete') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($body['id'] ?? 0);
    $pdo->prepare('DELETE FROM questions WHERE id=?')->execute([$id]);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'import') {
    if (empty($_FILES['fichier']) || $_FILES['fichier']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Aucun fichier reçu']);
        exit;
    }

    $tmpPath = $_FILES['fichier']['tmp_name'];
    $originalName = $_FILES['fichier']['name'];
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    try {
        $rows = [];
        if ($ext === 'csv') {
            $handle = fopen($tmpPath, 'r');
            if ($handle === false) throw new RuntimeException('Impossible de lire le fichier CSV');
            // Détection simple du séparateur (virgule ou point-virgule)
            $firstLine = fgets($handle);
            rewind($handle);
            $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $rows[] = $row;
            }
            fclose($handle);
        } elseif ($ext === 'xlsx') {
            $rows = xlsx_to_rows($tmpPath);
        } else {
            throw new RuntimeException('Format non supporté : utilisez un fichier .csv ou .xlsx');
        }

        if (count($rows) < 2) {
            throw new RuntimeException('Le fichier ne contient pas de lignes de données');
        }

        // En-tête attendu : categorie, enonce, option_a, option_b, option_c, option_d, bonne_reponse, points
        $header = array_map(fn($h) => strtolower(trim((string)$h)), array_shift($rows));
        $colIndex = array_flip($header);

        $required = ['enonce', 'option_a', 'option_b', 'bonne_reponse'];
        foreach ($required as $col) {
            if (!isset($colIndex[$col])) {
                throw new RuntimeException("Colonne manquante dans le fichier : $col");
            }
        }

        $get = function ($row, $col) use ($colIndex) {
            return isset($colIndex[$col], $row[$colIndex[$col]]) ? trim((string)$row[$colIndex[$col]]) : '';
        };

        $inserted = 0;
        $errors = [];
        $stmt = $pdo->prepare('INSERT INTO questions (categorie, enonce, option_a, option_b, option_c, option_d, bonne_reponse, points, actif) VALUES (?,?,?,?,?,?,?,?,1)');

        $pdo->beginTransaction();
        foreach ($rows as $i => $row) {
            if (empty(array_filter($row, fn($v) => trim((string)$v) !== ''))) continue; // ligne vide

            $enonce = $get($row, 'enonce');
            $a = $get($row, 'option_a');
            $b = $get($row, 'option_b');
            $c = $get($row, 'option_c');
            $d = $get($row, 'option_d');
            $categorie = $get($row, 'categorie') ?: 'Général';
            $bonne = strtolower($get($row, 'bonne_reponse'));
            $points = (int)($get($row, 'points') ?: 1);

            $lineNum = $i + 2;
            if ($enonce === '' || $a === '' || $b === '') {
                $errors[] = "Ligne $lineNum : énoncé ou réponses A/B manquants";
                continue;
            }
            if (!in_array($bonne, ['a', 'b', 'c', 'd'], true)) {
                $errors[] = "Ligne $lineNum : bonne_reponse invalide ('$bonne')";
                continue;
            }
            if (($bonne === 'c' && $c === '') || ($bonne === 'd' && $d === '')) {
                $errors[] = "Ligne $lineNum : la réponse correcte désignée n'a pas de texte";
                continue;
            }

            $stmt->execute([$categorie, $enonce, $a, $b, $c ?: null, $d ?: null, $bonne, max(1, $points)]);
            $inserted++;
        }
        $pdo->commit();

        echo json_encode(['success' => true, 'inserted' => $inserted, 'errors' => $errors]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Action inconnue']);
