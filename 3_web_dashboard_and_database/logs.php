<?php
/**
 * Pagina Log di Sistema e Diagnostica Bridge NewFoobot
 * Visualizza:
 * 1. Registro Connessioni ed Eventi di Rete / Blackout (foobot_connection_logs)
 * 2. Console Diagnostica Dettagliata del Bridge (foobot_bridge_logs) per sviluppo e debug
 * 
 * Funzionalità:
 * - Attiva / Disattiva ricezione log bridge dettagliato
 * - Aggiornamento manuale con pulsante
 * - Auto-aggiornamento live ogni minuto (attivabile / disattivabile via toggle)
 * - Pulsante per cancellare / svuotare il log
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/nav.php';

// Protezione e verifica sessione amministratore
$pdo = require_login();

// Controllo automatico Web-Cron al caricamento
if (file_exists(__DIR__ . '/cron_events.php')) {
    require_once __DIR__ . '/cron_events.php';
    if (function_exists('maybe_trigger_auto_backup')) {
        maybe_trigger_auto_backup($pdo);
    }
    if (function_exists('maybe_check_offline_email_alert')) {
        maybe_check_offline_email_alert($pdo);
    }
}

$username = get_current_user_name();
$is_admin = is_admin_user();
$current_lang = get_current_lang();

/**
 * Formatta un timestamp Unix in base alla lingua dell'interfaccia
 */
function format_log_datetime($ts, $lang = null) {
    if ($lang === null) $lang = get_current_lang();
    $d = (int)$ts;
    if ($lang === 'en') {
        return date('m/d/Y H:i:s', $d);
    } elseif ($lang === 'de') {
        return date('d.m.Y H:i:s', $d);
    } else {
        return date('d/m/Y H:i:s', $d);
    }
}

/**
 * Traduce dinamicamente i messaggi tecnici della console Bridge e i dettagli evento
 */
