<?php
/**
 * Parametri di Configurazione e Connessione Database per NewFoobot
 * Rinomina questo file in 'config.php' dopo aver impostato le credenziali del tuo hosting cPanel,
 * oppure avvia l'installazione automatica tramite install.php.
 */

// Blocco accesso non autorizzato
if (!defined('FOOBOT_ACCESS')) {
    define('FOOBOT_ACCESS', true);
}

// Coordinate Database cPanel
define('DB_HOST', 'localhost');
define('DB_NAME', 'nome_tuo_db_cpanel');
define('DB_USER', 'utente_tuo_db');
define('DB_PASS', 'password_tuo_db');
define('DB_CHARSET', 'utf8mb4');

// Chiave API segreta per proteggere l'invio dati dal Bridge (generare una stringa complessa)
define('API_SECRET_KEY', 'CHIAVE_SEGRETA_DA_CAMBIARE_ABC123XYZ');

// Funzione PDO per connessione sicura con Prepared Statements
function get_db_connection() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['status' => 'error', 'message' => 'Errore connessione database: ' . $e->getMessage()]);
            exit;
        }
    }
    return $pdo;
}
