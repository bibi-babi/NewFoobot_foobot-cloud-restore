<?php
/**
 * Guida Ufficiale e Manuale d'Uso del Portale NewFoobot
 * Accessibile a tutti gli utenti registrati (Amministratori e Visualizzatori)
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/nav.php';

$pdo = require_login();
$current_user = get_current_user_name();
$current_lang = get_current_lang();
$is_admin = is_admin_user();
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($current_lang); ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars(__('guide_title')); ?> — <?php echo htmlspecialchars(__('app_title')); ?></title>
  
  <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg?v=2">
  <link rel="apple-touch-icon" href="assets/img/favicon.png?v=2">
  <link rel="stylesheet" href="assets/css/style.css?v=25">
  <script src="assets/js/theme.js?v=8"></script>

  <style>
    .guide-hero {
      background: linear-gradient(135deg, rgba(37, 99, 235, 0.08) 0%, rgba(56, 189, 248, 0.04) 100%);
      border: 1px solid rgba(37, 99, 235, 0.2);
      border-radius: 16px;
      padding: 24px 28px;
      margin-bottom: 24px;
    }
    .guide-section {
      background: var(--card-bg, #ffffff);
      border: 1px solid var(--border-color, #e2e8f0);
      border-radius: 16px;
      padding: 24px;
      margin-bottom: 24px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    }
    .guide-section h2 {
      font-size: 1.25rem;
      font-weight: 700;
      color: var(--text-color, #0f172a);
      display: flex;
      align-items: center;
      gap: 10px;
      margin: 0 0 14px 0;
      padding-bottom: 12px;
      border-bottom: 1px solid var(--border-color, #e2e8f0);
    }
    .guide-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 16px;
      margin-top: 14px;
    }
    .guide-card-box {
      background: var(--input-bg, #f8fafc);
      border: 1px solid var(--border-color, #e2e8f0);
      border-radius: 12px;
      padding: 16px;
    }
    .guide-badge-pill {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 3px 8px;
      border-radius: 999px;
      font-size: 0.72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .badge-all {
      background: #e0f2fe;
      color: #0369a1;
    }
    .badge-admin-only {
      background: #fef3c7;
      color: #b45309;
    }
    .badge-accent {
      background: #dbeafe;
      color: #1d4ed8;
    }
    .guide-icon-box {
      width: 36px;
      height: 36px;
      border-radius: 10px;
      background: rgba(37, 99, 235, 0.12);
      color: var(--accent-blue, #2563eb);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }
    .guide-param-row {
      display: flex;
      align-items: flex-start;
      gap: 14px;
      padding: 12px 0;
      border-bottom: 1px dashed var(--border-color, #e2e8f0);
    }
    .guide-param-row:last-child {
      border-bottom: none;
    }
    .guide-code-chip {
      background: var(--bg-hover, #f1f5f9);
      border: 1px solid var(--border-color, #cbd5e1);
      padding: 2px 6px;
      border-radius: 6px;
      font-family: monospace;
      font-size: 0.85rem;
      color: var(--text-color, #0f172a);
    }
  </style>
</head>
<body class="bg-blue-50 text-slate-900 min-h-screen p-3 md:p-8">

  <div class="max-w-7xl mx-auto">
    
    <!-- Testata Principale con Logo e Menu -->
    <header class="dashboard-header mb-6">
      <div class="header-brand-wrap">
        <a href="index.php" class="header-brand-logo" title="<?php echo htmlspecialchars(__('app_title')); ?>">
          <?php echo get_foobot_logo_svg(44); ?>
        </a>
        <div>
          <h1><?php echo htmlspecialchars(__('guide_title')); ?></h1>
          <p class="card-subtext"><?php echo htmlspecialchars(__('guide_subtext')); ?></p>
        </div>
      </div>
      <div class="header-right">
        <?php render_main_nav('guide', $current_user); ?>
      </div>
    </header>

    <!-- HERO INTRODUTTIVO -->
    <section class="guide-hero">
      <div style="display:flex; align-items:center; gap:8px; margin-bottom:8px;">
        <span class="guide-badge-pill badge-accent"><?php echo htmlspecialchars(__('guide_badge_arch')); ?></span>
        <span class="guide-badge-pill" style="background:#dcfce7; color:#15803d;"><?php echo htmlspecialchars(__('guide_badge_host')); ?></span>
      </div>
      <h2 style="font-size:1.45rem; font-weight:700; margin:0 0 8px 0; color:var(--text-color);">
        <?php echo htmlspecialchars(__('guide_hero_h2')); ?>
      </h2>
      <p style="font-size:0.95rem; line-height:1.6; color:var(--text-muted); margin:0; max-width:850px;">
        <?php echo __('guide_hero_p'); ?>
      </p>
    </section>

    <!-- SEZIONE 1: IL MENU PRINCIPALE E LE ICONE -->
    <section class="guide-section">
      <h2>
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"></path></svg>
        <span><?php echo htmlspecialchars(__('guide_sec1_title')); ?></span>
      </h2>
      <p style="color:var(--text-muted); font-size:0.9rem; line-height:1.5; margin-bottom:14px;">
        <?php echo htmlspecialchars(__('guide_sec1_sub')); ?>
      </p>

      <div class="guide-grid">
        <!-- Home -->
        <div class="guide-card-box">
          <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px;">
            <div style="display:flex; align-items:center; gap:8px;">
              <div class="guide-icon-box">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
              </div>
              <strong style="font-size:1rem; color:var(--text-color);"><?php echo htmlspecialchars(__('guide_nav_home_title')); ?></strong>
            </div>
            <span class="guide-badge-pill badge-all"><?php echo htmlspecialchars(__('guide_badge_all')); ?></span>
          </div>
          <p style="font-size:0.85rem; color:var(--text-muted); line-height:1.45; margin:0;">
            <?php echo htmlspecialchars(__('guide_nav_home_desc')); ?>
          </p>
        </div>

        <!-- Log di Sistema -->
        <div class="guide-card-box">
          <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px;">
            <div style="display:flex; align-items:center; gap:8px;">
              <div class="guide-icon-box">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><polyline points="4 17 10 11 4 5"></polyline><line x1="12" y1="19" x2="20" y2="19"></line></svg>
              </div>
              <strong style="font-size:1rem; color:var(--text-color);"><?php echo htmlspecialchars(__('guide_nav_logs_title')); ?></strong>
            </div>
            <span class="guide-badge-pill badge-all"><?php echo htmlspecialchars(__('guide_badge_all')); ?></span>
          </div>
          <p style="font-size:0.85rem; color:var(--text-muted); line-height:1.45; margin:0;">
            <?php echo htmlspecialchars(__('guide_nav_logs_desc')); ?>
          </p>
        </div>

        <!-- Impostazioni -->
        <div class="guide-card-box">
          <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px;">
            <div style="display:flex; align-items:center; gap:8px;">
              <div class="guide-icon-box">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
              </div>
              <strong style="font-size:1rem; color:var(--text-color);"><?php echo htmlspecialchars(__('guide_nav_settings_title')); ?></strong>
            </div>
            <span class="guide-badge-pill badge-admin-only"><?php echo htmlspecialchars(__('guide_badge_admin')); ?></span>
          </div>
          <p style="font-size:0.85rem; color:var(--text-muted); line-height:1.45; margin:0;">
            <?php echo htmlspecialchars(__('guide_nav_settings_desc')); ?>
          </p>
        </div>

        <!-- App Mobile -->
        <div class="guide-card-box">
          <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px;">
            <div style="display:flex; align-items:center; gap:8px;">
              <div class="guide-icon-box">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect><line x1="12" y1="18" x2="12.01" y2="18"></line></svg>
              </div>
              <strong style="font-size:1rem; color:var(--text-color);"><?php echo htmlspecialchars(__('guide_nav_mobile_title')); ?></strong>
            </div>
            <span class="guide-badge-pill badge-all"><?php echo htmlspecialchars(__('guide_badge_all')); ?></span>
          </div>
          <p style="font-size:0.85rem; color:var(--text-muted); line-height:1.45; margin:0;">
            <?php echo htmlspecialchars(__('guide_nav_mobile_desc')); ?>
          </p>
        </div>

        <!-- Lingua e Tema -->
        <div class="guide-card-box">
          <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px;">
            <div style="display:flex; align-items:center; gap:8px;">
              <div class="guide-icon-box">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>
              </div>
              <strong style="font-size:1rem; color:var(--text-color);"><?php echo htmlspecialchars(__('guide_nav_lang_title')); ?></strong>
            </div>
            <span class="guide-badge-pill badge-all"><?php echo htmlspecialchars(__('guide_badge_all')); ?></span>
          </div>
          <p style="font-size:0.85rem; color:var(--text-muted); line-height:1.45; margin:0;">
            <?php echo __('guide_nav_lang_desc'); ?>
          </p>
        </div>

        <!-- Guida -->
        <div class="guide-card-box">
          <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px;">
            <div style="display:flex; align-items:center; gap:8px;">
              <div class="guide-icon-box">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path></svg>
              </div>
              <strong style="font-size:1rem; color:var(--text-color);"><?php echo htmlspecialchars(__('guide_nav_guide_title')); ?></strong>
            </div>
            <span class="guide-badge-pill badge-all"><?php echo htmlspecialchars(__('guide_badge_all')); ?></span>
          </div>
          <p style="font-size:0.85rem; color:var(--text-muted); line-height:1.45; margin:0;">
            <?php echo __('guide_nav_guide_desc'); ?>
          </p>
        </div>
      </div>
    </section>

    <!-- SEZIONE 2: LA DASHBOARD (HOME) E I FILTRI TEMPORALI -->
    <section class="guide-section">
      <h2>
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line></svg>
        <span><?php echo htmlspecialchars(__('guide_sec2_title')); ?></span>
      </h2>

      <h3 style="font-size:1.05rem; font-weight:700; color:var(--text-color); margin:18px 0 8px 0;"><?php echo htmlspecialchars(__('guide_sec2_a_title')); ?></h3>
      <p style="font-size:0.9rem; color:var(--text-muted); line-height:1.5; margin-bottom:10px;">
        <?php echo __('guide_sec2_a_sub'); ?>
      </p>
      <ul style="font-size:0.88rem; color:var(--text-color); line-height:1.6; margin-left:20px; margin-bottom:16px;">
        <li><strong><?php echo htmlspecialchars(__('drop_connection_status')); ?>:</strong> <?php echo __('guide_sec2_a_dot'); ?></li>
        <li><strong><?php echo htmlspecialchars(__('device_details_title')); ?>:</strong> <?php echo __('guide_sec2_a_drop'); ?>
          <ul style="margin-left:16px; margin-top:4px;">
            <li><strong><?php echo htmlspecialchars(__('drop_device_name')); ?>:</strong> <?php echo __('guide_sec2_a_drop_name'); ?></li>
            <li><strong><?php echo htmlspecialchars(__('drop_last_seen')); ?>:</strong> <?php echo __('guide_sec2_a_drop_status'); ?></li>
            <li><strong><?php echo htmlspecialchars(__('drop_count_total')); ?>:</strong> <?php echo __('guide_sec2_a_drop_count'); ?></li>
            <li><strong><?php echo htmlspecialchars(__('drop_other_devices')); ?>:</strong> <?php echo __('guide_sec2_a_drop_multi'); ?></li>
          </ul>
        </li>
        <li><strong><?php echo htmlspecialchars(__('chart_last_data')); ?>:</strong> <?php echo __('guide_sec2_a_last'); ?></li>
      </ul>

      <h3 style="font-size:1.05rem; font-weight:700; color:var(--text-color); margin:18px 0 8px 0;"><?php echo htmlspecialchars(__('guide_sec2_b_title')); ?></h3>
      <div style="font-size:0.88rem; color:var(--text-muted); line-height:1.6;">
        <div class="guide-param-row">
          <div><strong style="color:#0284c7; font-size:0.95rem;"><?php echo htmlspecialchars(__('guide_gpi_title')); ?></strong><br><?php echo htmlspecialchars(__('guide_gpi_desc')); ?></div>
        </div>
        <div class="guide-param-row">
          <div><strong style="color:#f59e0b; font-size:0.95rem;"><?php echo htmlspecialchars(__('guide_voc_title')); ?></strong><br><?php echo htmlspecialchars(__('guide_voc_desc')); ?></div>
        </div>
        <div class="guide-param-row">
          <div><strong style="color:#3b82f6; font-size:0.95rem;"><?php echo htmlspecialchars(__('guide_pm_title')); ?></strong><br><?php echo htmlspecialchars(__('guide_pm_desc')); ?></div>
        </div>
        <div class="guide-param-row">
          <div><strong style="color:#ea580c; font-size:0.95rem;"><?php echo htmlspecialchars(__('guide_co2_title')); ?></strong><br><?php echo htmlspecialchars(__('guide_co2_desc')); ?></div>
        </div>
        <div class="guide-param-row">
          <div><strong style="color:#10b981; font-size:0.95rem;"><?php echo htmlspecialchars(__('guide_th_title')); ?></strong><br><?php echo htmlspecialchars(__('guide_th_desc')); ?></div>
        </div>
      </div>

      <h3 style="font-size:1.05rem; font-weight:700; color:var(--text-color); margin:24px 0 8px 0;"><?php echo htmlspecialchars(__('guide_sec2_c_title')); ?></h3>
      <p style="font-size:0.9rem; color:var(--text-muted); line-height:1.5; margin-bottom:12px;">
        <?php echo htmlspecialchars(__('guide_sec2_c_sub')); ?>
      </p>

      <div style="background:var(--input-bg); border:1px solid var(--border-color); border-radius:12px; padding:16px;">
        <ul style="font-size:0.88rem; color:var(--text-color); line-height:1.7; margin-left:18px;">
          <li><span class="guide-code-chip"><?php echo htmlspecialchars(__('range_minutes')); ?></span>: <?php echo __('guide_range_min_desc'); ?></li>
          <li><span class="guide-code-chip"><?php echo htmlspecialchars(__('range_hours')); ?></span>: <?php echo __('guide_range_hours_desc'); ?></li>
          <li><span class="guide-code-chip"><?php echo htmlspecialchars(__('range_24h')); ?></span>: <?php echo __('guide_range_24h_desc'); ?></li>
          <li><span class="guide-code-chip">7 <?php echo htmlspecialchars(__('unit_days_short')); ?></span>: <?php echo __('guide_range_7d_desc'); ?></li>
          <li><span class="guide-code-chip">10 <?php echo htmlspecialchars(__('unit_days_short')); ?> / 30 <?php echo htmlspecialchars(__('unit_days_short')); ?></span>: <?php echo __('guide_range_10_30_desc'); ?></li>
          <li><span class="guide-code-chip"><?php echo htmlspecialchars(__('range_weeks')); ?></span>: <?php echo __('guide_range_weeks_desc'); ?></li>
          <li><span class="guide-code-chip">N° <?php echo htmlspecialchars(__('unit_days_short')); ?></span>: <?php echo __('guide_range_custom_desc'); ?></li>
          <li><strong><?php echo htmlspecialchars(__('nav_prev_period')); ?> / <?php echo htmlspecialchars(__('nav_next_period')); ?> (&lt; / &gt;)</strong>: <?php echo __('guide_range_arrows_desc'); ?></li>
        </ul>
      </div>
    </section>

    <!-- SEZIONE 3: PAGINA LOG DI SISTEMA -->
    <section class="guide-section">
      <h2>
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><polyline points="4 17 10 11 4 5"></polyline><line x1="12" y1="19" x2="20" y2="19"></line></svg>
        <span><?php echo htmlspecialchars(__('guide_sec3_title')); ?></span>
      </h2>
      <p style="font-size:0.9rem; color:var(--text-muted); line-height:1.5; margin-bottom:12px;">
        <?php echo htmlspecialchars(__('guide_sec3_sub')); ?>
      </p>
      <ul style="font-size:0.88rem; color:var(--text-color); line-height:1.6; margin-left:20px;">
        <li><strong><?php echo htmlspecialchars(__('drop_connection_status')); ?>:</strong> <?php echo __('guide_sec3_p1'); ?></li>
        <li><strong><?php echo htmlspecialchars(__('logs_diag_title') ?: 'Console Diagnostica'); ?>:</strong> <?php echo __('guide_sec3_p2'); ?></li>
        <li><strong><?php echo htmlspecialchars(__('drop_other_devices')); ?>:</strong> <?php echo __('guide_sec3_p3'); ?></li>
      </ul>
    </section>

    <!-- SEZIONE 4: PANNELLO IMPOSTAZIONI (SETTINGS) -->
    <section class="guide-section">
      <h2>
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
        <span><?php echo htmlspecialchars(__('guide_sec4_title')); ?></span>
      </h2>
      <p style="font-size:0.9rem; color:var(--text-muted); line-height:1.5; margin-bottom:14px;">
        <?php echo htmlspecialchars(__('guide_sec4_sub')); ?>
      </p>

      <div style="display:flex; flex-direction:column; gap:14px;">
        <div class="guide-card-box">
          <strong style="color:var(--text-color); font-size:0.95rem;"><?php echo htmlspecialchars(__('guide_sec4_t1')); ?></strong>
          <p style="font-size:0.85rem; color:var(--text-muted); margin:4px 0 0 0;">
            <?php echo __('guide_sec4_d1'); ?>
          </p>
        </div>

        <div class="guide-card-box">
          <strong style="color:var(--text-color); font-size:0.95rem;"><?php echo __('guide_sec4_t2'); ?></strong>
          <p style="font-size:0.85rem; color:var(--text-muted); margin:4px 0 0 0;">
            <?php echo __('guide_sec4_d2'); ?>
          </p>
        </div>

        <div class="guide-card-box">
          <strong style="color:var(--text-color); font-size:0.95rem;"><?php echo htmlspecialchars(__('guide_sec4_t3')); ?></strong>
          <p style="font-size:0.85rem; color:var(--text-muted); margin:4px 0 0 0;">
            <?php echo __('guide_sec4_d3'); ?>
          </p>
        </div>

        <div class="guide-card-box">
          <strong style="color:var(--text-color); font-size:0.95rem;"><?php echo __('guide_sec4_t4'); ?></strong>
          <p style="font-size:0.85rem; color:var(--text-muted); margin:4px 0 0 0;">
            <?php echo __('guide_sec4_d4'); ?>
          </p>
        </div>

        <div class="guide-card-box">
          <strong style="color:var(--text-color); font-size:0.95rem;"><?php echo htmlspecialchars(__('guide_sec4_t5')); ?></strong>
          <p style="font-size:0.85rem; color:var(--text-muted); margin:4px 0 0 0;">
            <?php echo __('guide_sec4_d5'); ?>
          </p>
        </div>

        <div class="guide-card-box">
          <strong style="color:var(--text-color); font-size:0.95rem;"><?php echo htmlspecialchars(__('guide_sec4_t6')); ?></strong>
          <p style="font-size:0.85rem; color:var(--text-muted); margin:4px 0 0 0;">
            <?php echo __('guide_sec4_d6'); ?>
          </p>
        </div>

        <div class="guide-card-box">
          <strong style="color:var(--text-color); font-size:0.95rem;"><?php echo htmlspecialchars(__('guide_sec4_t7')); ?></strong>
          <p style="font-size:0.85rem; color:var(--text-muted); margin:4px 0 0 0;">
            <?php echo __('guide_sec4_d7'); ?>
          </p>
        </div>
      </div>
    </section>

    <!-- SEZIONE 5: APP MOBILE & DESKTOP (PWA) -->
    <section class="guide-section">
      <h2>
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect><line x1="12" y1="18" x2="12.01" y2="18"></line></svg>
        <span><?php echo __('guide_sec5_title'); ?></span>
      </h2>
      <p style="font-size:0.9rem; color:var(--text-muted); line-height:1.5; margin-bottom:12px;">
        <?php echo __('guide_sec5_sub'); ?>
      </p>

      <div style="background:var(--input-bg); border:1px solid var(--border-color); border-radius:12px; padding:16px; margin-bottom:16px;">
        <h4 style="font-size:0.95rem; font-weight:700; color:var(--text-color); margin:0 0 6px 0;"><?php echo htmlspecialchars(__('guide_how_install')); ?></h4>
        <ul style="font-size:0.88rem; color:var(--text-color); line-height:1.6; margin-left:18px;">
          <li><?php echo __('guide_inst_android'); ?></li>
          <li><?php echo __('guide_inst_ios'); ?></li>
          <li><?php echo __('guide_inst_desktop'); ?></li>
        </ul>
      </div>

      <h4 style="font-size:0.95rem; font-weight:700; color:var(--text-color); margin:16px 0 6px 0;"><?php echo htmlspecialchars(__('guide_how_pairing')); ?></h4>
      <p style="font-size:0.88rem; color:var(--text-muted); line-height:1.5; margin-bottom:12px;">
        <?php echo __('guide_pairing_sub'); ?>
      </p>
      <ol style="font-size:0.88rem; color:var(--text-color); line-height:1.6; margin-left:20px; margin-bottom:16px;">
        <li><?php echo __('guide_pair_step1'); ?></li>
        <li><?php echo __('guide_pair_step2'); ?></li>
        <li><?php echo __('guide_pair_step3'); ?></li>
        <li><?php echo __('guide_pair_step4'); ?></li>
        <li><?php echo __('guide_pair_step5'); ?></li>
      </ol>

      <h4 style="font-size:0.95rem; font-weight:700; color:var(--text-color); margin:16px 0 6px 0;"><?php echo htmlspecialchars(__('guide_app_views')); ?></h4>
      <ul style="font-size:0.88rem; color:var(--text-color); line-height:1.6; margin-left:20px;">
        <li><?php echo __('guide_view1'); ?></li>
        <li><?php echo __('guide_view2'); ?></li>
        <li><?php echo __('guide_view3'); ?></li>
      </ul>
    </section>

    <!-- SEZIONE 6: AGGIUNGERE UNA NUOVA LINGUA -->
    <section class="guide-section">
      <h2>
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>
        <span><?php echo htmlspecialchars(__('guide_sec6_title')); ?></span>
      </h2>
      <p style="font-size:0.9rem; color:var(--text-muted); line-height:1.5; margin-bottom:14px;">
        <?php echo htmlspecialchars(__('guide_sec6_sub')); ?>
      </p>

      <div style="display:flex; flex-direction:column; gap:14px;">
        <div class="guide-card-box">
          <strong style="color:var(--text-color); font-size:0.95rem;"><?php echo htmlspecialchars(__('guide_lang_step1_title')); ?></strong>
          <p style="font-size:0.85rem; color:var(--text-muted); margin:4px 0 0 0;">
            <?php echo __('guide_lang_step1_desc'); ?>
          </p>
        </div>

        <div class="guide-card-box">
          <strong style="color:var(--text-color); font-size:0.95rem;"><?php echo htmlspecialchars(__('guide_lang_step2_title')); ?></strong>
          <p style="font-size:0.85rem; color:var(--text-muted); margin:4px 0 0 0;">
            <?php echo __('guide_lang_step2_desc'); ?>
          </p>
        </div>

        <div class="guide-card-box">
          <strong style="color:var(--text-color); font-size:0.95rem;"><?php echo htmlspecialchars(__('guide_lang_step3_title')); ?></strong>
          <p style="font-size:0.85rem; color:var(--text-muted); margin:4px 0 0 0;">
            <?php echo __('guide_lang_step3_desc'); ?>
          </p>
        </div>

        <div class="guide-card-box">
          <strong style="color:var(--text-color); font-size:0.95rem;"><?php echo htmlspecialchars(__('guide_lang_step4_title')); ?></strong>
          <p style="font-size:0.85rem; color:var(--text-muted); margin:4px 0 0 0;">
            <?php echo __('guide_lang_step4_desc'); ?>
          </p>
        </div>
      </div>
    </section>

  </div>

</body>
</html>
