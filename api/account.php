<?php
require_once __DIR__ . '/../includes/require_user.php';
require_once __DIR__ . '/../includes/db.php';

// ===================================================================
// Auto-service du compte : chaque utilisateur connecté peut consulter
// et modifier ses propres informations personnelles (nom, prénom,
// email, téléphone, n° de licence, club) et changer son mot de passe.
// Ne touche jamais au rôle ni à l'activation du compte : ça reste
// réservé à l'admin (voir api/users.php).
// ===================================================================

require_user();
header('Content-Type: application/json');
$pdo = get_db();
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$userId = (int)$_SESSION['user_id'];

if ($action === 'get') {
    $stmt = $pdo->prepare('SELECT username, nom, prenom, email, numero_licence, telephone, club FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Compte introuvable']);
        exit;
    }
    echo json_encode(['success' => true, 'account' => $row]);
    exit;
}

if ($action === 'save') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $nom = trim($body['nom'] ?? '') ?: null;
    $prenom = trim($body['prenom'] ?? '') ?: null;
    $email = trim($body['email'] ?? '') ?: null;
    $numeroLicence = trim($body['numero_licence'] ?? '') ?: null;
    $telephone = trim($body['telephone'] ?? '') ?: null;
    $club = trim($body['club'] ?? '') ?: null;

    if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => "L'adresse email n'est pas valide"]);
        exit;
    }

    $currentPassword = $body['current_password'] ?? '';
    $newPassword = $body['new_password'] ?? '';

    if ($newPassword !== '') {
        if (strlen($newPassword) < 8) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Le nouveau mot de passe doit contenir au moins 8 caractères']);
            exit;
        }
        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if (!$row || !password_verify($currentPassword, $row['password_hash'])) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Mot de passe actuel incorrect']);
            exit;
        }
    }

    try {
        if ($newPassword !== '') {
            $stmt = $pdo->prepare('UPDATE users SET nom=?, prenom=?, email=?, numero_licence=?, telephone=?, club=?, password_hash=? WHERE id=?');
            $stmt->execute([$nom, $prenom, $email, $numeroLicence, $telephone, $club, password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
        } else {
            $stmt = $pdo->prepare('UPDATE users SET nom=?, prenom=?, email=?, numero_licence=?, telephone=?, club=? WHERE id=?');
            $stmt->execute([$nom, $prenom, $email, $numeroLicence, $telephone, $club, $userId]);
        }
    } catch (PDOException $e) {
        if ((int)($e->errorInfo[1] ?? 0) === 1054) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => "La base de données n'est pas à jour : un administrateur doit lancer la mise à jour de la base depuis Administration > Mise à jour système avant de modifier un profil complet."]);
            exit;
        }
        throw $e;
    }

    echo json_encode(['success' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Action inconnue']);
