<?php
// Connexion PDO SQLite + création du schéma si besoin.
// Base stockée dans data/quizz.sqlite (non versionnée, voir .gitignore).

function get_db() {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    date_default_timezone_set('Europe/Paris');

    $dataDir = __DIR__ . '/../data';
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0700, true);
    }
    $dbFile = $dataDir . '/quizz.sqlite';

    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');

    // ---------------------------------------------------------------
    // Questions : QCM à réponse unique, QCM à réponses multiples, ou
    // question ouverte (réponse libre, non notée automatiquement).
    // Une question peut être réservée à l'examen (exclue des tirages
    // des questionnaires d'entraînement).
    // ---------------------------------------------------------------
    $pdo->exec("CREATE TABLE IF NOT EXISTS questions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        categorie TEXT NOT NULL DEFAULT 'Général',
        type TEXT NOT NULL DEFAULT 'qcm_unique',
        enonce TEXT NOT NULL,
        image TEXT,
        option_a TEXT,
        option_b TEXT,
        option_c TEXT,
        option_d TEXT,
        bonne_reponse TEXT,
        points INTEGER NOT NULL DEFAULT 1,
        examen_uniquement INTEGER NOT NULL DEFAULT 0,
        actif INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    )");

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
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nom TEXT NOT NULL,
        description TEXT,
        type TEXT NOT NULL DEFAULT 'entrainement',
        categorie_filtre TEXT,
        nombre_questions INTEGER NOT NULL DEFAULT 10,
        repartition TEXT,
        note_max INTEGER NOT NULL DEFAULT 20,
        seuil_reussite INTEGER NOT NULL DEFAULT 10,
        duree_minutes INTEGER,
        ouverture_debut TEXT,
        ouverture_fin TEXT,
        tentatives_max INTEGER,
        afficher_score INTEGER NOT NULL DEFAULT 1,
        actif INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    )");

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
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        quiz_id INTEGER REFERENCES quizzes(id) ON DELETE SET NULL,
        quiz_nom TEXT NOT NULL,
        quiz_type TEXT NOT NULL DEFAULT 'entrainement',
        candidat TEXT NOT NULL,
        statut TEXT NOT NULL DEFAULT 'en_cours',
        questions_json TEXT NOT NULL,
        reponses_json TEXT,
        score REAL,
        note_max REAL NOT NULL,
        seuil_reussite REAL NOT NULL DEFAULT 0,
        duree_minutes INTEGER,
        reussi INTEGER,
        afficher_score INTEGER NOT NULL DEFAULT 1,
        started_at TEXT NOT NULL DEFAULT (datetime('now')),
        completed_at TEXT,
        details TEXT
    )");

    return $pdo;
}
