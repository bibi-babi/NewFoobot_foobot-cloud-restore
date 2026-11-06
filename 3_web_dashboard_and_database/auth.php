<?php
/**
 * Autenticazione, Sicurezza, Rate Limiting, Geo-IP e Whitelist IP per NewFoobot
 */

// Impostazione fuso orario italiano di riferimento
if (function_exists('date_default_timezone_set')) {
    @date_default_timezone_set('Europe/Rome');
}

if (session_status() === PHP_SESSION_NONE) {
    // Durata sessione e cookie login: 1 anno (31536000 secondi)
    $one_year_seconds = 365 * 86400;
    @ini_set('session.gc_maxlifetime', (string)$one_year_seconds);

    // Riconoscimento HTTPS robusto anche dietro reverse-proxy, Cloudflare e cPanel
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
             || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
             || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
             || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on');

    session_set_cookie_params([
        'lifetime' => $one_year_seconds,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $is_https,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Header di sicurezza standard
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

/**
 * Riconosce se la richiesta corrente è sotto connessione sicura HTTPS
 */
function is_ssl_request() {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
        || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on');
}

/**
 * Imposta il cookie persistente della lingua con parametri uniformi e sicuri
 */
function set_app_lang_cookie($lang) {
    setcookie('foobot_lang', $lang, [
        'expires'  => time() + (365 * 86400),
        'path'     => '/',
        'domain'   => '',
        'secure'   => is_ssl_request(),
        'httponly' => false,
        'samesite' => 'Lax'
    ]);
}

/**
 * Restituisce l'indirizzo IP reale del visitatore
 */
function get_client_ip() {
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP']) && filter_var($_SERVER['HTTP_CF_CONNECTING_IP'], FILTER_VALIDATE_IP)) {
        return $_SERVER['HTTP_CF_CONNECTING_IP'];
    }
    if (!empty($_SERVER['REMOTE_ADDR']) && filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP)) {
        return $_SERVER['REMOTE_ADDR'];
    }
    return '0.0.0.0';
}

/**
 * Rileva il codice Paese a 2 lettere (es. IT, US, DE) dell'IP del visitatore
 */
function get_client_country($ip) {
    // 1. Header diretti del server / Cloudflare / GeoIP Apache
    if (!empty($_SERVER['HTTP_CF_IPCOUNTRY']) && strlen($_SERVER['HTTP_CF_IPCOUNTRY']) === 2) {
        return strtoupper($_SERVER['HTTP_CF_IPCOUNTRY']);
    }
    if (!empty($_SERVER['GEOIP_COUNTRY_CODE']) && strlen($_SERVER['GEOIP_COUNTRY_CODE']) === 2) {
        return strtoupper($_SERVER['GEOIP_COUNTRY_CODE']);
    }
    if (!empty($_SERVER['HTTP_X_COUNTRY_CODE']) && strlen($_SERVER['HTTP_X_COUNTRY_CODE']) === 2) {
        return strtoupper($_SERVER['HTTP_X_COUNTRY_CODE']);
    }

    // 2. IP locali o privati sono considerati locali (IT)
    if ($ip === '127.0.0.1' || $ip === '::1' || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return 'IT';
    }

    // 3. Cache in sessione per evitare chiamate ripetute
    if (isset($_SESSION['geo_cache'][$ip])) {
        return $_SESSION['geo_cache'][$ip];
    }

    // 4. Lookup leggero con timeout 2 secondi
    $ctx = stream_context_create(['http' => ['timeout' => 2, 'ignore_errors' => true]]);
    $resp = @file_get_contents('http://ip-api.com/json/' . urlencode($ip) . '?fields=status,countryCode', false, $ctx);
    if ($resp !== false) {
        $json = json_decode($resp, true);
        if (is_array($json) && ($json['status'] ?? '') === 'success' && !empty($json['countryCode'])) {
            $cc = strtoupper(substr($json['countryCode'], 0, 2));
            $_SESSION['geo_cache'][$ip] = $cc;
            return $cc;
        }
    }

    return '';
}

/**
 * Genera o recupera il token CSRF della sessione corrente
 */
function get_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Alias per retrocompatibilità con generate_csrf_token
 */
function generate_csrf_token() {
    return get_csrf_token();
}

/**
 * Verifica la validità del token CSRF inviato via POST
 */
function verify_csrf_token($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], (string)$token);
}

/**
 * Legge tutte le impostazioni dalla tabella foobot_settings come array associativo
 */
/**
 * Legge una singola impostazione da foobot_settings con valore di default
 */
function get_setting(PDO $pdo, $key, $default = '') {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM foobot_settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return ($val !== false && $val !== null) ? $val : $default;
    } catch (Exception $e) {
        return $default;
    }
}

function get_all_settings(PDO $pdo) {
    $settings = [
        'oracle_broker_ip'      => defined('ORACLE_BROKER_IP') ? ORACLE_BROKER_IP : '',
        'nextdns_server_ip'     => defined('NEXTDNS_SERVER_IP') ? NEXTDNS_SERVER_IP : '',
        'admin_ip_whitelist'    => '',
        'allowed_countries'     => '',
        'enforce_oracle_ip_api' => '0'
    ];
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM foobot_settings");
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    } catch (Exception $e) {
        // Ignora se la tabella non è ancora popolata
    }
    return $settings;
}

/**
 * Salva una singola impostazione in foobot_settings
 */
