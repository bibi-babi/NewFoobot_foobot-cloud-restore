<?php
/**
 * Web Dashboard NewFoobot
 * Pagina responsive avanzata con architettura SaaS (Tailwind CSS, Chart.js Annotation)
 * e soglie autentiche del firmware/app Airboxlab Foobot
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/nav.php';
$pdo = require_login();
$current_user = get_current_user_name();
$current_lang = get_current_lang();
$is_admin = is_admin_user();
$user_temp_unit = get_temperature_unit($pdo);

// Rilevamento dispositivi Foobot disponibili (Multi-Foobot) e selezione attiva
$available_foobots = get_available_foobot_devices($pdo);
$active_uuid = get_active_foobot_uuid($pdo);
$csrf_token = get_csrf_token();

// Gestione rinomina Foobot rapida da tendina (riservata all'Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'rename_foobot') {
    if ($is_admin && verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $r_uuid = trim($_POST['device_uuid'] ?? '');
        $r_name = trim($_POST['device_name'] ?? '');
        $r_loc  = trim($_POST['device_location'] ?? '');
        if ($r_uuid !== '' && $r_name !== '') {
            set_foobot_device_name($pdo, $r_uuid, $r_name, $r_loc);
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['success' => true, 'name' => $r_name]);
                exit;
            }
            header('Location: index.php?device=' . urlencode($r_uuid));
            exit;
        }
    }
}

// Web-Cron Autonomo: controlla backup programmato (orario e frequenza) e avviso email disconnessione (>30 min)
if (file_exists(__DIR__ . '/cron_events.php')) {
    require_once __DIR__ . '/cron_events.php';
    if (function_exists('maybe_trigger_auto_backup')) {
        maybe_trigger_auto_backup($pdo);
    }
    if (function_exists('maybe_check_offline_email_alert')) {
        maybe_check_offline_email_alert($pdo);
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($current_lang); ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars(__('app_title')); ?> - <?php echo htmlspecialchars(__('card_gpi_title')); ?></title>
  
  <!-- Librerie JavaScript Grafico Locali (Zero dipendenze esterne / Zero compilatori JS / Compatibile al 100% con VPN) -->
  <script src="assets/js/external/chart.min.js"></script>
  <script src="assets/js/external/chartjs-plugin-annotation.min.js"></script>
  
  <!-- Favicon Ufficiale Foobot SVG (Cerchietti Concentrici Azzurrini Blu) -->
  <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg?v=2">
  <link rel="apple-touch-icon" href="assets/img/favicon.png?v=2">
  
  <!-- Foglio di Stile CSS Puro (Zero dipendenze da framework JS esterni) e supporto temi -->
  <link rel="stylesheet" href="assets/css/style.css?v=32">
  <script src="assets/js/theme.js?v=8"></script>

  <style>
    body {
      font-family: 'Inter', sans-serif;
    }
    ::-webkit-scrollbar { width: 8px; height: 8px; }
    ::-webkit-scrollbar-track { background: #f1f5f9; }
    ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    
    /* Indicatore / Freccina sopra la barra di progresso */
    .bar-wrapper {
      position: relative;
      cursor: pointer;
      user-select: none;
    }
    .bar-indicator {
      position: absolute;
      top: -24px;
      transform: translateX(-50%);
      transition: left 0.5s ease-out;
      pointer-events: none;
      z-index: 10;
    }
    .bar-indicator-badge {
      font-size: 0.68rem;
      font-weight: 700;
      padding: 1px 6px;
      border-radius: 9999px;
      color: #ffffff;
      box-shadow: 0 1px 3px rgba(0,0,0,0.25);
      white-space: nowrap;
      position: relative;
      display: inline-block;
    }
    .bar-indicator-badge::after {
      content: '';
      position: absolute;
      top: 100%;
      left: 50%;
      transform: translateX(-50%);
      width: 0;
      height: 0;
      border-style: solid;
      border-width: 5px 4px 0 4px;

      /* FIX: Imposta tutto su trasparente, poi eredita solo il top dal parent */
      border-color: transparent;
      border-top-color: inherit;
    }

    /* Stili per datepicker */
    input[type="date"]::-webkit-inner-spin-button,
    input[type="date"]::-webkit-calendar-picker-indicator {
      opacity: 0.55;
      cursor: pointer;
      padding: 2px;
    }

    /* Nasconde la scrollbar orizzontale mantenendo lo scorrimento touch */
    .hide-scrollbar::-webkit-scrollbar {
      display: none;
    }
    .hide-scrollbar {
      -ms-overflow-style: none;
      scrollbar-width: none;
    }
  </style>