function translate_log_text($text, $lang = null) {
    if (empty($text)) return '';
    if ($lang === null) $lang = get_current_lang();

    // Normalizzazione: rimuove 'Oracle' dai riferimenti a Broker Bridge per compatibilità universale
    $text = str_ireplace('Broker Bridge Oracle', 'Broker Bridge', $text);
    if ($lang === 'it') return $text;

    // Pattern: Evento stato dispositivo [UUID]: event_type (details)
    if (preg_match('/^Evento stato dispositivo\s*\[([^\]]+)\]:\s*([^\s(]+)(?:\s*\((.*)\))?$/i', $text, $m)) {
        $uuid = $m[1];
        $ev = $m[2];
        $details = $m[3] ?? '';
        $trans_details = translate_log_text($details, $lang);

        $ev_labels = [
            'connected' => ['en' => 'Connected', 'fr' => 'Connecté', 'de' => 'Verbunden', 'es' => 'Conectado'],
            'disconnected' => ['en' => 'Disconnected', 'fr' => 'Déconnecté', 'de' => 'Getrennt', 'es' => 'Desconectado'],
            'power_cut' => ['en' => 'Blackout / No Signal', 'fr' => 'Panne de courant / Aucun signal', 'de' => 'Stromausfall / Kein Signal', 'es' => 'Apagón / Sin Señal']
        ];
        $ev_name = $ev_labels[$ev][$lang] ?? $ev;

        $tpl = [
            'en' => "Device status event [%s]: %s (%s)",
            'fr' => "Événement d'état du capteur [%s] : %s (%s)",
            'de' => "Gerätestatus-Ereignis [%s]: %s (%s)",
            'es' => "Evento de estado del sensor [%s]: %s (%s)"
        ];
        return sprintf($tpl[$lang] ?? "Device status event [%s]: %s (%s)", $uuid, $ev_name, $trans_details);
    }

    // Pattern: Nuova connessione TCP da ...
    if (preg_match('/^Nuova connessione TCP da\s+(.+)$/i', $text, $m)) {
        $from = $m[1];
        $tpl = [
            'en' => "New TCP connection from %s",
            'fr' => "Nouvelle connexion TCP depuis %s",
            'de' => "Neue TCP-Verbindung von %s",
            'es' => "Nueva conexión TCP desde %s"
        ];
        return sprintf($tpl[$lang] ?? "New TCP connection from %s", $from);
    }

    // Pattern: Connessione chiusa da ...
    if (preg_match('/^(?:Connessione chiusa da|Chiusura connessione socket TCP da)\s+(.+)$/i', $text, $m)) {
        $from = $m[1];
        $tpl = [
            'en' => "Connection closed by %s",
            'fr' => "Connexion fermée par %s",
            'de' => "Verbindung geschlossen von %s",
            'es' => "Conexión cerrada por %s"
        ];
        return sprintf($tpl[$lang] ?? "Connection closed by %s", $from);
    }

    // Pattern: Ricevuto pacchetto telemetria sensori (...)
    if (preg_match('/^Ricevuto pacchetto telemetria sensori(?:\s*\(([^)]+)\))?$/i', $text, $m)) {
        $size = $m[1] ?? '';
        $tpl = [
            'en' => $size ? "Sensor telemetry packet received (%s)" : "Sensor telemetry packet received",
            'fr' => $size ? "Paquet de télémétrie des capteurs reçu (%s)" : "Paquet de télémétrie des capteurs reçu",
            'de' => $size ? "Sensortelemetriepaket empfangen (%s)" : "Sensortelemetriepaket empfangen",
            'es' => $size ? "Paquete de telemetría de sensores recibido (%s)" : "Paquete de telemetría de sensores recibido"
        ];
        return $size ? sprintf($tpl[$lang] ?? "Sensor telemetry packet received (%s)", $size) : ($tpl[$lang] ?? "Sensor telemetry packet received");
    }

    // Pattern: Dati calibrati [UUID]: ... -> Inoltro a cPanel completato [HTTP ... - ID #...]
    if (preg_match('/^Dati calibrati\s*\[([^\]]+)\]:\s*(.+?)\s*->\s*Inoltro a cPanel completato\s*\[HTTP (\d+) - ID #(\d+)\]$/i', $text, $m)) {
        $uuid = $m[1];
        $telemetry = $m[2];
        $http = (int)$m[3];
        $id = (int)$m[4];
        $tpl = [
            'en' => "Calibrated data [%s]: %s -> Forwarding to cPanel completed [HTTP %d - ID #%d]",
            'fr' => "Données calibrées [%s] : %s -> Transfert vers cPanel terminé [HTTP %d - ID #%d]",
            'de' => "Kalibrierte Daten [%s]: %s -> Weiterleitung an cPanel abgeschlossen [HTTP %d - ID #%d]",
            'es' => "Datos calibrados [%s]: %s -> Reenvío a cPanel completado [HTTP %d - ID #%d]"
        ];
        return sprintf($tpl[$lang] ?? "Calibrated data [%s]: %s -> Forwarding to cPanel completed [HTTP %d - ID #%d]", $uuid, $telemetry, $http, $id);
    }

    // Pattern: Autenticazione Foobot MQTT CONNECT riuscita: ClientID=... (...)
    if (preg_match('/^Autenticazione Foobot MQTT CONNECT riuscita:\s*ClientID=([^\s]+)\s*\(([^)]+)\)$/i', $text, $m)) {
        $client_id = $m[1];
        $ip = $m[2];
        $tpl = [
            'en' => "Foobot MQTT CONNECT authentication succeeded: ClientID=%s (%s)",
            'fr' => "Authentification Foobot MQTT CONNECT réussie : ClientID=%s (%s)",
            'de' => "Foobot MQTT CONNECT-Authentifizierung erfolgreich: ClientID=%s (%s)",
            'es' => "Autenticación Foobot MQTT CONNECT exitosa: ClientID=%s (%s)"
        ];
        return sprintf($tpl[$lang] ?? "Foobot MQTT CONNECT authentication succeeded: ClientID=%s (%s)", $client_id, $ip);
    }

    // Pattern: Ricevuto pacchetto ...
    if (preg_match('/^Ricevuto pacchetto (?:telemetria|dati) da\s+(.+)$/i', $text, $m)) {
        $from = $m[1];
        $tpl = [
            'en' => "Telemetry data packet received from %s",
            'fr' => "Paquet de données reçu de %s",
            'de' => "Telemetriedatenpaket empfangen von %s",
            'es' => "Paquete de telemetría recibido desde %s"
        ];
        return sprintf($tpl[$lang] ?? "Telemetry data packet received from %s", $from);
    }

    // Dizionario dettagli ed eventi
    $dict = [
        'Connessione stabilita con il Broker Bridge' => [
            'en' => 'Connection established with Broker Bridge',
            'fr' => 'Connexion établie avec le Broker Bridge',
            'de' => 'Verbindung mit Broker Bridge hergestellt',
            'es' => 'Conexión establecida con el Broker Bridge'
        ],
        'Interruzione linea o alimentazione rilevata (assenza segnale >10 min)' => [
            'en' => 'Line or power outage detected (no signal >10 min)',
            'fr' => 'Coupure de ligne ou d\'alimentation détectée (absence de signal >10 min)',
            'de' => 'Leitungs- oder Stromausfall erkannt (kein Signal >10 Min.)',
            'es' => 'Interrupción de línea o energía detectada (sin señal >10 min)'
        ],
        'Interruzione linea o alimentazione rilevata (assenza segnale >12 min)' => [
            'en' => 'Line or power outage detected (no signal >12 min)',
            'fr' => 'Coupure de ligne ou d\'alimentation détectée (absence de signal >12 min)',
            'de' => 'Leitungs- oder Stromausfall erkannt (kein Signal >12 Min.)',
            'es' => 'Interrupción de línea o energía detectada (sin señal >12 min)'
        ],
        'Sensore online e invio telemetria attivo' => [
            'en' => 'Sensor online and telemetry streaming active',
            'fr' => 'Capteur en ligne et transmission de télémétrie active',
            'de' => 'Sensor online und Telemetrieübertragung aktiv',
            'es' => 'Sensor en línea y transmisión de telemetría activa'
        ],
        'Pacchetto dati ricevuto regolarmente' => [
            'en' => 'Data packet received regularly',
            'fr' => 'Paquet de données reçu régulièrement',
            'de' => 'Datenpaket ordnungsgemäß empfangen',
            'es' => 'Paquete de datos recibido regularmente'
        ],
        'Caduta connessione socket TCP' => [
            'en' => 'TCP socket connection drop',
            'fr' => 'Perte de connexion socket TCP',
            'de' => 'TCP-Socket-Verbindung getrennt',
            'es' => 'Pérdida de conexión socket TCP'
        ],
        'Caduta connessione Wi-Fi / Rete' => [
            'en' => 'Wi-Fi / Network connection drop',
            'fr' => 'Perte de connexion Wi-Fi / Réseau',
            'de' => 'Wi-Fi- / Netzwerkverbindungsverlust',
            'es' => 'Pérdida de conexión Wi-Fi / Red'
        ],
        'Assenza segnale o corrente (>12 min)' => [
            'en' => 'No signal or power cut (>12 min)',
            'fr' => 'Absence de signal ou coupure de courant (>12 min)',
            'de' => 'Kein Signal oder Stromausfall (>12 Min.)',
            'es' => 'Sin señal o corte de energía (>12 min)'
        ],
        'Chiusura connessione dal dispositivo' => [
            'en' => 'Connection closed by device',
            'fr' => 'Connexion fermée par le capteur',
            'de' => 'Verbindung vom Gerät geschlossen',
            'es' => 'Conexión cerrada por el dispositivo'
        ],
        'Handshake TLS completato con successo' => [
            'en' => 'TLS handshake completed successfully',
            'fr' => 'Handshake TLS réussi avec succès',
            'de' => 'TLS-Handshake erfolgreich abgeschlossen',
            'es' => 'Handshake TLS completado con éxito'
        ]
    ];

    if (isset($dict[$text][$lang])) {
        return $dict[$text][$lang];
    }

    foreach ($dict as $k => $trans) {
        if (stripos($text, $k) !== false && isset($trans[$lang])) {
            return str_ireplace($k, $trans[$lang], $text);
        }
    }

    return $text;
}

// Rilevamento dispositivi Foobot disponibili (Multi-Foobot)
$available_foobots = get_available_foobot_devices($pdo);
$filter_uuid = !empty($_GET['device']) ? substr(preg_replace('/[^A-Za-z0-9_-]/', '', (string)$_GET['device']), 0, 32) : '';
$msg = '';
$error = '';

