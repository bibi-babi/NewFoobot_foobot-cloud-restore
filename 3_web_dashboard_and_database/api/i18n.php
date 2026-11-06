<?php
/**
 * API Internazionalizzazione (i18n) Dinamica NewFoobot per Mobile PWA e Client Esterni
 * - Scansione automatica delle lingue dedicate PWA nella cartella app/languages/
 * - Restituzione dizionario completo per download on-demand di nuove lingue aggiunte dagli sviluppatori in app/languages/
 * - Sincronizzazione automatica con la lingua predefinita della dashboard/utente
 * - Resiliente: funziona sia con DB attivo sia da file statici
 */

header('Content-Type: application/json; charset=UTF-8');

// Gestione completa CORS per PWA e client remoti
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

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$site_root = dirname(__DIR__);

// Determina la cartella lingue da scansionare: preferenza per la cartella dedicata della PWA se richiesta o presente
$scope = strtolower(trim((string)($_GET['scope'] ?? ($_POST['scope'] ?? 'pwa'))));
if ($scope === 'site') {
    $lang_dir = $site_root . '/languages';
} else {
    // Default PWA: cerca in app/languages, con fallback alla cartella generale se non esistesse
    $lang_dir = file_exists($site_root . '/app/languages') ? ($site_root . '/app/languages') : ($site_root . '/languages');
}

// Carica il modulo i18n principale del sito PHP se presente per ereditare label ed eventuali helper
$i18n_main = $site_root . '/i18n.php';
if (file_exists($i18n_main)) {
    require_once $i18n_main;
}

$action = $_GET['action'] ?? ($_POST['action'] ?? 'languages');

/**
 * Restituisce l'elenco dei codici lingua scansionando una determinata directory
 */
function scan_languages_dir($directory) {
    $base_languages = ['it', 'en', 'fr', 'de', 'es'];
    $found = [];
    if (is_dir($directory)) {
        $files = scandir($directory);
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
    $all = array_values(array_unique(array_merge($base_languages, $found)));
    sort($all);
    return $all;
}

/**
 * Restituisce l'etichetta estesa con codice tra parentesi (es. 'Italiano (IT)')
 */
function get_extended_language_label($code) {
    $code_lower = strtolower(trim($code));
    $known = [
        'it'    => 'Italiano (IT)',
        'en'    => 'English (EN)',
        'fr'    => 'Français (FR)',
        'de'    => 'Deutsch (DE)',
        'es'    => 'Español (ES)',
        'pt'    => 'Português (PT)',
        'pt-br' => 'Português (BR)',
        'nl'    => 'Nederlands (NL)',
        'pl'    => 'Polski (PL)',
        'ru'    => 'Русский (RU)',
        'zh'    => '中文 (ZH)',
        'ja'    => '日本語 (JA)',
        'ar'    => 'العربية (AR)'
    ];
    if (isset($known[$code_lower])) {
        return $known[$code_lower];
    }
    $simple_label = function_exists('get_language_label') ? get_language_label($code_lower) : strtoupper($code_lower);
    return $simple_label . ' (' . strtoupper($code_lower) . ')';
}

switch ($action) {
    case 'languages':
    case 'list':
        $available_codes = scan_languages_dir($lang_dir);
        $languages_list = [];

        foreach ($available_codes as $code) {
            $languages_list[] = [
                'code'       => $code,
                'label'      => function_exists('get_language_label') ? get_language_label($code) : strtoupper($code),
                'full_label' => get_extended_language_label($code),
                'built_in'   => in_array($code, ['it', 'en', 'fr', 'de', 'es'], true)
            ];
        }

        $system_default = function_exists('get_system_default_lang') ? get_system_default_lang() : 'it';
        $dashboard_lang = function_exists('get_current_lang') ? get_current_lang() : $system_default;

        // Se è configurato il database e presente un token dispositivo, controlla preferenze utente
        $config_file = $site_root . '/config.php';
        if (file_exists($config_file)) {
            require_once $config_file;
            $headers = function_exists('getallheaders') ? array_change_key_case(getallheaders(), CASE_LOWER) : [];
            $token = $headers['x-device-token'] ?? ($_POST['device_token'] ?? ($_GET['device_token'] ?? ''));

            if (!empty($token) && function_exists('get_db_connection')) {
                try {
                    $pdo = get_db_connection();
                    if ($pdo instanceof PDO) {
                        $token_hash = hash('sha256', $token);
                        $stmt = $pdo->prepare("
                            SELECT u.language 
                            FROM foobot_authorized_devices d
                            JOIN foobot_users u ON d.user_id = u.id
                            WHERE d.device_token_hash = ? AND d.revoked_at IS NULL
                            LIMIT 1
                        ");
                        $stmt->execute([$token_hash]);
                        $user_pref = $stmt->fetchColumn();
                        if ($user_pref && in_array($user_pref, $available_codes, true)) {
                            $dashboard_lang = $user_pref;
                        }
                    }
                } catch (Exception $e) {}
            }
        }

        echo json_encode([
            'status'         => 'ok',
            'scope'          => $scope,
            'system_default' => $system_default,
            'dashboard_lang' => $dashboard_lang,
            'languages'      => $languages_list
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;

    case 'get':
        $lang = strtolower(trim((string)($_GET['lang'] ?? ($_POST['lang'] ?? ''))));
        if ($lang === '' || !preg_match('/^[a-z]{2,5}(-[a-z]{2,5})?$/', $lang)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Codice lingua non valido']);
            exit;
        }

        // Cerca prima nella cartella della PWA, poi fallback nella cartella radice
        $pwa_file = $site_root . '/app/languages/' . $lang . '.json';
        $site_file = $site_root . '/languages/' . $lang . '.json';
        $target_file = file_exists($pwa_file) ? $pwa_file : (file_exists($site_file) ? $site_file : null);

        if (!$target_file || !file_exists($target_file)) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Dizionario lingua non trovato per: ' . $lang]);
            exit;
        }

        $content = file_get_contents($target_file);
        $translations = json_decode($content, true);

        if (!is_array($translations)) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'File lingua danneggiato o non JSON valido']);
            exit;
        }

        echo json_encode([
            'status'       => 'ok',
            'lang'         => $lang,
            'scope'        => ($target_file === $pwa_file) ? 'pwa' : 'site',
            'label'        => function_exists('get_language_label') ? get_language_label($lang) : strtoupper($lang),
            'full_label'   => get_extended_language_label($lang),
            'translations' => $translations
        ], JSON_UNESCAPED_UNICODE);
        exit;

    default:
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Azione non riconosciuta. Azioni permesse: languages, get']);
        exit;
}
