<?php
require_once __DIR__ . '/../includes/require_admin.php';
require_once __DIR__ . '/../includes/maintenance.php';
require_once __DIR__ . '/../includes/db.php';

require_admin();
header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$actingUsername = $_SESSION['username'] ?? 'inconnu';

if ($method === 'GET' && $action === 'state') {
    $pdo = get_db();
    $backups = qa_list_backups();
    $pending = qa_pending_migrations($pdo);
    echo json_encode([
        'success' => true,
        'app_version' => qa_current_app_version(),
        'backups' => $backups,
        'backup_count' => count($backups),
        'github_configured' => qa_github_config() !== null,
        'pending_migrations' => array_map(fn($m) => ['id' => $m['id'], 'description' => $m['description']], $pending),
    ]);
    exit;
}

if ($method === 'POST' && $action === 'migrate-db') {
    $pdo = get_db();
    $pending = qa_pending_migrations($pdo);

    if (!$pending) {
        echo json_encode(['success' => true, 'applied' => [], 'message' => 'Aucune mise à jour de base de données en attente']);
        exit;
    }

    $applied = [];
    try {
        foreach ($pending as $migration) {
            qa_apply_migration($pdo, $migration);
            $applied[] = $migration['description'];
            qa_maintenance_log("Mise à jour de la base de données par {$actingUsername} : " . $migration['description']);
        }
    } catch (Throwable $e) {
        qa_maintenance_log("ÉCHEC mise à jour de la base de données par {$actingUsername} : " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour de la base de données : ' . $e->getMessage(), 'applied' => $applied]);
        exit;
    }

    echo json_encode(['success' => true, 'applied' => $applied]);
    exit;
}

if ($method === 'GET' && $action === 'github-check') {
    try {
        $result = qa_github_check_latest_release();
        echo json_encode(array_merge(['success' => true], $result));
    } catch (RuntimeException $e) {
        http_response_code(502);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'github-update') {
    $gh = qa_github_config();
    if ($gh === null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Mise à jour GitHub non configurée']);
        exit;
    }

    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $zipballUrl = (string)($body['zipball_url'] ?? '');
    $tagName = (string)($body['tag_name'] ?? '');
    if ($zipballUrl === '' || strpos($zipballUrl, 'https://api.github.com/') !== 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Paramètres de mise à jour invalides']);
        exit;
    }

    $tmpZipPath = null;
    try {
        $tmpZipPath = qa_github_download_zipball($zipballUrl, $gh['token']);
        if (!qa_looks_like_zip($tmpZipPath)) {
            throw new RuntimeException("L'archive téléchargée depuis GitHub n'est pas une archive ZIP valide");
        }

        $backupPath = qa_create_code_backup();
        qa_maintenance_log('Sauvegarde créée avant mise à jour GitHub par ' . $actingUsername . ' : ' . basename($backupPath));

        $previousVersion = qa_current_app_version();
        $result = qa_extract_and_apply_zip($tmpZipPath);
        $newVersion = qa_current_app_version();

        qa_maintenance_log(sprintf(
            'Mise à jour GitHub (%s) appliquée par %s : %d fichier(s) copié(s), %d ignoré(s). Version : %s -> %s.',
            $tagName !== '' ? $tagName : 'inconnu', $actingUsername, $result['copied'], $result['skipped'], $previousVersion, $newVersion
        ));

        echo json_encode([
            'success' => true,
            'files_copied' => $result['copied'],
            'files_skipped' => $result['skipped'],
            'backup_created' => basename($backupPath),
        ]);
    } catch (Throwable $e) {
        qa_maintenance_log('ÉCHEC de mise à jour GitHub (tenté par ' . $actingUsername . ') : ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Échec de la mise à jour depuis GitHub : ' . $e->getMessage()]);
    } finally {
        if ($tmpZipPath !== null && file_exists($tmpZipPath)) @unlink($tmpZipPath);
    }
    exit;
}

if ($action === 'update') {
    if (empty($_FILES['zipfile']) || $_FILES['zipfile']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Aucun fichier reçu, ou erreur pendant le transfert']);
        exit;
    }
    $uploaded = $_FILES['zipfile'];

    if ($uploaded['size'] > QA_MAX_UPLOAD_ZIP_BYTES) {
        http_response_code(413);
        echo json_encode(['success' => false, 'message' => 'Fichier trop volumineux (maximum 20 Mo)']);
        exit;
    }
    if (!str_ends_with(strtolower($uploaded['name']), '.zip')) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Le fichier doit avoir l'extension .zip"]);
        exit;
    }
    if (!qa_looks_like_zip($uploaded['tmp_name'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Le fichier fourni n'est pas une archive ZIP valide"]);
        exit;
    }

    try {
        $backupPath = qa_create_code_backup();
        qa_maintenance_log('Sauvegarde créée avant mise à jour par ' . $actingUsername . ' : ' . basename($backupPath));

        $previousVersion = qa_current_app_version();
        $result = qa_extract_and_apply_zip($uploaded['tmp_name']);
        $newVersion = qa_current_app_version();

        qa_maintenance_log(sprintf(
            'Mise à jour appliquée par %s : %d fichier(s) copié(s), %d ignoré(s) (chemins protégés). Version : %s -> %s.',
            $actingUsername, $result['copied'], $result['skipped'], $previousVersion, $newVersion
        ));

        echo json_encode([
            'success' => true,
            'files_copied' => $result['copied'],
            'files_skipped' => $result['skipped'],
            'backup_created' => basename($backupPath),
        ]);
    } catch (Throwable $e) {
        qa_maintenance_log('ÉCHEC de mise à jour (tenté par ' . $actingUsername . ') : ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Échec de la mise à jour : ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'restore-backup') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $backupPath = qa_safe_backup_path((string)($body['filename'] ?? ''));
    if ($backupPath === null) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Sauvegarde introuvable']);
        exit;
    }

    try {
        $result = qa_extract_and_apply_zip($backupPath);
        qa_maintenance_log(sprintf('Restauration de la sauvegarde %s par %s : %d fichier(s) restauré(s).', basename($backupPath), $actingUsername, $result['copied']));
        echo json_encode(['success' => true, 'files_copied' => $result['copied'], 'restored_from' => basename($backupPath)]);
    } catch (Throwable $e) {
        qa_maintenance_log('ÉCHEC de restauration (tenté par ' . $actingUsername . ') : ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Échec de la restauration : ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'delete-backup') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $filename = (string)($body['filename'] ?? '');
    $backupPath = qa_safe_backup_path($filename);
    if ($backupPath === null) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Sauvegarde introuvable']);
        exit;
    }

    if (!@unlink($backupPath)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Impossible de supprimer cette sauvegarde (droits insuffisants ?)']);
        exit;
    }

    qa_maintenance_log(sprintf('Sauvegarde %s supprimée par %s.', $filename, $actingUsername));
    echo json_encode(['success' => true, 'deleted' => $filename]);
    exit;
}

if ($method === 'GET' && $action === 'log') {
    $lines = [];
    if (is_file(QA_MAINTENANCE_LOG)) {
        $content = @file_get_contents(QA_MAINTENANCE_LOG);
        if ($content !== false && $content !== '') {
            $all = preg_split('/\r\n|\r|\n/', trim($content));
            $lines = array_reverse(array_slice($all, -300));
        }
    }
    echo json_encode(['success' => true, 'lines' => $lines]);
    exit;
}

if ($action === 'clear-log') {
    @file_put_contents(QA_MAINTENANCE_LOG, '', LOCK_EX);
    qa_maintenance_log('Journal de maintenance vidé manuellement par ' . $actingUsername . '.');
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Action inconnue']);