// Assicura l'esistenza delle tabelle necessarie
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS foobot_connection_logs (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        device_uuid VARCHAR(32) NOT NULL DEFAULT 'foobot',
        event_type ENUM('connected', 'disconnected', 'power_cut') NOT NULL COMMENT 'Tipo evento di rete o alimentazione',
        timestamp BIGINT UNSIGNED NOT NULL COMMENT 'Unix timestamp UTC dell evento',
        details VARCHAR(255) DEFAULT '' COMMENT 'Dettagli tecnici o motivo evento',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_device_time (device_uuid, timestamp),
        INDEX idx_event_type (event_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

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

    // Se la tabella foobot_connection_logs è appena stata creata (o è stata svuotata)
    // ma il Foobot ha inviato una lettura negli ultimi 10 minuti, registra lo stato "connected"
    $conn_count = (int)$pdo->query("SELECT COUNT(*) FROM foobot_connection_logs")->fetchColumn();
    if ($conn_count === 0) {
        $stmt_last_r = $pdo->query("SELECT device_uuid, timestamp FROM foobot_readings ORDER BY timestamp DESC LIMIT 1");
        $last_r = $stmt_last_r ? $stmt_last_r->fetch() : false;
        if ($last_r && (time() - (int)$last_r['timestamp']) <= 600) {
            $stmt_init_c = $pdo->prepare("INSERT INTO foobot_connection_logs (device_uuid, event_type, timestamp, details) VALUES (?, 'connected', ?, 'Sensore online e invio telemetria attivo')");
            $stmt_init_c->execute([$last_r['device_uuid'], (int)$last_r['timestamp']]);
        }
    }
} catch (Exception $e) {}

// Endpoint API interna per auto-aggiornamento live (chiamata fetch AJAX mirata o cumulativa)
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=UTF-8');
    try {
        $filter_device = !empty($_GET['device']) ? substr(preg_replace('/[^A-Za-z0-9_-]/', '', (string)$_GET['device']), 0, 32) : '';
        $ajax_mode = (string)$_GET['ajax']; // 'conn', 'bridge', oppure '1'/'all'

        $response = [
            'status' => 'ok',
            'server_time' => date('d/m/Y H:i:s')
        ];

        // 1. Dati eventi di connessione e blackout (Sezione 1)
        if ($ajax_mode === 'conn' || $ajax_mode === 'all' || $ajax_mode === '1') {
            if ($filter_device !== '') {
                $stmt_c = $pdo->prepare("SELECT * FROM foobot_connection_logs WHERE device_uuid = ? ORDER BY timestamp DESC LIMIT 50");
                $stmt_c->execute([$filter_device]);
            } else {
                $stmt_c = $pdo->query("SELECT * FROM foobot_connection_logs ORDER BY timestamp DESC LIMIT 50");
            }
            $response['connection_logs'] = $stmt_c->fetchAll();
        }

        // 2. Dati diagnostici console bridge (Sezione 2, solo per amministratore)
        if ($ajax_mode === 'bridge' || $ajax_mode === 'all' || $ajax_mode === '1') {
            $bridge_list = [];
            if (is_admin_user()) {
                if ($filter_device !== '') {
                    $stmt_b = $pdo->prepare("SELECT * FROM foobot_bridge_logs WHERE device_uuid = ? ORDER BY timestamp DESC, id DESC LIMIT 150");
                    $stmt_b->execute([$filter_device]);
                } else {
                    $stmt_b = $pdo->query("SELECT * FROM foobot_bridge_logs ORDER BY timestamp DESC, id DESC LIMIT 150");
                }
                $bridge_list = $stmt_b->fetchAll();
            }
            $response['bridge_logs'] = $bridge_list;
        }

        echo json_encode($response, JSON_INVALID_UTF8_SUBSTITUTE);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// Gestione azioni POST (Cancellazione o Attiva/Disattiva)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';

    if (!$is_admin) {
        $error = __('logs_err_not_allowed', "Operazione non consentita: solo l'amministratore può modificare o svuotare i registri.");
    } elseif (!verify_csrf_token($csrf)) {
        $error = __('logs_err_csrf', "Token di sicurezza non valido. Ricarica la pagina.");
    } elseif ($action === 'toggle_logging') {
        $new_val = ($_POST['enable_logging'] ?? '0') === '1' ? '1' : '0';
        save_setting($pdo, 'enable_bridge_logs', $new_val);
        $msg = $new_val === '1' 
            ? __('logs_msg_logging_enabled', "Ricezione log di diagnostica Bridge ATTIVATA!") 
            : __('logs_msg_logging_disabled', "Ricezione log di diagnostica Bridge DISATTIVATA (modalità silenziosa).");
    } elseif ($action === 'clear_bridge_logs') {
        try {
            $pdo->exec("TRUNCATE TABLE foobot_bridge_logs");
            $msg = __('logs_msg_bridge_cleared', "Console diagnostica Bridge svuotata con successo!");
        } catch (Exception $e) {
            $error = __('logs_err_bridge_clear', "Errore svuotamento log: ") . $e->getMessage();
        }
    } elseif ($action === 'clear_conn_logs') {
        try {
            $pdo->exec("TRUNCATE TABLE foobot_connection_logs");
            $msg = __('logs_msg_conn_cleared', "Registro eventi connessione svuotato con successo!");
        } catch (Exception $e) {
            $error = __('logs_err_conn_clear', "Errore svuotamento eventi: ") . $e->getMessage();
        }
    }
}

// Stato attuale impostazione log (default: '1' attiva)
$logging_enabled = get_setting($pdo, 'enable_bridge_logs', '1') !== '0';

// Lettura eventi di connessione (ultimi 50, con eventuale filtro per sensore)
$conn_logs = [];
try {
    if ($filter_uuid !== '') {
        $stmt_conn = $pdo->prepare("SELECT * FROM foobot_connection_logs WHERE device_uuid = ? ORDER BY timestamp DESC LIMIT 50");
        $stmt_conn->execute([$filter_uuid]);
    } else {
        $stmt_conn = $pdo->query("SELECT * FROM foobot_connection_logs ORDER BY timestamp DESC LIMIT 50");
    }
    $conn_logs = $stmt_conn->fetchAll();
} catch (Exception $e) {}

// Lettura diagnostica dettagliata bridge (ultimi 150, solo per amministratore)
$bridge_logs = [];
if ($is_admin) {
    try {
        if ($filter_uuid !== '') {
            $stmt_bridge = $pdo->prepare("SELECT * FROM foobot_bridge_logs WHERE device_uuid = ? ORDER BY timestamp DESC, id DESC LIMIT 150");
            $stmt_bridge->execute([$filter_uuid]);
        } else {
            $stmt_bridge = $pdo->query("SELECT * FROM foobot_bridge_logs ORDER BY timestamp DESC, id DESC LIMIT 150");
        }
        $bridge_logs = $stmt_bridge->fetchAll();
    } catch (Exception $e) {}
}

$csrf_token = get_csrf_token();
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($current_lang); ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars(__('nav_logs') ?: 'Log di Sistema'); ?> - <?php echo htmlspecialchars(__('app_title')); ?></title>
  <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg?v=2">
  <link rel="apple-touch-icon" href="assets/img/favicon.png?v=2">
  <link rel="stylesheet" href="assets/css/style.css?v=24">
  <script src="assets/js/theme.js?v=8"></script>
  <style>
    .logs-wrapper {
      max-width: 1200px;
      margin: 0 auto;
    }
    .log-section-card {
      background: var(--card-bg, #ffffff);
      border: 1px solid var(--border-color, #e2e8f0);
      border-radius: 12px;
      padding: 24px;
      margin-bottom: 24px;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }
    .log-header-flex {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 18px;
      flex-wrap: wrap;
      gap: 12px;
    }
    .log-controls-bar {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
    }
    .log-data-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.88rem;
    }
    .log-data-table th, .log-data-table td {
      padding: 10px 14px;
      text-align: left;
      border-bottom: 1px solid var(--border-color, #e2e8f0);
    }
    .log-data-table th {
      background: var(--input-bg, #f8fafc);
      font-weight: 600;
      color: var(--text-muted, #64748b);
    }
    .badge-status {
      display: inline-block;
      padding: 3px 8px;
      border-radius: 6px;
      font-size: 0.75rem;
      font-weight: 600;
      text-transform: uppercase;
    }
    .badge-connected { background: #dcfce7; color: #166534; }
    .badge-disconnected { background: #fef3c7; color: #92400e; }
    .badge-power_cut { background: #fee2e2; color: #991b1b; }
    .console-terminal {
      background: #0f172a;
      color: #e2e8f0;
      font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
      font-size: 0.82rem;
      padding: 16px;
      border-radius: 8px;
      max-height: 480px;
      overflow-y: auto;
      line-height: 1.55;
    }
    .console-row {
      padding: 4px 0;
      border-bottom: 1px solid rgba(255,255,255,0.06);
      display: flex;
      gap: 12px;
    }
    .console-time { color: #64748b; white-space: nowrap; }
    .console-level-INFO { color: #38bdf8; font-weight: bold; }
    .console-level-WARN { color: #fbbf24; font-weight: bold; }
    .console-level-ERROR { color: #f87171; font-weight: bold; }
    .console-body { flex: 1; word-break: break-word; }
    @media (max-width: 768px) {
      .console-row {
        flex-wrap: wrap;
        row-gap: 3px;
        column-gap: 10px;
        padding: 7px 0;
      }
      .console-body {
        flex: 0 0 100%;
        width: 100%;
        word-break: break-word;
      }
    }
    .toggle-live-label {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 0.85rem;
      font-weight: 600;
      cursor: pointer;
      padding: 6px 12px;
      border-radius: 6px;
      background: var(--input-bg, #f8fafc);
      border: 1px solid var(--border-color, #e2e8f0);
      user-select: none;
    }
    .pulse-dot {
      display: inline-block;
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: #94a3b8;
    }
    .pulse-dot.active {
      background: #22c55e;
      box-shadow: 0 0 8px #22c55e;
    }
  </style>
</head>
<body class="bg-blue-50 text-slate-900 min-h-screen p-3 md:p-8">
  <div class="logs-wrapper">

    <header class="dashboard-header mb-6">
      <div class="header-brand-wrap">
        <a href="index.php" class="header-brand-logo" title="<?php echo htmlspecialchars(__('app_title')); ?>">
          <?php echo get_foobot_logo_svg(44); ?>
        </a>
        <div>
          <h1><?php echo htmlspecialchars(__('nav_logs') ?: 'Log di Sistema'); ?></h1>
          <p class="card-subtext"><?php echo htmlspecialchars($is_admin ? __('logs_sub_admin') : __('logs_sub_user')); ?></p>
        </div>
      </div>
      <div class="header-right">
        <?php render_main_nav('logs', $username); ?>
      </div>
    </header>

    <?php if (count($available_foobots) > 1): ?>
    <!-- Selettore / Filtro Multi-Foobot nei Log -->
    <div class="mb-4 flex flex-wrap items-center gap-2" style="background:var(--input-bg); padding:6px 14px; border-radius:12px; border:1px solid var(--border-color); margin-bottom:20px;">
      <span style="font-size:0.75rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.5px; margin-right:4px;"><?php echo htmlspecialchars(__('logs_filter_sensor')); ?></span>
      <a href="logs.php" 
         class="nav-link" 
         style="padding:4px 10px; border-radius:8px; font-size:0.82rem; font-weight:600; text-decoration:none; <?php echo $filter_uuid === '' ? 'background:var(--accent-blue); color:#fff;' : 'color:var(--text-color);'; ?>">
        <?php echo htmlspecialchars(__('logs_all_sensors')); ?>
      </a>
      <?php foreach ($available_foobots as $fb): 
        $is_curr = ($fb['device_uuid'] === $filter_uuid);
      ?>
      <a href="?device=<?php echo urlencode($fb['device_uuid']); ?>" 
         class="nav-link" 
         style="padding:4px 10px; border-radius:8px; font-size:0.82rem; font-weight:600; text-decoration:none; display:inline-flex; align-items:center; gap:6px; <?php echo $is_curr ? 'background:var(--accent-blue); color:#fff;' : 'color:var(--text-color);'; ?>">
        <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:<?php echo $fb['online'] ? '#10b981' : '#94a3b8'; ?>;"></span>
        <span><?php echo htmlspecialchars($fb['device_name']); ?></span>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($msg)): ?>
      <div class="alert alert-success" style="margin-bottom: 20px;"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
      <div class="alert alert-danger" style="margin-bottom: 20px;"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- SEZIONE 1: EVENTI DI CONNESSIONE E BLACKOUT -->
    <section class="log-section-card">
      <div class="log-header-flex">
        <div>
          <h2 style="font-size: 1.25rem; margin: 0 0 4px 0; font-weight: 700;"><?php echo htmlspecialchars(__('logs_conn_title')); ?></h2>
          <p style="margin: 0; font-size: 0.85rem; color: var(--text-muted, #64748b);">
            <?php echo htmlspecialchars(__('logs_conn_desc')); ?>
          </p>
        </div>
        <div class="log-controls-bar">
          <button type="button" class="btn-secondary" style="padding: 6px 12px; font-size: 0.85rem;" onclick="refreshConnectionLogs();"><?php echo htmlspecialchars(__('logs_btn_refresh')); ?></button>
          <?php if ($is_admin && !empty($conn_logs)): ?>
          <form method="POST" onsubmit="return confirm(<?php echo htmlspecialchars(json_encode(__('logs_confirm_clear_conn')), ENT_QUOTES, 'UTF-8'); ?>);" style="margin: 0;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="action" value="clear_conn_logs">
            <button type="submit" class="btn-danger" style="padding: 6px 12px; font-size: 0.85rem; background: #ef4444; color: #fff; border: none; border-radius: 6px; cursor: pointer;"><?php echo htmlspecialchars(__('logs_btn_clear')); ?></button>
          </form>
          <?php endif; ?>
        </div>
      </div>

      <div id="connLogsContainer">
        <p id="emptyConnMsg" style="color: var(--text-muted, #64748b); font-size: 0.9rem; padding: 12px 0; <?php echo empty($conn_logs) ? '' : 'display:none;'; ?>"><?php echo htmlspecialchars(__('logs_empty_conn')); ?></p>
        <div id="connLogsTableWrap" style="overflow-x: auto; <?php echo empty($conn_logs) ? 'display:none;' : ''; ?>">
          <table class="log-data-table">
            <thead>
              <tr>
                <th><?php echo htmlspecialchars(__('logs_th_datetime')); ?></th>
                <th><?php echo htmlspecialchars(__('logs_th_device')); ?></th>
                <th><?php echo htmlspecialchars(__('logs_th_status')); ?></th>
                <th><?php echo htmlspecialchars(__('logs_th_details')); ?></th>
              </tr>
            </thead>
            <tbody id="connLogsTbody">
              <?php foreach ($conn_logs as $log): ?>
                <tr>
                  <td style="white-space: nowrap;"><?php echo htmlspecialchars(format_log_datetime($log['timestamp'], $current_lang)); ?></td>
                  <td><code><?php echo htmlspecialchars($log['device_uuid']); ?></code></td>
                  <td>
                    <?php
                    $ev = htmlspecialchars($log['event_type']);
                    $label = $ev;
                    if ($ev === 'connected') $label = __('logs_status_connected');
                    elseif ($ev === 'disconnected') $label = __('logs_status_disconnected');
                    elseif ($ev === 'power_cut') $label = __('logs_status_power_cut');
                    ?>
                    <span class="badge-status badge-<?php echo $ev; ?>"><?php echo htmlspecialchars($label); ?></span>
                  </td>
                  <td><?php echo htmlspecialchars(translate_log_text($log['details'], $current_lang)); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <div id="connLastUpdatedNotice" style="font-size: 0.75rem; color: var(--text-muted, #64748b); margin-top: 8px; text-align: right;">
        <?php echo htmlspecialchars(__('logs_last_check')); ?> <?php echo htmlspecialchars(format_log_datetime(time(), $current_lang)); ?>
      </div>
    </section>

    <?php if ($is_admin): ?>
    <!-- SEZIONE 2: CONSOLE DIAGNOSTICA DETTAGLIATA DEL BRIDGE (RISERVATA AGLI AMMINISTRATORI) -->
    <section class="log-section-card">
      <div class="log-header-flex">
        <div>
          <h2 style="font-size: 1.25rem; margin: 0 0 4px 0; font-weight: 700;"><?php echo htmlspecialchars(__('logs_bridge_title')); ?></h2>
          <p style="margin: 0; font-size: 0.85rem; color: var(--text-muted, #64748b);">
            <?php echo htmlspecialchars(__('logs_bridge_desc')); ?>
          </p>
        </div>

        <div class="log-controls-bar">
          <!-- Toggle Attiva / Disattiva Ricezione Log -->
          <form method="POST" style="margin: 0; display: inline-flex; align-items: center;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="action" value="toggle_logging">
            <input type="hidden" name="enable_logging" value="<?php echo $logging_enabled ? '0' : '1'; ?>">
            <button type="submit" class="btn-secondary" style="padding: 6px 12px; font-size: 0.85rem; border-color: <?php echo $logging_enabled ? '#22c55e' : '#cbd5e1'; ?>;">
              <?php if ($logging_enabled): ?>
                <span style="color: #16a34a; font-weight: bold;"><?php echo htmlspecialchars(__('logs_btn_active')); ?></span> <?php echo htmlspecialchars(__('logs_click_to_disable')); ?>
              <?php else: ?>
                <span style="color: #64748b;"><?php echo htmlspecialchars(__('logs_btn_inactive')); ?></span> <?php echo htmlspecialchars(__('logs_click_to_enable')); ?>
              <?php endif; ?>
            </button>
          </form>

          <!-- Toggle Auto-Aggiornamento Live ogni minuto -->
          <label class="toggle-live-label" title="<?php echo htmlspecialchars(__('logs_live_toggle_title')); ?>">
            <input type="checkbox" id="liveAutoRefreshToggle" onchange="toggleAutoRefresh(this.checked)" style="margin: 0;">
            <span class="pulse-dot" id="livePulseDot"></span>
            <span><?php echo htmlspecialchars(__('logs_live_label')); ?></span>
          </label>

          <!-- Pulsante Aggiornamento Semplice Manuale -->
          <button type="button" class="btn-secondary" style="padding: 6px 12px; font-size: 0.85rem;" onclick="refreshBridgeLogs();"><?php echo htmlspecialchars(__('logs_btn_refresh_logs')); ?></button>

          <!-- Pulsante Svuota Log Console -->
          <?php if (!empty($bridge_logs)): ?>
          <form method="POST" onsubmit="return confirm(<?php echo htmlspecialchars(json_encode(__('logs_confirm_clear_console')), ENT_QUOTES, 'UTF-8'); ?>);" style="margin: 0;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="action" value="clear_bridge_logs">
            <button type="submit" class="btn-danger" style="padding: 6px 12px; font-size: 0.85rem; background: #ef4444; color: #fff; border: none; border-radius: 6px; cursor: pointer;"><?php echo htmlspecialchars(__('logs_btn_clear_console')); ?></button>
          </form>
          <?php endif; ?>
        </div>
      </div>

      <div class="console-terminal" id="consoleBox">
        <?php if (empty($bridge_logs)): ?>
          <div id="consoleEmptyText" style="text-align: center; padding: 36px; color: #94a3b8;">
            <?php if ($logging_enabled): ?>
              <?php echo htmlspecialchars(__('logs_waiting_data')); ?>
            <?php else: ?>
              <?php echo htmlspecialchars(__('logs_reception_disabled')); ?>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <?php foreach ($bridge_logs as $bl): ?>
            <div class="console-row">
              <span class="console-time">[<?php echo htmlspecialchars(format_log_datetime((int)$bl['timestamp'], $current_lang)); ?>]</span>
              <span class="console-level-<?php echo htmlspecialchars($bl['log_level']); ?>">[<?php echo htmlspecialchars($bl['log_level']); ?>]</span>
              <span class="console-body"><?php echo htmlspecialchars(translate_log_text($bl['message'], $current_lang)); ?></span>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <div id="lastUpdatedNotice" style="font-size: 0.75rem; color: var(--text-muted, #64748b); margin-top: 8px; text-align: right;">
        <?php echo htmlspecialchars(__('logs_last_check')); ?> <?php echo htmlspecialchars(format_log_datetime(time(), $current_lang)); ?>
      </div>
    </section>
    <?php endif; ?>

  </div>

  <script>
    let autoRefreshInterval = null;

    function toggleAutoRefresh(enabled) {
      localStorage.setItem('foobot_logs_autorefresh', enabled ? '1' : '0');
      const dot = document.getElementById('livePulseDot');
      if (enabled) {
        dot.classList.add('active');
        if (autoRefreshInterval) clearInterval(autoRefreshInterval);
        autoRefreshInterval = setInterval(refreshLogsDataNow, 60000); // 60 secondi
      } else {
        dot.classList.remove('active');
        if (autoRefreshInterval) {
          clearInterval(autoRefreshInterval);
          autoRefreshInterval = null;
        }
      }
    }

    const appLocale = (document.documentElement.lang || 'it').toLowerCase();
    const statusLabels = {
      'connected': <?php echo json_encode(__('logs_status_connected', 'Connesso')); ?>,
      'disconnected': <?php echo json_encode(__('logs_status_disconnected', 'Disconnesso')); ?>,
      'power_cut': <?php echo json_encode(__('logs_status_power_cut', 'Blackout / Assenza Segnale')); ?>
    };
    const txtLastCheck = <?php echo json_encode(__('logs_last_check', 'Ultimo controllo: ')); ?>;
    const txtRefreshError = <?php echo json_encode(__('logs_refresh_error', 'Errore aggiornamento: ')); ?>;
    const txtUpdating = <?php echo json_encode(__('logs_updating', 'Aggiornamento in corso...')); ?>;

    function translateLogText(text) {
      if (!text) return '';
      // Normalizzazione: rimuove 'Oracle' dai riferimenti a Broker Bridge per compatibilità universale
      text = text.replace(/Broker Bridge Oracle/gi, 'Broker Bridge');
      const lang = appLocale;
      if (lang === 'it') return text;

      // Pattern: Evento stato dispositivo [UUID]: event_type (details)
      const devEvMatch = text.match(/^Evento stato dispositivo\s*\[([^\]]+)\]:\s*([^\s(]+)(?:\s*\((.*)\))?$/i);
      if (devEvMatch) {
        const uuid = devEvMatch[1];
        const ev = devEvMatch[2];
        const details = devEvMatch[3] || '';
        const transDetails = translateLogText(details);
        const evName = (statusLabels && statusLabels[ev]) ? statusLabels[ev] : ev;
        if (lang === 'en') return `Device status event [${uuid}]: ${evName} (${transDetails})`;
        if (lang === 'fr') return `Événement d'état du capteur [${uuid}] : ${evName} (${transDetails})`;
        if (lang === 'de') return `Gerätestatus-Ereignis [${uuid}]: ${evName} (${transDetails})`;
        if (lang === 'es') return `Evento de estado del sensor [${uuid}]: ${evName} (${transDetails})`;
        return `Device status event [${uuid}]: ${evName} (${transDetails})`;
      }

      // Pattern: Nuova connessione TCP da ...
      const tcpMatch = text.match(/^Nuova connessione TCP da\s+(.+)$/i);
      if (tcpMatch) {
        const from = tcpMatch[1];
        if (lang === 'en') return `New TCP connection from ${from}`;
        if (lang === 'fr') return `Nouvelle connexion TCP depuis ${from}`;
        if (lang === 'de') return `Neue TCP-Verbindung von ${from}`;
        if (lang === 'es') return `Nueva conexión TCP desde ${from}`;
      }

      // Pattern: Connessione chiusa da ...
      const closeMatch = text.match(/^(?:Connessione chiusa da|Chiusura connessione socket TCP da)\s+(.+)$/i);
      if (closeMatch) {
        const from = closeMatch[1];
        if (lang === 'en') return `Connection closed by ${from}`;
        if (lang === 'fr') return `Connexion fermée par ${from}`;
        if (lang === 'de') return `Verbindung geschlossen von ${from}`;
        if (lang === 'es') return `Conexión cerrada por ${from}`;
      }

      // Pattern: Ricevuto pacchetto telemetria sensori (...)
      const telPktMatch = text.match(/^Ricevuto pacchetto telemetria sensori(?:\s*\(([^)]+)\))?$/i);
      if (telPktMatch) {
        const size = telPktMatch[1] || '';
        if (lang === 'en') return size ? `Sensor telemetry packet received (${size})` : 'Sensor telemetry packet received';
        if (lang === 'fr') return size ? `Paquet de télémétrie des capteurs reçu (${size})` : 'Paquet de télémétrie des capteurs reçu';
        if (lang === 'de') return size ? `Sensortelemetriepaket empfangen (${size})` : 'Sensortelemetriepaket empfangen';
        if (lang === 'es') return size ? `Paquete de telemetría de sensores recibido (${size})` : 'Paquete de telemetría de sensores recibido';
        return size ? `Sensor telemetry packet received (${size})` : 'Sensor telemetry packet received';
      }

      // Pattern: Dati calibrati [UUID]: ... -> Inoltro a cPanel completato [HTTP ... - ID #...]
      const calMatch = text.match(/^Dati calibrati\s*\[([^\]]+)\]:\s*(.+?)\s*->\s*Inoltro a cPanel completato\s*\[HTTP (\d+) - ID #(\d+)\]$/i);
      if (calMatch) {
        const uuid = calMatch[1];
        const telemetry = calMatch[2];
        const http = calMatch[3];
        const id = calMatch[4];
        if (lang === 'en') return `Calibrated data [${uuid}]: ${telemetry} -> Forwarding to cPanel completed [HTTP ${http} - ID #${id}]`;
        if (lang === 'fr') return `Données calibrées [${uuid}] : ${telemetry} -> Transfert vers cPanel terminé [HTTP ${http} - ID #${id}]`;
        if (lang === 'de') return `Kalibrierte Daten [${uuid}]: ${telemetry} -> Weiterleitung an cPanel abgeschlossen [HTTP ${http} - ID #${id}]`;
        if (lang === 'es') return `Datos calibrados [${uuid}]: ${telemetry} -> Reenvío a cPanel completado [HTTP ${http} - ID #${id}]`;
        return `Calibrated data [${uuid}]: ${telemetry} -> Forwarding to cPanel completed [HTTP ${http} - ID #${id}]`;
      }

      // Pattern: Autenticazione Foobot MQTT CONNECT riuscita: ClientID=... (...)
      const authMatch = text.match(/^Autenticazione Foobot MQTT CONNECT riuscita:\s*ClientID=([^\s]+)\s*\(([^)]+)\)$/i);
      if (authMatch) {
        const clientId = authMatch[1];
        const ip = authMatch[2];
        if (lang === 'en') return `Foobot MQTT CONNECT authentication successful: ClientID=${clientId} (${ip})`;
        if (lang === 'fr') return `Authentification Foobot MQTT CONNECT réussie : ClientID=${clientId} (${ip})`;
        if (lang === 'de') return `Foobot MQTT CONNECT-Authentifizierung erfolgreich: ClientID=${clientId} (${ip})`;
        if (lang === 'es') return `Autenticación Foobot MQTT CONNECT exitosa: ClientID=${clientId} (${ip})`;
        return `Foobot MQTT CONNECT authentication successful: ClientID=${clientId} (${ip})`;
      }

      // Pattern: Ricevuto pacchetto ...
      const pktMatch = text.match(/^Ricevuto pacchetto (?:telemetria|dati) da\s+(.+)$/i);
      if (pktMatch) {
        const from = pktMatch[1];
        if (lang === 'en') return `Telemetry data packet received from ${from}`;
        if (lang === 'fr') return `Paquet de données reçu de ${from}`;
        if (lang === 'de') return `Telemetriedatenpaket empfangen von ${from}`;
        if (lang === 'es') return `Paquete de telemetría recibido desde ${from}`;
      }

      const dict = {
        'Connessione stabilita con il Broker Bridge': {
          'en': 'Connection established with Broker Bridge',
          'fr': 'Connexion établie avec le Broker Bridge',
          'de': 'Verbindung mit Broker Bridge hergestellt',
          'es': 'Conexión establecida con el Broker Bridge'
        },
        'Interruzione linea o alimentazione rilevata (assenza segnale >10 min)': {
          'en': 'Line or power outage detected (no signal >10 min)',
          'fr': "Coupure de ligne ou d'alimentation détectée (absence de signal >10 min)",
          'de': 'Leitungs- oder Stromausfall erkannt (kein Signal >10 Min.)',
          'es': 'Interrupción de línea o energía detectada (sin señal >10 min)'
        },
        'Interruzione linea o alimentazione rilevata (assenza segnale >12 min)': {
          'en': 'Line or power outage detected (no signal >12 min)',
          'fr': "Coupure de ligne ou d'alimentation détectée (absence de signal >12 min)",
          'de': 'Leitungs- oder Stromausfall erkannt (kein Signal >12 Min.)',
          'es': 'Interrupción de línea o energía detectada (sin señal >12 min)'
        },
        'Sensore online e invio telemetria attivo': {
          'en': 'Sensor online and telemetry streaming active',
          'fr': 'Capteur en ligne et transmission de télémétrie active',
          'de': 'Sensor online und Telemetrieübertragung aktiv',
          'es': 'Sensor en línea y transmisión de telemetría activa'
        },
        'Pacchetto dati ricevuto regolarmente': {
          'en': 'Data packet received regularly',
          'fr': 'Paquet de données reçu régulièrement',
          'de': 'Datenpaket ordnungsgemäß empfangen',
          'es': 'Paquete de datos recibido regularmente'
        },
        'Caduta connessione socket TCP': {
          'en': 'TCP socket connection drop',
          'fr': 'Perte de connexion socket TCP',
          'de': 'TCP-Socket-Verbindung getrennt',
          'es': 'Pérdida de conexión socket TCP'
        },
        'Caduta connessione Wi-Fi / Rete': {
          'en': 'Wi-Fi / Network connection drop',
          'fr': 'Perte de connexion Wi-Fi / Réseau',
          'de': 'Wi-Fi- / Netzwerkverbindungsverlust',
          'es': 'Pérdida de conexión Wi-Fi / Red'
        },
        'Assenza segnale o corrente (>12 min)': {
          'en': 'No signal or power cut (>12 min)',
          'fr': 'Absence de signal ou coupure de courant (>12 min)',
          'de': 'Kein Signal oder Stromausfall (>12 Min.)',
          'es': 'Sin señal o corte de energía (>12 min)'
        },
        'Chiusura connessione dal dispositivo': {
          'en': 'Connection closed by device',
          'fr': 'Connexion fermée par le capteur',
          'de': 'Verbindung vom Gerät geschlossen',
          'es': 'Conexión cerrada por el dispositivo'
        },
        'Handshake TLS completato con successo': {
          'en': 'TLS handshake completed successfully',
          'fr': 'Handshake TLS réussi avec succès',
          'de': 'TLS-Handshake erfolgreich abgeschlossen',
          'es': 'Handshake TLS completado con éxito'
        }
      };

      if (dict[text] && dict[text][lang]) {
        return dict[text][lang];
      }

      for (const [k, v] of Object.entries(dict)) {
        if (text.includes(k) && v[lang]) {
          return text.replace(k, v[lang]);
        }
      }

      return text;
    }

    const currentDeviceFilter = <?php echo json_encode($filter_uuid); ?>;

    // Aggiornamento indipendente SEZIONE 1: Solo Eventi di Connessione e Blackout
    async function refreshConnectionLogs() {
      const notice = document.getElementById('connLastUpdatedNotice');
      if (notice) notice.innerText = txtUpdating;
      try {
        let url = 'logs.php?ajax=conn';
        if (currentDeviceFilter) url += '&device=' + encodeURIComponent(currentDeviceFilter);
        const response = await fetch(url);
        if (!response.ok) throw new Error('HTTP ' + response.status);
        const data = await response.json();
        if (data.status === 'ok' && Array.isArray(data.connection_logs)) {
          const tbody = document.getElementById('connLogsTbody');
          const emptyMsg = document.getElementById('emptyConnMsg');
          const tableWrap = document.getElementById('connLogsTableWrap');

          if (data.connection_logs.length === 0) {
            if (emptyMsg) emptyMsg.style.display = 'block';
            if (tableWrap) tableWrap.style.display = 'none';
            if (tbody) tbody.innerHTML = '';
          } else {
            let tbHtml = '';
            for (const cl of data.connection_logs) {
              const d = new Date(cl.timestamp * 1000);
              const dateStr = d.toLocaleDateString(appLocale, { day: '2-digit', month: '2-digit', year: 'numeric' }) + ' ' + d.toLocaleTimeString(appLocale, { hour: '2-digit', minute: '2-digit', second: '2-digit' });
              const ev = escapeHtml(cl.event_type || '');
              const label = statusLabels[ev] || ev;
              const details = translateLogText(cl.details || '');

              tbHtml += `<tr>
                <td style="white-space:nowrap;">${dateStr}</td>
                <td><code>${escapeHtml(cl.device_uuid || '')}</code></td>
                <td><span class="badge-status badge-${ev}">${escapeHtml(label)}</span></td>
                <td>${escapeHtml(details)}</td>
              </tr>`;
            }
            if (tbody) tbody.innerHTML = tbHtml;
            if (emptyMsg) emptyMsg.style.display = 'none';
            if (tableWrap) tableWrap.style.display = 'block';
          }
          if (notice) notice.innerText = txtLastCheck + (new Date().toLocaleTimeString(appLocale, { hour: '2-digit', minute: '2-digit', second: '2-digit' }));
        }
      } catch (err) {
        if (notice) notice.innerText = txtRefreshError + err.message;
      }
    }

    // Aggiornamento indipendente SEZIONE 2: Solo Console Diagnostica del Bridge
    async function refreshBridgeLogs() {
      const notice = document.getElementById('lastUpdatedNotice');
      if (notice) notice.innerText = txtUpdating;
      try {
        let url = 'logs.php?ajax=bridge';
        if (currentDeviceFilter) url += '&device=' + encodeURIComponent(currentDeviceFilter);
        const response = await fetch(url);
        if (!response.ok) throw new Error('HTTP ' + response.status);
        const data = await response.json();
        if (data.status === 'ok' && Array.isArray(data.bridge_logs)) {
          const consoleBox = document.getElementById('consoleBox');
          if (consoleBox) {
            if (data.bridge_logs.length === 0) {
              consoleBox.innerHTML = '<div style="text-align:center; padding:36px; color:#94a3b8;">' + <?php echo json_encode(__('logs_console_empty')); ?> + '</div>';
            } else {
              let html = '';
              for (const bl of data.bridge_logs) {
                const d = new Date(bl.timestamp * 1000);
                const fullDateTimeStr = d.toLocaleDateString(appLocale, { day: '2-digit', month: '2-digit', year: 'numeric' }) + ' ' + d.toLocaleTimeString(appLocale, { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                const lvl = escapeHtml(bl.log_level || 'INFO');
                const msg = escapeHtml(translateLogText(bl.message || ''));
                html += `<div class="console-row">
                  <span class="console-time">[${fullDateTimeStr}]</span>
                  <span class="console-level-${lvl}">[${lvl}]</span>
                  <span class="console-body">${msg}</span>
                </div>`;
              }
              consoleBox.innerHTML = html;
            }
          }
          if (notice) notice.innerText = txtLastCheck + (new Date().toLocaleTimeString(appLocale, { hour: '2-digit', minute: '2-digit', second: '2-digit' }));
        }
      } catch (err) {
        if (notice) notice.innerText = txtRefreshError + err.message;
      }
    }

    // Funzione cumulativa (utilizzata solo dall'intervallo Live di sfondo se attivo)
    function refreshLogsDataNow() {
      refreshBridgeLogs();
    }

    function escapeHtml(text) {
      const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
      return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // Ripristina preferenza auto-refresh del bridge (non distrugge i log già renderizzati da PHP)
    window.addEventListener('DOMContentLoaded', () => {
      const saved = localStorage.getItem('foobot_logs_autorefresh');
      const toggle = document.getElementById('liveAutoRefreshToggle');
      if (saved === '1' && toggle) {
        toggle.checked = true;
        toggleAutoRefresh(true);
      }
    });
  </script>
</body>
</html>
