<?php
/**
 * Modulo Internazionalizzazione (i18n) Dinamico NewFoobot
 * Rileva automaticamente tutti i file .json presenti nella cartella languages/
 * Supporta lingua predefinita configurabile da DB/cookie/sessione e fallback
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/iso_countries.php';

/**
 * Scansiona la cartella languages/ e restituisce i codici lingua disponibili
 * Ignora file come template.json o file non validi
 */
function get_available_languages() {
    static $languages = null;
    if ($languages !== null) {
        return $languages;
    }
    // Lingue standard supportate dal sistema
    $base_languages = ['it', 'en', 'fr', 'de', 'es'];
    $dir = __DIR__ . '/languages';
    $found = [];
    if (is_dir($dir)) {
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === 'template.json' || substr($file, -5) !== '.json') {
                continue;
            }
            $code = strtolower(pathinfo($file, PATHINFO_FILENAME));
            if (preg_match('/^[a-z]{2,5}(-[a-z]{2,5})?$/', $code)) {
                $found[] = $code;
            }
        }
    }
    // Garantisce la presenza delle 5 lingue principali anche in caso di disallineamento temporaneo su hosting
    $languages = array_values(array_unique(array_merge($base_languages, $found)));
    sort($languages);
    return $languages;
}

/**
 * Restituisce il nome formale o etichetta di una lingua
 */
function get_language_label($code) {
    $labels = [
        'en' => 'English',
        'it' => 'Italiano',
        'fr' => 'Français',
        'de' => 'Deutsch',
        'es' => 'Español'
    ];
    return $labels[$code] ?? strtoupper($code);
}

/**
 * Recupera la lingua di default impostata globalmente nel database (se configurato)
 */
function get_system_default_lang() {
    static $sys_default = null;
    if ($sys_default !== null) {
        return $sys_default;
    }
    $sys_default = 'en'; // Default assoluto per installazione e avvio
    $config_file = __DIR__ . '/config.php';
    if (file_exists($config_file)) {
        require_once $config_file;
        if (function_exists('get_db_connection')) {
            try {
                $pdo = get_db_connection();
                if ($pdo instanceof PDO) {
                    $stmt = $pdo->prepare("SELECT setting_value FROM foobot_settings WHERE setting_key = 'default_language' LIMIT 1");
                    $stmt->execute();
                    if ($val = $stmt->fetchColumn()) {
                        $val = strtolower(trim($val));
                        $avail = get_available_languages();
                        if (in_array($val, $avail, true)) {
                            $sys_default = $val;
                        }
                    }
                }
            } catch (Exception $e) {}
        }
    }
    return $sys_default;
}

// Gestione cambio lingua manuale tramite parametro GET ?lang=...
if (isset($_GET['lang'])) {
    $req_lang = strtolower(trim($_GET['lang']));
    $available = get_available_languages();
    if (in_array($req_lang, $available, true)) {
        $_SESSION['foobot_lang'] = $req_lang;
        if (function_exists('set_app_lang_cookie')) {
            set_app_lang_cookie($req_lang);
        } else {
            $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                     || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
            setcookie('foobot_lang', $req_lang, [
                'expires'  => time() + (365 * 86400),
                'path'     => '/',
                'domain'   => '',
                'secure'   => $is_https,
                'httponly' => false,
                'samesite' => 'Lax'
            ]);
        }
        // Se l'utente è autenticato, sincronizza permanentemente la scelta della lingua nel database dell'account
        if (!empty($_SESSION['foobot_user']) && function_exists('get_db_connection')) {
            try {
                $db_conn = get_db_connection();
                if ($db_conn instanceof PDO) {
                    $upd_user = $db_conn->prepare("UPDATE foobot_users SET language = ? WHERE username = ?");
                    $upd_user->execute([$req_lang, $_SESSION['foobot_user']]);
                }
            } catch (Exception $e) {}
        }
    }
}

/**
 * Determina il codice della lingua corrente dell'utente
 */
function get_current_lang() {
    $available = get_available_languages();
    $sys_default = get_system_default_lang();

    // 1. Lingua esplicita in sessione
    if (!empty($_SESSION['foobot_lang']) && in_array($_SESSION['foobot_lang'], $available, true)) {
        return $_SESSION['foobot_lang'];
    }

    // 2. Lingua memorizzata nel cookie del browser
    if (!empty($_COOKIE['foobot_lang']) && in_array($_COOKIE['foobot_lang'], $available, true)) {
        return $_COOKIE['foobot_lang'];
    }

    // 3. Lingua predefinita del sistema impostata dall'admin
    if (in_array($sys_default, $available, true)) {
        return $sys_default;
    }

    return in_array('en', $available, true) ? 'en' : $available[0];
}

/**
 * Carica l'array dei testi della lingua corrente
 */
function get_translations($lang = null) {
    static $loaded = [];
    if ($lang === null) {
        $lang = get_current_lang();
    }
    if (!isset($loaded[$lang])) {
        $file = __DIR__ . '/languages/' . $lang . '.json';
        if (file_exists($file)) {
            $json = file_get_contents($file);
            $json = preg_replace('/^\xEF\xBB\xBF/', '', $json);
            $loaded[$lang] = json_decode($json, true) ?: [];
        } else {
            // Fallback su en.json se la lingua non esiste
            $fallback_file = __DIR__ . '/languages/en.json';
            if (file_exists($fallback_file)) {
                $json = file_get_contents($fallback_file);
                $json = preg_replace('/^\xEF\xBB\xBF/', '', $json);
                $loaded[$lang] = json_decode($json, true) ?: [];
            } else {
                $loaded[$lang] = [];
            }
        }
    }
    return $loaded[$lang];
}

/**
 * Funzione helper di traduzione: restituisce il testo tradotto con fallback
 */
function __($key, $default = null) {
    $translations = get_translations();
    if (isset($translations[$key])) {
        return $translations[$key];
    }
    // Fallback secondario su en.json
    $en_translations = get_translations('en');
    if (isset($en_translations[$key])) {
        return $en_translations[$key];
    }
    return $default !== null ? $default : $key;
}

/**
 * Renderizza un selettore di lingua compatto fluttuante per le pagine di autenticazione (login, forgot, reset)
 */
function render_auth_lang_switcher($extra_params = []) {
    $current_lang = get_current_lang();
    $switcher_langs = get_available_languages();
    echo '<div class="auth-lang-switcher" style="position:fixed; top:16px; right:64px; display:inline-flex; align-items:center; gap:2px; background:var(--card-bg); padding:3px 6px; border-radius:8px; border:1px solid var(--border-color); box-shadow:var(--shadow); font-size:0.75rem; font-weight:600; z-index:100;">';
    foreach ($switcher_langs as $l_code) {
        $l_upper = strtoupper($l_code);
        $l_name = get_language_label($l_code);
        if ($current_lang === $l_code) {
            echo '<span style="padding:2px 6px; border-radius:6px; background:var(--accent-blue); color:#fff;" title="' . htmlspecialchars($l_name) . '">' . htmlspecialchars($l_upper) . '</span>';
        } else {
            $params = array_merge($extra_params, ['lang' => $l_code]);
            $url = '?' . http_build_query($params);
            echo '<a href="' . htmlspecialchars($url) . '" style="padding:2px 6px; border-radius:6px; color:var(--text-muted); text-decoration:none;" title="' . htmlspecialchars($l_name) . '">' . htmlspecialchars($l_upper) . '</a>';
        }
    }
    echo '</div>';
}
