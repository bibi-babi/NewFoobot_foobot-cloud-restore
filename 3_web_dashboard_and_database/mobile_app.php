<?php
/**
 * Pagina di Gestione App Mobile PWA & Dispositivi Autorizzati
 * Consente a tutti gli utenti (Admin e Utenti standard) di:
 * 1. Conoscere e installare l'App PWA (Android 8+, iOS, Windows, Mac, Linux)
 * 2. Autorizzare nuovi smartphone/desktop tramite QR Code crittografico monouso (scadenza 10 min)
 * 3. Gestire e revocare i propri dispositivi connessi
 * 4. Per gli Amministratori: monitorare e revocare anche i dispositivi degli altri utenti
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/nav.php';

$pdo = require_login();
$current_user = get_current_user_name();
$current_user_id = get_current_user_id();
$is_admin = is_admin_user();
$current_lang = get_current_lang();

// Rilevamento URL base del server PHP attuale (es. https://tuodominio.it o http://localhost/foobot)
$is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
$protocol = $is_https ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script_dir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
$server_base_url = rtrim($protocol . $host . $script_dir, '/');
$pwa_url = $server_base_url . '/app/';

// Ticket di accoppiamento istantaneo per il collegamento trasparente senza schermata di associazione
$direct_pair_ticket = create_device_pairing_ticket($pdo, $current_user_id, 'Web App (' . $current_user . ')');
$direct_pwa_url = $direct_pair_ticket ? ($pwa_url . '?ticket=' . urlencode($direct_pair_ticket['ticket_code'])) : $pwa_url;

$flash_msg = '';
$flash_type = 'success';

// Gestione richiesta AJAX rapida di generazione Ticket Pairing (QR Code)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'generate_pairing_ticket') {
    header('Content-Type: application/json; charset=UTF-8');
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => __('mobile_flash_csrf_error', 'Token di sicurezza CSRF non valido.')]);
        exit;
    }
    $label = trim((string)($_POST['device_name'] ?? 'Mio Smartphone'));
    $ticket = create_device_pairing_ticket($pdo, $current_user_id, $label);
    if ($ticket) {
        $direct_pair_url = $pwa_url . '?ticket=' . urlencode($ticket['ticket_code']);
        $qr_payload = [
            'app'         => 'NewFoobot Air Quality',
            'server_url'  => $server_base_url,
            'ticket_code' => $ticket['ticket_code'],
            'device_name' => $ticket['device_label'],
            'expires_at'  => $ticket['expires_at'],
            'pair_url'    => $direct_pair_url
        ];
        echo json_encode([
            'status'       => 'ok',
            'ticket_code'  => $ticket['ticket_code'],
            'device_name'  => $ticket['device_label'],
            'expires_at'   => $ticket['expires_at'],
            'seconds_left' => 600,
            'qr_payload'   => $direct_pair_url,
            'pair_url'     => $direct_pair_url,
            'pwa_url'      => $pwa_url
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => __('mobile_ticket_gen_err', 'Impossibile generare il seriale di accoppiamento.')]);
    }
    exit;
}

// Gestione cancellazione/revoca dispositivo via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'revoke_device') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $device_id = (int)($_POST['device_id'] ?? 0);
        $ok = revoke_authorized_device($pdo, $device_id, $current_user_id, $is_admin);
        if ($ok) {
            $flash_msg = __('mobile_flash_revoked_success', 'Dispositivo scollegato e revocato con successo.');
            $flash_type = 'success';
        } else {
            $flash_msg = __('mobile_flash_revoked_error', 'Impossibile revocare il dispositivo specificato.');
            $flash_type = 'danger';
        }
    } else {
        $flash_msg = __('mobile_flash_csrf_error', 'Errore di sicurezza CSRF. Riprova.');
        $flash_type = 'danger';
    }
}

// Gestione revoca di tutti i dispositivi dell'utente corrente via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'revoke_all_my_devices') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $count = revoke_all_authorized_devices($pdo, $current_user_id, $current_user_id, $is_admin);
        $flash_msg = sprintf(__('mobile_revoke_all_my_success', 'Tutti i tuoi dispositivi connessi sono stati scollegati con successo (%d).'), $count);
        $flash_type = 'success';
    } else {
        $flash_msg = __('mobile_flash_csrf_error', 'Errore di sicurezza CSRF. Riprova.');
        $flash_type = 'danger';
    }
}

// Gestione revoca di tutti i dispositivi di un utente specifico (Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'revoke_all_user_devices') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $t_uid = (int)($_POST['target_user_id'] ?? 0);
        $t_name = trim((string)($_POST['target_username'] ?? ''));
        $count = revoke_all_authorized_devices($pdo, $t_uid, $current_user_id, $is_admin);
        $flash_msg = sprintf(__('mobile_revoke_all_user_success', 'Tutti i dispositivi dell\'utente %s sono stati scollegati con successo (%d).'), htmlspecialchars($t_name ?: 'utente'), $count);
        $flash_type = 'success';
    } else {
        $flash_msg = __('mobile_flash_csrf_error', 'Errore di sicurezza CSRF. Riprova.');
        $flash_type = 'danger';
    }
}

// Gestione revoca di tutti i dispositivi degli altri utenti (Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'revoke_all_other_devices') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $count = revoke_all_other_authorized_devices($pdo, $current_user_id, $is_admin);
        $flash_msg = sprintf(__('mobile_revoke_all_others_success', 'Tutti i dispositivi degli altri utenti sono stati scollegati con successo (%d).'), $count);
        $flash_type = 'success';
    } else {
        $flash_msg = __('mobile_flash_csrf_error', 'Errore di sicurezza CSRF. Riprova.');
        $flash_type = 'danger';
    }
}

// Gestione rinomina dispositivo via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'rename_device') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $device_id = (int)($_POST['device_id'] ?? 0);
        $new_name = trim((string)($_POST['device_name'] ?? ''));
        if ($device_id > 0 && $new_name !== '') {
            if ($is_admin) {
                $upd = $pdo->prepare("UPDATE foobot_authorized_devices SET device_name = ? WHERE id = ?");
                $upd->execute([$new_name, $device_id]);
                $flash_msg = __('mobile_rename_success', 'Nome del dispositivo aggiornato con successo.');
                $flash_type = 'success';
            } else {
                $upd = $pdo->prepare("UPDATE foobot_authorized_devices SET device_name = ? WHERE id = ? AND user_id = ?");
                $upd->execute([$new_name, $device_id, $current_user_id]);
                if ($upd->rowCount() > 0) {
                    $flash_msg = __('mobile_rename_success', 'Nome del dispositivo aggiornato con successo.');
                    $flash_type = 'success';
                } else {
                    $flash_msg = __('mobile_rename_err', 'Dispositivo non trovato o non autorizzato.');
                    $flash_type = 'danger';
                }
            }
        }
    } else {
        $flash_msg = __('mobile_flash_csrf_error', 'Errore di sicurezza CSRF. Riprova.');
        $flash_type = 'danger';
    }
}

// Caricamento dei dispositivi autorizzati
$my_devices = get_user_authorized_devices($pdo, $current_user_id);
$other_users_devices = [];
if ($is_admin) {
    $all_devs = get_user_authorized_devices($pdo, null);
    foreach ($all_devs as $dev) {
        if ((int)$dev['user_id'] !== $current_user_id) {
            $other_users_devices[$dev['username']][] = $dev;
        }
    }
}

// Dispositivi Foobot fisici rilevati (Multi-Foobot)
$available_foobots = get_available_foobot_devices($pdo);
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($current_lang); ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars(__('mobile_page_title')); ?></title>
  
  <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg?v=2">
  <link rel="apple-touch-icon" href="assets/img/favicon.png?v=2">
  <link rel="stylesheet" href="assets/css/style.css?v=25">
  <script src="assets/js/theme.js?v=8"></script>

  <style>
    .mobile-hero-card {
      background: linear-gradient(135deg, rgba(56, 189, 248, 0.08) 0%, rgba(37, 99, 235, 0.05) 100%);
      border: 1px solid rgba(56, 189, 248, 0.25);
      border-radius: 16px;
      padding: 24px;
      margin-bottom: 24px;
    }
    .platform-badge-list {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-top: 14px;
      margin-bottom: 18px;
    }
    .platform-pill {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 4px 10px;
      border-radius: 9999px;
      font-size: 0.78rem;
      font-weight: 600;
      background: var(--input-bg);
      border: 1px solid var(--border-color);
      color: var(--text-color);
    }
    .device-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
      gap: 12px;
      margin-top: 10px;
    }
    .device-card {
      background: var(--card-bg, #ffffff);
      border: 1px solid var(--border-color, #e2e8f0);
      border-radius: 12px;
      padding: 10px 12px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.04);
      position: relative;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }
    .device-card-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 6px;
    }
    .device-icon-box {
      width: 32px;
      height: 32px;
      border-radius: 8px;
      background: rgba(56, 189, 248, 0.15);
      color: var(--accent-blue, #2563eb);
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }
    .device-status-badge {
      font-size: 0.68rem;
      font-weight: 700;
      padding: 2px 7px;
      border-radius: 9999px;
    }
    .device-status-badge.active {
      background: #dcfce7;
      color: #15803d;
    }
    .device-status-badge.idle {
      background: #f1f5f9;
      color: #64748b;
    }
    .device-serial-tag {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
      font-size: 0.72rem;
      font-weight: 700;
      background: rgba(2, 132, 199, 0.08);
      color: #0284c7;
      padding: 2px 6px;
      border-radius: 5px;
      border: 1px solid rgba(2, 132, 199, 0.22);
      letter-spacing: 0.02em;
    }
    .device-reg-id-badge {
      display: inline-flex;
      align-items: center;
      gap: 3px;
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
      font-size: 0.72rem;
      font-weight: 800;
      background: #0284c7;
      color: #ffffff;
      padding: 1px 6px;
      border-radius: 4px;
      letter-spacing: 0.03em;
    }
    .badge-app-mode {
      display: inline-flex;
      align-items: center;
      gap: 3px;
      font-size: 0.65rem;
      font-weight: 700;
      padding: 1px 6px;
      border-radius: 5px;
      text-transform: uppercase;
      letter-spacing: 0.02em;
    }
    .badge-app-mode.mode-standalone {
      background: #e0f2fe;
      color: #0369a1;
      border: 1px solid #bae6fd;
    }
    .badge-app-mode.mode-browser {
      background: #f1f5f9;
      color: #475569;
      border: 1px solid #cbd5e1;
    }
    .device-card-actions {
      margin-top: 8px;
      padding-top: 8px;
      border-top: 1px solid var(--border-color);
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 6px;
      flex-wrap: wrap;
    }
    .btn-rename-pill {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 4px;
      padding: 5px 10px;
      font-size: 0.75rem;
      font-weight: 600;
      border-radius: 6px;
      border: 1px solid var(--border-color);
      background: var(--input-bg);
      color: var(--text-color);
      cursor: pointer;
      transition: all 0.2s ease;
      white-space: nowrap;
      flex-shrink: 0;
    }
    .btn-rename-pill:hover {
      border-color: var(--accent-blue);
      color: var(--accent-blue);
    }
    .btn-disconnect-pill {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 5px;
      padding: 5px 12px;
      font-size: 0.75rem;
      font-weight: 600;
      border-radius: 6px;
      border: 1px solid rgba(239, 68, 68, 0.35);
      background: rgba(239, 68, 68, 0.08);
      color: #dc2626;
      cursor: pointer;
      transition: all 0.2s ease;
      white-space: nowrap;
      flex-shrink: 0;
      min-width: 90px;
    }
    .btn-disconnect-pill:hover {
      background: #dc2626;
      color: #ffffff;
      border-color: #dc2626;
    }
    .btn-edit-device {
      background: none;
      border: none;
      color: var(--text-muted);
      cursor: pointer;
      padding: 2px;
      display: inline-flex;
      align-items: center;
      border-radius: 4px;
      transition: color 0.2s;
    }
    .btn-edit-device:hover {
      color: var(--accent-blue);
    }
    /* Stile Modale Popup QR Code */
    .qr-modal-overlay {
      position: fixed;
      top: 0; left: 0; right: 0; bottom: 0;
      background: rgba(15, 23, 42, 0.65);
      backdrop-filter: blur(4px);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 1000;
      padding: 16px;
    }
    .qr-modal-overlay.active {
      display: flex;
    }
    .qr-modal-box {
      background: var(--card-bg, #ffffff);
      border: 1px solid var(--border-color, #cbd5e1);
      border-radius: 18px;
      width: 100%;
      max-width: 480px;
      padding: 24px;
      box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2);
      text-align: center;
      animation: qrModalPop 0.25s ease-out;
    }
    @keyframes qrModalPop {
      0% { transform: scale(0.94); opacity: 0; }
      100% { transform: scale(1); opacity: 1; }
    }
    #qrcode-canvas-wrap {
      background: #ffffff;
      padding: 16px;
      border-radius: 12px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      margin: 16px auto;
      border: 1px solid #e2e8f0;
      box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }
    .pairing-code-display {
      font-family: monospace;
      font-size: 1.5rem;
      letter-spacing: 3px;
      font-weight: 700;
      color: var(--accent-blue, #2563eb);
      background: var(--input-bg, #f8fafc);
      padding: 8px 16px;
      border-radius: 8px;
      border: 1px dashed var(--border-color, #cbd5e1);
      display: inline-block;
      margin-top: 8px;
    }
    /* Box Guida Installazione PWA (Computer & Smartphone) - Conforme WCAG AAA */
    .pwa-guide-box {
      background: #ffffff;
      border-radius: 16px;
      padding: 22px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      transition: box-shadow 0.2s ease, border-color 0.2s ease;
    }
    .pwa-guide-box-pc {
      border: 2px solid #1e40af;
      box-shadow: 0 4px 14px rgba(30, 64, 175, 0.08);
    }
    .pwa-guide-box-pc:hover {
      box-shadow: 0 6px 20px rgba(30, 64, 175, 0.14);
    }
    .pwa-guide-box-phone {
      border: 2px solid #166534;
      box-shadow: 0 4px 14px rgba(22, 101, 52, 0.08);
    }
    .pwa-guide-box-phone:hover {
      box-shadow: 0 6px 20px rgba(22, 101, 52, 0.14);
    }
    .pwa-box-icon-pc {
      width: 46px;
      height: 46px;
      border-radius: 12px;
      background: #eff6ff;
      border: 1px solid rgba(30, 64, 175, 0.25);
      color: #1e40af;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }
    .pwa-box-icon-phone {
      width: 46px;
      height: 46px;
      border-radius: 12px;
      background: #f0fdf4;
      border: 1px solid rgba(22, 101, 52, 0.25);
      color: #166534;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }
    .btn-pwa-link {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 12px 18px;
      font-weight: 700;
      font-size: 0.95rem;
      text-align: center;
      text-decoration: none;
      border-radius: 10px;
      width: 100%;
      cursor: pointer;
      transition: all 0.2s ease;
    }
    .btn-pwa-link-pc {
      background: #eff6ff;
      border: 1.5px solid #1e40af;
      color: #1e40af !important;
      box-shadow: 0 2px 6px rgba(30, 64, 175, 0.1);
    }
    .btn-pwa-link-pc svg {
      stroke: #1e40af;
      transition: stroke 0.2s ease;
    }
    .btn-pwa-link-pc:hover {
      background: #1e40af;
      color: #ffffff !important;
      border-color: #1e40af;
      box-shadow: 0 4px 12px rgba(30, 64, 175, 0.25);
    }
    .btn-pwa-link-pc:hover svg {
      stroke: #ffffff;
    }
    .btn-pwa-link-phone {
      background: #f0fdf4;
      border: 1.5px solid #166534;
      color: #14532d !important;
      box-shadow: 0 2px 6px rgba(22, 101, 52, 0.1);
    }
    .btn-pwa-link-phone svg {
      stroke: #14532d;
      transition: stroke 0.2s ease;
    }
    .btn-pwa-link-phone:hover {
      background: #166534;
      color: #ffffff !important;
      border-color: #166534;
      box-shadow: 0 4px 12px rgba(22, 101, 52, 0.25);
    }
    .btn-pwa-link-phone:hover svg {
      stroke: #ffffff;
    }
    .btn-pwa-serial {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 10px 18px;
      font-weight: 700;
      font-size: 0.9rem;
      text-align: center;
      text-decoration: none;
      border-radius: 10px;
      width: 100%;
      background: #ffffff;
      border: 1.5px solid #64748b;
      color: #0f172a !important;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
      cursor: pointer;
      transition: all 0.2s ease;
    }
    .btn-pwa-serial svg {
      stroke: #0f172a;
      transition: stroke 0.2s ease;
    }
    .btn-pwa-serial:hover {
      background: #f1f5f9;
      border-color: #334155;
      color: #000000 !important;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
    }
    /* Adattamenti per eventuale Dark Mode */
    html[data-theme="dark"] .pwa-guide-box {
      background: var(--card-bg, #161b22);
    }
    html[data-theme="dark"] .pwa-guide-box-pc {
      border-color: #3b82f6;
    }
    html[data-theme="dark"] .pwa-guide-box-phone {
      border-color: #22c55e;
    }
    html[data-theme="dark"] .btn-pwa-link-pc {
      background: rgba(59, 130, 246, 0.12);
      border-color: #3b82f6;
      color: #93c5fd !important;
    }
    html[data-theme="dark"] .btn-pwa-link-pc svg {
      stroke: #93c5fd;
    }
    html[data-theme="dark"] .btn-pwa-link-pc:hover {
      background: #2563eb;
      color: #ffffff !important;
    }
    html[data-theme="dark"] .btn-pwa-link-pc:hover svg {
      stroke: #ffffff;
    }
    html[data-theme="dark"] .btn-pwa-link-phone {
      background: rgba(34, 197, 94, 0.12);
      border-color: #22c55e;
      color: #86efac !important;
    }
    html[data-theme="dark"] .btn-pwa-link-phone svg {
      stroke: #86efac;
    }
    html[data-theme="dark"] .btn-pwa-link-phone:hover {
      background: #16a34a;
      color: #ffffff !important;
    }
    html[data-theme="dark"] .btn-pwa-link-phone:hover svg {
      stroke: #ffffff;
    }
    html[data-theme="dark"] .btn-pwa-serial {
      background: var(--surface-alt, #21262d);
      border-color: #475569;
      color: #f1f5f9 !important;
    }
    html[data-theme="dark"] .btn-pwa-serial svg {
      stroke: #f1f5f9;
    }
    html[data-theme="dark"] .btn-pwa-serial:hover {
      background: #334155;
      color: #ffffff !important;
    }
  </style>
</head>
<body class="bg-blue-50 text-slate-900 min-h-screen p-3 md:p-8">

  <div class="max-w-7xl mx-auto">
    
    <!-- Testata con Logo e Menu -->
    <header class="dashboard-header mb-6">
      <div class="header-brand-wrap">
        <a href="index.php" class="header-brand-logo" title="<?php echo htmlspecialchars(__('app_title')); ?>">
          <?php echo get_foobot_logo_svg(44); ?>
        </a>
        <div>
          <h1><?php echo htmlspecialchars(__('app_title')); ?></h1>
          <p class="card-subtext"><?php echo htmlspecialchars(__('mobile_subtext')); ?></p>
        </div>
      </div>
      <div class="header-right">
        <?php render_main_nav('mobile', $current_user); ?>
      </div>
    </header>

    <?php if ($flash_msg !== ''): ?>
    <div class="mb-5 p-4 rounded-xl border text-sm font-medium <?php echo $flash_type === 'success' ? 'bg-emerald-50 border-emerald-300 text-emerald-800' : 'bg-rose-50 border-rose-300 text-rose-800'; ?>">
      <?php echo htmlspecialchars($flash_msg); ?>
    </div>
    <?php endif; ?>

    <!-- HERO CARD: Spiegazione e Piattaforme Supportate -->
    <section class="mobile-hero-card">
      <div>
        <div style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
          <span class="badge" style="background:#2563eb; color:#fff; font-size:0.75rem;"><?php echo htmlspecialchars(__('mobile_release_badge')); ?></span>
          <span class="badge" style="background:#10b981; color:#fff; font-size:0.75rem;"><?php echo htmlspecialchars(__('mobile_multisensor_badge')); ?></span>
        </div>
        <h2 style="font-size:1.35rem; font-weight:700; margin:0 0 6px 0;"><?php echo htmlspecialchars(__('mobile_hero_title')); ?></h2>
        <p class="card-subtext" style="max-width:100%; margin:0; line-height:1.5;">
          <?php echo __('mobile_hero_desc'); ?>
        </p>

        <div class="platform-badge-list" style="margin-bottom:0;">
          <span class="platform-pill"><?php echo __('mobile_platform_android'); ?></span>
          <span class="platform-pill"><?php echo __('mobile_platform_ios'); ?></span>
          <span class="platform-pill"><?php echo __('mobile_platform_desktop'); ?></span>
          <span class="platform-pill"><?php echo __('mobile_platform_zeroport'); ?></span>
        </div>
      </div>
    </section>

    <!-- SEZIONE DUE BOX: GUIDA INSTALLAZIONE COMPUTER & SMARTPHONE/TABLET -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; margin-bottom: 24px;">
      
      <!-- BOX 1: Computer Fissi e Portatili (PC Windows, Mac, Linux) -->
      <div class="pwa-guide-box pwa-guide-box-pc">
        <div>
          <!-- Intestazione Box Computer con Icona -->
          <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 14px;">
            <div class="pwa-box-icon-pc">
              <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                <line x1="8" y1="21" x2="16" y2="21"></line>
                <line x1="12" y1="17" x2="12" y2="21"></line>
              </svg>
            </div>
            <div>
              <h3 style="font-size: 1.15rem; font-weight: 700; margin: 0; color: var(--text-color, #0f172a);">
                <?php echo htmlspecialchars(__('mobile_box_pc_title', 'Installa su PC, Mac e Linux')); ?>
              </h3>
              <span style="font-size: 0.78rem; color: #1e40af; font-weight: 700;"><?php echo htmlspecialchars(__('mobile_box_pc_sub', 'Computer Fissi e Portatili')); ?></span>
            </div>
          </div>

          <!-- Spiegazione a step per Computer -->
          <div style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.55; margin-bottom: 16px;">
            <div style="margin-bottom: 10px;">
              <?php echo __('mobile_box_pc_step1', '<strong style="color: var(--text-color, #0f172a);">1. Apri l\'App nel Browser:</strong> Clicca sul pulsante sottostante <em>"Apri App nel Browser"</em>. L\'App si aprirà in una nuova finestra/scheda e si connetterà automaticamente in 1 secondo alla tua sessione web registrando il computer (senza codici né configurazioni).'); ?>
            </div>
            <div style="margin-bottom: 10px;">
              <?php echo __('mobile_box_pc_step2', '<strong style="color: var(--text-color, #0f172a);">2. Usa il comando Installa:</strong> Nella parte alta della schermata comparirà la barra con il pulsante <strong>"Installa Ora"</strong> (oppure l\'icona del monitor con freccia nella barra degli indirizzi di Chrome / Edge).'); ?>
            </div>
            <div style="margin-bottom: 10px;">
              <?php echo __('mobile_box_pc_step3', '<strong style="color: var(--text-color, #0f172a);">3. Programma indipendente:</strong> Cliccando <em>"Installa"</em>, il browser installerà la Web App come applicazione desktop dedicata (con la sua icona sul Desktop/menu Avvio), sempre apribile a pieno schermo senza barre del browser.'); ?>
            </div>
            <div style="font-size: 0.76rem; color: #334155; background: #f8fafc; padding: 8px 10px; border-radius: 8px; border-left: 3px solid #1e40af;">
              <?php echo __('mobile_box_pc_compat', '<strong>Compatibilità:</strong> L\'installazione PWA come programma autonomo è supportata nativamente da <strong>Google Chrome, Microsoft Edge, Brave, Opera</strong> e <strong>Safari</strong> (su Mac con macOS Sonoma o sup.). Su <strong>Firefox</strong> è sempre utilizzabile istantaneamente nel browser.'); ?>
            </div>
          </div>
        </div>

        <!-- Pulsante di Azione Computer -->
        <a href="<?php echo htmlspecialchars($direct_pwa_url); ?>" target="_blank" class="btn-pwa-link btn-pwa-link-pc">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
          <span><?php echo htmlspecialchars(__('mobile_box_pc_btn', 'Apri App nel Browser')); ?></span>
        </a>
      </div>

      <!-- BOX 2: Smartphone & Tablet (Android & iOS) -->
      <div class="pwa-guide-box pwa-guide-box-phone">
        <div>
          <!-- Intestazione Box Smartphone con Icona -->
          <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 14px;">
            <div class="pwa-box-icon-phone">
              <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect>
                <line x1="12" y1="18" x2="12.01" y2="18"></line>
              </svg>
            </div>
            <div>
              <h3 style="font-size: 1.15rem; font-weight: 700; margin: 0; color: var(--text-color, #0f172a);">
                <?php echo htmlspecialchars(__('mobile_box_phone_title', 'Installa su Smartphone e Tablet')); ?>
              </h3>
              <span style="font-size: 0.78rem; color: #166534; font-weight: 700;"><?php echo htmlspecialchars(__('mobile_box_phone_sub', 'Android, iPhone e iPad')); ?></span>
            </div>
          </div>

          <!-- Spiegazione a step per Smartphone -->
          <div style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.55; margin-bottom: 16px;">
            <div style="margin-bottom: 10px;">
              <?php echo __('mobile_box_phone_step1', '<strong style="color: var(--text-color, #0f172a);">1. Login dal telefono:</strong> Apri il browser del tuo cellulare (Chrome su Android o Safari su iPhone), entra nel sito NewFoobot effettuando il login e vai a questa pagina (<em>App Mobile</em>).'); ?>
            </div>
            <div style="margin-bottom: 10px;">
              <?php echo __('mobile_box_phone_step2', '<strong style="color: var(--text-color, #0f172a);">2. Apri l\'App:</strong> Tocca il pulsante <strong>"Apri App su Smartphone"</strong>: l\'App si aprirà collegandosi direttamente al tuo account e registrando automaticamente il telefono.'); ?>
            </div>
            <div style="margin-bottom: 10px;">
              <?php echo __('mobile_box_phone_step3_title', '<strong style="color: var(--text-color, #0f172a);">3. Aggiungi alla Schermata Home:</strong>'); ?>
              <ul style="margin: 4px 0 0 0; padding-left: 18px;">
                <li><?php echo __('mobile_box_phone_step3_android', '<strong>Android (Chrome)</strong>: tocca <em>"Installa Ora"</em> nella barra in cima, oppure tocca il menu (⋮) in alto a destra &rarr; <em>"Installa applicazione"</em>.'); ?></li>
                <li><?php echo __('mobile_box_phone_step3_ios', '<strong>iPhone / iPad (Safari)</strong>: tocca il tasto <strong>Condividi</strong> (icona quadrata con la freccia in alto) e seleziona <em>"Aggiungi alla schermata Home"</em>.'); ?></li>
              </ul>
            </div>
            <div style="font-size: 0.76rem; color: #334155; background: #f8fafc; padding: 8px 10px; border-radius: 8px; border-left: 3px solid #166534;">
              <?php echo __('mobile_box_phone_alt', '<strong>In alternativa da PC:</strong> se sei su computer e vuoi collegare il telefono senza fare il login dal cellulare, genera un <strong>Seriale di Accoppiamento</strong> dal pulsante sottostante e digitalo nell\'App sul telefono.'); ?>
            </div>
          </div>
        </div>

        <!-- Pulsanti di Azione Smartphone -->
        <div style="display: flex; flex-direction: column; gap: 10px; width: 100%;">
          <a href="<?php echo htmlspecialchars($direct_pwa_url); ?>" target="_blank" class="btn-pwa-link btn-pwa-link-phone">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
            <span><?php echo htmlspecialchars(__('mobile_box_phone_btn', 'Apri App su Smartphone')); ?></span>
          </a>
          <button type="button" class="btn-pwa-serial" onclick="openPairingModal()">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
            <span><?php echo htmlspecialchars(__('mobile_generate_serial_btn', 'Genera Seriale di Accoppiamento')); ?></span>
          </button>
        </div>
      </div>

    </div>

    <!-- BOX 1: I Miei Dispositivi Collegati -->
    <section class="card mb-6">
      <div class="flex items-center justify-between border-b border-slate-200/70 pb-3 mb-4" style="gap:10px; flex-wrap:wrap;">
        <div>
          <h3 style="font-size:1.15rem; font-weight:700; margin:0;">
            <?php echo htmlspecialchars(sprintf(__('mobile_my_devices_title', 'I Miei Dispositivi Connessi (%d)'), count($my_devices))); ?>
            <span style="font-weight:500; font-size:0.95rem; color:var(--text-muted);">&bull; <?php echo htmlspecialchars($current_user); ?></span>
          </h3>
          <p class="card-subtext" style="margin:2px 0 0 0;"><?php echo htmlspecialchars(__('mobile_my_devices_sub')); ?></p>
        </div>
        <div style="display:flex; align-items:center; gap:8px;">
          <?php if (!empty($my_devices)): ?>
          <form method="POST" onsubmit="return confirm('<?php echo htmlspecialchars(addslashes(__('mobile_disconnect_all_my_confirm', 'Sei sicuro di voler scollegare TUTTI i tuoi dispositivi connessi?'))); ?>');" style="margin:0;">
            <input type="hidden" name="action" value="revoke_all_my_devices">
            <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
            <button type="submit" class="btn-disconnect-pill" style="padding:6px 12px; font-size:0.82rem;" title="<?php echo htmlspecialchars(__('mobile_disconnect_all_my_title', 'Disconnette tutti i tuoi dispositivi')); ?>">
              <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
              <span><?php echo htmlspecialchars(__('mobile_btn_disconnect_all', 'Scollega Tutti')); ?></span>
            </button>
          </form>
          <?php endif; ?>
          <button type="button" class="btn-action" onclick="openPairingModal()" style="font-size:0.82rem; padding:6px 12px;">
            <?php echo htmlspecialchars(__('mobile_connect_new')); ?>
          </button>
        </div>
      </div>

      <?php if (empty($my_devices)): ?>
      <div style="padding:24px; text-align:center; color:var(--text-muted); background:var(--input-bg); border-radius:12px; border:1px dashed var(--border-color);">
        <p style="margin:0 0 10px 0;"><?php echo htmlspecialchars(__('mobile_no_devices')); ?></p>
        <button type="button" class="btn-primary" onclick="openPairingModal()" style="font-size:0.85rem; padding:8px 16px;">
          <?php echo htmlspecialchars(__('mobile_generate_serial_btn', 'Genera Seriale di Accoppiamento')); ?>
        </button>
      </div>
      <?php else: ?>
      <div class="device-grid">
        <?php foreach ($my_devices as $dev): 
          $last_active = (int)$dev['last_active_at'];
          $is_recent = ($last_active > 0 && (time() - $last_active) < 86400);
          $serial = !empty($dev['device_serial']) ? $dev['device_serial'] : ('NF-DEV-' . strtoupper(substr(md5((string)$dev['id']), 0, 4)) . '-' . strtoupper(substr(md5((string)$dev['created_at']), 0, 4)));
          $dev_info = parse_device_info($dev['user_agent'] ?: $dev['platform'], $dev['app_mode'] ?? 'browser', $dev['platform'] ?: 'Web App');
          $is_standalone = ($dev_info['app_mode'] === 'standalone');
        ?>
        <div class="device-card">
          <div>
            <div class="device-card-header">
              <div style="display:flex; align-items:center; gap:8px; min-width:0;">
                <div class="device-icon-box" title="<?php echo htmlspecialchars($dev_info['summary']); ?>">
                  <?php if (stripos($dev_info['os'], 'Android') !== false || stripos($dev_info['os'], 'iOS') !== false): ?>
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2"></rect><line x1="12" y1="18" x2="12.01" y2="18"></line></svg>
                  <?php else: ?>
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                  <?php endif; ?>
                </div>
                <div style="min-width:0;">
                  <div style="display:flex; align-items:center; gap:6px;">
                    <strong style="font-size:0.9rem; font-weight:700; color:var(--text-color); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:160px;" title="<?php echo htmlspecialchars($dev['device_name']); ?>"><?php echo htmlspecialchars($dev['device_name']); ?></strong>
                    <button type="button" class="btn-edit-device" onclick="openRenameDeviceModal(<?php echo (int)$dev['id']; ?>, '<?php echo htmlspecialchars(addslashes($dev['device_name'])); ?>')" title="<?php echo htmlspecialchars(__('mobile_edit_name_title', 'Modifica nome dispositivo')); ?>">
                      <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                    </button>
                  </div>
                  <div style="display:flex; align-items:center; gap:5px; margin-top:2px;">
                    <span class="badge-app-mode <?php echo $is_standalone ? 'mode-standalone' : 'mode-browser'; ?>">
                      <?php echo $is_standalone ? 'App Installata (PWA)' : 'Scheda Browser'; ?>
                    </span>
                  </div>
                </div>
              </div>
              <span class="device-status-badge <?php echo $is_recent ? 'active' : 'idle'; ?>" title="<?php echo $is_recent ? 'Dispositivo attivo nelle ultime 24 ore' : 'Dispositivo inattivo'; ?>">
                <?php echo $is_recent ? htmlspecialchars(__('mobile_status_active')) : htmlspecialchars(__('mobile_status_idle')); ?>
              </span>
            </div>

            <!-- Dettaglio Seriale e Ambiente -->
            <div style="margin: 6px 0 8px 0; display:flex; align-items:center; justify-content:space-between; gap:6px; background:rgba(2,132,199,0.05); padding:4px 8px; border-radius:6px; border:1px solid rgba(2,132,199,0.18);">
              <div style="display:flex; align-items:center; gap:5px;">
                <span class="device-reg-id-badge" title="Identificativo progressivo di registrazione nel database">ID #<?php echo (int)$dev['id']; ?></span>
                <span class="device-serial-tag" title="Seriale Univoco"><?php echo htmlspecialchars($serial); ?></span>
              </div>
              <span style="font-size:0.68rem; font-weight:700; color:var(--text-muted); text-transform:uppercase;"><?php echo htmlspecialchars(__('mobile_reg_db', 'Reg. DB')); ?></span>
            </div>

            <div style="font-size:0.73rem; color:var(--text-muted); line-height:1.45; background:var(--input-bg); padding:6px 8px; border-radius:8px; border:1px solid var(--border-color);">
              <div style="display:flex; justify-content:space-between; margin-bottom:2px;" title="<?php echo htmlspecialchars($dev_info['summary']); ?>">
                <span><?php echo htmlspecialchars(__('mobile_sys_browser', 'Sistema / Browser:')); ?></span>
                <strong style="color:var(--text-color); font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:145px;"><?php echo htmlspecialchars($dev_info['os']); ?></strong>
              </div>
              <div style="display:flex; justify-content:space-between; margin-bottom:2px;">
                <span><?php echo htmlspecialchars(__('settings_device_reg_id', 'ID Dispositivo App:')); ?></span>
                <strong style="color:var(--accent-blue); font-family:monospace; font-size:0.78rem;">#<?php echo (int)$dev['id']; ?></strong>
              </div>
              <div style="display:flex; justify-content:space-between; margin-bottom:2px;">
                <span><?php echo htmlspecialchars(__('mobile_ip_last_act')); ?></span>
                <code><?php echo htmlspecialchars($dev['last_ip'] ?: 'N/D'); ?></code>
              </div>
              <div style="display:flex; justify-content:space-between; margin-bottom:2px;">
                <span><?php echo htmlspecialchars(__('mobile_last_access')); ?></span>
                <strong><?php echo $last_active > 0 ? date('d/m/Y H:i', $last_active) : htmlspecialchars(__('mobile_never')); ?></strong>
              </div>
              <div style="display:flex; justify-content:space-between;">
                <span><?php echo htmlspecialchars(__('mobile_authorized_on')); ?></span>
                <span><?php echo date('d/m/Y', strtotime($dev['created_at'])); ?></span>
              </div>
            </div>
          </div>

          <div class="device-card-actions">
            <button type="button" class="btn-rename-pill" onclick="openRenameDeviceModal(<?php echo (int)$dev['id']; ?>, '<?php echo htmlspecialchars(addslashes($dev['device_name'])); ?>')">
              <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
              <span><?php echo htmlspecialchars(__('mobile_btn_rename', 'Rinomina')); ?></span>
            </button>
            <form method="POST" onsubmit="return confirm('<?php echo htmlspecialchars(addslashes(__('mobile_disconnect_confirm'))); ?>');" style="margin:0;">
              <input type="hidden" name="action" value="revoke_device">
              <input type="hidden" name="device_id" value="<?php echo (int)$dev['id']; ?>">
              <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
              <button type="submit" class="btn-disconnect-pill" title="<?php echo htmlspecialchars(__('mobile_disconnect')); ?>">
                <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                <span><?php echo htmlspecialchars(__('mobile_disconnect')); ?></span>
              </button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </section>

    <!-- BOX 2: Dispositivi degli Altri Utenti (Riservato agli Amministratori) -->
    <?php if ($is_admin): ?>
    <section class="card mb-6">
      <div class="flex items-center justify-between border-b border-slate-200/70 pb-3 mb-4" style="gap:10px; flex-wrap:wrap;">
        <div>
          <div style="display:flex; align-items:center; gap:8px;">
            <span class="badge" style="background:#f59e0b; color:#fff; font-size:0.7rem;"><?php echo htmlspecialchars(__('mobile_admin_badge')); ?></span>
            <h3 style="font-size:1.15rem; font-weight:700; margin:0;"><?php echo htmlspecialchars(__('mobile_other_devices_title')); ?></h3>
          </div>
          <p class="card-subtext" style="margin:2px 0 0 0;"><?php echo htmlspecialchars(__('mobile_other_devices_sub')); ?></p>
        </div>
        <?php if (!empty($other_users_devices)): ?>
        <form method="POST" onsubmit="return confirm('<?php echo htmlspecialchars(addslashes(__('mobile_disconnect_all_others_confirm', 'ATTENZIONE: Sei sicuro di voler scollegare TUTTI i dispositivi di TUTTI gli altri utenti?'))); ?>');" style="margin:0;">
          <input type="hidden" name="action" value="revoke_all_other_devices">
          <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
          <button type="submit" class="btn-disconnect-pill" style="padding:6px 12px; font-size:0.82rem;" title="<?php echo htmlspecialchars(__('mobile_disconnect_all_others_title', 'Scollega tutti i dispositivi configurati da tutti gli altri utenti')); ?>">
            <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
            <span><?php echo htmlspecialchars(__('mobile_btn_disconnect_all_others', 'Scollega Tutti')); ?></span>
          </button>
        </form>
        <?php endif; ?>
      </div>

      <?php if (empty($other_users_devices)): ?>
      <p style="color:var(--text-muted); font-size:0.88rem; margin:0;"><?php echo htmlspecialchars(__('mobile_no_other_devices')); ?></p>
      <?php else: ?>
        <?php foreach ($other_users_devices as $username => $u_devs): 
          $target_uid = !empty($u_devs[0]['user_id']) ? (int)$u_devs[0]['user_id'] : 0;
        ?>
        <div style="margin-bottom:20px; background:var(--input-bg); padding:14px; border-radius:12px; border:1px solid var(--border-color);">
          <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px; gap:8px; flex-wrap:wrap;">
            <div style="display:flex; align-items:center; gap:8px;">
              <span style="font-weight:700; font-size:0.92rem; color:var(--accent-blue);"><?php echo htmlspecialchars(__('mobile_user_label')); ?> <?php echo htmlspecialchars($username); ?></span>
            </div>
            <?php if (!empty($u_devs)): ?>
            <form method="POST" onsubmit="return confirm('<?php echo htmlspecialchars(addslashes(sprintf(__('mobile_disconnect_all_user_confirm', 'Scollegare tutti i dispositivi di %s?'), $username))); ?>');" style="margin:0;">
              <input type="hidden" name="action" value="revoke_all_user_devices">
              <input type="hidden" name="target_user_id" value="<?php echo $target_uid; ?>">
              <input type="hidden" name="target_username" value="<?php echo htmlspecialchars($username); ?>">
              <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
              <button type="submit" class="btn-disconnect-pill" style="padding:4px 10px; font-size:0.75rem;" title="<?php echo htmlspecialchars(sprintf(__('mobile_disconnect_all_user_title', 'Scollega tutti i dispositivi di %s'), $username)); ?>">
                <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                <span><?php echo htmlspecialchars(__('mobile_btn_disconnect_all', 'Scollega Tutti')); ?></span>
              </button>
            </form>
            <?php endif; ?>
          </div>
          <div class="device-grid">
            <?php foreach ($u_devs as $dev): 
              $last_active = (int)$dev['last_active_at'];
              $is_recent = ($last_active > 0 && (time() - $last_active) < 86400);
              $serial = !empty($dev['device_serial']) ? $dev['device_serial'] : ('NF-DEV-' . strtoupper(substr(md5((string)$dev['id']), 0, 4)) . '-' . strtoupper(substr(md5((string)$dev['created_at']), 0, 4)));
              $dev_info = parse_device_info($dev['user_agent'] ?: $dev['platform'], $dev['app_mode'] ?? 'browser', $dev['platform'] ?: 'Web App');
              $is_standalone = ($dev_info['app_mode'] === 'standalone');
            ?>
            <div class="device-card">
              <div>
                <div class="device-card-header">
                  <div style="display:flex; align-items:center; gap:8px; min-width:0;">
                    <div class="device-icon-box" title="<?php echo htmlspecialchars($dev_info['summary']); ?>">
                      <?php if (stripos($dev_info['os'], 'Android') !== false || stripos($dev_info['os'], 'iOS') !== false): ?>
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2"></rect><line x1="12" y1="18" x2="12.01" y2="18"></line></svg>
                      <?php else: ?>
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                      <?php endif; ?>
                    </div>
                    <div style="min-width:0;">
                      <div style="display:flex; align-items:center; gap:6px;">
                        <strong style="font-size:0.9rem; font-weight:700; color:var(--text-color); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:160px;" title="<?php echo htmlspecialchars($dev['device_name']); ?>"><?php echo htmlspecialchars($dev['device_name']); ?></strong>
                        <button type="button" class="btn-edit-device" onclick="openRenameDeviceModal(<?php echo (int)$dev['id']; ?>, '<?php echo htmlspecialchars(addslashes($dev['device_name'])); ?>')" title="<?php echo htmlspecialchars(__('mobile_edit_name_title', 'Modifica nome dispositivo')); ?>">
                          <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                        </button>
                      </div>
                      <div style="display:flex; align-items:center; gap:5px; margin-top:2px;">
                        <span class="badge-app-mode <?php echo $is_standalone ? 'mode-standalone' : 'mode-browser'; ?>">
                          <?php echo $is_standalone ? 'App Installata (PWA)' : 'Scheda Browser'; ?>
                        </span>
                      </div>
                    </div>
                  </div>
                  <span class="device-status-badge <?php echo $is_recent ? 'active' : 'idle'; ?>" title="<?php echo $is_recent ? 'Dispositivo attivo nelle ultime 24 ore' : 'Dispositivo inattivo'; ?>">
                    <?php echo $is_recent ? htmlspecialchars(__('mobile_status_active')) : htmlspecialchars(__('mobile_status_idle')); ?>
                  </span>
                </div>

                <!-- Dettaglio Seriale e Ambiente -->
                <div style="margin: 6px 0 8px 0; display:flex; align-items:center; justify-content:space-between; gap:6px; background:rgba(2,132,199,0.05); padding:4px 8px; border-radius:6px; border:1px solid rgba(2,132,199,0.18);">
                  <div style="display:flex; align-items:center; gap:5px;">
                    <span class="device-reg-id-badge" title="Identificativo progressivo di registrazione nel database">ID #<?php echo (int)$dev['id']; ?></span>
                    <span class="device-serial-tag" title="Seriale Univoco"><?php echo htmlspecialchars($serial); ?></span>
                  </div>
                  <span style="font-size:0.68rem; font-weight:700; color:var(--text-muted); text-transform:uppercase;"><?php echo htmlspecialchars(__('mobile_reg_db', 'Reg. DB')); ?></span>
                </div>

                <div style="font-size:0.73rem; color:var(--text-muted); line-height:1.45; background:var(--card-bg); padding:6px 8px; border-radius:8px; border:1px solid var(--border-color);">
                  <div style="display:flex; justify-content:space-between; margin-bottom:2px;" title="<?php echo htmlspecialchars($dev_info['summary']); ?>">
                    <span><?php echo htmlspecialchars(__('mobile_sys_browser', 'Sistema / Browser:')); ?></span>
                    <strong style="color:var(--text-color); font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:145px;"><?php echo htmlspecialchars($dev_info['os']); ?></strong>
                  </div>
                  <div style="display:flex; justify-content:space-between; margin-bottom:2px;">
                    <span><?php echo htmlspecialchars(__('settings_device_reg_id', 'ID Dispositivo App:')); ?></span>
                    <strong style="color:var(--accent-blue); font-family:monospace; font-size:0.78rem;">#<?php echo (int)$dev['id']; ?></strong>
                  </div>
                  <div style="display:flex; justify-content:space-between; margin-bottom:2px;">
                    <span><?php echo htmlspecialchars(__('mobile_last_ip', 'IP Ultima Attività:')); ?></span> <code><?php echo htmlspecialchars($dev['last_ip'] ?: 'N/D'); ?></code>
                  </div>
                  <div style="display:flex; justify-content:space-between;">
                    <span><?php echo htmlspecialchars(__('mobile_last_access', 'Ultimo Accesso:')); ?></span> <strong><?php echo $last_active > 0 ? date('d/m/Y H:i', $last_active) : htmlspecialchars(__('mobile_never')); ?></strong>
                  </div>
                </div>
              </div>

              <div class="device-card-actions">
                <button type="button" class="btn-rename-pill" onclick="openRenameDeviceModal(<?php echo (int)$dev['id']; ?>, '<?php echo htmlspecialchars(addslashes($dev['device_name'])); ?>')">
                  <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                  <span><?php echo htmlspecialchars(__('mobile_btn_rename', 'Rinomina')); ?></span>
                </button>
                <form method="POST" onsubmit="return confirm('<?php echo htmlspecialchars(addslashes(str_replace('{user}', $username, __('mobile_revoke_confirm')))); ?>');" style="margin:0;">
                  <input type="hidden" name="action" value="revoke_device">
                  <input type="hidden" name="device_id" value="<?php echo (int)$dev['id']; ?>">
                  <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                  <button type="submit" class="btn-disconnect-pill" title="<?php echo htmlspecialchars(__('mobile_revoke_btn')); ?>">
                    <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                    <span><?php echo htmlspecialchars(__('mobile_revoke_btn')); ?></span>
                  </button>
                </form>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>
    <?php endif; ?>

  </div>

  <!-- MODALE POPUP: Generatore Seriale di Accoppiamento -->
  <div class="qr-modal-overlay" id="qr-modal">
    <div class="qr-modal-box" style="max-width: 440px;">
      <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; border-bottom:1px solid var(--border-color); padding-bottom:8px;">
        <h3 style="font-size:1.15rem; font-weight:700; margin:0; display:flex; align-items:center; gap:8px;">
          <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#0284c7" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
          <span><?php echo htmlspecialchars(__('mobile_modal_title', 'Seriale di Accoppiamento')); ?></span>
        </h3>
        <button type="button" onclick="closePairingModal()" style="background:none; border:none; font-size:1.5rem; cursor:pointer; color:var(--text-muted); line-height:1;">&times;</button>
      </div>

      <p class="card-subtext" style="font-size:0.82rem; margin:0 0 12px 0; text-align:left;">
        <?php echo __('mobile_modal_desc', 'Digita questo seriale monouso nell\'App sul tuo smartphone o tablet per autorizzare il nuovo dispositivo (validità 10 minuti):'); ?>
      </p>

      <div style="margin-bottom:12px; text-align:left;">
        <label style="font-size:0.78rem; font-weight:600; display:block; margin-bottom:4px; color:var(--text-color);"><?php echo htmlspecialchars(__('mobile_modal_name_label', 'Nome Dispositivo:')); ?></label>
        <input type="text" id="modal-device-name-input" class="input-text" value="Smartphone <?php echo htmlspecialchars($current_user); ?>" style="width:100%; font-size:0.85rem; padding:8px 10px; border-radius:8px; border:1px solid var(--border-color); background:var(--input-bg); color:var(--text-color);">
      </div>

      <!-- Box Seriale Prominente con Tasto Copia -->
      <div style="background: var(--input-bg, #f8fafc); border: 2px dashed rgba(56, 189, 248, 0.6); border-radius: 12px; padding: 16px; margin: 14px 0; text-align: center;">
        <div style="font-size: 0.72rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); letter-spacing: 1px; margin-bottom: 6px;">
          <?php echo htmlspecialchars(__('mobile_modal_or_code', 'Codice Seriale Monouso')); ?>
        </div>
        <div class="pairing-code-display" id="pairing-code-label" onclick="copyPairingCode()" style="font-size: 2rem; font-family: monospace; font-weight: 800; letter-spacing: 4px; color: #0284c7; padding: 10px 18px; background: #ffffff; border-radius: 10px; display: inline-block; box-shadow: 0 2px 6px rgba(0,0,0,0.05); user-select: all; cursor: pointer;" title="Clicca per copiare">
          <?php echo htmlspecialchars(__('mobile_modal_loading')); ?>
        </div>
        <div style="margin-top: 10px;">
          <button type="button" id="btn-copy-code" class="btn-secondary" onclick="copyPairingCode()" style="font-size: 0.78rem; padding: 5px 12px; display: inline-flex; align-items: center; gap: 6px;">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
            <span id="btn-copy-code-text"><?php echo htmlspecialchars(__('mobile_btn_copy_code', 'Copia Seriale')); ?></span>
          </button>
        </div>
        <div style="font-size: 0.75rem; font-weight: 600; color: #ef4444; margin-top: 10px;" id="pairing-timer-label">
          <?php echo htmlspecialchars(str_replace('{time}', '10:00', __('mobile_modal_expires_in'))); ?>
        </div>
      </div>

      <div style="margin-top:14px; display:flex; flex-direction:column; gap:8px;">
        <button type="button" class="btn-secondary" onclick="generateNewPairingTicket()" style="font-size:0.8rem; padding:8px 12px; display:inline-flex; align-items:center; justify-content:center; gap:6px;">
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"></polyline><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path></svg>
          <span><?php echo htmlspecialchars(__('mobile_modal_regen', 'Genera Nuovo Seriale')); ?></span>
        </button>
        <button type="button" class="btn-primary" id="btn-direct-pair-here" style="font-size:0.8rem; padding:8px 14px;">
          <?php echo htmlspecialchars(__('mobile_modal_activate_now', 'Attiva Subito su Questo Browser (1 Clic)')); ?>
        </button>
      </div>
    </div>
  </div>

  <!-- MODALE POPUP: Rinomina Dispositivo -->
  <div class="qr-modal-overlay" id="rename-device-modal">
    <div class="qr-modal-box" style="max-width: 400px; text-align: left;">
      <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; border-bottom:1px solid var(--border-color); padding-bottom:8px;">
        <h3 style="font-size:1.15rem; font-weight:700; margin:0;"><?php echo htmlspecialchars(__('mobile_rename_modal_title', 'Rinomina Dispositivo')); ?></h3>
        <button type="button" onclick="closeRenameDeviceModal()" style="background:none; border:none; font-size:1.5rem; cursor:pointer; color:var(--text-muted); line-height:1;">&times;</button>
      </div>

      <form method="POST" id="form-rename-device">
        <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
        <input type="hidden" name="action" value="rename_device">
        <input type="hidden" name="device_id" id="rename-device-id" value="">

        <div style="margin-bottom:16px;">
          <label style="font-size:0.78rem; font-weight:600; display:block; margin-bottom:5px; color:var(--text-color);"><?php echo htmlspecialchars(__('mobile_modal_name_label', 'Nome Dispositivo:')); ?></label>
          <input type="text" name="device_name" id="rename-device-name-input" required class="input-text" style="width:100%; font-size:0.88rem; padding:8px 10px; border-radius:8px; border:1px solid var(--border-color); background:var(--input-bg); color:var(--text-color);">
        </div>

        <div style="display:flex; justify-content:flex-end; gap:8px;">
          <button type="button" class="btn-secondary" onclick="closeRenameDeviceModal()" style="font-size:0.82rem; padding:6px 12px; border-radius:8px;">
            <?php echo htmlspecialchars(__('btn_cancel', 'Annulla')); ?>
          </button>
          <button type="submit" class="btn-primary" style="font-size:0.82rem; padding:6px 14px; border-radius:8px;">
            <?php echo htmlspecialchars(__('btn_save_name', 'Salva Nome')); ?>
          </button>
        </div>
      </form>
    </div>
  </div>

  <script>
    let countdownInterval = null;
    let secondsRemaining = 600;
    const csrfToken = <?php echo json_encode(get_csrf_token()); ?>;
    const pwaAppUrl = <?php echo json_encode($pwa_url); ?>;
    const i18nExpiredRegen = <?php echo json_encode(__('mobile_modal_expired_regen')); ?>;
    const i18nExpired = <?php echo json_encode(__('mobile_modal_expired')); ?>;
    const i18nExpiresInTpl = <?php echo json_encode(__('mobile_modal_expires_in')); ?>;
    const i18nErrConn = <?php echo json_encode(__('mobile_modal_err_conn')); ?>;
    const i18nCopied = <?php echo json_encode(__('mobile_copied', 'Copiato!')); ?>;
    const i18nMyDevice = <?php echo json_encode(__('mobile_my_device_default', 'Mio Dispositivo')); ?>;

    function openPairingModal() {
      document.getElementById('qr-modal').classList.add('active');
      generateNewPairingTicket();
    }

    function closePairingModal() {
      document.getElementById('qr-modal').classList.remove('active');
      if (countdownInterval) clearInterval(countdownInterval);
    }

    function openRenameDeviceModal(deviceId, currentName) {
      document.getElementById('rename-device-id').value = deviceId;
      const input = document.getElementById('rename-device-name-input');
      input.value = currentName || '';
      document.getElementById('rename-device-modal').classList.add('active');
      setTimeout(() => input.focus(), 50);
    }

    function closeRenameDeviceModal() {
      document.getElementById('rename-device-modal').classList.remove('active');
    }

    // Chiudi con tasto ESC
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        closePairingModal();
        closeRenameDeviceModal();
      }
    });

    document.getElementById('btn-open-pair-modal')?.addEventListener('click', openPairingModal);

    function copyPairingCode() {
      const code = document.getElementById('pairing-code-label').textContent.trim();
      if (!code || code === '...' || code === 'Error' || code === 'SCADUTO') return;
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(code).then(() => {
          showCopiedFeedback();
        }).catch(() => fallbackSelectCode());
      } else {
        fallbackSelectCode();
      }
    }

    function showCopiedFeedback() {
      const btnText = document.getElementById('btn-copy-code-text');
      if (btnText) {
        const orig = btnText.textContent;
        btnText.textContent = i18nCopied;
        setTimeout(() => { btnText.textContent = orig; }, 2000);
      }
    }

    function fallbackSelectCode() {
      const el = document.getElementById('pairing-code-label');
      const range = document.createRange();
      range.selectNodeContents(el);
      window.getSelection().removeAllRanges();
      window.getSelection().addRange(range);
    }

    async function generateNewPairingTicket() {
      const label = document.getElementById('modal-device-name-input').value.trim() || i18nMyDevice;
      const codeLabel = document.getElementById('pairing-code-label');
      const timerLabel = document.getElementById('pairing-timer-label');
      codeLabel.textContent = '...';

      try {
        const formData = new FormData();
        formData.append('ajax_action', 'generate_pairing_ticket');
        formData.append('device_name', label);
        formData.append('csrf_token', csrfToken);

        const res = await fetch('mobile_app.php', { method: 'POST', body: formData });
        const json = await res.json();

        if (json.status === 'ok') {
          codeLabel.textContent = json.ticket_code;
          secondsRemaining = json.seconds_left || 600;
          startCountdown();

          // Configura il pulsante di attivazione diretta per questo browser
          const btnDirect = document.getElementById('btn-direct-pair-here');
          btnDirect.onclick = function() {
            window.location.href = json.pwa_url + '?ticket=' + encodeURIComponent(json.ticket_code);
          };
        } else {
          codeLabel.textContent = 'Error';
          timerLabel.textContent = json.message || 'Error';
        }
      } catch (err) {
        codeLabel.textContent = 'Error';
        timerLabel.textContent = i18nErrConn;
      }
    }

    function startCountdown() {
      if (countdownInterval) clearInterval(countdownInterval);
      const timerLabel = document.getElementById('pairing-timer-label');
      countdownInterval = setInterval(() => {
        secondsRemaining--;
        if (secondsRemaining <= 0) {
          clearInterval(countdownInterval);
          timerLabel.textContent = i18nExpiredRegen;
          document.getElementById('pairing-code-label').textContent = i18nExpired;
        } else {
          const m = Math.floor(secondsRemaining / 60);
          const s = secondsRemaining % 60;
          const timeStr = `${m}:${s < 10 ? '0' : ''}${s}`;
          timerLabel.textContent = i18nExpiresInTpl.replace('{time}', timeStr);
        }
      }, 1000);
    }
  </script>
</body>
</html>