</head>
<body class="bg-blue-50 text-slate-900 min-h-screen p-3 md:p-8">

  <!-- Container Principale -->
  <div class="max-w-7xl mx-auto">
    
    <!-- Header con Comandi di Navigazione, Badge e Tendina -->
    <header class="dashboard-header mb-6">
      <div class="header-brand-wrap">
        <a href="index.php" class="header-brand-logo" title="<?php echo htmlspecialchars(__('app_title')); ?>">
          <?php echo get_foobot_logo_svg(44); ?>
        </a>
        <div>
          <h1><?php echo htmlspecialchars(__('app_title')); ?></h1>
          <p class="card-subtext"><?php echo htmlspecialchars(__('app_subtitle')); ?></p>
        </div>
      </div>
      <div class="header-right">
        <?php render_main_nav('home', $current_user); ?>
      </div>
    </header>

    <?php if (count($available_foobots) > 1): ?>
    <!-- Selettore Multi-Foobot: passaggio rapido tra i sensori fisici collegati (Casa, Ufficio, ecc.) -->
    <div class="mb-4 flex flex-wrap items-center gap-2" style="background:var(--input-bg); padding:6px 14px; border-radius:12px; border:1px solid var(--border-color);">
      <span style="font-size:0.75rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.5px; margin-right:4px;">Sensore Foobot:</span>
      <?php foreach ($available_foobots as $fb): 
        $is_curr = ($fb['device_uuid'] === $active_uuid);
      ?>
      <a href="?device=<?php echo urlencode($fb['device_uuid']); ?>" 
         class="nav-link" 
         title="Visualizza telemetrie di <?php echo htmlspecialchars($fb['device_name']); ?> (UUID: <?php echo htmlspecialchars($fb['device_uuid']); ?>)"
         style="padding:5px 12px; border-radius:8px; font-size:0.84rem; font-weight:600; text-decoration:none; display:inline-flex; align-items:center; gap:6px; <?php echo $is_curr ? 'background:var(--accent-blue); color:#fff; box-shadow:0 1px 3px rgba(37,99,235,0.3);' : 'color:var(--text-color);'; ?>">
        <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:<?php echo $fb['online'] ? '#10b981' : '#94a3b8'; ?>;"></span>
        <span><?php echo htmlspecialchars($fb['device_name']); ?></span>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php
    $active_fb_name = 'Foobot';
    $active_fb_loc = '';
    foreach ($available_foobots as $fb) {
        if ($fb['device_uuid'] === $active_uuid) {
            $active_fb_name = $fb['device_name'];
            $active_fb_loc = $fb['location'] ?? '';
            break;
        }
    }
    ?>

    <!-- Barra 100% Stato Dispositivo e Ultimo Aggiornamento Rilevato da Foobot -->
    <div class="foobot-status-bar-container w-full bg-white border border-slate-200/80 rounded-2xl p-3.5 mb-6 shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 text-sm">
      <!-- Sinistra: Box Stato Dispositivo con Tendina Informativa -->
      <div class="device-badge-wrapper" id="device-status-wrapper">
        <button type="button" class="device-badge-btn" id="device-badge-btn" aria-expanded="false" aria-haspopup="true">
          <span id="device-status-dot" class="status-dot nodata"></span>
          <span id="device-status-text"><?php echo htmlspecialchars($active_fb_name); ?>: <?php echo htmlspecialchars(__('status_verifying')); ?></span>
        </button>
        
        <div class="device-dropdown" id="device-dropdown">
          <h4>
            <span><?php echo htmlspecialchars(__('device_details_title')); ?></span>
            <span id="device-pill-status" class="badge">--</span>
          </h4>

          <div class="device-dropdown-row">
            <span class="device-dropdown-label"><?php echo htmlspecialchars(__('drop_device_name')); ?></span>
            <span class="device-dropdown-val" style="display:inline-flex; align-items:center; gap:6px;">
              <strong id="drop-device-name"><?php echo htmlspecialchars($active_fb_name); ?></strong>
              <?php if ($is_admin): ?>
              <button type="button" 
                      onclick="openRenameFoobotModal('<?php echo htmlspecialchars($active_uuid); ?>', '<?php echo htmlspecialchars(addslashes($active_fb_name)); ?>', '<?php echo htmlspecialchars(addslashes($active_fb_loc)); ?>')" 
                      title="<?php echo htmlspecialchars(__('drop_edit_title')); ?>" 
                      style="background:none; border:none; color:var(--accent-blue, #2563eb); cursor:pointer; padding:2px; display:inline-flex; align-items:center;">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
              </button>
              <?php endif; ?>
            </span>
          </div>

          <div class="device-dropdown-row" id="drop-row-location" style="<?php echo empty($active_fb_loc) ? 'display:none;' : ''; ?>">
            <span class="device-dropdown-label"><?php echo htmlspecialchars(__('drop_location')); ?></span>
            <span class="device-dropdown-val" id="drop-device-location"><?php echo htmlspecialchars($active_fb_loc); ?></span>
          </div>

          <div class="device-dropdown-row">
            <span class="device-dropdown-label"><?php echo htmlspecialchars(__('drop_connection_status')); ?></span>
            <span class="device-dropdown-val" id="drop-status"><?php echo htmlspecialchars(__('status_waiting')); ?></span>
          </div>
          <?php if ($is_admin): ?>
          <div class="device-dropdown-row">
            <span class="device-dropdown-label"><?php echo htmlspecialchars(__('drop_device_uuid')); ?></span>
            <span class="device-dropdown-val" id="drop-uuid"><?php echo htmlspecialchars($active_uuid); ?></span>
          </div>
          <?php endif; ?>
          <div class="device-dropdown-row">
            <span class="device-dropdown-label"><?php echo htmlspecialchars(__('drop_last_seen')); ?></span>
            <span class="device-dropdown-val" id="drop-last-seen">--</span>
          </div>
          <div class="device-dropdown-row">
            <span class="device-dropdown-label"><?php echo htmlspecialchars(__('drop_last_connected')); ?></span>
            <span class="device-dropdown-val" id="drop-last-connected">--</span>
          </div>
          <div class="device-dropdown-row" id="drop-row-disconnected" style="display:none;">
            <span class="device-dropdown-label"><?php echo htmlspecialchars(__('drop_last_disconnected')); ?></span>
            <span class="device-dropdown-val" id="drop-last-disconnected" style="color:var(--accent-danger);">--</span>
          </div>
          <div class="device-dropdown-row">
            <span class="device-dropdown-label"><?php echo htmlspecialchars(__('drop_count_24h')); ?></span>
            <span class="device-dropdown-val" id="drop-count-24h">0</span>
          </div>
          <div class="device-dropdown-row">
            <span class="device-dropdown-label"><?php echo htmlspecialchars(__('drop_count_total')); ?></span>
            <span class="device-dropdown-val" id="drop-count-total">0</span>
          </div>

          <?php if (count($available_foobots) > 1): ?>
          <div style="margin-top:10px; padding-top:8px; border-top:1px solid var(--border-color, #e2e8f0);">
            <div style="font-size:0.73rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; margin-bottom:6px;"><?php echo htmlspecialchars(__('drop_other_devices')); ?></div>
            <div style="display:flex; flex-direction:column; gap:4px;">
              <?php foreach ($available_foobots as $fb): 
                $is_curr = ($fb['device_uuid'] === $active_uuid);
              ?>
              <div style="display:flex; align-items:center; justify-content:space-between; padding:4px 6px; border-radius:6px; background:<?php echo $is_curr ? 'var(--input-bg, #f1f5f9)' : 'transparent'; ?>;">
                <a href="?device=<?php echo urlencode($fb['device_uuid']); ?>" style="text-decoration:none; font-size:0.8rem; font-weight:600; color:var(--text-color); display:flex; align-items:center; gap:6px;">
                  <span style="display:inline-block; width:7px; height:7px; border-radius:50%; background:<?php echo $fb['online'] ? '#10b981' : '#94a3b8'; ?>;"></span>
                  <span><?php echo htmlspecialchars($fb['device_name']); ?></span>
                  <?php if ($is_curr): ?><span style="font-size:0.7rem; color:var(--accent-blue);">(<?php echo htmlspecialchars(__('drop_active_badge')); ?>)</span><?php endif; ?>
                </a>
                <?php if ($is_admin): ?>
                <button type="button" 
                        onclick="openRenameFoobotModal('<?php echo htmlspecialchars($fb['device_uuid']); ?>', '<?php echo htmlspecialchars(addslashes($fb['device_name'])); ?>', '<?php echo htmlspecialchars(addslashes($fb['location'] ?? '')); ?>')" 
                        title="Modifica nome <?php echo htmlspecialchars($fb['device_name']); ?>" 
                        style="background:none; border:none; color:var(--text-muted); cursor:pointer; padding:2px;">
                  <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                </button>
                <?php endif; ?>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>

        </div>
      </div>

      <!-- Destra: Badge Intervallo seguito dall'Orario Ultimo Dato (senza secondi) -->
      <div class="flex items-center gap-3">
        <div class="flex items-center gap-2 text-xs text-slate-500 bg-slate-50 border border-slate-200/60 px-3 py-1.5 rounded-xl">
          <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
          <span><?php echo htmlspecialchars(__('banner_interval_note')); ?></span>
        </div>
        <strong id="banner-lastupdate" class="text-slate-900 font-bold text-sm tracking-tight"><?php echo htmlspecialchars(__('banner_waiting_data')); ?></strong>
      </div>
    </div>

    <!-- Menu Navigazione Sensori (Tabs con Icone a Sinistra) -->
    <div class="flex flex-wrap gap-2 mb-6 border-b border-slate-200 pb-4" id="sensor-tabs">
      <button data-tab="tutti" class="tab-btn active inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold bg-blue-600 text-white transition-all">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
        <span><?php echo htmlspecialchars(__('tab_complete_view')); ?></span>
      </button>
      <button data-tab="inquinamento" class="tab-btn inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium bg-white text-slate-600 hover:bg-slate-100 border border-slate-200 transition-all">
        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        <span><?php echo htmlspecialchars(__('tab_gpi')); ?></span>
      </button>
      <button data-tab="voc" class="tab-btn inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium bg-white text-slate-600 hover:bg-slate-100 border border-slate-200 transition-all">
        <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
        <span><?php echo htmlspecialchars(__('tab_voc')); ?></span>
      </button>
      <button data-tab="pm25" class="tab-btn inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium bg-white text-slate-600 hover:bg-slate-100 border border-slate-200 transition-all">
        <svg class="w-4 h-4 text-blue-500" fill="currentColor" viewBox="0 0 24 24"><circle cx="4" cy="7" r="2.2"></circle><circle cx="11" cy="5" r="1.5"></circle><circle cx="18" cy="8" r="2.5"></circle><circle cx="8" cy="12" r="2"></circle><circle cx="15" cy="14" r="1.8"></circle><circle cx="5" cy="18" r="1.5"></circle><circle cx="12" cy="19" r="2.2"></circle><circle cx="19" cy="17" r="1.6"></circle></svg>
        <span><?php echo htmlspecialchars(__('tab_pm25')); ?></span>
      </button>
      <button data-tab="co2" class="tab-btn inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium bg-white text-slate-600 hover:bg-slate-100 border border-slate-200 transition-all">
        <svg class="w-4 h-4 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"></path></svg>
        <span><?php echo htmlspecialchars(__('tab_eco2')); ?></span>
      </button>
      <button data-tab="temp" class="tab-btn inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium bg-white text-slate-600 hover:bg-slate-100 border border-slate-200 transition-all">
        <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 14.76V5a2 2 0 10-4 0v9.76a4 4 0 104 0z"></path><circle cx="12" cy="17" r="1.5" fill="currentColor"></circle></svg>
        <span><?php echo htmlspecialchars(__('tab_temp')); ?></span>
      </button>
      <button data-tab="umidita" class="tab-btn inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium bg-white text-slate-600 hover:bg-slate-100 border border-slate-200 transition-all">
        <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 14c0 3.313-2.687 6-6 6s-6-2.687-6-6 6-10 6-10 6 6.687 6 10z"></path></svg>
        <span><?php echo htmlspecialchars(__('tab_hum')); ?></span>
      </button>
    </div>

    <!-- Griglia Schede Valori (KPI Cards) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 md:gap-6 mb-8" id="cards-container">
      
      <!-- Card 1: GPI - Indice Inquinamento -->
      <div id="card-inquinamento" class="sensor-card bg-white p-5 rounded-2xl shadow-sm border border-slate-200/60 hover:shadow-md transition-all">
        <div class="flex justify-between items-start mb-3">
          <div class="flex items-center gap-3 flex-1 min-w-0">
            <div class="p-2 bg-emerald-50 text-emerald-600 rounded-xl">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <div class="flex flex-col items-start gap-1.5 min-w-0">
              <h3 class="text-sm font-semibold text-slate-600 uppercase tracking-wide"><?php echo htmlspecialchars(__('card_gpi_title')); ?></h3>
              <span id="badge-allpollu" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold">--</span>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <button type="button" class="text-slate-400 hover:text-blue-600 transition-colors p-1" onclick="openInfoModal('allpollu')" title="Spiegazione e soglie Foobot">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"></circle><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 16v-4m0-4h.01"></path></svg>
            </button>
          </div>
        </div>

        <div class="flex items-baseline gap-2">
          <span class="text-4xl font-extrabold text-slate-900 tracking-tight"><span id="val-allpollu">--</span><span class="text-2xl text-slate-500 font-medium ml-1">%</span></span>
        </div>
        <p class="text-xs text-slate-500 mt-2"><?php echo htmlspecialchars(__('card_gpi_sub')); ?></p>
        
        <div class="mt-7 w-full bar-wrapper" onclick="showBarTooltip(event, 'allpollu')">
          <div id="indicator-allpollu" class="bar-indicator" style="left: 0%;">
            <span id="indicator-badge-allpollu" class="bar-indicator-badge bg-emerald-500" style="border-top-color: #10b981; background-color: #10b981;">0%</span>
          </div>
          <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
            <div id="bar-allpollu" class="h-2 rounded-full transition-all duration-500 bg-emerald-500" style="width: 0%"></div>
          </div>
        </div>
      </div>

      <!-- Card 2: VOC - Composti Volatili -->
      <div id="card-voc" class="sensor-card bg-white p-5 rounded-2xl shadow-sm border border-slate-200/60 hover:shadow-md transition-all">
        <div class="flex justify-between items-start mb-3">
          <div class="flex items-center gap-3 flex-1 min-w-0">
            <div class="p-2 bg-purple-50 text-purple-600 rounded-xl">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
            </div>
            <div class="flex flex-col items-start gap-1.5 min-w-0">
              <h3 class="text-sm font-semibold text-slate-600 uppercase tracking-wide"><?php echo htmlspecialchars(__('card_voc_title')); ?></h3>
              <span id="badge-voc" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold">--</span>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <button type="button" class="text-slate-400 hover:text-blue-600 transition-colors p-1" onclick="openInfoModal('voc')" title="Spiegazione e soglie Foobot">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"></circle><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 16v-4m0-4h.01"></path></svg>
            </button>
          </div>
        </div>

        <div class="flex items-baseline gap-2">
          <span class="text-4xl font-extrabold text-slate-900 tracking-tight"><span id="val-voc">--</span><span class="text-xl text-slate-500 font-medium ml-1">ppb</span></span>
        </div>
        <p class="text-xs text-slate-500 mt-2"><?php echo htmlspecialchars(__('card_voc_sub')); ?></p>
        
        <div class="mt-7 w-full bar-wrapper" onclick="showBarTooltip(event, 'voc')">
          <div id="indicator-voc" class="bar-indicator" style="left: 0%;">
            <span id="indicator-badge-voc" class="bar-indicator-badge bg-emerald-500" style="border-top-color: #10b981; background-color: #10b981;">0 ppb</span>
          </div>
          <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
            <div id="bar-voc" class="h-2 rounded-full transition-all duration-500 bg-emerald-500" style="width: 0%"></div>
          </div>
        </div>
      </div>

      <!-- Card 3: PM2.5 - Particolato Fine -->
      <div id="card-pm25" class="sensor-card bg-white p-5 rounded-2xl shadow-sm border border-slate-200/60 hover:shadow-md transition-all">
        <div class="flex justify-between items-start mb-3">
          <div class="flex items-center gap-3 flex-1 min-w-0">
            <div class="p-2 bg-blue-50 text-blue-600 rounded-xl">
              <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><circle cx="4" cy="7" r="2.2"></circle><circle cx="11" cy="5" r="1.5"></circle><circle cx="18" cy="8" r="2.5"></circle><circle cx="8" cy="12" r="2"></circle><circle cx="15" cy="14" r="1.8"></circle><circle cx="5" cy="18" r="1.5"></circle><circle cx="12" cy="19" r="2.2"></circle><circle cx="19" cy="17" r="1.6"></circle></svg>
            </div>
            <div class="flex flex-col items-start gap-1.5 min-w-0">
              <h3 class="text-sm font-semibold text-slate-600 uppercase tracking-wide"><?php echo htmlspecialchars(__('card_pm_title')); ?></h3>
              <span id="badge-pm" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold">--</span>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <button type="button" class="text-slate-400 hover:text-blue-600 transition-colors p-1" onclick="openInfoModal('pm')" title="Spiegazione e soglie Foobot">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"></circle><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 16v-4m0-4h.01"></path></svg>
            </button>
          </div>
        </div>

        <div class="flex items-baseline gap-2">
          <span class="text-4xl font-extrabold text-slate-900 tracking-tight"><span id="val-pm">--</span><span class="text-xl text-slate-500 font-medium ml-1">µg/m³</span></span>
        </div>
        <p class="text-xs text-slate-500 mt-2"><?php echo htmlspecialchars(__('card_pm_sub')); ?></p>
        
        <div class="mt-7 w-full bar-wrapper" onclick="showBarTooltip(event, 'pm')">
          <div id="indicator-pm" class="bar-indicator" style="left: 0%;">
            <span id="indicator-badge-pm" class="bar-indicator-badge bg-emerald-500" style="border-top-color: #10b981; background-color: #10b981;">0.0 µg/m³</span>
          </div>
          <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
            <div id="bar-pm" class="h-2 rounded-full transition-all duration-500 bg-emerald-500" style="width: 0%"></div>
          </div>
        </div>
      </div>

      <!-- Card 4: eCO2 - CO2 Equivalente -->
      <div id="card-co2" class="sensor-card bg-white p-5 rounded-2xl shadow-sm border border-slate-200/60 hover:shadow-md transition-all">
        <div class="flex justify-between items-start mb-3">
          <div class="flex items-center gap-3 flex-1 min-w-0">
            <div class="p-2 bg-cyan-50 text-cyan-600 rounded-xl">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"></path></svg>
            </div>
            <div class="flex flex-col items-start gap-1.5 min-w-0">
              <h3 class="text-sm font-semibold text-slate-600 uppercase tracking-wide"><?php echo htmlspecialchars(__('card_co2_title')); ?></h3>
              <span id="badge-co2" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold">--</span>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <button type="button" class="text-slate-400 hover:text-blue-600 transition-colors p-1" onclick="openInfoModal('co2')" title="Spiegazione e soglie Foobot">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"></circle><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 16v-4m0-4h.01"></path></svg>
            </button>
          </div>
        </div>

        <div class="flex items-baseline gap-2">
          <span class="text-4xl font-extrabold text-slate-900 tracking-tight"><span id="val-co2">--</span><span class="text-xl text-slate-500 font-medium ml-1">ppm</span></span>
        </div>
        <p class="text-xs text-slate-500 mt-2"><?php echo htmlspecialchars(__('card_co2_sub')); ?></p>
        
        <div class="mt-7 w-full bar-wrapper" onclick="showBarTooltip(event, 'co2')">
          <div id="indicator-co2" class="bar-indicator" style="left: 0%;">
            <span id="indicator-badge-co2" class="bar-indicator-badge bg-emerald-500" style="border-top-color: #10b981; background-color: #10b981;">450 ppm</span>
          </div>
          <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
            <div id="bar-co2" class="h-2 rounded-full transition-all duration-500 bg-emerald-500" style="width: 0%"></div>
          </div>
        </div>
      </div>

      <!-- Card 5: TMP - Temperatura -->
      <div id="card-temp" class="sensor-card bg-white p-5 rounded-2xl shadow-sm border border-slate-200/60 hover:shadow-md transition-all">
        <div class="flex justify-between items-start mb-3">
          <div class="flex items-center gap-3 flex-1 min-w-0">
            <div class="p-2 bg-red-50 text-red-500 rounded-xl">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 14.76V5a2 2 0 10-4 0v9.76a4 4 0 104 0z"></path><circle cx="12" cy="17" r="1.5" fill="currentColor"></circle></svg>
            </div>
            <div class="flex flex-col items-start gap-1.5 min-w-0">
              <h3 class="text-sm font-semibold text-slate-600 uppercase tracking-wide"><?php echo htmlspecialchars(__('card_temp_title')); ?></h3>
              <span id="badge-temp" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold">--</span>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <button type="button" class="text-slate-400 hover:text-blue-600 transition-colors p-1" onclick="openInfoModal('temp')" title="Spiegazione e soglie Foobot">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"></circle><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 16v-4m0-4h.01"></path></svg>
            </button>
          </div>
        </div>

        <div class="flex items-baseline gap-2">
          <span class="text-4xl font-extrabold text-slate-900 tracking-tight"><span id="val-tmp">--</span><span class="text-xl text-slate-500 font-medium ml-1" id="unit-tmp">°<?php echo $user_temp_unit; ?></span></span>
        </div>
        <p class="text-xs text-slate-500 mt-2" id="sub-tmp"><?php echo htmlspecialchars($user_temp_unit === 'F' ? __('card_temp_sub_f', 'Temperatura ambiente — Scala barra: 50 - 95 °F') : __('card_temp_sub', 'Temperatura ambiente — Scala barra: 10 - 35 °C')); ?></p>
        
        <div class="mt-7 w-full bar-wrapper" onclick="showBarTooltip(event, 'temp')">
          <div id="indicator-temp" class="bar-indicator" style="left: 0%;">
            <span id="indicator-badge-temp" class="bar-indicator-badge bg-emerald-500" style="border-top-color: #10b981; background-color: #10b981;"><?php echo $user_temp_unit === 'F' ? '68.0 °F' : '20.0 °C'; ?></span>
          </div>
          <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
            <div id="bar-temp" class="h-2 rounded-full transition-all duration-500 bg-emerald-500" style="width: 40%"></div>
          </div>
        </div>
      </div>

      <!-- Card 6: HUM - Umidità Relativa -->
      <div id="card-umidita" class="sensor-card bg-white p-5 rounded-2xl shadow-sm border border-slate-200/60 hover:shadow-md transition-all">
        <div class="flex justify-between items-start mb-3">
          <div class="flex items-center gap-3 flex-1 min-w-0">
            <div class="p-2 bg-orange-50 text-orange-600 rounded-xl">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 14c0 3.313-2.687 6-6 6s-6-2.687-6-6 6-10 6-10 6 6.687 6 10z"></path></svg>
            </div>
            <div class="flex flex-col items-start gap-1.5 min-w-0">
              <h3 class="text-sm font-semibold text-slate-600 uppercase tracking-wide"><?php echo htmlspecialchars(__('card_hum_title')); ?></h3>
              <span id="badge-hum" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold">--</span>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <button type="button" class="text-slate-400 hover:text-blue-600 transition-colors p-1" onclick="openInfoModal('hum')" title="Spiegazione e soglie Foobot">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"></circle><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 16v-4m0-4h.01"></path></svg>
            </button>
          </div>
        </div>

        <div class="flex items-baseline gap-2">
          <span class="text-4xl font-extrabold text-slate-900 tracking-tight"><span id="val-hum">--</span><span class="text-xl text-slate-500 font-medium ml-1">%</span></span>
        </div>
        <p class="text-xs text-slate-500 mt-2"><?php echo htmlspecialchars(__('card_hum_sub')); ?></p>
        
        <div class="mt-7 w-full bar-wrapper" onclick="showBarTooltip(event, 'hum')">
          <div id="indicator-hum" class="bar-indicator" style="left: 0%;">
            <span id="indicator-badge-hum" class="bar-indicator-badge bg-emerald-500" style="border-top-color: #10b981; background-color: #10b981;">50%</span>
          </div>
          <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
            <div id="bar-hum" class="h-2 rounded-full transition-all duration-500 bg-emerald-500" style="width: 50%"></div>
          </div>
        </div>
      </div>

    </div>

    <!-- Area Grafico Storico con Controlli Flessibili -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/60 p-5 md:p-6 mb-8">
      
      <!-- HEADER -->
      <div class="pb-4 mb-4 border-b border-slate-100">
        <h2 class="text-lg md:text-xl font-bold text-slate-900"><?php echo htmlspecialchars(__('chart_time_trend')); ?></h2>
        <p class="text-xs md:text-sm text-slate-500 mt-1"><?php echo htmlspecialchars(__('chart_subtitle')); ?></p>
      </div>

      <!-- RIGA 1: FILTRI TEMPORALI E INTERVALLO DATE -->
      <div class="pb-4 mb-4 border-b border-slate-100">
        <div class="flex flex-col gap-3">
          
          <!-- 1. Selezione Rapida (con andata a capo automatica su mobile e tablet) -->
          <div class="flex items-center gap-2 w-full">
            <div class="w-full">
              <div id="chart-time-ranges" class="inline-flex flex-wrap items-center bg-slate-100 p-1 rounded-xl border border-slate-200/80 gap-1">
                <button type="button" class="range-btn px-3 py-1.5 rounded-lg text-xs sm:text-sm font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-200/50 transition-colors whitespace-nowrap" onclick="switchRange('minuti', this)"><?php echo htmlspecialchars(__('range_minutes')); ?></button>
                <button type="button" class="range-btn px-3 py-1.5 rounded-lg text-xs sm:text-sm font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-200/50 transition-colors whitespace-nowrap" onclick="switchRange('ore', this)"><?php echo htmlspecialchars(__('range_hours')); ?></button>
                <button type="button" class="range-btn active px-3 py-1.5 bg-white rounded-lg text-xs sm:text-sm font-semibold text-blue-600 shadow-sm border border-slate-200 transition-all whitespace-nowrap" onclick="switchRange('24h', this)"><?php echo htmlspecialchars(__('range_24h')); ?></button>
                <button type="button" class="range-btn px-3 py-1.5 rounded-lg text-xs sm:text-sm font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-200/50 transition-colors whitespace-nowrap" onclick="switchRange('7d', this)">7 <?php echo htmlspecialchars(__('unit_days_short')); ?></button>
                <button type="button" class="range-btn px-3 py-1.5 rounded-lg text-xs sm:text-sm font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-200/50 transition-colors whitespace-nowrap" onclick="switchRange('10d', this)">10 <?php echo htmlspecialchars(__('unit_days_short')); ?></button>
                <button type="button" class="range-btn px-3 py-1.5 rounded-lg text-xs sm:text-sm font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-200/50 transition-colors whitespace-nowrap" onclick="switchRange('30d', this)">30 <?php echo htmlspecialchars(__('unit_days_short')); ?></button>
                <button type="button" class="range-btn px-3 py-1.5 rounded-lg text-xs sm:text-sm font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-200/50 transition-colors whitespace-nowrap" onclick="switchRange('settimane', this)"><?php echo htmlspecialchars(__('range_weeks')); ?></button>
                
                <!-- Input N Giorni Personalizzati -->
                <div id="custom-days-box">
                  <div class="custom-days-field-wrap">
                    <input type="number" min="1" max="90" placeholder="N°" id="custom-days-input" onkeydown="if(event.key==='Enter') submitCustomDays();">
                    <span id="custom-days-label"><?php echo htmlspecialchars(__('unit_days_short')); ?></span>
                  </div>
                  <button type="button" id="custom-days-btn" onclick="submitCustomDays()"><?php echo htmlspecialchars(__('range_go')); ?></button>
                </div>
              </div>
            </div>
          </div>

          <!-- Contenitore flessibile per Date + Navigazione Temporale (sempre a capo su riga dedicata) -->
          <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 w-full">
            
            <!-- 2. Date Range Picker conforme allo stile degli altri pulsanti -->
            <div id="date-range-filter-box" class="w-full lg:w-auto">
              <div class="date-picker-group">
                <div class="date-input-pill">
                  <span class="date-input-label"><?php echo htmlspecialchars(__('date_from_label')); ?></span>
                  <input type="date" id="chart-date-from" max="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="date-input-pill">
                  <span class="date-input-label"><?php echo htmlspecialchars(__('date_to_label')); ?></span>
                  <input type="date" id="chart-date-to" max="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="date-actions-wrap">
                  <button type="button" id="btn-apply-daterange" onclick="applyCustomDateRange()" title="<?php echo htmlspecialchars(__('btn_filter_title')); ?>">
                    <?php echo htmlspecialchars(__('btn_filter')); ?>
                  </button>
                  <button type="button" id="btn-reset-daterange" onclick="resetCustomDateRange()" style="display:none;" title="<?php echo htmlspecialchars(__('btn_reset_daterange_title')); ?>">
                    ✕ <?php echo htmlspecialchars(__('btn_reset_daterange')); ?>
                  </button>
                </div>
              </div>
            </div>

            <!-- 3. Navigazione e Tempo Reale -->
            <div id="chart-nav-bar" class="flex flex-wrap items-center gap-1.5 bg-slate-100 p-1 rounded-xl border border-slate-200/80 w-full lg:w-auto justify-between lg:justify-start">
              <button type="button" id="btn-chart-prev" onclick="shiftChartTime(1)" class="chart-nav-btn" title="<?php echo htmlspecialchars(__('nav_prev_period')); ?>">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
              </button>
              <span id="chart-period-label" class="px-3 py-1 text-xs sm:text-sm font-semibold text-slate-700 text-center flex-1 sm:flex-initial"><?php echo htmlspecialchars(__('nav_realtime')); ?></span>
              <button type="button" id="btn-chart-next" onclick="shiftChartTime(-1)" disabled class="chart-nav-btn" title="<?php echo htmlspecialchars(__('nav_next_period')); ?>">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 6 15 12 9 18"></polyline></svg>
              </button>
              <button type="button" id="btn-chart-live" onclick="resetChartTime()" style="display:none;" class="px-2.5 py-1 text-xs font-bold text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-lg border border-blue-200 transition-colors" title="<?php echo htmlspecialchars(__('nav_back_to_today')); ?>">
                <?php echo htmlspecialchars(__('nav_today')); ?>
              </button>
            </div>

          </div>
        </div>
      </div>

      <!-- RIGA 2: GRAFICI ATTIVI (PARAMETRI) -->
      <div class="py-3 px-3.5 mb-5 rounded-xl bg-slate-50 border border-slate-100 flex flex-col md:flex-row md:items-center gap-2 md:gap-3">
        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider whitespace-nowrap"><?php echo htmlspecialchars(__('chart_active_graphs')); ?></span>
        
        <!-- Contenitore con andata a capo automatica su mobile e tablet -->
        <div class="w-full">
          <div id="chart-legend-container" class="flex flex-wrap items-center gap-2 md:gap-2.5 w-full"></div>
        </div>
      </div>

      <!-- AREA GRAFICO (con assi Y sinistro/destro fissi e solo area dati scrollabile orizzontalmente) -->
      <div id="chart-viewport-shell" class="relative w-full" style="position: relative; width: 100%; height: 350px; min-height: 280px; overflow: hidden;">
        <!-- Colonna Asse Y Sinistra Fissa -->
        <div id="chart-sticky-y-left" style="position: absolute; left: 0; top: 0; bottom: 38px; width: 38px; z-index: 20; pointer-events: none; display: none;"></div>
        <!-- Colonna Asse Y Destra Fissa -->
        <div id="chart-sticky-y-right" style="position: absolute; right: 0; top: 0; bottom: 38px; width: 42px; z-index: 20; pointer-events: none; display: none;"></div>
        <!-- Overlay Etichette Soglie Fisse -->
        <div id="chart-sticky-zones-labels" style="position: absolute; left: 42px; right: 44px; top: 0; bottom: 38px; z-index: 15; pointer-events: none; overflow: hidden;"></div>
        <!-- Contenitore Scrollabile Orizzontale -->
        <div id="chart-canvas-wrapper" class="relative w-full h-full hide-scrollbar" style="position: relative; width: 100%; height: 100%; overflow-x: auto; overflow-y: hidden; -webkit-overflow-scrolling: touch;">
          <div id="chart-inner-container" style="position: relative; height: 100%; width: 100%; min-width: 100%;">
            <canvas id="trendChart" style="display: block; width: 100%; height: 100%;"></canvas>
          </div>
        </div>
      </div>

      <!-- Box Ispezione Dettaglio Punto Grafico per Schermi Mobile (Posizionato fuori dal canvas per evitare tagli) -->
      <div id="mobile-chart-inspector" class="mobile-chart-inspector" style="display: none;"></div>

      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 mt-4 pt-3 border-t border-slate-100 text-xs text-slate-500">
        <span><?php echo htmlspecialchars(__('chart_auto_refresh')); ?></span>
        <span><?php echo htmlspecialchars(__('chart_last_data')); ?> <strong id="val-lastupdate" class="text-slate-700">--</strong></span>
      </div>
    </div>

  </div>

  <!-- Modal Informativo Dettagli Soglie e Spiegazioni Ufficiali Foobot -->
  <div id="info-modal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-100 transform transition-all" onclick="event.stopPropagation()">
      <div class="flex justify-between items-start mb-4">
        <div class="flex items-center gap-3">
          <div id="modal-icon" class="p-2 rounded-xl"></div>
          <div>
            <h3 id="modal-title" class="text-lg font-bold text-slate-900"></h3>
            <p id="modal-subtitle" class="text-xs text-slate-500"></p>
          </div>
        </div>
        <button type="button" class="text-slate-400 hover:text-slate-700 p-1" onclick="closeInfoModal()" aria-label="Chiudi finestra">
          <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:block;"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        </button>
      </div>

      <div class="text-sm text-slate-600 mb-5 leading-relaxed" id="modal-description"></div>

      <h4 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2"><?php echo htmlspecialchars(__('modal_official_thresholds')); ?></h4>
      <div class="space-y-2 mb-6" id="modal-thresholds"></div>

      <div class="flex justify-end">
        <button type="button" class="btn-modal-understand" onclick="closeInfoModal()">
          <?php echo htmlspecialchars(__('modal_i_understand')); ?>
        </button>
      </div>
    </div>
  </div>

  <!-- Iniezione Dizionario di Traduzione per il frontend JavaScript -->
  <script>
    window.FOOBOT_I18N = <?php echo json_encode(get_translations(), JSON_UNESCAPED_UNICODE); ?>;
    window.CURRENT_DEVICE_UUID = <?php echo json_encode($active_uuid); ?>;
    window.FOOBOT_TEMP_UNIT = <?php echo json_encode($user_temp_unit); ?>;
  </script>
  <script src="assets/js/dashboard.js?v=46"></script>

  <?php if ($is_admin): ?>
  <!-- Modale Rinomina Foobot (Solo Admin) -->
  <div id="rename-foobot-modal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[150] hidden flex items-center justify-center p-4" style="z-index: 9999;">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-100" onclick="event.stopPropagation()">
      <div class="flex justify-between items-center mb-4 pb-2 border-b border-slate-100">
        <h3 class="text-base font-bold text-slate-900"><?php echo htmlspecialchars(__('rename_modal_title')); ?></h3>
        <button type="button" class="text-slate-400 hover:text-slate-700 p-1" onclick="closeRenameFoobotModal()">
          <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        </button>
      </div>
      <form method="POST" id="form-rename-foobot">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
        <input type="hidden" name="action" value="rename_foobot">
        <input type="hidden" name="device_uuid" id="rename-modal-uuid" value="">

        <div class="mb-4">
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1"><?php echo htmlspecialchars(__('rename_device_name_label')); ?></label>
          <input type="text" name="device_name" id="rename-modal-name" required class="w-full px-3 py-2 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-blue-500" placeholder="<?php echo htmlspecialchars(__('rename_name_placeholder')); ?>">
        </div>

        <div class="mb-5">
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1"><?php echo htmlspecialchars(__('rename_location_label')); ?></label>
          <input type="text" name="device_location" id="rename-modal-loc" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-blue-500" placeholder="<?php echo htmlspecialchars(__('rename_loc_placeholder')); ?>">
        </div>

        <div class="flex justify-end gap-2">
          <button type="button" class="px-4 py-2 rounded-xl text-sm font-semibold border border-slate-200 text-slate-600 hover:bg-slate-50" onclick="closeRenameFoobotModal()"><?php echo htmlspecialchars(__('rename_btn_cancel')); ?></button>
          <button type="submit" class="px-4 py-2 rounded-xl text-sm font-semibold bg-blue-600 text-white hover:bg-blue-700 shadow-sm"><?php echo htmlspecialchars(__('rename_btn_save')); ?></button>
        </div>
      </form>
    </div>
  </div>
  <script>
    function openRenameFoobotModal(uuid, currentName, currentLoc) {
      // Chiude la tendina dei dettagli del sensore per liberare la visuale
      const drop = document.getElementById('device-dropdown');
      if (drop) drop.classList.remove('show');
      const badgeBtn = document.getElementById('device-badge-btn');
      if (badgeBtn) badgeBtn.setAttribute('aria-expanded', 'false');

      document.getElementById('rename-modal-uuid').value = uuid;
      document.getElementById('rename-modal-name').value = currentName || '';
      document.getElementById('rename-modal-loc').value = currentLoc || '';
      const m = document.getElementById('rename-foobot-modal');
      if (m) m.classList.remove('hidden');
    }
    function closeRenameFoobotModal() {
      const m = document.getElementById('rename-foobot-modal');
      if (m) m.classList.add('hidden');
    }
  </script>
  <?php endif; ?>
</body>
</html>
