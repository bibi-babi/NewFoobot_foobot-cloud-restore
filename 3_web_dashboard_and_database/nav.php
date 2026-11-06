<?php
/**
 * Menu orizzontale condiviso NewFoobot con Selettore Lingua IT / EN
 * Uso: render_main_nav('home' | 'settings' | 'logs', $current_user)
 */

require_once __DIR__ . '/i18n.php';

function render_main_nav($active, $current_user = null) {
    $icon_home = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>';
    $icon_settings = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>';
    $icon_logs = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="4 17 10 11 4 5"></polyline><line x1="12" y1="19" x2="20" y2="19"></line></svg>';
    $icon_guide = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path></svg>';
    $icon_mobile = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect><line x1="12" y1="18" x2="12.01" y2="18"></line></svg>';
    $icon_logout = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>';

    $is_admin = is_admin_user();
    $logs_url = $current_user ? 'logs.php' : 'login.php';
    $settings_url = $current_user ? 'settings.php' : 'login.php';
    $current_lang = get_current_lang();

    echo '<nav class="main-nav" aria-label="Menu principale">';
    echo '<div class="nav-links-group">';
    echo '<a href="index.php" class="nav-link' . ($active === 'home' ? ' active" aria-current="page' : '') . '">' . $icon_home . '<span>' . htmlspecialchars(__('nav_home')) . '</span></a>';
    echo '<a href="' . $logs_url . '" class="nav-link' . ($active === 'logs' ? ' active" aria-current="page' : '') . '">' . $icon_logs . '<span>' . htmlspecialchars(__('nav_logs') ?: 'Log') . '</span></a>';
    if ($is_admin) {
        echo '<a href="' . $settings_url . '" class="nav-link' . ($active === 'settings' ? ' active" aria-current="page' : '') . '">' . $icon_settings . '<span>' . htmlspecialchars(__('nav_settings')) . '</span></a>';
    }
    echo '<a href="guide.php" class="nav-link' . ($active === 'guide' ? ' active" aria-current="page' : '') . '">' . $icon_guide . '<span>' . htmlspecialchars(__('nav_guide') ?: 'Guida') . '</span></a>';
    if ($current_user) {
        $mobile_title = htmlspecialchars(__('nav_mobile_app', 'App Mobile & Desktop'));
        echo '<a href="mobile_app.php" class="nav-link' . ($active === 'mobile' ? ' active" aria-current="page' : '') . '" title="' . $mobile_title . '" aria-label="' . $mobile_title . '" style="padding: 6px 10px;">' . $icon_mobile . '</a>';
    }
    echo '</div>';

    echo '<div class="nav-tools-group">';
    if ($current_user) {
        $role_label = $is_admin ? 'Admin' : 'Utente';
        echo '<a href="logout.php" class="nav-link nav-logout" title="' . htmlspecialchars(__('nav_logout')) . ' (' . htmlspecialchars($current_user) . ' - ' . $role_label . ')">' . $icon_logout . '<span>' . htmlspecialchars(__('nav_logout')) . '</span></a>';
    }
    // Selettore Rapido Multilingua per le pagine interne del sito: tutte le lingue supportate
    $switcher_langs = get_available_languages();

    echo '<div class="lang-switcher" style="display:inline-flex; align-items:center; gap:2px; background:var(--input-bg); padding:2px 4px; border-radius:8px; border:1px solid var(--border-color); font-size:0.75rem; font-weight:600;">';
    foreach ($switcher_langs as $l_code) {
        $l_upper = strtoupper($l_code);
        $l_name = get_language_label($l_code);
        if ($current_lang === $l_code) {
            echo '<span style="padding:2px 6px; border-radius:6px; background:var(--accent-blue); color:#fff;" title="' . htmlspecialchars($l_name) . '">' . htmlspecialchars($l_upper) . '</span>';
        } else {
            echo '<a href="?lang=' . urlencode($l_code) . '" style="padding:2px 6px; border-radius:6px; color:var(--text-muted); text-decoration:none;" title="' . htmlspecialchars($l_name) . '">' . htmlspecialchars($l_upper) . '</a>';
        }
    }
    echo '</div>';

    echo '<button type="button" class="theme-toggle" aria-label="Cambia tema"></button>';

    // Icona Informativa (i) che punta al portale ufficiale e guida di NewFoobot
    $icon_info = '<svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>';
    echo '<a href="http://newfoobot-restart.barbaralerici.net/" target="_blank" rel="noopener noreferrer" class="nav-link nav-info-pill" title="' . htmlspecialchars(__('nav_info_guide') ?: 'Guida e Portale NewFoobot') . '" style="padding: 6px 10px; border-radius: 8px; color: var(--accent-blue); display: inline-flex; align-items: center; justify-content: center; flex: 0 0 auto;">' . $icon_info . '</a>';
    echo '</div>';

    echo '</nav>';
}

/**
 * Restituisce il markup SVG vettoriale del logo originale Foobot (cerchietti azzurrini blu)
 */
function get_foobot_logo_svg($size = 44) {
    $s = (int)$size;
    return '<svg viewBox="0 0 100 100" width="' . $s . '" height="' . $s . '" style="display:block;" aria-hidden="true">'
         . '<defs>'
         . '<linearGradient id="foobotHeaderBlue" x1="0%" y1="0%" x2="100%" y2="100%">'
         . '<stop offset="0%" stop-color="#38bdf8"/>'
         . '<stop offset="50%" stop-color="#2563eb"/>'
         . '<stop offset="100%" stop-color="#1d4ed8"/>'
         . '</linearGradient>'
         . '</defs>'
         . '<circle cx="50" cy="50" r="42.3" fill="none" stroke="url(#foobotHeaderBlue)" stroke-width="8.6"/>'
         . '<circle cx="50" cy="50" r="24.4" fill="none" stroke="url(#foobotHeaderBlue)" stroke-width="8.6"/>'
         . '<circle cx="50" cy="50" r="9.9" fill="url(#foobotHeaderBlue)"/>'
         . '</svg>';
}
