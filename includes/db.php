<?php
// Connexion PDO MariaDB/MySQL + création du schéma si besoin.
//
// Les identifiants de connexion sont lus, par ordre de priorité :
//   1. Variables d'environnement DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS
//   2. Fichier includes/db-config.php (non versionné, voir .gitignore) —
//      copier includes/db-config.sample.php et renseigner ses identifiants.

function qa_db_config() {
    $config = [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'database' => getenv('DB_NAME') ?: 'archeryops_judging',
        'username' => getenv('DB_USER') ?: 'archeryops',
        'password' => getenv('DB_PASS') ?: '',
    ];

    $configFile = __DIR__ . '/db-config.php';
    if (file_exists($configFile)) {
        $config = array_merge($config, require $configFile);
    }

    return $config;
}

// Vrai si des identifiants ont déjà été renseignés explicitement (fichier
// db-config.php présent, ou variables d'environnement DB_*), plutôt que les
// valeurs par défaut du code : sert à distinguer "pas encore installé" d'une
// vraie panne de connexion, pour orienter vers install.php le cas échéant.
function qa_db_configured() {
    return file_exists(__DIR__ . '/db-config.php') || getenv('DB_HOST') !== false;
}

function qa_column_exists($pdo, $table, $column) {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
    );
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetch()['c'] > 0;
}

// ---------------------------------------------------------------------
// Migrations de schéma : liste centrale des évolutions de la base au fil
// des versions. Une migration n'est JAMAIS appliquée automatiquement sur
// une installation existante (voir qa_sync_schema_migrations ci-dessous) —
// elle doit être lancée volontairement par l'admin depuis l'onglet
// Administration > Mise à jour système, après une mise à jour de code,
// pour que l'admin sache explicitement qu'une modification de la base a
// eu lieu plutôt que de la voir se produire en silence.
// Sur une INSTALLATION NEUVE en revanche, les colonnes existent déjà
// (CREATE TABLE ci-dessous les inclut) : la migration est alors marquée
// automatiquement comme appliquée, sans ALTER TABLE ni notification.
// ---------------------------------------------------------------------
function qa_schema_migrations() {
    return [
        [
            'id' => 'users_profil_2026_07',
            'description' => "Ajout des champs profil sur les comptes utilisateurs (prénom, nom, email, téléphone, n° de licence, club)",
            'columns' => [
                ['table' => 'users', 'column' => 'nom', 'definition' => 'VARCHAR(191) NULL'],
                ['table' => 'users', 'column' => 'prenom', 'definition' => 'VARCHAR(191) NULL'],
                ['table' => 'users', 'column' => 'email', 'definition' => 'VARCHAR(191) NULL'],
                ['table' => 'users', 'column' => 'numero_licence', 'definition' => 'VARCHAR(50) NULL'],
                ['table' => 'users', 'column' => 'telephone', 'definition' => 'VARCHAR(30) NULL'],
                ['table' => 'users', 'column' => 'club', 'definition' => 'VARCHAR(191) NULL'],
            ],
        ],
    ];
}

function qa_migration_columns_present($pdo, $migration) {
    foreach ($migration['columns'] as $col) {
        if (!qa_column_exists($pdo, $col['table'], $col['column'])) return false;
    }
    return true;
}

// Marque comme "appliquées" les migrations dont les colonnes sont déjà
// toutes présentes (installation neuve, ou migration déjà appliquée
// manuellement) — n'altère jamais le schéma lui-même.
function qa_sync_schema_migrations($pdo) {
    foreach (qa_schema_migrations() as $migration) {
        if (qa_migration_columns_present($pdo, $migration)) {
            $stmt = $pdo->prepare('INSERT IGNORE INTO schema_migrations (id) VALUES (?)');
            $stmt->execute([$migration['id']]);
        }
    }
}

