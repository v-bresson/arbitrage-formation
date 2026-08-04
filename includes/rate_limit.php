<?php
// ===================================================================
// Anti-brute-force sur la connexion (api/auth.php, action=login).
// Compte les échecs récents par couple (identifiant, IP) dans la table
// login_attempts (includes/db.php) : au-delà de QA_LOGIN_MAX_ATTEMPTS
// échecs sur QA_LOGIN_WINDOW_MINUTES minutes, les tentatives suivantes
// sont bloquées jusqu'à expiration de la fenêtre — sans distinguer
// "mauvais mot de passe" de "compte inexistant" pour ne pas révéler
// quels identifiants existent.
// ===================================================================

const QA_LOGIN_MAX_ATTEMPTS = 5;
const QA_LOGIN_WINDOW_MINUTES = 15;

function qa_client_ip() {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// Nombre d'échecs récents pour ce couple identifiant/IP, dans la fenêtre
// glissante. Sert à la fois à décider du blocage et à purger au passage
// les lignes trop anciennes (pas de tâche cron dédiée nécessaire).
function qa_recent_login_failures($pdo, $username, $ip) {
    $pdo->prepare('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL ? MINUTE)')
        ->execute([QA_LOGIN_WINDOW_MINUTES]);
    $stmt = $pdo->prepare('SELECT COUNT(*) c FROM login_attempts WHERE username = ? AND ip_address = ? AND attempted_at >= (NOW() - INTERVAL ? MINUTE)');
    $stmt->execute([$username, $ip, QA_LOGIN_WINDOW_MINUTES]);
    return (int)$stmt->fetch()['c'];
}

// À appeler avant de vérifier le mot de passe : renvoie le nombre de
// minutes restantes si le couple identifiant/IP est actuellement bloqué,
// ou null si la tentative peut être traitée normalement.
function qa_login_blocked_minutes($pdo, $username, $ip) {
    if (qa_recent_login_failures($pdo, $username, $ip) < QA_LOGIN_MAX_ATTEMPTS) return null;
    return QA_LOGIN_WINDOW_MINUTES;
}

function qa_record_login_failure($pdo, $username, $ip) {
    $pdo->prepare('INSERT INTO login_attempts (username, ip_address) VALUES (?, ?)')->execute([$username, $ip]);
}

function qa_clear_login_failures($pdo, $username, $ip) {
    $pdo->prepare('DELETE FROM login_attempts WHERE username = ? AND ip_address = ?')->execute([$username, $ip]);
}
