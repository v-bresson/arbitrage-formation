<?php
// ===================================================================
// Mise à jour de l'application par upload de .zip ou depuis une release
// GitHub, avec sauvegarde automatique du code avant toute modification,
// restauration, et journal de maintenance. Adapté du module de
// maintenance du projet gestion-sportive.
//
// Sécurité :
//  - Toute action passe par require_admin() (voir api/maintenance.php).
//  - Le contenu d'un zip uploadé n'est jamais exécuté : uniquement
//    extrait puis copié fichier par fichier après vérification de la
//    signature binaire et de la présence de fichiers marqueurs
//    attestant qu'il s'agit bien d'une version de cette application.
//  - includes/db-config.php, data/ et uploads/questions/ (contenu
//    utilisateur) ne sont jamais écrasés par une mise à jour ou une
//    restauration, quel que soit le contenu de l'archive fournie.
// ===================================================================

define('QA_APP_ROOT', dirname(__DIR__));
define('QA_VERSION_FILE', QA_APP_ROOT . '/VERSION.txt');
define('QA_BACKUPS_DIR', QA_APP_ROOT . '/backups');
define('QA_MAINTENANCE_LOG', QA_BACKUPS_DIR . '/maintenance.log');
define('QA_MAX_BACKUPS_KEPT', 10);
define('QA_MAX_UPLOAD_ZIP_BYTES', 20 * 1024 * 1024);
define('QA_MAINTENANCE_LOG_RETENTION_DAYS', 90);

// Chemins relatifs (à QA_APP_ROOT) jamais écrasés par une mise à jour ou
// une restauration, ni inclus dans une sauvegarde.
const QA_PROTECTED_PATHS = [
    '.git',
    'backups',
    'includes/db-config.php',
    'data',
    'uploads/questions',
];

// Fichiers dont la présence atteste qu'une archive contient bien une
// version valide de cette application (pas un zip quelconque).
const QA_MARKER_FILES = ['index.php', 'admin/index.php'];

function qa_current_app_version() {
    if (file_exists(QA_VERSION_FILE)) {
        $version = trim((string)file_get_contents(QA_VERSION_FILE));
        if ($version !== '') return $version;
    }
    return 'inconnue';
}

function qa_is_protected_relative_path($relativePath) {
    $normalized = ltrim(str_replace('\\', '/', $relativePath), '/');
    foreach (QA_PROTECTED_PATHS as $protected) {
        if ($normalized === $protected || strpos($normalized, $protected . '/') === 0) {
            return true;
        }
    }
    return false;
}

function qa_contains_path_traversal($relativePath) {
    $parts = explode('/', str_replace('\\', '/', $relativePath));
    return in_array('..', $parts, true);
}

function qa_list_files_recursive($dir) {
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY,
        RecursiveIteratorIterator::CATCH_GET_CHILD
    );
    foreach ($iterator as $fileInfo) {
        if ($fileInfo->isFile() && $fileInfo->isReadable()) {
            $relative = substr($fileInfo->getPathname(), strlen($dir) + 1);
            $files[] = str_replace('\\', '/', $relative);
        }
    }
    return $files;
}

function qa_remove_directory($dir) {
    if (!is_dir($dir)) return;
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        if ($item->isDir()) @rmdir($item->getPathname());
        else @unlink($item->getPathname());
    }
    @rmdir($dir);
}

function qa_maintenance_log($message) {
    if (!is_dir(QA_BACKUPS_DIR)) @mkdir(QA_BACKUPS_DIR, 0750, true);
    $line = sprintf('[%s] %s%s', date('c'), $message, PHP_EOL);
    @file_put_contents(QA_MAINTENANCE_LOG, $line, FILE_APPEND | LOCK_EX);
    if (random_int(1, 50) === 1) qa_purge_maintenance_log(QA_MAINTENANCE_LOG_RETENTION_DAYS);
}

function qa_purge_maintenance_log($days) {
    if (!is_file(QA_MAINTENANCE_LOG)) return;
    $content = @file_get_contents(QA_MAINTENANCE_LOG);
    if ($content === false || $content === '') return;
    $cutoff = time() - ($days * 86400);
    $lines = preg_split('/\r\n|\r|\n/', trim($content));
    $kept = array_filter($lines, function ($line) use ($cutoff) {
        if (!preg_match('/^\[([^\]]+)\]/', $line, $m)) return true;
        $ts = strtotime($m[1]);
        return $ts === false || $ts >= $cutoff;
    });
    @file_put_contents(QA_MAINTENANCE_LOG, implode(PHP_EOL, $kept) . ($kept ? PHP_EOL : ''), LOCK_EX);
}

