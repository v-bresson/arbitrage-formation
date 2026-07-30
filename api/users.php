<?php
require_once __DIR__ . '/../includes/require_admin.php';
require_once __DIR__ . '/../includes/db.php';

require_admin();
header('Content-Type: application/json');

$pdo = get_db();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function qa_user_row_out($row) {
    return [
        'id' => (int)$row['id'],
        'username' => $row['username'],
        'role' => $row['role'],
        'actif' => (bool)$row['actif'],
        'created_at' => $row['created_at'],
    ];
}

function qa_admin_count($pdo, $excludeId = null) {
    if ($excludeId) {
        $stmt = $pdo->prepare("SELECT COUNT(*) c FROM users WHERE role='admin' AND actif=1 AND id != ?");
        $stmt->execute([$excludeId]);
    } else {
        $stmt = $pdo->query("SELECT COUNT(*) c FROM users WHERE role='admin' AND actif=1");
    }
    return (int)$stmt->fetch()['c'];
}

if ($action === 'list') {
    $stmt = $pdo->query('SELECT * FROM users ORDER BY username');
    echo json_encode(array_map('qa_user_row_out', $stmt->fetchAll()));
    exit;
}

if ($action === 'save') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = $body['id'] ?? null;
    $username = trim($body['username'] ?? '');
    $password = $body['password'] ?? '';
    $role = in_array($body['role'] ?? '', ['admin', 'user'], true) ? $body['role'] : 'user';
    $actif = !empty($body['actif']) ? 1 : 0;

    if ($username === '' || strlen($username) < 3) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => "L'identifiant doit contenir au moins 3 caractères"]);
        exit;
    }
    if (!$id && strlen($password) < 8) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Le mot de passe doit contenir au moins 8 caractères']);
        exit;
    }
    if ($password !== '' && strlen($password) < 8) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Le mot de passe doit contenir au moins 8 caractères']);
        exit;
    }

    // Empêche de désactiver ou rétrograder le dernier administrateur actif
    if ($id && ($role !== 'admin' || !$actif) && qa_admin_count($pdo, $id) === 0) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => "Impossible : il doit rester au moins un administrateur actif"]);
        exit;
    }

    try {
        if ($id) {
            if ($password !== '') {
                $stmt = $pdo->prepare('UPDATE users SET username=?, password_hash=?, role=?, actif=? WHERE id=?');
                $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $role, $actif, $id]);
            } else {
                $stmt = $pdo->prepare('UPDATE users SET username=?, role=?, actif=? WHERE id=?');
                $stmt->execute([$username, $role, $actif, $id]);
            }
        } else {
            $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, role, actif) VALUES (?,?,?,?)');
            $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $role, $actif]);
            $id = $pdo->lastInsertId();
        }
    } catch (PDOException $e) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Cet identifiant est déjà utilisé']);
        exit;
    }

    $stmt = $pdo->prepare('SELECT * FROM users WHERE id=?');
    $stmt->execute([$id]);
    echo json_encode(['success' => true, 'user' => qa_user_row_out($stmt->fetch())]);
    exit;
}

if ($action === 'delete') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($body['id'] ?? 0);

    if (qa_admin_count($pdo, $id) === 0) {
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id=?");
        $stmt->execute([$id]);
        $target = $stmt->fetch();
        if ($target && $target['role'] === 'admin') {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => "Impossible : il doit rester au moins un administrateur actif"]);
            exit;
        }
    }

    $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Action inconnue']);