function qa_pending_migrations($pdo) {
    $stmt = $pdo->query('SELECT id FROM schema_migrations');
    $applied = array_column($stmt->fetchAll(), 'id');
    $pending = [];
    foreach (qa_schema_migrations() as $migration) {
        if (!in_array($migration['id'], $applied, true)) {
            $pending[] = $migration;
        }
    }
    return $pending;
}

// Applique réellement une migration (ALTER TABLE) — appelé uniquement à
// la demande explicite de l'admin (api/maintenance.php, action db-migrate).
function qa_apply_migration($pdo, $migration) {
    foreach ($migration['columns'] as $col) {
        if (!qa_column_exists($pdo, $col['table'], $col['column'])) {
            $pdo->exec("ALTER TABLE `{$col['table']}` ADD COLUMN `{$col['column']}` {$col['definition']}");
        }
    }
    $stmt = $pdo->prepare('INSERT IGNORE INTO schema_migrations (id) VALUES (?)');
    $stmt->execute([$migration['id']]);
}

function get_db() {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    date_default_timezone_set('Europe/Paris');

    $config = qa_db_config();
    $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset=utf8mb4";

    try {
        $pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'needs_install' => !qa_db_configured(),
            'message' => "Connexion à la base MariaDB impossible. Vérifiez includes/db-config.php (ou les variables d'environnement DB_HOST/DB_NAME/DB_USER/DB_PASS) et que la base existe. Détail : " . $e->getMessage(),
        ]);
        exit;
    }

    // ---------------------------------------------------------------
    // Questions : QCM à réponse unique, QCM à réponses multiples, ou
    // question ouverte (réponse libre, non notée automatiquement).
    // Une question peut être réservée à l'examen (exclue des tirages
    // des questionnaires d'entraînement).
    // ---------------------------------------------------------------
    $pdo->exec("CREATE TABLE IF NOT EXISTS questions (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        categorie VARCHAR(191) NOT NULL DEFAULT 'Général',
        type VARCHAR(20) NOT NULL DEFAULT 'qcm_unique',
        enonce TEXT NOT NULL,
        image VARCHAR(255) NULL,
        option_a TEXT NULL,
        option_b TEXT NULL,
        option_c TEXT NULL,
        option_d TEXT NULL,
        bonne_reponse VARCHAR(50) NULL,
        points INT NOT NULL DEFAULT 1,
        examen_uniquement TINYINT(1) NOT NULL DEFAULT 0,
        actif TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_questions_categorie (categorie),
        INDEX idx_questions_actif (actif)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ---------------------------------------------------------------
    // Questionnaires : entraînement (piochent uniquement dans les
    // questions non réservées à l'examen) ou examen (piochent dans
    // toute la banque, fenêtre d'ouverture, durée, tentatives max et
    // affichage du score configurables). `repartition` est un JSON
    // optionnel [{"categorie":"Sécurité","nombre_questions":3}, ...] :
    // quand il est renseigné, il remplace categorie_filtre/nombre_questions
    // et pioche un nombre de questions donné par thématique.
    // ---------------------------------------------------------------
    $pdo->exec("CREATE TABLE IF NOT EXISTS quizzes (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        nom VARCHAR(255) NOT NULL,
        description TEXT NULL,
        type VARCHAR(20) NOT NULL DEFAULT 'entrainement',
        categorie_filtre VARCHAR(191) NULL,
        nombre_questions INT NOT NULL DEFAULT 10,
        repartition TEXT NULL,
        note_max DECIMAL(6,2) NOT NULL DEFAULT 20,
        seuil_reussite DECIMAL(6,2) NOT NULL DEFAULT 10,
        duree_minutes INT NULL,
        ouverture_debut DATETIME NULL,
        ouverture_fin DATETIME NULL,
        tentatives_max INT NULL,
        afficher_score TINYINT(1) NOT NULL DEFAULT 1,
        actif TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ---------------------------------------------------------------
    // Tentatives : archive de tous les passages (entraînement et
    // examen). Les questions tirées et les bonnes réponses sont figées
    // dans questions_json au démarrage, pour que la correction et
    // l'archive restent valables même si la banque de questions ou le
    // questionnaire changent ensuite. La tentative n'est pas liée par
    // contrainte stricte au questionnaire (ON DELETE SET NULL) afin de
    // rester consultable même si le questionnaire est supprimé.
    // ---------------------------------------------------------------
    $pdo->exec("CREATE TABLE IF NOT EXISTS tentatives (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        quiz_id INT UNSIGNED NULL,
        quiz_nom VARCHAR(255) NOT NULL,
        quiz_type VARCHAR(20) NOT NULL DEFAULT 'entrainement',
        candidat VARCHAR(255) NOT NULL,
        statut VARCHAR(20) NOT NULL DEFAULT 'en_cours',
        questions_json LONGTEXT NOT NULL,
        reponses_json LONGTEXT NULL,
        score DECIMAL(6,2) NULL,
        note_max DECIMAL(6,2) NOT NULL,
        seuil_reussite DECIMAL(6,2) NOT NULL DEFAULT 0,
        duree_minutes INT NULL,
        reussi TINYINT(1) NULL,
        afficher_score TINYINT(1) NOT NULL DEFAULT 1,
        started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        completed_at DATETIME NULL,
        details LONGTEXT NULL,
        INDEX idx_tentatives_quiz_candidat (quiz_id, candidat),
        CONSTRAINT fk_tentatives_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ---------------------------------------------------------------
    // Utilisateurs : compte unique pour toute l'application (connexion,
    // dashboard, tuiles). Le rôle 'admin' donne accès à l'espace
    // d'administration ; 'user' n'a accès qu'au dashboard et aux tuiles
    // non réservées. Le premier compte créé (écran de configuration
    // initiale) est toujours admin.
    // ---------------------------------------------------------------
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        role VARCHAR(20) NOT NULL DEFAULT 'user',
        actif TINYINT(1) NOT NULL DEFAULT 1,
        nom VARCHAR(191) NULL,
        prenom VARCHAR(191) NULL,
        email VARCHAR(191) NULL,
        numero_licence VARCHAR(50) NULL,
        telephone VARCHAR(30) NULL,
        club VARCHAR(191) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ---------------------------------------------------------------
    // Tuiles du dashboard : configurables depuis l'admin. type=questionnaire
    // pointe toujours vers quiz.php (module de questionnaires intégré) ;
    // type=lien pointe vers l'URL définie (autre module de la future
    // architecture ArcheryOps, ou lien externe). admin_uniquement masque
    // la tuile aux utilisateurs non-admin.
    // ---------------------------------------------------------------
    $pdo->exec("CREATE TABLE IF NOT EXISTS tiles (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        nom VARCHAR(191) NOT NULL,
        description TEXT NULL,
        type VARCHAR(20) NOT NULL DEFAULT 'lien',
        url VARCHAR(500) NULL,
        icone VARCHAR(50) NOT NULL DEFAULT 'info',
        admin_uniquement TINYINT(1) NOT NULL DEFAULT 0,
        ordre INT NOT NULL DEFAULT 0,
        actif TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $tileCount = (int)$pdo->query('SELECT COUNT(*) c FROM tiles')->fetch()['c'];
    if ($tileCount === 0) {
        $pdo->exec("INSERT INTO tiles (nom, description, type, icone, admin_uniquement, ordre, actif) VALUES
            ('Candidats arbitres', 'Passer un questionnaire d\\'entraînement ou d\\'examen', 'questionnaire', 'target', 0, 1, 1)");
    }

    // ---------------------------------------------------------------
    // Suivi des migrations de schéma (voir qa_schema_migrations ci-dessus).
    // ---------------------------------------------------------------
    $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
        id VARCHAR(191) NOT NULL PRIMARY KEY,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    qa_sync_schema_migrations($pdo);

    return $pdo;
}