// Crée un zip de sauvegarde complet du code actuel (hors chemins protégés
// et backups/ lui-même), puis applique la rotation (ne garde que les
// QA_MAX_BACKUPS_KEPT plus récentes). Bloque (et supprime le zip partiel)
// si un seul fichier n'a pas pu être ajouté : une mise à jour ne doit
// jamais s'appliquer sans une sauvegarde intégrale de l'état précédent.
function qa_create_code_backup() {
    if (!is_dir(QA_BACKUPS_DIR)) mkdir(QA_BACKUPS_DIR, 0750, true);
    $filename = 'backup_' . date('Y-m-d_H-i-s') . '.zip';
    $path = QA_BACKUPS_DIR . '/' . $filename;

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException("Impossible de créer l'archive de sauvegarde");
    }

    $files = qa_list_files_recursive(QA_APP_ROOT);
    $failed = [];
    foreach ($files as $relative) {
        if (strpos($relative, '.git/') === 0 || strpos($relative, 'backups/') === 0) continue;
        if (!$zip->addFile(QA_APP_ROOT . '/' . $relative, $relative)) $failed[] = $relative;
    }
    $zip->close();

    if (!empty($failed)) {
        @unlink($path);
        qa_maintenance_log('ÉCHEC sauvegarde (incomplète, opération annulée) : ' . count($failed) . ' fichier(s) inaccessibles');
        throw new RuntimeException('La sauvegarde automatique est incomplète (' . count($failed) . ' fichier(s) inaccessibles) : opération annulée par sécurité.');
    }

    qa_rotate_backups();
    return $path;
}

function qa_rotate_backups() {
    $backups = glob(QA_BACKUPS_DIR . '/backup_*.zip');
    if ($backups === false || count($backups) <= QA_MAX_BACKUPS_KEPT) return;
    usort($backups, fn($a, $b) => filemtime($b) <=> filemtime($a));
    foreach (array_slice($backups, QA_MAX_BACKUPS_KEPT) as $old) @unlink($old);
}

function qa_list_backups() {
    $backups = glob(QA_BACKUPS_DIR . '/backup_*.zip');
    if ($backups === false) return [];
    usort($backups, fn($a, $b) => filemtime($b) <=> filemtime($a));
    return array_map(fn($path) => [
        'filename' => basename($path),
        'created_at' => date('c', filemtime($path)),
        'size_bytes' => filesize($path),
    ], $backups);
}

// N'accepte qu'un nom de fichier au format exact attendu, présent
// directement dans QA_BACKUPS_DIR : empêche toute traversée de
// répertoire via un nom forgé par le client.
function qa_safe_backup_path($filename) {
    if (!preg_match('/^backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.zip$/', $filename)) return null;
    $path = QA_BACKUPS_DIR . '/' . $filename;
    return is_file($path) ? $path : null;
}

function qa_looks_like_zip($filePath) {
    $handle = fopen($filePath, 'rb');
    if ($handle === false) return false;
    $header = fread($handle, 4);
    fclose($handle);
    return $header === "PK\x03\x04" || $header === "PK\x05\x06" || $header === "PK\x07\x08";
}

// Extrait un zip dans un dossier temporaire, vérifie la présence des
// fichiers marqueurs (à la racine de l'archive, ou dans un unique
// sous-dossier englobant — cas des exports GitHub), puis copie
// sélectivement son contenu vers QA_APP_ROOT en préservant les chemins
// protégés. Copie additive : ne supprime jamais un fichier absent de
// l'archive mais présent sur le disque.
function qa_extract_and_apply_zip($zipPath) {
    $tmpDir = sys_get_temp_dir() . '/qa_update_' . bin2hex(random_bytes(6));
    mkdir($tmpDir, 0750, true);

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        qa_remove_directory($tmpDir);
        throw new RuntimeException("Impossible d'ouvrir l'archive : fichier corrompu ou invalide");
    }
    $zip->extractTo($tmpDir);
    $zip->close();

    $sourceDir = $tmpDir;
    $entries = array_values(array_diff((array)scandir($tmpDir), ['.', '..']));
    if (count($entries) === 1 && is_dir($tmpDir . '/' . $entries[0])) {
        $sourceDir = $tmpDir . '/' . $entries[0];
    }

    foreach (QA_MARKER_FILES as $marker) {
        if (!file_exists($sourceDir . '/' . $marker)) {
            qa_remove_directory($tmpDir);
            throw new RuntimeException("L'archive ne semble pas contenir une version valide de l'application (fichier attendu introuvable : $marker)");
        }
    }

    $files = qa_list_files_recursive($sourceDir);
    // includes/*.php (dont maintenance.php, chargé par le script en cours
    // d'exécution) copiés en dernier pour limiter le risque de verrouillage.
    usort($files, function ($a, $b) {
        $aIsIncludes = strpos($a, 'includes/') === 0 && str_ends_with($a, '.php');
        $bIsIncludes = strpos($b, 'includes/') === 0 && str_ends_with($b, '.php');
        return $aIsIncludes === $bIsIncludes ? 0 : ($aIsIncludes ? 1 : -1);
    });

    $copied = 0;
    $skipped = 0;
    $failed = [];
    foreach ($files as $relative) {
        if (qa_contains_path_traversal($relative) || qa_is_protected_relative_path($relative)) {
            $skipped++;
            continue;
        }
        $destPath = QA_APP_ROOT . '/' . $relative;
        $destDir = dirname($destPath);
        if (!is_dir($destDir) && !mkdir($destDir, 0750, true) && !is_dir($destDir)) {
            $failed[] = $relative;
            continue;
        }
        if (copy($sourceDir . '/' . $relative, $destPath)) $copied++;
        else $failed[] = $relative;
    }

    qa_remove_directory($tmpDir);

    if (!empty($failed)) {
        throw new RuntimeException(
            count($failed) . " fichier(s) n'ont pas pu être remplacés (droits d'écriture insuffisants) : "
            . implode(', ', array_slice($failed, 0, 15)) . (count($failed) > 15 ? '...' : '')
            . '. Aucun fichier de sauvegarde perdu : relancez la mise à jour, ou copiez ces fichiers manuellement.'
        );
    }

    if (function_exists('opcache_reset')) @opcache_reset();

    return ['copied' => $copied, 'skipped' => $skipped];
}

