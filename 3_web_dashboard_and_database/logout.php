<?php
/**
 * Logout Sessione Utente
 */

require_once __DIR__ . '/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Rimuove il cookie di persistenza di 1 anno e cancella il token dal DB
if (function_exists('get_db_connection')) {
    try {
        $pdo = get_db_connection();
        if ($pdo instanceof PDO) {
            clear_remember_cookie($pdo);
        }
    } catch (Exception $e) {
        clear_remember_cookie();
    }
} else {
    clear_remember_cookie();
}

$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();
session_write_close();

header('Location: login.php');
exit;
