<?php
/**
 * API Stato Dispositivo: indica se il Foobot Ã¨ connesso al sistema
 * Connesso = ultima lettura ricevuta entro ONLINE_THRESHOLD secondi.
 * I dati tecnici sensibili (UUID completo, IP infrastruttura) sono mostrati solo agli utenti autenticati.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/common.php';

require_once dirname(__DIR__) . '/auth.php';

// Blindatura:
// 1. Accesso tramite sessione utente web, chiave API o token dispositivo mobile autorizzato (App PWA)
// 2. I dati di infrastruttura (IP Oracle/NextDNS e UUID in chiaro) sono riservati esclusivamente all'Amministratore
$pdo = api_db();
$logged_in = is_logged_in();
$is_admin = is_admin_user();
$headers = function_exists('getallheaders') ? array_change_key_case(getallheaders(), CASE_LOWER) : [];
$provided_key = $headers['x-api-key'] ?? ($_SERVER['HTTP_X_API_KEY'] ?? ($_GET['key'] ?? ''));
$has_valid_api_key = (defined('API_SECRET_KEY') && API_SECRET_KEY !== '' && hash_equals((string)API_SECRET_KEY, (string)$provided_key));
$is_ajax_dashboard = (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest');

// Controllo token dispositivo mobile: header X-Device-Token oppure parametro token/device_token
$dev_token = $headers['x-device-token'] ?? ($_SERVER['HTTP_X_DEVICE_TOKEN'] ?? ($_GET['token'] ?? ($_GET['device_token'] ?? ($_POST['token'] ?? ($_POST['device_token'] ?? '')))));
$app_mode = $headers['x-app-mode'] ?? ($_SERVER['HTTP_X_APP_MODE'] ?? null);
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
$dev_auth = false;
$is_authorized_mobile = false;
if ($dev_token !== '') {
    $dev_auth = verify_device_token($pdo, $dev_token, $app_mode, $user_agent);
    if ($dev_auth) {
        $is_authorized_mobile = true;
        if (($dev_auth['role'] ?? '') === 'admin') {
            $is_admin = true;
        }
    }
}

if (!$is_admin && !$has_valid_api_key && !$is_authorized_mobile && !$logged_in) {
    http_response_code(403);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'status'  => 'forbidden',
        'error'   => 'Accesso negato: questa API è blindata ed è visibile unicamente agli utenti autorizzati o all\'amministratore.'
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

const ONLINE_THRESHOLD = 900; // 15 minuti
$now = time();

$dev_serial = $headers['x-device-serial'] ?? ($_SERVER['HTTP_X_DEVICE_SERIAL'] ?? ($_GET['device_serial'] ?? ''));
$reg_dev = function_exists('api_resolve_registered_device')
    ? api_resolve_registered_device($pdo, $dev_auth ?: null, $dev_serial)
    : ['id' => ($dev_auth ? (int)$dev_auth['id'] : null), 'device_serial' => ($dev_auth ? ($dev_auth['device_serial'] ?? null) : null), 'username' => null];

$resolved_user = function_exists('api_resolve_registered_username')
    ? api_resolve_registered_username($pdo, ($dev_auth && !empty($dev_auth['username'])) ? $dev_auth['username'] : ($logged_in ? get_current_user_name() : ($reg_dev['username'] ?? null)))
    : (($dev_auth && !empty($dev_auth['username'])) ? $dev_auth['username'] : ($logged_in ? get_current_user_name() : ($reg_dev['username'] ?? null)));

if (empty($resolved_user)) {
    try {
        $stmt_owner = $pdo->query("SELECT username FROM foobot_users WHERE username != '' ORDER BY (role = 'admin') DESC, id ASC LIMIT 1");
        $resolved_user = $stmt_owner ? trim((string)$stmt_owner->fetchColumn()) : null;
    } catch (Exception $e_u) {}
}

$i18n_file = dirname(__DIR__) . '/i18n.php';
if (file_exists($i18n_file)) {
    require_once $i18n_file;
}

try {
    $uuid = api_resolve_uuid($pdo, $_GET['uuid'] ?? '');

    $result = [
        'status'                => 'nodata',
        'online'                => false,
        'uuid'                  => '',
        'device_name'           => '',
        'last_seen'             => null,
        'seconds_ago'           => null,
        'first_seen'            => null,
        'readings_24h'          => 0,
        'readings_total'        => 0,
        'location'              => null,
        'avg_interval'          => null,
        'threshold'             => ONLINE_THRESHOLD,
        'server_time'           => $now,
        'is_admin'              => $is_admin,
        'connected_user'        => $resolved_user,
        'user'                  => $resolved_user,
        'device_reg_id'         => $reg_dev['id'],
        'device_id'             => $reg_dev['id'],
        'device_serial'         => $reg_dev['device_serial'],
        'settings_device_reg_id'=> function_exists('__') ? __('settings_device_reg_id', 'ID Dispositivo App:') : 'ID Dispositivo App:',
        'i18n_labels'           => [
            'it' => ['settings_device_reg_id' => 'ID Dispositivo App:'],
            'en' => ['settings_device_reg_id' => 'App Device ID:']
        ],
        'all_devices'           => get_available_foobot_devices($pdo),
    ];

    if ($uuid !== '') {
        // Recupera eventuale nome amichevole e stanza configurati
        try {
            $stmt_name = $pdo->prepare("SELECT device_name, location FROM foobot_devices_meta WHERE device_uuid = ? LIMIT 1");
            $stmt_name->execute([$uuid]);
            $meta_row = $stmt_name->fetch(PDO::FETCH_ASSOC);
            $custom_name = $meta_row && !empty($meta_row['device_name']) ? $meta_row['device_name'] : null;
            $result['device_name'] = $custom_name ?: ('Foobot ' . (strlen($uuid) > 6 ? substr($uuid, -4) : $uuid));
            $result['location'] = ($meta_row && !empty($meta_row['location'])) ? $meta_row['location'] : null;
        } catch (Exception $e_n) {
            $result['device_name'] = 'Foobot ' . $uuid;
            $result['location'] = null;
        }
        $stmt = $pdo->prepare('SELECT MAX(timestamp) AS last_ts, MIN(timestamp) AS first_ts, COUNT(*) AS total
                               FROM foobot_readings WHERE device_uuid = ?');
        $stmt->execute([$uuid]);
        $agg = $stmt->fetch();

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM foobot_readings WHERE device_uuid = ? AND timestamp >= ?');
        $stmt->execute([$uuid, $now - 86400]);
        $count24 = (int)$stmt->fetchColumn();

        if ($agg && $agg['last_ts']) {
            $last = (int)$agg['last_ts'];
            $ago = max(0, $now - $last);
            $result['last_seen']      = $last;
            $result['seconds_ago']    = $ago;
            $result['first_seen']     = (int)$agg['first_ts'];
            $result['readings_total'] = (int)$agg['total'];
            $result['readings_24h']   = $count24;
            $result['avg_interval']   = $count24 > 1 ? (int)round(86400 / $count24) : null;
            $result['online']         = $ago <= ONLINE_THRESHOLD;
            $result['status']         = $result['online'] ? 'online' : 'offline';
        }

        // Interroga la tabella degli eventi di connessione/interruzione
        $result['last_connected_at'] = null;
        $result['last_disconnected_at'] = null;
        $result['last_event_type'] = null;
        $result['last_event_details'] = null;

        try {
            $stmt_last_evt = $pdo->prepare('SELECT event_type, timestamp, details FROM foobot_connection_logs WHERE device_uuid = ? ORDER BY timestamp DESC LIMIT 1');
            $stmt_last_evt->execute([$uuid]);
            $lastEvtRow = $stmt_last_evt->fetch();
            if ($lastEvtRow) {
                $result['last_event_type'] = $lastEvtRow['event_type'];
                $result['last_event_details'] = $lastEvtRow['details'];
                if ($lastEvtRow['event_type'] === 'connected') {
                    $result['last_connected_at'] = (int)$lastEvtRow['timestamp'];
                } else {
                    $result['last_disconnected_at'] = (int)$lastEvtRow['timestamp'];
                    $result['online'] = false;
                    $result['status'] = 'offline';
                }
            }

            // Ultima data di connessione nota
            $stmt_conn = $pdo->prepare("SELECT timestamp FROM foobot_connection_logs WHERE device_uuid = ? AND event_type = 'connected' ORDER BY timestamp DESC LIMIT 1");
            $stmt_conn->execute([$uuid]);
            $tsConn = $stmt_conn->fetchColumn();
            if ($tsConn) {
                $result['last_connected_at'] = (int)$tsConn;
            }

            // Ultima data di disconnessione / caduta linea
            $stmt_disc = $pdo->prepare("SELECT timestamp, event_type, details FROM foobot_connection_logs WHERE device_uuid = ? AND event_type IN ('disconnected', 'power_cut') ORDER BY timestamp DESC LIMIT 1");
            $stmt_disc->execute([$uuid]);
            $rowDisc = $stmt_disc->fetch();
            if ($rowDisc) {
                $result['last_disconnected_at'] = (int)$rowDisc['timestamp'];
                $result['disconnect_reason'] = $rowDisc['details'];
            }
        } catch (Exception $e_ev) {}

        // UUID completo solo per l'amministratore, mascherato per i visitatori
        // UUID completo per admin, mascherato con pallini puliti per i visitatori
        $result['uuid'] = $is_admin ? $uuid : (strlen($uuid) > 4 ? "\u{2022}\u{2022}\u{2022}\u{2022}\u{2022}\u{2022}\u{2022}\u{2022}" . substr($uuid, -4) : "\u{2022}\u{2022}\u{2022}\u{2022}\u{2022}\u{2022}\u{2022}\u{2022}\u{2022}\u{2022}\u{2022}\u{2022}");
    }

    if ($is_admin) {
        $result['oracle_ip']  = defined('ORACLE_BROKER_IP') ? ORACLE_BROKER_IP : '';
        $result['nextdns_ip'] = defined('NEXTDNS_SERVER_IP') ? NEXTDNS_SERVER_IP : '';
    }

    // Controllo automatico avviso email disconnessione (>30 minuti) e Web-Cron backup
    $cron_file = dirname(__DIR__) . '/cron_events.php';
    if (file_exists($cron_file)) {
        require_once $cron_file;
        if (function_exists('maybe_check_offline_email_alert')) {
            maybe_check_offline_email_alert($pdo);
        }
        if (function_exists('maybe_trigger_auto_backup')) {
            maybe_trigger_auto_backup($pdo);
        }
    }

    $result['temp_unit'] = get_temperature_unit($pdo);
    $result['dashboard_lang'] = api_resolve_user_lang($pdo, $auth['user_id'] ?? null);
    echo json_encode($result);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