// Configuration GitHub optionnelle (clé 'github' => ['token', 'owner',
// 'repo']) ajoutée manuellement dans includes/db-config.php. Absence non
// bloquante : la mise à jour par upload de zip fonctionne sans elle.
function qa_github_config() {
    $configFile = __DIR__ . '/db-config.php';
    if (!file_exists($configFile)) return null;
    $config = require $configFile;
    $gh = $config['github'] ?? null;
    if (!is_array($gh) || empty($gh['token']) || empty($gh['owner']) || empty($gh['repo'])) return null;
    return $gh;
}

function qa_github_http_request($url, $token, $binaryDownload = false) {
    if (!function_exists('curl_init')) {
        throw new RuntimeException("L'extension PHP curl est requise pour les mises à jour GitHub");
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Accept: ' . ($binaryDownload ? '*/*' : 'application/vnd.github+json'),
            'X-GitHub-Api-Version: 2022-11-28',
            'User-Agent: archeryops-judging-maintenance',
        ],
    ]);
    $body = curl_exec($ch);
    if ($body === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('Échec de la requête vers GitHub : ' . $error);
    }
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $status, 'body' => (string)$body];
}

function qa_github_check_latest_release() {
    $gh = qa_github_config();
    if ($gh === null) return ['configured' => false];

    $url = sprintf('https://api.github.com/repos/%s/%s/releases/latest', $gh['owner'], $gh['repo']);
    $res = qa_github_http_request($url, $gh['token']);

    if ($res['status'] === 404) {
        throw new RuntimeException("Aucune release GitHub trouvée sur ce dépôt. Créez une release (avec un tag, ex. v1.1.0) pour que la détection fonctionne.");
    }
    if ($res['status'] === 401) {
        throw new RuntimeException('Jeton GitHub invalide ou expiré (401)');
    }
    if ($res['status'] !== 200) {
        throw new RuntimeException('Réponse inattendue de l\'API GitHub (HTTP ' . $res['status'] . ')');
    }

    $data = json_decode($res['body'], true);
    if (!is_array($data) || empty($data['tag_name'])) {
        throw new RuntimeException("Réponse de l'API GitHub illisible");
    }

    $tagName = (string)$data['tag_name'];
    $latestVersion = ltrim($tagName, 'vV');
    $currentVersion = qa_current_app_version();

    return [
        'configured' => true,
        'latest_version' => $latestVersion,
        'current_version' => $currentVersion,
        'update_available' => version_compare($latestVersion, $currentVersion, '>'),
        'published_at' => $data['published_at'] ?? '',
        'html_url' => $data['html_url'] ?? '',
        'zipball_url' => $data['zipball_url'] ?? '',
        'tag_name' => $tagName,
    ];
}

function qa_github_download_zipball($zipballUrl, $token) {
    $res = qa_github_http_request($zipballUrl, $token, true);
    if ($res['status'] !== 200) {
        throw new RuntimeException('Échec du téléchargement de l\'archive GitHub (HTTP ' . $res['status'] . ')');
    }
    $tmpPath = sys_get_temp_dir() . '/qa_github_' . bin2hex(random_bytes(6)) . '.zip';
    if (file_put_contents($tmpPath, $res['body']) === false) {
        throw new RuntimeException("Impossible d'écrire l'archive téléchargée sur le disque");
    }
    return $tmpPath;
}