function save_setting(PDO $pdo, $key, $value) {
    $stmt = $pdo->prepare("INSERT INTO foobot_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute([$key, (string)$value]);
}

/**
 * Assicura che le tabelle ausiliarie e le colonne esistano (migrazione automatica senza perdita dati)
 */
function ensure_security_tables(PDO $pdo) {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    // Controllo rapido a costo zero: se la colonna più recente esiste già, esci subito evitando lock
    try {
        $test = $pdo->query("SELECT device_serial FROM foobot_authorized_devices LIMIT 1");
        if ($test !== false) {
            return;
        }
    } catch (Exception $e_fast) {
        // Colonna o tabella mancante: procede con la creazione idempotente una tantum
    }

    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $is_mysql = ($driver === 'mysql');

    try {
        if ($is_mysql) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS foobot_login_attempts (
                ip_address VARCHAR(45) NOT NULL PRIMARY KEY,
                attempts INT UNSIGNED NOT NULL DEFAULT 0,
                locked_until BIGINT UNSIGNED NOT NULL DEFAULT 0,
                last_attempt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS foobot_password_resets (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(128) NOT NULL,
                token VARCHAR(64) NOT NULL UNIQUE,
                expires_at BIGINT UNSIGNED NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_token (token)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Tabella token di accesso persistente (Remember Me / Login 1 anno)
            $pdo->exec("CREATE TABLE IF NOT EXISTS foobot_user_tokens (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                token_hash VARCHAR(64) NOT NULL UNIQUE,
                expires_at BIGINT UNSIGNED NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user (user_id),
                INDEX idx_token (token_hash),
                INDEX idx_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Tabella metadati sensori Foobot fisici (Nomi amichevoli per Multi-Foobot)
            $pdo->exec("CREATE TABLE IF NOT EXISTS foobot_devices_meta (
                device_uuid VARCHAR(32) NOT NULL PRIMARY KEY,
                device_name VARCHAR(64) NOT NULL DEFAULT '',
                location VARCHAR(64) NOT NULL DEFAULT '',
                color_badge VARCHAR(16) NOT NULL DEFAULT 'blue',
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Tabella dispositivi mobili/desktop autorizzati per App PWA
            $pdo->exec("CREATE TABLE IF NOT EXISTS foobot_authorized_devices (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                device_name VARCHAR(100) NOT NULL,
                device_serial VARCHAR(64) NOT NULL DEFAULT '',
                device_token_hash VARCHAR(64) NOT NULL UNIQUE,
                platform VARCHAR(64) NOT NULL DEFAULT 'unknown',
                app_mode VARCHAR(32) NOT NULL DEFAULT 'browser',
                user_agent VARCHAR(255) NOT NULL DEFAULT '',
                device_fingerprint VARCHAR(128) NOT NULL DEFAULT '',
                last_ip VARCHAR(45) NOT NULL DEFAULT '',
                last_active_at BIGINT UNSIGNED NOT NULL DEFAULT 0,
                is_revoked TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user (user_id),
                INDEX idx_token (device_token_hash),
                INDEX idx_serial (device_serial)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Assicura colonne 'device_serial', 'app_mode', 'user_agent' in foobot_authorized_devices (migrazione schema)
            $cols_ds = $pdo->query("SHOW COLUMNS FROM foobot_authorized_devices LIKE 'device_serial'")->fetchAll();
            if (empty($cols_ds)) {
                $pdo->exec("ALTER TABLE foobot_authorized_devices ADD COLUMN device_serial VARCHAR(64) NOT NULL DEFAULT '' AFTER device_name");
            }

            $cols_am = $pdo->query("SHOW COLUMNS FROM foobot_authorized_devices LIKE 'app_mode'")->fetchAll();
            if (empty($cols_am)) {
                $pdo->exec("ALTER TABLE foobot_authorized_devices ADD COLUMN app_mode VARCHAR(32) NOT NULL DEFAULT 'browser' AFTER platform");
            }

            $cols_ua = $pdo->query("SHOW COLUMNS FROM foobot_authorized_devices LIKE 'user_agent'")->fetchAll();
            if (empty($cols_ua)) {
                $pdo->exec("ALTER TABLE foobot_authorized_devices ADD COLUMN user_agent VARCHAR(255) NOT NULL DEFAULT '' AFTER app_mode");
            }

            // Popola Seriale Dispositivo per i record esistenti sprovvisti
            $pdo->exec("UPDATE foobot_authorized_devices 
                        SET device_serial = CONCAT('NF-DEV-', UPPER(SUBSTRING(device_token_hash, 1, 4)), '-', UPPER(SUBSTRING(device_token_hash, 5, 4))) 
                        WHERE device_serial = '' OR device_serial IS NULL");

            // Tabella ticket monouso di accoppiamento per QR Code (Scadenza 10 minuti)
            $pdo->exec("CREATE TABLE IF NOT EXISTS foobot_pairing_tickets (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                ticket_code VARCHAR(32) NOT NULL UNIQUE,
                token_secret VARCHAR(64) NOT NULL,
                device_label VARCHAR(100) NOT NULL DEFAULT '',
                expires_at BIGINT UNSIGNED NOT NULL,
                used_at BIGINT UNSIGNED NULL DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_ticket (ticket_code),
                INDEX idx_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Assicura colonna 'role' in foobot_users se creata con vecchio schema
            $cols = $pdo->query("SHOW COLUMNS FROM foobot_users LIKE 'role'")->fetchAll();
            if (empty($cols)) {
                $pdo->exec("ALTER TABLE foobot_users ADD COLUMN role ENUM('admin', 'user') NOT NULL DEFAULT 'admin' AFTER email");
            }

            // Assicura colonna 'temp_unit' in foobot_users
            $cols_t = $pdo->query("SHOW COLUMNS FROM foobot_users LIKE 'temp_unit'")->fetchAll();
            if (empty($cols_t)) {
                $pdo->exec("ALTER TABLE foobot_users ADD COLUMN temp_unit VARCHAR(1) NOT NULL DEFAULT 'C' AFTER language");
            }
        } else {
            // Compatibilità SQLite per test e ambienti leggeri
            try { $pdo->exec("ALTER TABLE foobot_users ADD COLUMN temp_unit TEXT NOT NULL DEFAULT 'C'"); } catch (Exception $e_col) {}
            $pdo->exec("CREATE TABLE IF NOT EXISTS foobot_login_attempts (
                ip_address TEXT PRIMARY KEY,
                attempts INTEGER NOT NULL DEFAULT 0,
                locked_until INTEGER NOT NULL DEFAULT 0,
                last_attempt DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            $pdo->exec("CREATE TABLE IF NOT EXISTS foobot_devices_meta (
                device_uuid TEXT PRIMARY KEY,
                device_name TEXT NOT NULL DEFAULT '',
                location TEXT NOT NULL DEFAULT '',
                color_badge TEXT NOT NULL DEFAULT 'blue',
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            $pdo->exec("CREATE TABLE IF NOT EXISTS foobot_authorized_devices (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                device_name TEXT NOT NULL,
                device_serial TEXT NOT NULL DEFAULT '',
                device_token_hash TEXT NOT NULL UNIQUE,
                platform TEXT NOT NULL DEFAULT 'unknown',
                app_mode TEXT NOT NULL DEFAULT 'browser',
                user_agent TEXT NOT NULL DEFAULT '',
                device_fingerprint TEXT NOT NULL DEFAULT '',
                last_ip TEXT NOT NULL DEFAULT '',
                last_active_at INTEGER NOT NULL DEFAULT 0,
                is_revoked INTEGER NOT NULL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            try { $pdo->exec("ALTER TABLE foobot_authorized_devices ADD COLUMN device_serial TEXT NOT NULL DEFAULT ''"); } catch (Exception $e_col) {}
            try { $pdo->exec("ALTER TABLE foobot_authorized_devices ADD COLUMN app_mode TEXT NOT NULL DEFAULT 'browser'"); } catch (Exception $e_col) {}
            try { $pdo->exec("ALTER TABLE foobot_authorized_devices ADD COLUMN user_agent TEXT NOT NULL DEFAULT ''"); } catch (Exception $e_col) {}

            $pdo->exec("CREATE TABLE IF NOT EXISTS foobot_pairing_tickets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                ticket_code TEXT NOT NULL UNIQUE,
                token_secret TEXT NOT NULL,
                device_label TEXT NOT NULL DEFAULT '',
                expires_at INTEGER NOT NULL,
                used_at INTEGER DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
        }
    } catch (Exception $e) {
        // Ignora errori silenziosamente
    }
}

/**
 * Verifica se un IP è bloccato per troppi tentativi falliti (max 10 tentativi)
 * Restituisce i secondi rimanenti di blocco (0 se non bloccato)
 */
function check_brute_force_lock(PDO $pdo, $ip) {
    ensure_security_tables($pdo);
    try {
        $stmt = $pdo->prepare("SELECT attempts, locked_until FROM foobot_login_attempts WHERE ip_address = ? LIMIT 1");
        $stmt->execute([$ip]);
        $row = $stmt->fetch();
        if ($row && (int)$row['locked_until'] > time()) {
            return (int)$row['locked_until'] - time();
        }
    } catch (Exception $e) {}
    return 0;
}

/**
 * Registra un tentativo di login fallito; al 10° tentativo blocca l'IP per 15 minuti (900s)
 */
function record_failed_login(PDO $pdo, $ip, $max_attempts = 10, $lockout_seconds = 900) {
    ensure_security_tables($pdo);
    try {
        $stmt = $pdo->prepare("SELECT attempts, locked_until FROM foobot_login_attempts WHERE ip_address = ? LIMIT 1");
        $stmt->execute([$ip]);
        $row = $stmt->fetch();

        $now = time();
        if ($row) {
            // Se un blocco precedente era scaduto, riparte da 1
            $attempts = ((int)$row['locked_until'] > 0 && (int)$row['locked_until'] <= $now) ? 1 : ((int)$row['attempts'] + 1);
        } else {
            $attempts = 1;
        }

        $locked_until = ($attempts >= $max_attempts) ? ($now + $lockout_seconds) : 0;

        $up = $pdo->prepare("INSERT INTO foobot_login_attempts (ip_address, attempts, locked_until) VALUES (?, ?, ?)
                             ON DUPLICATE KEY UPDATE attempts = VALUES(attempts), locked_until = VALUES(locked_until)");
        $up->execute([$ip, $attempts, $locked_until]);

        return max(0, $max_attempts - $attempts);
    } catch (Exception $e) {
        return $max_attempts;
    }
}

/**
 * Azzera i tentativi falliti dopo un login riuscito
 */
function clear_login_attempts(PDO $pdo, $ip) {
    ensure_security_tables($pdo);
    try {
        $stmt = $pdo->prepare("DELETE FROM foobot_login_attempts WHERE ip_address = ?");
        $stmt->execute([$ip]);
    } catch (Exception $e) {}
}

/**
 * Verifica se un IP è presente nella lista whitelist (separata da virgole o a capo)
 */
function is_ip_in_whitelist($ip, $whitelist_raw) {
    $whitelist_raw = trim((string)$whitelist_raw);
    if ($whitelist_raw === '') {
        return true; // Se la whitelist è vuota, non filtra per IP specifico
    }
    $items = preg_split('/[\s,;]+/', $whitelist_raw, -1, PREG_SPLIT_NO_EMPTY);
    if (empty($items)) {
        return true;
    }
    return in_array($ip, $items, true);
}

/**
 * Verifica se il Paese dell'IP è consentito dalla restrizione geografica (es. IT)
 */
function is_country_allowed($ip, $allowed_countries_raw) {
    $allowed_countries_raw = strtoupper(trim((string)$allowed_countries_raw));
    if ($allowed_countries_raw === '' || $allowed_countries_raw === 'ALL' || $allowed_countries_raw === '*') {
        return true;
    }
    $allowed_list = preg_split('/[\s,;]+/', $allowed_countries_raw, -1, PREG_SPLIT_NO_EMPTY);
    if (empty($allowed_list)) {
        return true;
    }
    $client_country = get_client_country($ip);
    if ($client_country === '') {
        // Se il servizio di geolocalizzazione esterno non risponde, non blocca l'utente legittimo
        return true;
    }
    return in_array($client_country, $allowed_list, true);
}

/**
 * Verifica se la connessione al database MySQL è funzionante.
 * Se config.php non esiste o la connessione fallisce, esegue redirect immediato a install.php.
 */
function require_valid_database() {
    $config_file = __DIR__ . '/config.php';
    if (!file_exists($config_file)) {
        header('Location: install.php?step=1');
        exit;
    }

    require_once $config_file;

    if (!function_exists('get_db_connection')) {
        header('Location: install.php?step=1');
        exit;
    }

    $pdo = get_db_connection();
    if (!$pdo instanceof PDO) {
        header('Location: install.php?step=1&error=db_unreachable');
        exit;
    }

    try {
        $count = $pdo->query("SELECT COUNT(*) FROM foobot_users")->fetchColumn();
        if ($count == 0) {
            header('Location: install.php?step=2');
            exit;
        }
    } catch (Exception $e) {
        header('Location: install.php?step=1');
        exit;
    }

    ensure_security_tables($pdo);
    return $pdo;
}

/**
 * Richiede login utente per visualizzare o modificare le pagine protette (es. settings.php)
 */
function require_login() {
    $pdo = require_valid_database();

    // Se la sessione non è attiva, tenta il ripristino automatico tramite cookie persistente di 1 anno
    if (empty($_SESSION['foobot_user'])) {
        try_restore_login_from_cookie($pdo);
    }

    if (empty($_SESSION['foobot_user'])) {
        header('Location: login.php');
        exit;
    }
    // Verifica in tempo reale sul database che l'utente esista ancora e sincronizza il ruolo
    try {
        $stmt = $pdo->prepare("SELECT role, language FROM foobot_users WHERE username = ? LIMIT 1");
        $stmt->execute([$_SESSION['foobot_user']]);
        $u_row = $stmt->fetch();
        if (!$u_row) {
            clear_remember_cookie($pdo);
            $_SESSION = [];
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }
            header('Location: login.php');
            exit;
        }
        $_SESSION['foobot_role'] = !empty($u_row['role']) ? $u_row['role'] : 'admin';
        if (!empty($u_row['language'])) {
            $user_db_lang = strtolower(trim($u_row['language']));
            $avail = function_exists('get_available_languages') ? get_available_languages() : ['it', 'en', 'fr', 'de', 'es'];
            if (in_array($user_db_lang, $avail, true)) {
                // Se non c'è un cambio esplicito ?lang= nella richiesta corrente, sincronizza permanentemente la lingua del profilo
                if (!isset($_GET['lang'])) {
                    $_SESSION['foobot_lang'] = $user_db_lang;
                    if (empty($_COOKIE['foobot_lang']) || $_COOKIE['foobot_lang'] !== $user_db_lang) {
                        set_app_lang_cookie($user_db_lang);
                    }
                }
            }
        }
    } catch (Exception $e) {}

    return $pdo;
}

/**
 * Tenta di ripristinare la sessione utente dal cookie persistente 'foobot_remember'
 */
function try_restore_login_from_cookie(PDO $pdo) {
    if (empty($_COOKIE['foobot_remember'])) {
        return false;
    }
    $cookie_val = $_COOKIE['foobot_remember'];
    $parts = explode(':', $cookie_val, 2);
    if (count($parts) !== 2 || empty($parts[0]) || empty($parts[1])) {
        return false;
    }
    $uid = (int)$parts[0];
    $token = $parts[1];
    $token_hash = hash('sha256', $token);

    try {
        ensure_security_tables($pdo);
        $stmt = $pdo->prepare("SELECT t.id, t.expires_at, u.username, u.role, u.language 
                               FROM foobot_user_tokens t
                               JOIN foobot_users u ON t.user_id = u.id
                               WHERE t.user_id = ? AND t.token_hash = ? AND t.expires_at > ? LIMIT 1");
        $stmt->execute([$uid, $token_hash, time()]);
        $row = $stmt->fetch();

        if ($row) {
            session_regenerate_id(true);
            $_SESSION['foobot_user'] = $row['username'];
            $_SESSION['foobot_role'] = !empty($row['role']) ? $row['role'] : 'admin';
            if (!empty($row['language'])) {
                $u_lang = strtolower(trim($row['language']));
                $_SESSION['foobot_lang'] = $u_lang;
                set_app_lang_cookie($u_lang);
            }
            return true;
        } else {
            // Token non valido o scaduto: rimuovi cookie
            clear_remember_cookie($pdo);
        }
    } catch (Exception $e) {}

    return false;
}

/**
 * Crea e registra un cookie di accesso persistente valido per 1 anno (365 giorni)
 */
function set_remember_cookie(PDO $pdo, $user_id) {
    ensure_security_tables($pdo);
    $user_id = (int)$user_id;
    if ($user_id <= 0) return;

    $token = bin2hex(random_bytes(32));
    $token_hash = hash('sha256', $token);
    $one_year = 365 * 86400;
    $expires_at = time() + $one_year;

    try {
        // Rimuove eventuali vecchi token scaduti per pulizia tabella
        $pdo->exec("DELETE FROM foobot_user_tokens WHERE expires_at < " . time());

        $ins = $pdo->prepare("INSERT INTO foobot_user_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)");
        $ins->execute([$user_id, $token_hash, $expires_at]);

        $cookie_val = $user_id . ':' . $token;
        $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                 || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
                 || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
                 || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on');

        setcookie('foobot_remember', $cookie_val, [
            'expires'  => $expires_at,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $is_https,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    } catch (Exception $e) {}
}

/**
 * Rimuove il cookie e il token persistente dal database
 */
function clear_remember_cookie(PDO $pdo = null) {
    if (!empty($_COOKIE['foobot_remember'])) {
        $parts = explode(':', $_COOKIE['foobot_remember'], 2);
        if (count($parts) === 2 && $pdo instanceof PDO) {
            $token_hash = hash('sha256', $parts[1]);
            try {
                $del = $pdo->prepare("DELETE FROM foobot_user_tokens WHERE user_id = ? AND token_hash = ?");
                $del->execute([(int)$parts[0], $token_hash]);
            } catch (Exception $e) {}
        }
        $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
        setcookie('foobot_remember', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $is_https,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        unset($_COOKIE['foobot_remember']);
    }
}

/**
 * Verifica se c'è un utente autenticato in sessione (admin o user)
 */
function is_logged_in() {
    if (!empty($_SESSION['foobot_user'])) {
        return true;
    }
    if (function_exists('get_db_connection')) {
        try {
            $pdo = get_db_connection();
            if ($pdo instanceof PDO && try_restore_login_from_cookie($pdo)) {
                return true;
            }
        } catch (Exception $e) {}
    }
    return !empty($_SESSION['foobot_user']);
}

/**
 * Restituisce il nome dell'utente loggato oppure null
 */
function get_current_user_name() {
    return $_SESSION['foobot_user'] ?? null;
}

/**
 * Restituisce il ruolo dell'utente loggato ('admin' o 'user')
 */
function get_current_user_role() {
    return $_SESSION['foobot_role'] ?? 'user';
}

/**
 * Verifica se l'utente attualmente autenticato è amministratore
 */
function is_admin_user() {
    if (empty($_SESSION['foobot_user'])) {
        return false;
    }
    if (empty($_SESSION['foobot_role']) && function_exists('get_db_connection')) {
        try {
            $pdo = get_db_connection();
            if ($pdo instanceof PDO) {
                $stmt = $pdo->prepare("SELECT role FROM foobot_users WHERE username = ? LIMIT 1");
                $stmt->execute([$_SESSION['foobot_user']]);
                $role = $stmt->fetchColumn();
                if ($role) {
                    $_SESSION['foobot_role'] = $role;
                }
            }
        } catch (Exception $e) {}
    }
    return ($_SESSION['foobot_role'] ?? 'user') === 'admin';
}

/**
 * Blocca l'accesso alle sole funzioni amministrative (es. settings.php)
 */
function require_admin_role() {
    $pdo = require_login();
    if (!is_admin_user()) {
        header('Location: index.php');
        exit;
    }
    return $pdo;
}

/**
 * Restituisce l'ID numerico dell'utente attualmente loggato in sessione
 */
function get_current_user_id() {
    if (empty($_SESSION['foobot_user']) || !function_exists('get_db_connection')) {
        return 0;
    }
    try {
        $pdo = get_db_connection();
        if ($pdo instanceof PDO) {
            $stmt = $pdo->prepare("SELECT id FROM foobot_users WHERE username = ? LIMIT 1");
            $stmt->execute([$_SESSION['foobot_user']]);
            $uid = $stmt->fetchColumn();
            return $uid ? (int)$uid : 0;
        }
    } catch (Exception $e) {}
    return 0;
}

/**
 * Restituisce l'unità di temperatura attiva per l'utente corrente ('C' o 'F')
 * Priorità: 1) Preferenza profilo utente autenticato; 2) Impostazione globale sito; 3) 'C'
 */
function get_temperature_unit(PDO $pdo, $user_id = null) {
    ensure_security_tables($pdo);
    if ($user_id === null && function_exists('get_current_user_id')) {
        $user_id = get_current_user_id();
    }
    if ($user_id > 0) {
        try {
            $stmt = $pdo->prepare("SELECT temp_unit FROM foobot_users WHERE id = ? LIMIT 1");
            $stmt->execute([(int)$user_id]);
            $unit = strtoupper(trim((string)$stmt->fetchColumn()));
            if ($unit === 'F' || $unit === 'C') {
                return $unit;
            }
        } catch (Exception $e) {}
    }
    $site_unit = strtoupper(trim((string)get_setting($pdo, 'temperature_unit', 'C')));
    return ($site_unit === 'F') ? 'F' : 'C';
}

/**
 * Converte un valore in gradi Celsius nell'unità desiderata ('C' o 'F')
 */
function celsius_to_user_temp($celsius, $unit = 'C') {
    $celsius = (float)$celsius;
    if (strtoupper($unit) === 'F') {
        return round(($celsius * 9 / 5) + 32, 1);
    }
    return round($celsius, 1);
}

/**
 * Restituisce l'elenco di tutti i sensori Foobot fisici rilevati nel database (Multi-Foobot)
 * con eventuale nome amichevole configurato in foobot_devices_meta
 */
function get_available_foobot_devices(PDO $pdo) {
    ensure_security_tables($pdo);
    $devices = [];
    try {
        $stmt = $pdo->query("SELECT r.device_uuid, 
                                    MAX(r.timestamp) AS last_ts, 
                                    COUNT(*) AS total_readings,
                                    COALESCE(m.device_name, '') AS custom_name,
                                    COALESCE(m.location, '') AS location,
                                    COALESCE(m.color_badge, 'blue') AS color_badge
                             FROM foobot_readings r
                             LEFT JOIN foobot_devices_meta m ON r.device_uuid = m.device_uuid
                             GROUP BY r.device_uuid
                             ORDER BY last_ts DESC");
        $raw_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total_devs = count($raw_rows);
        $now = time();
        foreach ($raw_rows as $idx => $row) {
            $uuid = $row['device_uuid'];
            $last_ts = (int)$row['last_ts'];
            $is_online = ($now - $last_ts) <= 900; // 15 minuti di tolleranza
            $name = trim($row['custom_name']);
            if ($name === '') {
                $name = ($total_devs <= 1) ? 'Foobot' : ('Foobot ' . ($idx + 1));
            }
            $devices[] = [
                'device_uuid'    => $uuid,
                'device_name'    => $name,
                'location'       => $row['location'],
                'color_badge'    => $row['color_badge'],
                'last_seen'      => $last_ts,
                'online'         => $is_online,
                'total_readings' => (int)$row['total_readings']
            ];
        }
    } catch (Exception $e) {}

    // Fallback: se la tabella letture è ancora vuota, restituisce almeno un dispositivo default
    if (empty($devices)) {
        $devices[] = [
            'device_uuid'    => 'foobot',
            'device_name'    => 'Foobot',
            'location'       => 'Indoor',
            'color_badge'    => 'blue',
            'last_seen'      => 0,
            'online'         => false,
            'total_readings' => 0
        ];
    }
    return $devices;
}

/**
 * Determina l'UUID del sensore Foobot attualmente attivo per la visualizzazione
 */
function get_active_foobot_uuid(PDO $pdo) {
    $devices = get_available_foobot_devices($pdo);
    $valid_uuids = array_column($devices, 'device_uuid');

    // 1. Parametro GET esplicito
    if (!empty($_GET['device'])) {
        $req = substr(preg_replace('/[^A-Za-z0-9_-]/', '', (string)$_GET['device']), 0, 32);
        if (in_array($req, $valid_uuids, true)) {
            $_SESSION['active_foobot_uuid'] = $req;
            return $req;
        }
    }

    // 2. Valore già memorizzato in sessione
    if (!empty($_SESSION['active_foobot_uuid']) && in_array($_SESSION['active_foobot_uuid'], $valid_uuids, true)) {
        return $_SESSION['active_foobot_uuid'];
    }

    // 3. Default: primo dispositivo rilevato
    $default_uuid = $devices[0]['device_uuid'] ?? 'foobot';
    $_SESSION['active_foobot_uuid'] = $default_uuid;
    return $default_uuid;
}

/**
 * Assegna un nome personalizzato (e location) a un sensore fisico Foobot
 */
function set_foobot_device_name(PDO $pdo, $uuid, $name, $location = '', $color_badge = 'blue') {
    ensure_security_tables($pdo);
    $uuid = substr(preg_replace('/[^A-Za-z0-9_-]/', '', (string)$uuid), 0, 32);
    $name = trim(strip_tags((string)$name));
    $location = trim(strip_tags((string)$location));
    $color_badge = in_array($color_badge, ['blue', 'emerald', 'amber', 'purple', 'rose'], true) ? $color_badge : 'blue';

    if ($uuid === '') {
        return false;
    }
    $stmt = $pdo->prepare("INSERT INTO foobot_devices_meta (device_uuid, device_name, location, color_badge) 
                           VALUES (?, ?, ?, ?)
                           ON DUPLICATE KEY UPDATE device_name = VALUES(device_name), 
                                                   location = VALUES(location), 
                                                   color_badge = VALUES(color_badge)");
    return $stmt->execute([$uuid, $name, $location, $color_badge]);
}

/**
 * Crea un ticket monouso di accoppiamento (pairing) per il QR Code mobile
 * Scadenza: 10 minuti (600 secondi)
 */
function create_device_pairing_ticket(PDO $pdo, $user_id, $device_label = 'Mio Smartphone') {
    ensure_security_tables($pdo);
    $user_id = (int)$user_id;
    if ($user_id <= 0) return null;

    // Codice ticket a 8 caratteri alfanumerici facili da leggere e scansionare
    $ticket_code = 'NF-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    // Token segreto definitivo a 256-bit generato in anticipo
    $token_secret = bin2hex(random_bytes(32));
    $expires_at = time() + 600; // 10 minuti

    // Rimuove eventuali vecchi ticket scaduti (con tolleranza di 5 minuti)
    $pdo->exec("DELETE FROM foobot_pairing_tickets WHERE expires_at < " . (time() - 300) . " OR used_at IS NOT NULL");

    $stmt = $pdo->prepare("INSERT INTO foobot_pairing_tickets (user_id, ticket_code, token_secret, device_label, expires_at) 
                           VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $ticket_code, $token_secret, $device_label, $expires_at]);

    return [
        'ticket_code'  => $ticket_code,
        'token_secret' => $token_secret,
        'device_label' => $device_label,
        'expires_at'   => $expires_at,
        'seconds_left' => 600
    ];
}

/**
 * Genera un Seriale Dispositivo univoco e permanente (formato NF-DEV-XXXX-XXXX)
 */
function generate_device_serial() {
    return 'NF-DEV-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4)) . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
}

/**
 * Analizza la stringa User-Agent e la modalità app per restituire metadati dettagliati su OS, browser e ambiente
 */
function parse_device_info($ua, $app_mode = 'browser', $fallback = 'Web App') {
    $ua = (string)$ua;
    $os = 'Sconosciuto';
    $browser = 'Browser Web';

    if (preg_match('/Android\s*([0-9\.]+)?(?:;\s*([^;\)]+))?/i', $ua, $m)) {
        $v = !empty($m[1]) ? (' ' . $m[1]) : '';
        $model = !empty($m[2]) ? trim($m[2]) : '';
        $model = preg_replace('/Build\/[A-Za-z0-9\._\-]+/i', '', $model);
        $model = trim($model, " ;,\t\n\r");
        $os = 'Android' . $v . ($model !== '' ? (' (' . $model . ')') : '');
    } elseif (preg_match('/iPhone.*?OS\s*([0-9_]+)/i', $ua, $m)) {
        $os = 'iOS ' . str_replace('_', '.', $m[1]) . ' (iPhone)';
    } elseif (preg_match('/iPad.*?OS\s*([0-9_]+)/i', $ua, $m)) {
        $os = 'iPadOS ' . str_replace('_', '.', $m[1]) . ' (iPad)';
    } elseif (preg_match('/Windows NT 10\.0/i', $ua)) {
        $os = 'Windows 10/11';
    } elseif (preg_match('/Windows NT ([0-9\.]+)/i', $ua, $m)) {
        $os = 'Windows (NT ' . $m[1] . ')';
    } elseif (preg_match('/Macintosh.*?Mac OS X\s*([0-9_]+)/i', $ua, $m)) {
        $os = 'macOS ' . str_replace('_', '.', $m[1]);
    } elseif (preg_match('/Linux/i', $ua)) {
        $os = 'Linux';
    }

    if (preg_match('/Edg(?:e|A|iOS)?\/([0-9\.]+)/i', $ua, $m)) {
        $browser = 'Microsoft Edge ' . explode('.', $m[1])[0];
    } elseif (preg_match('/OPR\/([0-9\.]+)/i', $ua, $m)) {
        $browser = 'Opera ' . explode('.', $m[1])[0];
    } elseif (preg_match('/Chrome\/([0-9\.]+)/i', $ua, $m)) {
        $browser = 'Chrome ' . explode('.', $m[1])[0];
    } elseif (preg_match('/Firefox\/([0-9\.]+)/i', $ua, $m)) {
        $browser = 'Firefox ' . explode('.', $m[1])[0];
    } elseif (preg_match('/Version\/([0-9\.]+).*Safari/i', $ua, $m)) {
        $browser = 'Safari ' . explode('.', $m[1])[0];
    }

    $is_standalone = in_array(strtolower((string)$app_mode), ['standalone', 'pwa', 'installed'], true);
    $mode_label = $is_standalone ? 'App Installata (PWA)' : 'Scheda Browser';

    return [
        'os'         => $os,
        'browser'    => $browser,
        'app_mode'   => $is_standalone ? 'standalone' : 'browser',
        'mode_label' => $mode_label,
        'summary'    => ($os !== 'Sconosciuto') ? ($os . ' &bull; ' . $browser) : ($fallback ?: 'Web Client')
    ];
}

/**
 * Riscatta il ticket di accoppiamento inviato dall'App mobile al primo avvio
 * e registra il dispositivo tra quelli autorizzati
 */
function claim_pairing_ticket(PDO $pdo, $ticket_code, $device_fingerprint = '', $platform = 'unknown', $ip = '0.0.0.0', $device_serial = '', $app_mode = 'browser', $user_agent = '') {
    ensure_security_tables($pdo);
    $ticket_code = strtoupper(trim((string)$ticket_code));
    if ($ticket_code === '') {
        return ['success' => false, 'error' => 'Codice ticket non valido'];
    }

    $alt_ticket_code = (strpos($ticket_code, 'NF-') === 0) ? substr($ticket_code, 3) : ('NF-' . $ticket_code);

    $stmt = $pdo->prepare("SELECT id, user_id, token_secret, device_label, expires_at, used_at 
                           FROM foobot_pairing_tickets 
                           WHERE UPPER(ticket_code) IN (?, ?) LIMIT 1");
    $stmt->execute([$ticket_code, $alt_ticket_code]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        return ['success' => false, 'error' => 'Ticket di accoppiamento non trovato o non più valido'];
    }
    if (!empty($ticket['used_at'])) {
        return ['success' => false, 'error' => 'Questo ticket è già stato utilizzato (monouso)'];
    }
    // Tolleranza di 60 secondi sulla scadenza per compensare eventuali leggeri disallineamenti di orologio
    if ((int)$ticket['expires_at'] < (time() - 60)) {
        return ['success' => false, 'error' => 'Ticket scaduto (validità 10 minuti superata). Genera un nuovo QR Code dal sito.'];
    }

    // Segna il ticket come bruciato (monouso)
    $now = time();
    $upd = $pdo->prepare("UPDATE foobot_pairing_tickets SET used_at = ? WHERE id = ?");
    $upd->execute([$now, $ticket['id']]);

    // Registra il dispositivo in foobot_authorized_devices
    $token_secret = $ticket['token_secret'];
    $token_hash = hash('sha256', $token_secret);
    $device_name = !empty($ticket['device_label']) ? $ticket['device_label'] : 'Dispositivo Mobile';

    // Valida o genera il seriale univoco del dispositivo
    $device_serial = trim((string)$device_serial);
    if ($device_serial === '' || !preg_match('/^[A-Za-z0-9_-]{6,64}$/', $device_serial)) {
        $device_serial = generate_device_serial();
    }
    $app_mode = in_array(strtolower((string)$app_mode), ['standalone', 'pwa', 'installed'], true) ? 'standalone' : 'browser';
    $user_agent = substr(trim((string)$user_agent), 0, 255);

    $ins = $pdo->prepare("INSERT INTO foobot_authorized_devices 
                          (user_id, device_name, device_serial, device_token_hash, platform, app_mode, user_agent, device_fingerprint, last_ip, last_active_at, is_revoked) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)");
    $ins->execute([
        (int)$ticket['user_id'],
        $device_name,
        $device_serial,
        $token_hash,
        substr((string)$platform, 0, 32),
        $app_mode,
        $user_agent,
        substr((string)$device_fingerprint, 0, 128),
        $ip,
        $now
    ]);

    $device_id = (int)$pdo->lastInsertId();

    return [
        'success'       => true,
        'user_id'       => (int)$ticket['user_id'],
        'device_id'     => $device_id,
        'device_reg_id' => $device_id,
        'device_name'   => $device_name,
        'device_serial' => $device_serial,
        'device_token'  => $token_secret
    ];
}

/**
 * Verifica l'autenticità di un token dispositivo mobile inviato via HTTP
 * Restituisce i dettagli del dispositivo autorizzato oppure false
 */
function verify_device_token(PDO $pdo, $raw_token, $app_mode = null, $user_agent = null) {
    if (empty($raw_token) || strlen($raw_token) < 20) {
        return false;
    }
    ensure_security_tables($pdo);
    $hash = hash('sha256', (string)$raw_token);
    try {
        $stmt = $pdo->prepare("SELECT d.id, d.user_id, d.device_name, d.device_serial, d.platform, d.app_mode, d.user_agent, d.is_revoked, u.username, u.role
                               FROM foobot_authorized_devices d
                               LEFT JOIN foobot_users u ON d.user_id = u.id
                               WHERE d.device_token_hash = ? AND d.is_revoked = 0 LIMIT 1");
        $stmt->execute([$hash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            if (empty($row['username'])) {
                $stmt_u = $pdo->query("SELECT username, role FROM foobot_users ORDER BY (role = 'admin') DESC, id ASC LIMIT 1");
                $u_fallback = $stmt_u ? $stmt_u->fetch(PDO::FETCH_ASSOC) : null;
                if ($u_fallback) {
                    $row['username'] = $u_fallback['username'];
                    $row['role']     = $u_fallback['role'] ?? 'admin';
                }
            }
            // Aggiorna l'orario di ultima attività, IP e opzionalmente app_mode / user_agent
            $now = time();
            $ip = get_client_ip();
            if ($app_mode !== null && $user_agent !== null) {
                $clean_mode = in_array(strtolower((string)$app_mode), ['standalone', 'pwa', 'installed'], true) ? 'standalone' : 'browser';
                $clean_ua = substr(trim((string)$user_agent), 0, 255);
                $upd = $pdo->prepare("UPDATE foobot_authorized_devices SET last_active_at = ?, last_ip = ?, app_mode = ?, user_agent = ? WHERE id = ?");
                $upd->execute([$now, $ip, $clean_mode, $clean_ua, $row['id']]);
                $row['app_mode'] = $clean_mode;
                $row['user_agent'] = $clean_ua;
            } elseif ($app_mode !== null) {
                $clean_mode = in_array(strtolower((string)$app_mode), ['standalone', 'pwa', 'installed'], true) ? 'standalone' : 'browser';
                $upd = $pdo->prepare("UPDATE foobot_authorized_devices SET last_active_at = ?, last_ip = ?, app_mode = ? WHERE id = ?");
                $upd->execute([$now, $ip, $clean_mode, $row['id']]);
                $row['app_mode'] = $clean_mode;
            } else {
                $upd = $pdo->prepare("UPDATE foobot_authorized_devices SET last_active_at = ?, last_ip = ? WHERE id = ?");
                $upd->execute([$now, $ip, $row['id']]);
            }
            return $row;
        }
    } catch (Exception $e) {}
    return false;
}

/**
 * Restituisce l'elenco dei dispositivi mobili autorizzati
 * Se $user_id è specificato, filtra per quell'utente. Se null e admin, restituisce tutti.
 */
function get_user_authorized_devices(PDO $pdo, $user_id = null) {
    ensure_security_tables($pdo);
    try {
        if ($user_id !== null) {
            $stmt = $pdo->prepare("SELECT d.id, d.user_id, d.device_name, d.device_serial, d.platform, d.app_mode, d.user_agent, d.last_ip, 
                                          d.last_active_at, d.is_revoked, d.created_at, u.username, u.role
                                   FROM foobot_authorized_devices d
                                   JOIN foobot_users u ON d.user_id = u.id
                                   WHERE d.user_id = ?
                                   ORDER BY d.created_at DESC");
            $stmt->execute([(int)$user_id]);
        } else {
            $stmt = $pdo->query("SELECT d.id, d.user_id, d.device_name, d.device_serial, d.platform, d.app_mode, d.user_agent, d.last_ip, 
                                        d.last_active_at, d.is_revoked, d.created_at, u.username, u.role
                                 FROM foobot_authorized_devices d
                                 JOIN foobot_users u ON d.user_id = u.id
                                 ORDER BY d.created_at DESC");
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Modifica il nome personalizzato di un dispositivo autorizzato
 */
function update_authorized_device_name(PDO $pdo, $device_id, $new_name, $current_user_id, $is_admin = false) {
    ensure_security_tables($pdo);
    $device_id = (int)$device_id;
    $new_name = trim(strip_tags((string)$new_name));
    if ($device_id <= 0 || $new_name === '') {
        return false;
    }
    if ($is_admin) {
        $stmt = $pdo->prepare("UPDATE foobot_authorized_devices SET device_name = ? WHERE id = ?");
        return $stmt->execute([$new_name, $device_id]);
    } else {
        $stmt = $pdo->prepare("UPDATE foobot_authorized_devices SET device_name = ? WHERE id = ? AND user_id = ?");
        return $stmt->execute([$new_name, $device_id, (int)$current_user_id]);
    }
}

/**
 * Revoca o elimina un dispositivo mobile autorizzato
 * L'utente standard può revocare solo i propri; l'amministratore può revocare qualsiasi device
 */
function revoke_authorized_device(PDO $pdo, $device_id, $current_user_id, $is_admin = false) {
    ensure_security_tables($pdo);
    $device_id = (int)$device_id;
    if ($device_id <= 0) return false;

    if ($is_admin) {
        $stmt = $pdo->prepare("DELETE FROM foobot_authorized_devices WHERE id = ?");
        return $stmt->execute([$device_id]);
    } else {
        $stmt = $pdo->prepare("DELETE FROM foobot_authorized_devices WHERE id = ? AND user_id = ?");
        return $stmt->execute([$device_id, (int)$current_user_id]);
    }
}

/**
 * Revoca tutti i dispositivi di un utente specifico (o tutti i propri)
 */
function revoke_all_authorized_devices(PDO $pdo, $target_user_id, $current_user_id, $is_admin = false) {
    ensure_security_tables($pdo);
    $target_user_id = (int)$target_user_id;
    if ($target_user_id <= 0) return 0;

    // Solo l'amministratore può revocare i dispositivi di altri utenti
    if (!$is_admin && $target_user_id !== (int)$current_user_id) {
        return 0;
    }

    $stmt = $pdo->prepare("DELETE FROM foobot_authorized_devices WHERE user_id = ?");
    $stmt->execute([$target_user_id]);
    return $stmt->rowCount();
}

/**
 * Revoca tutti i dispositivi di tutti gli altri utenti (escluso l'utente corrente)
 * Riservato agli amministratori
 */
function revoke_all_other_authorized_devices(PDO $pdo, $current_user_id, $is_admin = false) {
    ensure_security_tables($pdo);
    if (!$is_admin) return 0;

    $stmt = $pdo->prepare("DELETE FROM foobot_authorized_devices WHERE user_id != ?");
    $stmt->execute([(int)$current_user_id]);
    return $stmt->rowCount();
}

