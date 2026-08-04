<?php
// Gestion des images jointes aux questions. Stockées dans uploads/questions/
// (dossier public, servi directement par le serveur web), nommées avec un
// identifiant aléatoire pour éviter toute collision ou déduction du nom
// d'origine.

define('QA_UPLOADS_DIR', __DIR__ . '/../uploads/questions');
define('QA_UPLOADS_URL', 'uploads/questions');

function qa_uploads_ensure_dir() {
    if (!is_dir(QA_UPLOADS_DIR)) {
        mkdir(QA_UPLOADS_DIR, 0755, true);
    }
}

function qa_save_question_image($file) {
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];

    if ($file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException("L'image ne doit pas dépasser 5 Mo");
    }

    // Le Content-Type envoyé par le navigateur ($file['type']) est
    // falsifiable : on vérifie le type réel du contenu (finfo) plutôt que
    // de lui faire confiance, avant de choisir l'extension de destination.
    $realType = @finfo_file(finfo_open(FILEINFO_MIME_TYPE), $file['tmp_name']);
    if (!isset($allowed[$realType])) {
        throw new RuntimeException('Format d\'image non supporté (jpg, png, webp ou gif uniquement)');
    }

    qa_uploads_ensure_dir();
    $ext = $allowed[$realType];
    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    $dest = QA_UPLOADS_DIR . '/' . $name;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException("Impossible d'enregistrer l'image");
    }

    return QA_UPLOADS_URL . '/' . $name;
}

function qa_delete_question_image($relativePath) {
    if (!$relativePath) return;
    $full = __DIR__ . '/../' . $relativePath;
    if (is_file($full) && strpos(realpath($full), realpath(QA_UPLOADS_DIR)) === 0) {
        @unlink($full);
    }
}
