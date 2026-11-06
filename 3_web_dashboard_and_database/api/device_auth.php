<?php
/**
 * API per l'Accoppiamento e l'Autenticazione Sicura dell'Applicazione Mobile (PWA)
 * - Riscatto del ticket monouso (QR Code) con scadenza a 10 minuti
 * - Verifica integrità e validità del Device Token
 * - Protezione attiva Anti-Brute Force con blocco IP
 */

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/common.php';
require_once dirname(__DIR__) . '/auth.php';
$i18n_file = dirname(__DIR__) . '/i18n.php';
if (file_exists($i18n_file)) {
    require_once $i18n_file;
}

$pdo = api_db();
$client_ip = get_client_ip();

// Decodifica sempre il body JSON se inviato dal client (fetch application/json)
$raw_input = file_get_contents('php://input');
if ($raw_input) {
    $json_data = json_decode($raw_input, true);
    if (is_array($json_data)) {
        $_POST = array_merge($_POST, $json_data);
    }
}

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// Se la sessione web è già autenticata, pulisci eventuali blocchi IP e bypassa il rate-limit
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
$is_user_authed = is_logged_in();
if ($is_user_authed) {
    clear_login_attempts($pdo, $client_ip);
} else {
    // Controllo blocco Anti-Brute Force per chiamate anonime/non autenticate
    $locked_seconds = check_brute_force_lock($pdo, $client_ip);
    if ($locked_seconds > 0) {
        http_response_code(429);
        echo json_encode([
            'status'         => 'error',
            'error_code'     => 'ip_locked',
            'message'        => sprintf('Troppi tentativi errati. Indirizzo IP temporaneamente bloccato per motivi di sicurezza per altri %d secondi.', $locked_seconds),
            'locked_seconds' => $locked_seconds
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}

switch ($action) {
    case 'claim_ticket':
        // Riscatto ticket QR Code monouso inviato dall'App Mobile
        $ticket_code = trim((string)($_POST['ticket_code'] ?? ($_POST['ticket'] ?? ($_GET['ticket_code'] ?? ($_GET['ticket'] ?? '')))));
        $platform = trim((string)($_POST['platform'] ?? ($_GET['platform'] ?? 'Mobile Web PWA')));
        $fingerprint = trim((string)($_POST['fingerprint'] ?? ($_GET['fingerprint'] ?? '')));
        $device_name = trim((string)($_POST['device_name'] ?? ($_GET['device_name'] ?? '')));
        $device_serial = trim((string)($_POST['device_serial'] ?? ($_GET['device_serial'] ?? '')));
        $app_mode = trim((string)($_POST['app_mode'] ?? ($_GET['app_mode'] ?? 'browser')));
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        if ($ticket_code === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Codice ticket di accoppiamento non fornito. Inserisci il codice visualizzato sul sito (es. NF-A1B2C3).']);
            exit;
        }

        $claim_res = claim_pairing_ticket($pdo, $ticket_code, $fingerprint, $platform, $client_ip, $device_serial, $app_mode, $user_agent);

        if (!$claim_res['success']) {
            // Tentativo fallito: registra nei tentativi di brute force (max 10 tentativi per i ticket, lockout 10 min)
            $remaining = record_failed_login($pdo, $client_ip, 10, 600);
            http_response_code(403);
            echo json_encode([
                'status'             => 'error',
                'message'            => $claim_res['error'],
                'attempts_remaining' => $remaining
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Accoppiamento riuscito: azzera i tentativi falliti per questo IP
        clear_login_attempts($pdo, $client_ip);

        // Se l'app ha fornito un nome personalizzato, aggiornalo sul record
        if ($device_name !== '') {
            $hash = hash('sha256', $claim_res['device_token']);
            $upd = $pdo->prepare("UPDATE foobot_authorized_devices SET device_name = ? WHERE device_token_hash = ?");
            $upd->execute([$device_name, $hash]);
        }

        $all_devices = get_available_foobot_devices($pdo);

        $u_name = '';
        if (!empty($claim_res['user_id'])) {
            $u_stmt = $pdo->prepare("SELECT username FROM foobot_users WHERE id = ?");
            $u_stmt->execute([(int)$claim_res['user_id']]);
            $u_row = $u_stmt->fetch();
            if ($u_row) $u_name = $u_row['username'];
        }
        $resolved_claim_user = api_resolve_registered_username($pdo, $u_name);

        echo json_encode([
            'status'                 => 'ok',
            'success'                => true,
            'message'                => 'Dispositivo autorizzato con successo!',
            'device_token'           => $claim_res['device_token'],
            'device_name'            => $device_name ?: $claim_res['device_name'],
            'device_serial'          => $claim_res['device_serial'] ?? '',
            'device_id'              => $claim_res['device_id'] ?? null,
            'device_reg_id'          => $claim_res['device_id'] ?? null,
            'settings_device_reg_id' => function_exists('__') ? __('settings_device_reg_id', 'ID Dispositivo App:') : 'ID Dispositivo App:',
            'i18n_labels'            => [
                'it' => ['settings_device_reg_id' => 'ID Dispositivo App:'],
                'en' => ['settings_device_reg_id' => 'App Device ID:']
            ],
            'app_mode'               => $app_mode,
            'user'                   => $resolved_claim_user ?: 'Utente',
            'connected_user'         => $resolved_claim_user ?: 'Utente',
            'user_id'                => $claim_res['user_id'],
            'devices'                => $all_devices,
            'default_uuid'           => $all_devices[0]['device_uuid'] ?? 'foobot',
            'temp_unit'              => get_temperature_unit($pdo, $claim_res['user_id']),
            'dashboard_lang'         => api_resolve_user_lang($pdo, $claim_res['user_id'] ?? null),
            'server_time'            => time()
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;

    case 'check_token':
        // Verifica validità del Device Token memorizzato nell'App
        $headers = function_exists('getallheaders') ? array_change_key_case(getallheaders(), CASE_LOWER) : [];
        $token = $headers['x-device-token'] ?? ($_POST['device_token'] ?? ($_GET['device_token'] ?? ''));
        $app_mode = $headers['x-app-mode'] ?? ($_POST['app_mode'] ?? ($_GET['app_mode'] ?? null));
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        if ($token === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Token dispositivo non fornito.']);
            exit;
        }

        $dev_info = verify_device_token($pdo, $token, $app_mode, $user_agent);
        if (!$dev_info) {
            http_response_code(401);
            echo json_encode([
                'status'  => 'revoked',
                'valid'   => false,
                'message' => 'Il dispositivo è stato revocato o il token non è più valido. Ricollega l\'applicazione tramite QR Code.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $all_devices = get_available_foobot_devices($pdo);
        $resolved_check_user = api_resolve_registered_username($pdo, $dev_info['username'] ?? null);

        echo json_encode([
            'status'                 => 'ok',
            'valid'                  => true,
            'device_id'              => $dev_info['id'],
            'device_reg_id'          => $dev_info['id'],
            'settings_device_reg_id' => function_exists('__') ? __('settings_device_reg_id', 'ID Dispositivo App:') : 'ID Dispositivo App:',
            'i18n_labels'            => [
                'it' => ['settings_device_reg_id' => 'ID Dispositivo App:'],
                'en' => ['settings_device_reg_id' => 'App Device ID:']
            ],
            'device_name'            => $dev_info['device_name'],
            'device_serial'          => $dev_info['device_serial'] ?? '',
            'app_mode'               => $dev_info['app_mode'] ?? 'browser',
            'user'                   => $resolved_check_user,
            'connected_user'         => $resolved_check_user,
            'is_admin'               => ($dev_info['role'] === 'admin'),
            'dashboard_lang'         => api_resolve_user_lang($pdo, $dev_info['user_id'] ?? null),
            'devices'                => $all_devices,
            'default_uuid'           => $all_devices[0]['device_uuid'] ?? 'foobot'
        ], JSON_UNESCAPED_UNICODE);
        exit;

    case 'login':
        // Autenticazione diretta dall'App Mobile PWA con Nome Utente / Email e Password
        $identity = trim((string)($_POST['identity'] ?? ($_POST['username'] ?? '')));
        $password = (string)($_POST['password'] ?? '');
        $device_name = trim((string)($_POST['device_name'] ?? 'PWA Mobile'));
        $device_serial = trim((string)($_POST['device_serial'] ?? ''));
        $app_mode = trim((string)($_POST['app_mode'] ?? 'browser'));
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        if ($identity === '' || $password === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Inserisci nome utente o email e la relativa password.']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT * FROM foobot_users WHERE username = ? OR (email != '' AND email = ?) LIMIT 1");
        $stmt->execute([$identity, $identity]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            clear_login_attempts($pdo, $client_ip);
            $ticket = create_device_pairing_ticket($pdo, (int)$user['id'], $device_name);
            if ($ticket) {
                $claim = claim_pairing_ticket($pdo, $ticket['ticket_code'], 'pwa_login', 'PWA App', $client_ip, $device_serial, $app_mode, $user_agent);
                if ($claim && !empty($claim['success'])) {
                    $all_devices = get_available_foobot_devices($pdo);
                    echo json_encode([
                        'status'                 => 'ok',
                        'success'                => true,
                        'message'                => 'Login e collegamento completati con successo.',
                        'device_token'           => $claim['device_token'],
                        'device_name'            => $device_name,
                        'device_serial'          => $claim['device_serial'] ?? '',
                        'device_id'              => $claim['device_id'] ?? null,
                        'device_reg_id'          => $claim['device_id'] ?? null,
                        'settings_device_reg_id' => function_exists('__') ? __('settings_device_reg_id', 'ID Dispositivo App:') : 'ID Dispositivo App:',
                        'i18n_labels'            => [
                            'it' => ['settings_device_reg_id' => 'ID Dispositivo App:'],
                            'en' => ['settings_device_reg_id' => 'App Device ID:']
                        ],
                        'app_mode'               => $app_mode,
                        'user'                   => $user['username'],
                        'connected_user'         => $user['username'],
                        'devices'                => $all_devices,
                        'default_uuid'           => $all_devices[0]['device_uuid'] ?? 'foobot',
                        'dashboard_lang'         => api_resolve_user_lang($pdo, (int)$user['id']),
                        'temp_unit'              => get_temperature_unit($pdo, (int)$user['id'])
                    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                    exit;
                }
            }
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Errore nella generazione del token per il dispositivo.']);
            exit;
        }

        $left = record_failed_login($pdo, $client_ip, 10, 600);
        http_response_code(401);
        echo json_encode([
            'status'             => 'error',
            'message'            => 'Credenziali non corrette. Verifica username e password.',
            'attempts_remaining' => max(0, $left)
        ], JSON_UNESCAPED_UNICODE);
        exit;

    case 'direct_pair':
        // Abbinamento istantaneo per utenti già autenticati nel browser che aprono l'app dallo stesso telefono o PC
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        if (!is_logged_in()) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Nessuna sessione attiva trovata in questo browser. Accedi prima al pannello web o inserisci il codice di accoppiamento.']);
            exit;
        }
        $uid = get_current_user_id();
        if ($uid <= 0) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Utente di sessione non valido.']);
            exit;
        }

        $device_serial = trim((string)($_POST['device_serial'] ?? ($_GET['device_serial'] ?? '')));
        $app_mode = trim((string)($_POST['app_mode'] ?? ($_GET['app_mode'] ?? 'browser')));
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $label = trim((string)($_POST['device_name'] ?? ($_GET['device_name'] ?? '')));
        if ($label === '') {
            $parsed_dev = parse_device_info($user_agent, $app_mode);
            $u_name = get_current_user_name() ?: 'Utente';
            $label = $parsed_dev['os'] . ' (' . $u_name . ')';
        }

        $ticket = create_device_pairing_ticket($pdo, $uid, $label);
        if (!$ticket) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Impossibile generare il ticket di accoppiamento di sessione.']);
            exit;
        }

        $claim = claim_pairing_ticket($pdo, $ticket['ticket_code'], 'direct_session', 'PWA Same Device', $client_ip, $device_serial, $app_mode, $user_agent);

        if (!$claim || empty($claim['success'])) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => $claim['error'] ?? 'Impossibile completare il pairing istantaneo.']);
            exit;
        }

        // Pulisce eventuali tentativi falliti per questo IP
        clear_login_attempts($pdo, $client_ip);

        $all_devices = get_available_foobot_devices($pdo);

        echo json_encode([
            'status'                 => 'ok',
            'success'                => true,
            'device_token'           => $claim['device_token'],
            'device_name'            => $label,
            'device_serial'          => $claim['device_serial'] ?? '',
            'device_id'              => $claim['device_id'] ?? null,
            'device_reg_id'          => $claim['device_id'] ?? null,
            'settings_device_reg_id' => function_exists('__') ? __('settings_device_reg_id', 'ID Dispositivo App:') : 'ID Dispositivo App:',
            'i18n_labels'            => [
                'it' => ['settings_device_reg_id' => 'ID Dispositivo App:'],
                'en' => ['settings_device_reg_id' => 'App Device ID:']
            ],
            'app_mode'               => $app_mode,
            'user'                   => $u_name ?: 'Utente',
            'connected_user'         => $u_name ?: 'Utente',
            'devices'                => $all_devices,
            'default_uuid'           => $all_devices[0]['device_uuid'] ?? 'foobot',
            'dashboard_lang'         => api_resolve_user_lang($pdo, $uid),
            'temp_unit'              => get_temperature_unit($pdo, $uid)
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;

    case 'update_name':
        // Rinomina un dispositivo autorizzato (via POST AJAX)
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $device_id = (int)($_POST['device_id'] ?? 0);
        $new_name = trim((string)($_POST['device_name'] ?? ''));
        if ($device_id <= 0 || $new_name === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Parametri mancanti per la rinomina del dispositivo.']);
            exit;
        }
        $is_admin = is_admin_user();
        $curr_uid = get_current_user_id();
        if ($curr_uid <= 0 && !$is_admin) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Non autorizzato ad effettuare questa operazione.']);
            exit;
        }
        $res = update_authorized_device_name($pdo, $device_id, $new_name, $curr_uid, $is_admin);
        if ($res) {
            echo json_encode(['status' => 'ok', 'message' => 'Nome del dispositivo aggiornato con successo.']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Impossibile aggiornare il nome del dispositivo.']);
        }
        exit;

    default:
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Azione non valida.']);
        exit;
}
