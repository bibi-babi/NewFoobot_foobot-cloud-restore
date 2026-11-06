<?php
/**
 * Funzioni comuni per le API NewFoobot
 */

header('Content-Type: application/json; charset=UTF-8');

// Gestione completa CORS per Web App PWA e chiamate API cross-origin
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (!empty($origin)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Device-Token, X-Api-Key');
} else {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Device-Token, X-Api-Key');
}

// Risposta immediata per preflight OPTIONS
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$config_file = __DIR__ . '/../config.php';
if (!file_exists($config_file)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Configurazione mancante. Eseguire install.php.']);
    exit;
}
require_once $config_file;

/**
 * Restituisce la connessione PDO o termina con errore JSON
 */
function api_db() {
    $pdo = get_db_connection();
    if (!$pdo instanceof PDO) {
        http_response_code(503);
        echo json_encode(['status' => 'error', 'message' => 'Database non raggiungibile.']);
        exit;
    }
    return $pdo;
}

/**
 * Determina l'UUID del dispositivo: parametro esplicito, oppure sessione attiva, oppure ultimo registrato
 */
function api_resolve_uuid(PDO $pdo, $requested = '') {
    $requested = substr(preg_replace('/[^A-Za-z0-9_-]/', '', (string)$requested), 0, 32);
    if ($requested !== '') {
        return $requested;
    }
    if (!empty($_SESSION['active_foobot_uuid'])) {
        $sess_uuid = substr(preg_replace('/[^A-Za-z0-9_-]/', '', (string)$_SESSION['active_foobot_uuid']), 0, 32);
        if ($sess_uuid !== '') {
            return $sess_uuid;
        }
    }
    $row = $pdo->query('SELECT device_uuid FROM foobot_readings ORDER BY timestamp DESC LIMIT 1')->fetch();
    return $row ? $row['device_uuid'] : 'foobot';
}

