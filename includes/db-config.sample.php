<?php
// Copier ce fichier en db-config.php (même dossier) et renseigner les
// identifiants de la base MariaDB/MySQL. includes/db-config.php n'est pas
// versionné (voir .gitignore) — les identifiants ne partent jamais sur Git.
//
// Alternative : définir les variables d'environnement DB_HOST, DB_PORT,
// DB_NAME, DB_USER, DB_PASS (utile en conteneur/hébergement mutualisé) ;
// ce fichier, s'il existe, complète/écrase ces variables.

return [
    'host' => '127.0.0.1',
    'port' => '3306',
    'database' => 'archeryops_judging',
    'username' => 'archeryops',
    'password' => 'change-me',

    // Optionnel : permet la détection/mise à jour depuis une release GitHub
    // dans l'onglet Maintenance de l'admin. Le token doit avoir le droit de
    // lecture du dépôt (repo privé) ou des releases (repo public). Sans
    // cette clé, la mise à jour reste possible par upload manuel d'un .zip.
    // 'github' => [
    //     'token' => 'ghp_xxx',
    //     'owner' => 'v-bresson',
    //     'repo' => 'quizz-arbitre',
    // ],
];
