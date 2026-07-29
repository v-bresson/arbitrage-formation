<?php
// Connexion PDO SQLite + création du schéma si besoin.
// Base stockée dans data/quizz.sqlite (non versionnée, voir .gitignore).

function get_db() {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dataDir = __DIR__ . '/../data';
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0700, true);
    }
    $dbFile = $dataDir . '/quizz.sqlite';
    $isNew = !file_exists($dbFile);

    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');

    $pdo->exec("CREATE TABLE IF NOT EXISTS questions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        categorie TEXT NOT NULL DEFAULT 'Général',
        enonce TEXT NOT NULL,
        option_a TEXT NOT NULL,
        option_b TEXT NOT NULL,
        option_c TEXT,
        option_d TEXT,
        bonne_reponse TEXT NOT NULL,
        points INTEGER NOT NULL DEFAULT 1,
        actif INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS quizzes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nom TEXT NOT NULL,
        description TEXT,
        categorie_filtre TEXT,
        nombre_questions INTEGER NOT NULL DEFAULT 10,
        note_max INTEGER NOT NULL DEFAULT 20,
        seuil_reussite INTEGER NOT NULL DEFAULT 10,
        actif INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS tentatives (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        quiz_id INTEGER NOT NULL REFERENCES quizzes(id) ON DELETE CASCADE,
        candidat TEXT NOT NULL,
        score REAL NOT NULL,
        note_max REAL NOT NULL,
        reussi INTEGER NOT NULL,
        details TEXT,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    )");

    return $pdo;
}