function api_resolve_registered_device(PDO $pdo, $dev_auth = null, $preferred_serial = null) {
    if (is_array($dev_auth) && !empty($dev_auth['id'])) {
        return [
            'id'            => (int)$dev_auth['id'],
            'device_serial' => $dev_auth['device_serial'] ?? '',
            'device_name'   => $dev_auth['device_name'] ?? '',
            'username'      => $dev_auth['username'] ?? null
        ];
    }

    $serial = trim((string)($preferred_serial ?? ''));
    if ($serial !== '') {
        try {
            $stmt_s = $pdo->prepare("SELECT d.id, d.device_serial, d.device_name, u.username
                                     FROM foobot_authorized_devices d
                                     LEFT JOIN foobot_users u ON d.user_id = u.id
                                     WHERE d.device_serial = ? AND d.is_revoked = 0
                                     ORDER BY d.last_active_at DESC, d.id DESC LIMIT 1");
            $stmt_s->execute([$serial]);
            $row_s = $stmt_s->fetch(PDO::FETCH_ASSOC);
            if ($row_s) {
                return [
                    'id'            => (int)$row_s['id'],
                    'device_serial' => $row_s['device_serial'] ?? '',
                    'device_name'   => $row_s['device_name'] ?? '',
                    'username'      => $row_s['username'] ?? null
                ];
            }
        } catch (Exception $e) {}
    }

    // Se non corrisponde al seriale del client, verifica se l'utente web autenticato ha un proprio dispositivo registrato
    if (function_exists('get_current_user_id')) {
        $curr_uid = (int)get_current_user_id();
        if ($curr_uid > 0) {
            try {
                $stmt_u = $pdo->prepare("SELECT d.id, d.device_serial, d.device_name, u.username
                                         FROM foobot_authorized_devices d
                                         LEFT JOIN foobot_users u ON d.user_id = u.id
                                         WHERE d.user_id = ? AND d.is_revoked = 0
                                         ORDER BY d.last_active_at DESC, d.id DESC LIMIT 1");
                $stmt_u->execute([$curr_uid]);
                $row_u = $stmt_u->fetch(PDO::FETCH_ASSOC);
                if ($row_u) {
                    return [
                        'id'            => (int)$row_u['id'],
                        'device_serial' => $row_u['device_serial'] ?? '',
                        'device_name'   => $row_u['device_name'] ?? '',
                        'username'      => $row_u['username'] ?? null
                    ];
                }
            } catch (Exception $e) {}
        }
    }

    return ['id' => null, 'device_serial' => null, 'device_name' => null, 'username' => null];
}

/**
 * Risolve il nome utente che ha registrato il dispositivo (da token mobile, sessione o utente titolare nel DB)
 */
function api_resolve_registered_username(PDO $pdo, $preferred_username = null) {
    $name = trim((string)($preferred_username ?? ''));
    if ($name !== '' && $name !== 'admin_key') {
        return $name;
    }
    if (function_exists('get_current_user_name')) {
        $sess_name = trim((string)get_current_user_name());
        if ($sess_name !== '') {
            return $sess_name;
        }
    }
    try {
        // Cerca l'ultimo utente associato in foobot_authorized_devices
        $stmt_d = $pdo->query("SELECT u.username
                               FROM foobot_authorized_devices d
                               JOIN foobot_users u ON d.user_id = u.id
                               WHERE d.is_revoked = 0 AND u.username != ''
                               ORDER BY d.last_active_at DESC, d.id DESC LIMIT 1");
        $dev_user = $stmt_d ? trim((string)$stmt_d->fetchColumn()) : '';
        if ($dev_user !== '') {
            return $dev_user;
        }
    } catch (Exception $e) {}

    try {
        // Fallback all'utente proprietario/amministratore registrato in foobot_users
        $stmt_u = $pdo->query("SELECT username FROM foobot_users WHERE username != '' ORDER BY (role = 'admin') DESC, id ASC LIMIT 1");
        $owner_user = $stmt_u ? trim((string)$stmt_u->fetchColumn()) : '';
        if ($owner_user !== '') {
            return $owner_user;
        }
    } catch (Exception $e) {}

    return null;
}

/**
 * Risolve la lingua predefinita della dashboard o dell'utente (da DB, sessione o impostazione di sistema)
 */
function api_resolve_user_lang(PDO $pdo, $user_id = null) {
    $i18n_file = dirname(__DIR__) . '/i18n.php';
    if (file_exists($i18n_file)) {
        require_once $i18n_file;
    }
    if ($user_id && $pdo instanceof PDO) {
        try {
            $stmt = $pdo->prepare("SELECT language FROM foobot_users WHERE id = ? LIMIT 1");
            $stmt->execute([(int)$user_id]);
            $lang = $stmt->fetchColumn();
            if ($lang) {
                return strtolower(trim($lang));
            }
        } catch (Exception $e) {}
    }
    if (function_exists('get_current_lang')) {
        return get_current_lang();
    }
    if (function_exists('get_system_default_lang')) {
        return get_system_default_lang();
    }
    return 'it';
}

/**
 * Autentica il chiamante delle API client (Dashboard Web o App Mobile PWA)
 * Supporta:
 * 1. Sessione utente web attiva (Admin o Utente standard)
 * 2. API_SECRET_KEY (Amministratore o script interni)
 * 3. Token crittografico del dispositivo mobile autorizzato (X-Device-Token o param device_token)
 * Restituisce array con i permessi oppure termina con HTTP 403
 */
function api_authenticate_client(PDO $pdo) {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    require_once dirname(__DIR__) . '/auth.php';

    // 1. Token crittografico del dispositivo mobile autorizzato (App PWA) - Priorità all'utente del token
    $headers = function_exists('getallheaders') ? array_change_key_case(getallheaders(), CASE_LOWER) : [];
    $dev_token = $headers['x-device-token'] ?? ($_SERVER['HTTP_X_DEVICE_TOKEN'] ?? ($_GET['token'] ?? ($_GET['device_token'] ?? ($_POST['token'] ?? ($_POST['device_token'] ?? '')))));
    $dev_serial = $headers['x-device-serial'] ?? ($_SERVER['HTTP_X_DEVICE_SERIAL'] ?? ($_GET['device_serial'] ?? ''));
    if ($dev_token !== '') {
        $auth_dev = verify_device_token($pdo, $dev_token);
        if ($auth_dev) {
            return [
                'type'          => 'device',
                'device_id'     => (int)$auth_dev['id'],
                'device_serial' => $auth_dev['device_serial'] ?? '',
                'device_name'   => $auth_dev['device_name'],
                'user'          => api_resolve_registered_username($pdo, $auth_dev['username'] ?? null),
                'is_admin'      => ($auth_dev['role'] === 'admin')
            ];
        }
    }

    // 2. Sessione web attiva
    if (is_logged_in()) {
        $reg_dev = api_resolve_registered_device($pdo, null, $dev_serial);
        return [
            'type'          => 'session',
            'device_id'     => $reg_dev['id'],
            'device_serial' => $reg_dev['device_serial'],
            'device_name'   => $reg_dev['device_name'],
            'user'          => api_resolve_registered_username($pdo, get_current_user_name()),
            'is_admin'      => is_admin_user()
        ];
    }

    // 3. Chiave API globale di sistema
    $provided_key = $headers['x-api-key'] ?? ($_SERVER['HTTP_X_API_KEY'] ?? ($_GET['key'] ?? ($_POST['key'] ?? '')));
    if ($provided_key !== '' && defined('API_SECRET_KEY') && hash_equals((string)API_SECRET_KEY, (string)$provided_key)) {
        $reg_dev = api_resolve_registered_device($pdo, null, $dev_serial);
        return [
            'type'          => 'api_key',
            'device_id'     => $reg_dev['id'],
            'device_serial' => $reg_dev['device_serial'],
            'device_name'   => $reg_dev['device_name'],
            'user'          => api_resolve_registered_username($pdo, null),
            'is_admin'      => true
        ];
    }

    // Nessuna credenziale valida fornita
    http_response_code(403);
    echo json_encode([
        'status' => 'forbidden',
        'error'  => 'Accesso negato: richiesta non autenticata. Effettua il login o autorizza l\'applicazione mobile dal sito.'
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
