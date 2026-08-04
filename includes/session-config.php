<?php
// Configuration de session commune à toutes les pages qui démarrent une session.
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    // SameSite=Lax en défense en profondeur contre le CSRF (les mutations
    // passent déjà par un corps JSON en POST, jamais par la query string) ;
    // Secure dès que la requête arrive en HTTPS, pour ne pas casser un
    // accès de dev en HTTP simple.
    ini_set('session.cookie_samesite', 'Lax');
    $qaHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    if ($qaHttps) ini_set('session.cookie_secure', 1);
}
