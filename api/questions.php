<?php
require_once __DIR__ . '/../includes/require_admin.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/xlsx_reader.php';
require_once __DIR__ . '/../includes/uploads.php';

require_admin();
header('Content-Type: application/json');

$pdo = get_db();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

const QA_QUESTION_TYPES = ['qcm_unique', 'qcm_multiple', 'ouverte'];

function qa_question_row_out($row) {
    return [
        'id' => (int)$row['id'],
        'categorie' => $row['categorie'],
        'type' => $row['type'],
        'enonce' => $row['enonce'],
        'image' => $row['image'],
        'options' => [
            'a' => $row['option_a'],
            'b' => $row['option_b'],
            'c' => $row['option_c'],
            'd' => $row['option_d'],
        ],
        'bonne_reponse' => $row['bonne_reponse'],
        'points' => (int)$row['points'],
        'examen_uniquement' => (bool)$row['examen_uniquement'],
        'actif' => (bool)$row['actif'],
        'created_at' => $row['created_at'],
    ];
}

// Valide et normalise les champs propres à chaque type de question.
// Lève une RuntimeException avec un message utilisateur en cas d'erreur.
function qa_validate_question($type, $enonce, &$a, &$b, &$c, &$d, &$bonne) {
    if ($enonce === '') {
        throw new RuntimeException("L'énoncé est requis");
    }
    if (!in_array($type, QA_QUESTION_TYPES, true)) {
        throw new RuntimeException('Type de question invalide');
    }

    if ($type === 'ouverte') {
        $a = $b = $c = $d = null;
        $bonne = null;
        return;
    }

    if ($a === '' || $b === '') {
        throw new RuntimeException("Les réponses A et B sont requises pour un QCM");
    }

    $optionsText = ['a' => $a, 'b' => $b, 'c' => $c, 'd' => $d];

    if ($type === 'qcm_unique') {
        $bonne = strtolower(trim($bonne));
        if (!in_array($bonne, ['a', 'b', 'c', 'd'], true)) {
            throw new RuntimeException('La bonne réponse doit être a, b, c ou d');
        }
        if (($optionsText[$bonne] ?? '') === '') {
            throw new RuntimeException("La réponse choisie comme correcte n'a pas de texte");
        }
        return;
    }

    // qcm_multiple : bonne_reponse est une liste de lettres séparées par des virgules
    $letters = array_filter(array_map('trim', explode(',', strtolower($bonne))));
    $letters = array_values(array_unique($letters));
    if (count($letters) < 1) {
        throw new RuntimeException('Sélectionnez au moins une bonne réponse');
    }
    foreach ($letters as $l) {
        if (!in_array($l, ['a', 'b', 'c', 'd'], true) || ($optionsText[$l] ?? '') === '') {
            throw new RuntimeException("Une des bonnes réponses sélectionnées n'a pas de texte");
        }
    }
    sort($letters);
    $bonne = implode(',', $letters);
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
    // multipart/form-data : permet de joindre une image en même temps que les champs
    $id = $_POST['id'] ?? null;
    $categorie = trim($_POST['categorie'] ?? '') ?: 'Général';
    $type = $_POST['type'] ?? 'qcm_unique';
    $enonce = trim($_POST['enonce'] ?? '');
    $a = trim($_POST['option_a'] ?? '');
    $b = trim($_POST['option_b'] ?? '');
    $c = trim($_POST['option_c'] ?? '');
    $d = trim($_POST['option_d'] ?? '');
    $bonne = $_POST['bonne_reponse'] ?? '';
    $points = max(1, (int)($_POST['points'] ?? 1));
    $examenUniquement = !empty($_POST['examen_uniquement']) ? 1 : 0;
    $actif = !empty($_POST['actif']) ? 1 : 0;
    $removeImage = !empty($_POST['remove_image']);

    try {
        qa_validate_question($type, $enonce, $a, $b, $c, $d, $bonne);
    } catch (RuntimeException $e) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }

    $existingImage = null;
    if ($id) {
        $stmt = $pdo->prepare('SELECT image FROM questions WHERE id=?');
        $stmt->execute([$id]);
        $existingImage = $stmt->fetchColumn() ?: null;
    }

    $image = $existingImage;
    try {
        if ($removeImage) {
            qa_delete_question_image($existingImage);
            $image = null;
        }
        if (!empty($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $newImage = qa_save_question_image($_FILES['image']);
            if ($existingImage && !$removeImage) qa_delete_question_image($existingImage);
            $image = $newImage;
        }
    } catch (RuntimeException $e) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }

    if ($id) {
        $stmt = $pdo->prepare('UPDATE questions SET categorie=?, type=?, enonce=?, image=?, option_a=?, option_b=?, option_c=?, option_d=?, bonne_reponse=?, points=?, examen_uniquement=?, actif=? WHERE id=?');
        $stmt->execute([$categorie, $type, $enonce, $image, $a ?: null, $b ?: null, $c ?: null, $d ?: null, $bonne, $points, $examenUniquement, $actif, $id]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO questions (categorie, type, enonce, image, option_a, option_b, option_c, option_d, bonne_reponse, points, examen_uniquement, actif) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$categorie, $type, $enonce, $image, $a ?: null, $b ?: null, $c ?: null, $d ?: null, $bonne, $points, $examenUniquement, $actif]);
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
    $stmt = $pdo->prepare('SELECT image FROM questions WHERE id=?');
    $stmt->execute([$id]);
    $image = $stmt->fetchColumn();
    $pdo->prepare('DELETE FROM questions WHERE id=?')->execute([$id]);
    if ($image) qa_delete_question_image($image);
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

        // En-tête attendu : categorie, type, enonce, option_a, option_b, option_c,
        // option_d, bonne_reponse, points, examen_uniquement
        // type ∈ qcm_unique | qcm_multiple | ouverte (défaut qcm_unique)
        // bonne_reponse : une lettre (qcm_unique) ou une liste séparée par des
        // virgules, ex. "a,c" (qcm_multiple). Ignoré pour les questions ouvertes.
        $header = array_map(fn($h) => strtolower(trim((string)$h)), array_shift($rows));
        $colIndex = array_flip($header);

        if (!isset($colIndex['enonce'])) {
            throw new RuntimeException("Colonne manquante dans le fichier : enonce");
        }

        $get = function ($row, $col) use ($colIndex) {
            return isset($colIndex[$col], $row[$colIndex[$col]]) ? trim((string)$row[$colIndex[$col]]) : '';
        };

        $inserted = 0;
        $errors = [];
        $stmt = $pdo->prepare('INSERT INTO questions (categorie, type, enonce, option_a, option_b, option_c, option_d, bonne_reponse, points, examen_uniquement, actif) VALUES (?,?,?,?,?,?,?,?,?,?,1)');

        $pdo->beginTransaction();
        foreach ($rows as $i => $row) {
            if (empty(array_filter($row, fn($v) => trim((string)$v) !== ''))) continue;

            $lineNum = $i + 2;
            $enonce = $get($row, 'enonce');
            $type = strtolower($get($row, 'type')) ?: 'qcm_unique';
            $a = $get($row, 'option_a');
            $b = $get($row, 'option_b');
            $c = $get($row, 'option_c');
            $d = $get($row, 'option_d');
            $categorie = $get($row, 'categorie') ?: 'Général';
            $bonne = $get($row, 'bonne_reponse');
            $points = (int)($get($row, 'points') ?: 1);
            $examenUniquement = in_array(strtolower($get($row, 'examen_uniquement')), ['1', 'oui', 'true', 'vrai'], true) ? 1 : 0;

            try {
                qa_validate_question($type, $enonce, $a, $b, $c, $d, $bonne);
            } catch (RuntimeException $e) {
                $errors[] = "Ligne $lineNum : " . $e->getMessage();
                continue;
            }

            $stmt->execute([$categorie, $type, $enonce, $a, $b, $c, $d, $bonne, max(1, $points), $examenUniquement]);
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
