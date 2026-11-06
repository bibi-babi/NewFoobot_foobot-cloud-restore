<?php
/**
 * API Ingest: ricezione letture del sensore Foobot inviate dal bridge Oracle
 * Metodo: POST (JSON oppure form)
 * Autenticazione: header 'X-Api-Key' oppure parametro 'api_key'
 * Protezione opzionale: Whitelist IP Server Oracle
 */

require_once __DIR__ . '/common.php';
require_once dirname(__DIR__) . '/auth.php';

// Gestione apertura via browser (GET):
// - Non loggato / Utente non-admin -> 403 Accesso negato
// - Admin loggato (o chiave API valida) -> Mostra lo stato dell'endpoint e gli ultimi dati ricevuti dal Foobot
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $headers_get = function_exists('getallheaders') ? array_change_key_case(getallheaders(), CASE_LOWER) : [];
    $provided_key_get = $headers_get['x-api-key'] ?? ($_SERVER['HTTP_X_API_KEY'] ?? ($_GET['api_key'] ?? ($_GET['key'] ?? '')));
    $has_valid_key_get = ($provided_key_get !== '' && defined('API_SECRET_KEY') && hash_equals((string)API_SECRET_KEY, (string)$provided_key_get));

    if (!is_admin_user() && !$has_valid_key_get) {
        http_response_code(403);
        echo json_encode([
            'status'  => 'forbidden',
            'message' => 'Accesso negato. Effettua il login come amministratore per visualizzare i dati di ingestione oppure utilizza il metodo POST con X-Api-Key.'
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    try {
        $pdo = api_db();
        $total_readings = (int)$pdo->query('SELECT COUNT(*) FROM foobot_readings')->fetchColumn();

        $stmt_last = $pdo->query('SELECT id, device_uuid, timestamp, allpollu, pm, voc, co2, tmp, hum, created_at FROM foobot_readings ORDER BY timestamp DESC LIMIT 5');
        $recent = $stmt_last->fetchAll();
        foreach ($recent as &$r) {
            $r['id']        = (int)$r['id'];
            $r['timestamp'] = (int)$r['timestamp'];
            $r['datetime']  = date('d/m/Y H:i:s', (int)$r['timestamp']);
            $r['allpollu']  = (int)$r['allpollu'];
            $r['pm']        = (float)$r['pm'];
            $r['voc']       = (int)$r['voc'];
            $r['co2']       = (int)$r['co2'];
            $r['tmp']       = (float)$r['tmp'];
            $r['hum']       = (int)$r['hum'];
        }
        unset($r);

        $last_conn_event = null;
        try {
            $stmt_ev = $pdo->query('SELECT id, device_uuid, event_type, timestamp, details, created_at FROM foobot_connection_logs ORDER BY timestamp DESC LIMIT 1');
            $ev = $stmt_ev->fetch();
            if ($ev) {
                $ev['id']        = (int)$ev['id'];
                $ev['timestamp'] = (int)$ev['timestamp'];
                $ev['datetime']  = date('d/m/Y H:i:s', (int)$ev['timestamp']);
                $last_conn_event = $ev;
            }
        } catch (Exception $e_ev) {}

        $bridge_logs_enabled = true;
        try {
            $stmt_bl = $pdo->query("SELECT setting_value FROM foobot_settings WHERE setting_key = 'enable_bridge_logs' LIMIT 1");
            $val_bl = $stmt_bl->fetchColumn();
            if ($val_bl !== false) {
                $bridge_logs_enabled = ($val_bl !== '0');
            }
        } catch (Exception $e_bl) {}

        echo json_encode([
            'status'              => 'ok',
            'endpoint'            => 'api/ingest.php',
            'authenticated_admin' => $_SESSION['foobot_user'] ?? 'api_key',
            'server_time'         => date('d/m/Y H:i:s'),
            'bridge_logs_enabled' => $bridge_logs_enabled,
            'total_readings'      => $total_readings,
            'last_reading'        => $recent[0] ?? null,
            'last_event'          => $last_conn_event,
            'recent_5_readings'   => $recent
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Errore lettura dati ingest: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$pdo = api_db();

// Controllo opzionale Whitelist IP per i dati Foobot in ingresso (Broker Bridge o IP Foobot autorizzati)
$sender_ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
try {
    $stmt_set = $pdo->query("SELECT setting_key, setting_value FROM foobot_settings WHERE setting_key IN ('enforce_oracle_ip_api', 'oracle_broker_ip', 'admin_ip_whitelist', 'foobot_ip_whitelist')");
    $sec = [];
    while ($r = $stmt_set->fetch()) {
        $sec[$r['setting_key']] = $r['setting_value'];
    }
    $enforce_ip = ($sec['enforce_oracle_ip_api'] ?? '0') === '1';
    
    // Costruisce la lista degli IP autorizzati per l'ingestione dati Foobot
    $allowed_ips = [];
    $oracle_ip = trim($sec['oracle_broker_ip'] ?? (defined('ORACLE_BROKER_IP') ? ORACLE_BROKER_IP : ''));
    if ($oracle_ip !== '') {
        $allowed_ips = array_merge($allowed_ips, preg_split('/[\s,;]+/', $oracle_ip, -1, PREG_SPLIT_NO_EMPTY));
    }
    $foobot_wl = trim($sec['foobot_ip_whitelist'] ?? ($sec['admin_ip_whitelist'] ?? ''));
    if ($foobot_wl !== '') {
        $allowed_ips = array_merge($allowed_ips, preg_split('/[\s,;]+/', $foobot_wl, -1, PREG_SPLIT_NO_EMPTY));
    }
    $allowed_ips = array_unique(array_filter($allowed_ips));

    if ($enforce_ip && !empty($allowed_ips) && !in_array($sender_ip, $allowed_ips, true) && $sender_ip !== '127.0.0.1') {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Accesso rifiutato: IP mittente (' . $sender_ip . ') non autorizzato nella Whitelist di ricezione dati Foobot.']);
        exit;
    }
} catch (Exception $e) {
    // Continua se la tabella impostazioni non è presente
}

// Verifica autenticazione con la passkey API
$headers = function_exists('getallheaders') ? array_change_key_case(getallheaders(), CASE_LOWER) : [];
$provided_key = $headers['x-api-key'] ?? ($_SERVER['HTTP_X_API_KEY'] ?? ($_POST['api_key'] ?? ($_GET['api_key'] ?? '')));

if ($provided_key === '' || !hash_equals((string)API_SECRET_KEY, (string)$provided_key)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Accesso non autorizzato. API Key non valida.']);
    exit;
}

// Lettura dati: JSON nel corpo oppure campi form
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data) || empty($data)) {
    $data = $_POST;
}
if (empty($data)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Nessun dato valido ricevuto.']);
    exit;
}

// Estrazione e validazione identificativo dispositivo
$uuid = substr(preg_replace('/[^A-Za-z0-9]/', '', (string)($data['device_uuid'] ?? $data['uuid'] ?? '')), 0, 32);
if ($uuid === '') {
    $uuid = 'foobot';
}
$timestamp = isset($data['time']) ? (int)$data['time'] : time();

// Gestione evento di connessione / disconnessione / caduta linea
$eventType = strtolower(trim((string)($data['event'] ?? $data['event_type'] ?? '')));
// Gestione log dettagliato Bridge (diagnostica console per sviluppo)
if ($eventType === 'bridge_log') {
    // Verifica se la ricezione del log dettagliato e attiva nelle impostazioni (default '1')
    try {
        $stmt_log_en = $pdo->query("SELECT setting_value FROM foobot_settings WHERE setting_key = 'enable_bridge_logs' LIMIT 1");
        $is_log_enabled = $stmt_log_en->fetchColumn();
        if ($is_log_enabled === '0') {
            echo json_encode(['status' => 'ignored', 'message' => 'Bridge logs disabilitati dall utente']);
            exit;
        }
    } catch (Exception $e) {}
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS foobot_bridge_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            device_uuid VARCHAR(32) NOT NULL DEFAULT 'foobot',
            log_level VARCHAR(16) NOT NULL DEFAULT 'INFO',
            message TEXT NOT NULL,
            timestamp BIGINT UNSIGNED NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_timestamp (timestamp),
            INDEX idx_device (device_uuid),
            INDEX idx_level (log_level)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $level = strtoupper(substr(trim((string)($data['level'] ?? $data['log_level'] ?? 'INFO')), 0, 16));
        $msgText = trim((string)($data['message'] ?? $data['details'] ?? ''));
        if ($msgText !== '') {
            $stmt_bl = $pdo->prepare('INSERT INTO foobot_bridge_logs (device_uuid, log_level, message, timestamp) VALUES (?, ?, ?, ?)');
            $stmt_bl->execute([$uuid, $level, $msgText, $timestamp]);

            // Pulizia automatica: conserva solo gli ultimi 1500 log di diagnostica
            if (mt_rand(1, 20) === 1) {
                $pdo->exec("DELETE FROM foobot_bridge_logs WHERE id NOT IN (SELECT id FROM (SELECT id FROM foobot_bridge_logs ORDER BY id DESC LIMIT 1500) t)");
            }
        }
        echo json_encode(['status' => 'ok', 'event' => 'bridge_log', 'logged' => true]);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Errore registrazione bridge_log: ' . $e->getMessage()]);
        exit;
    }
}

if (in_array($eventType, ['connected', 'disconnected', 'power_cut'], true)) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS foobot_connection_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            device_uuid VARCHAR(32) NOT NULL DEFAULT 'foobot',
            event_type ENUM('connected', 'disconnected', 'power_cut') NOT NULL,
            timestamp BIGINT UNSIGNED NOT NULL,
            details VARCHAR(255) DEFAULT '',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_device_time (device_uuid, timestamp),
            INDEX idx_event_type (event_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $details = substr((string)($data['details'] ?? ''), 0, 255);
        $stmt_evt = $pdo->prepare('INSERT INTO foobot_connection_logs (device_uuid, event_type, timestamp, details) VALUES (?, ?, ?, ?)');
        $stmt_evt->execute([$uuid, $eventType, $timestamp, $details]);

        // Registra anche nella Console Diagnostica Bridge (se i log bridge sono attivi)
        try {
            $stmt_log_en = $pdo->query("SELECT setting_value FROM foobot_settings WHERE setting_key = 'enable_bridge_logs' LIMIT 1");
            $is_log_enabled = $stmt_log_en->fetchColumn();
            if ($is_log_enabled !== '0') {
                $lvl = ($eventType === 'connected') ? 'INFO' : 'WARNING';
                $msg_evt = sprintf("Evento stato dispositivo [%s]: %s (%s)", $uuid, $eventType, $details);
                $stmt_bl = $pdo->prepare('INSERT INTO foobot_bridge_logs (device_uuid, log_level, message, timestamp) VALUES (?, ?, ?, ?)');
                $stmt_bl->execute([$uuid, $lvl, $msg_evt, $timestamp]);
            }
        } catch (Exception $e_bl) {}

        echo json_encode(['status' => 'ok', 'event' => $eventType, 'logged' => true]);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Errore registrazione evento: ' . $e->getMessage()]);
        exit;
    }
}

// Ingestione telemetria sensori
$allpollu  = max(0, min(100, (int)($data['allpollu'] ?? 0)));
$pm        = max(0.0, (float)($data['pm'] ?? 0.0));
$voc       = max(0, (int)($data['voc'] ?? 0));
$co2       = max(0, (int)($data['co2'] ?? 0));
$tmp       = (float)($data['tmp'] ?? 0.0);
$hum       = max(0, min(100, (int)($data['hum'] ?? 0)));

// Scarta pacchetti spuri di warm-up o riavvio con tutti i parametri a zero per non inquinare il DB
if ($hum === 0 && $co2 === 0 && abs($tmp) < 0.001 && $pm < 0.001 && $voc === 0) {
    echo json_encode(['status' => 'ignored', 'message' => 'Pacchetto warm-up / vuoto ignorato per preservare l integrità del grafico.']);
    exit;
}

try {
    $stmt = $pdo->prepare('INSERT INTO foobot_readings (device_uuid, timestamp, allpollu, pm, voc, co2, tmp, hum)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$uuid, $timestamp, $allpollu, $pm, $voc, $co2, $tmp, $hum]);
    $inserted_id = (int)$pdo->lastInsertId();

    // Registra nel log della Console Diagnostica Bridge i dati calibrati e la conferma di salvataggio su cPanel (se attivi)
    try {
        $stmt_log_en = $pdo->query("SELECT setting_value FROM foobot_settings WHERE setting_key = 'enable_bridge_logs' LIMIT 1");
        $is_log_enabled = $stmt_log_en->fetchColumn();
        if ($is_log_enabled !== '0') {
            $cal_msg = sprintf(
                "Dati calibrati [%s]: Temp: %.1f C | Hum: %d%% | PM2.5: %.1f ug/m3 | VOC: %d ppb | CO2: %d ppm | allpollu: %d -> Inoltro a cPanel completato [HTTP 200 - ID #%d]",
                $uuid, $tmp, $hum, $pm, $voc, $co2, $allpollu, $inserted_id
            );
            $stmt_bl = $pdo->prepare('INSERT INTO foobot_bridge_logs (device_uuid, log_level, message, timestamp) VALUES (?, ?, ?, ?)');
            $stmt_bl->execute([$uuid, 'INFO', $cal_msg, $timestamp]);
        }
    } catch (Exception $e_bl2) {}

    // Registra anche la presenza online automatica nella tabella di connessione
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS foobot_connection_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            device_uuid VARCHAR(32) NOT NULL DEFAULT 'foobot',
            event_type ENUM('connected', 'disconnected', 'power_cut') NOT NULL,
            timestamp BIGINT UNSIGNED NOT NULL,
            details VARCHAR(255) DEFAULT '',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_device_time (device_uuid, timestamp),
            INDEX idx_event_type (event_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $stmt_chk = $pdo->prepare('SELECT event_type FROM foobot_connection_logs WHERE device_uuid = ? ORDER BY timestamp DESC LIMIT 1');
        $stmt_chk->execute([$uuid]);
        $lastEvt = $stmt_chk->fetchColumn();
        if ($lastEvt !== 'connected') {
            $stmt_c = $pdo->prepare('INSERT INTO foobot_connection_logs (device_uuid, event_type, timestamp, details) VALUES (?, ?, ?, ?)');
            $stmt_c->execute([$uuid, 'connected', $timestamp, 'Pacchetto dati ricevuto regolarmente']);
        }
    } catch (Exception $e_c) {}

    // Se il Foobot torna a inviare dati, azzera il flag di allerta email offline
    try {
        save_setting($pdo, 'offline_email_alert_sent', '0');
    } catch (Exception $e_al) {}

    // Web-Cron Autonomo: controlla se sono passate 24 ore e salva il backup CSV senza dipendere da cPanel
    $cron_file = __DIR__ . '/../cron_events.php';
    if (file_exists($cron_file)) {
        require_once $cron_file;
        if (function_exists('maybe_trigger_auto_backup')) {
            maybe_trigger_auto_backup($pdo);
        }
    }

    echo json_encode(['status' => 'ok', 'id' => $inserted_id]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Errore salvataggio: ' . $e->getMessage()]);
}

