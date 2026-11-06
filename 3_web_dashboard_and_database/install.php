<?php
/**
 * Installatore Automatico e Configurazione Guidata per NewFoobot
 * Step 1: Connessione Database MySQL e creazione tabelle
 * Step 2: Registrazione primo utente amministratore, Passkey API e IP infrastruttura
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('FOOBOT_INSTALLER', true);
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/nav.php';
$config_file = __DIR__ . '/config.php';
$schema_file = __DIR__ . '/schema.sql';

$db_connected = false;
$error = '';
$success = '';

// Verifica se config.php esiste già e se il database risponde
if (file_exists($config_file)) {
    require_once $config_file;
    if (function_exists('get_db_connection')) {
        $pdo = get_db_connection();
        if ($pdo instanceof PDO) {
            $db_connected = true;
            try {
                $check_users = $pdo->query("SELECT COUNT(*) FROM foobot_users")->fetchColumn();
                // Se il sistema è già installato con un amministratore, consenti la riconfigurazione solo se autenticato
                if ($check_users > 0) {
                    require_once __DIR__ . '/auth.php';
                    require_admin_role();
                }
            } catch (Exception $e) {
                if (file_exists($schema_file)) {
                    $pdo->exec(file_get_contents($schema_file));
                }
            }
        }
    }
}

// Determina lo step corrente (1: Database, 2: Utente, Passkey e IP)
$step = 1;
if ($db_connected) {
    $step = 2;
}
if (isset($_GET['step']) && is_numeric($_GET['step'])) {
    $requested_step = (int)$_GET['step'];
    if ($requested_step === 1 || ($requested_step === 2 && $db_connected)) {
        $step = $requested_step;
    }
}

// Helper per salvare la configurazione in config.php
function save_foobot_config($file, $host, $name, $user, $pass, $api_key, $oracle_ip = '', $nextdns_ip = '') {
    $content = "<?php\n";
    $content .= "/**\n * Parametri di configurazione NewFoobot generati dall'installatore\n */\n";
    $content .= "if (!defined('FOOBOT_ACCESS')) {\n    define('FOOBOT_ACCESS', true);\n}\n\n";
    $content .= "define('DB_HOST', " . var_export($host, true) . ");\n";
    $content .= "define('DB_NAME', " . var_export($name, true) . ");\n";
    $content .= "define('DB_USER', " . var_export($user, true) . ");\n";
    $content .= "define('DB_PASS', " . var_export($pass, true) . ");\n";
    $content .= "define('DB_CHARSET', 'utf8mb4');\n\n";
    $content .= "// Chiave segreta per autenticare l'invio delle letture da parte del bridge\n";
    $content .= "define('API_SECRET_KEY', " . var_export($api_key, true) . ");\n\n";
    $content .= "// Riferimenti infrastruttura Cloud e DNS\n";
    $content .= "define('ORACLE_BROKER_IP', " . var_export($oracle_ip, true) . ");\n";
    $content .= "define('NEXTDNS_SERVER_IP', " . var_export($nextdns_ip, true) . ");\n\n";
    $content .= "function get_db_connection() {\n";
    $content .= "    static \$pdo = null;\n";
    $content .= "    if (\$pdo === null) {\n";
    $content .= "        \$dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);\n";
    $content .= "        \$options = [\n";
    $content .= "            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,\n";
    $content .= "            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,\n";
    $content .= "            PDO::ATTR_EMULATE_PREPARES   => false,\n";
    $content .= "        ];\n";
    $content .= "        try {\n";
    $content .= "            \$pdo = new PDO(\$dsn, DB_USER, DB_PASS, \$options);\n";
    $content .= "        } catch (PDOException \$e) {\n";
    $content .= "            return null;\n";
    $content .= "        }\n";
    $content .= "    }\n";
    $content .= "    return \$pdo;\n";
    $content .= "}\n";

    return file_put_contents($file, $content) !== false;
}

// Elaborazione Step 1: Configurazione Database
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'setup_db') {
    $db_host = trim($_POST['db_host'] ?? 'localhost');
    $db_name = trim($_POST['db_name'] ?? '');
    $db_user = trim($_POST['db_user'] ?? '');
    $db_pass = trim($_POST['db_pass'] ?? '');

    if (empty($db_name) || empty($db_user)) {
        $error = "Compila tutti i campi obbligatori del database (Nome e Utente).";
    } else {
        try {
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $db_host, $db_name);
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $test_pdo = new PDO($dsn, $db_user, $db_pass, $options);

            // Esegui schema.sql
            if (file_exists($schema_file)) {
                $sql = file_get_contents($schema_file);
                $test_pdo->exec($sql);
            }

            $current_api_key = defined('API_SECRET_KEY') ? API_SECRET_KEY : bin2hex(random_bytes(16));
            $oracle_ip = defined('ORACLE_BROKER_IP') ? ORACLE_BROKER_IP : '';
            $nextdns_ip = defined('NEXTDNS_SERVER_IP') ? NEXTDNS_SERVER_IP : '';

            if (!save_foobot_config($config_file, $db_host, $db_name, $db_user, $db_pass, $current_api_key, $oracle_ip, $nextdns_ip)) {
                throw new Exception("Impossibile salvare il file config.php. Verifica i permessi di scrittura della cartella.");
            }

            header('Location: install.php?step=2');
            exit;

        } catch (Exception $e) {
            $error = "Errore di connessione al database: " . $e->getMessage();
        }
    }
}

// Elaborazione Step 2: Registrazione Utente, Passkey API e Dati Rete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'setup_user') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $api_passkey = trim($_POST['api_passkey'] ?? '');
    $oracle_ip = trim($_POST['oracle_ip'] ?? '');
    $nextdns_ip = trim($_POST['nextdns_ip'] ?? '');
    $default_lang = strtolower(trim($_POST['default_lang'] ?? 'en'));
    $available_langs = get_available_languages();
    if (!in_array($default_lang, $available_langs, true)) {
        $default_lang = 'en';
    }
    $temp_unit = strtoupper(trim($_POST['temp_unit'] ?? 'C')) === 'F' ? 'F' : 'C';

    if (empty($username) || empty($password)) {
        $error = "Inserisci nome utente e password.";
    } elseif ($password !== $password_confirm) {
        $error = "Le due password inserite non coincidono. Riprova.";
    } elseif (strlen($password) < 6) {
        $error = "La password deve contenere almeno 6 caratteri.";
    } elseif (empty($api_passkey)) {
        $error = "Inserisci o genera una passkey segreta per le API.";
    } else {
        try {
            if (!$pdo instanceof PDO) {
                throw new Exception("Connessione al database non disponibile.");
            }

            require_once __DIR__ . '/auth.php';
            ensure_security_tables($pdo);

            $pwd_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO foobot_users (username, password_hash, email, temp_unit) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), email = VALUES(email), temp_unit = VALUES(temp_unit)");
            $stmt->execute([$username, $pwd_hash, $email, $temp_unit]);

            $set_stmt = $pdo->prepare("INSERT INTO foobot_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $set_stmt->execute(['oracle_broker_ip', $oracle_ip]);
            $set_stmt->execute(['nextdns_server_ip', $nextdns_ip]);
            $set_stmt->execute(['default_language', $default_lang]);
            $set_stmt->execute(['temperature_unit', $temp_unit]);

            // Imposta anche la lingua della sessione dell'amministratore
            $_SESSION['foobot_lang'] = $default_lang;
            setcookie('foobot_lang', $default_lang, time() + (365 * 86400), '/', '', false, false);

            require_once $config_file;
            save_foobot_config($config_file, DB_HOST, DB_NAME, DB_USER, DB_PASS, $api_passkey, $oracle_ip, $nextdns_ip);

            $_SESSION['foobot_user'] = $username;
            $_SESSION['foobot_role'] = 'admin';

            // Imposta cookie di login persistente per 1 anno (365 giorni)
            require_once __DIR__ . '/auth.php';
            $u_id_stmt = $pdo->prepare("SELECT id FROM foobot_users WHERE username = ? LIMIT 1");
            $u_id_stmt->execute([$username]);
            if ($u_id = $u_id_stmt->fetchColumn()) {
                set_remember_cookie($pdo, $u_id);
            }

            header('Location: settings.php?msg=installed');
            exit;

        } catch (Exception $e) {
            $error = "Errore durante la registrazione: " . $e->getMessage();
        }
    }
}

$suggested_api_key = defined('API_SECRET_KEY') ? API_SECRET_KEY : bin2hex(random_bytes(16));
$current_oracle_ip = defined('ORACLE_BROKER_IP') ? ORACLE_BROKER_IP : '';
$current_nextdns_ip = defined('NEXTDNS_SERVER_IP') ? NEXTDNS_SERVER_IP : '';
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(get_current_lang()); ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars(__('install_title')); ?></title>
  <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg?v=2">
  <link rel="apple-touch-icon" href="assets/img/favicon.png?v=2">
  <link rel="stylesheet" href="assets/css/style.css?v=24">
  <script src="assets/js/theme.js?v=15"></script>
</head>
<body>
  <div style="position: absolute; top: 16px; right: 20px; display: flex; align-items: center; gap: 8px; z-index: 50;">
    <!-- Selettore lingue dinamico sempre in prima linea -->
    <div class="lang-switcher" style="display:inline-flex; align-items:center; gap:2px; background:var(--input-bg); padding:3px 6px; border-radius:8px; border:1px solid var(--border-color); font-size:0.75rem; font-weight:600;">
      <?php 
        $current_lang = get_current_lang();
        foreach (get_available_languages() as $l_code): 
          $l_upper = strtoupper($l_code);
          $l_name = get_language_label($l_code);
          if ($current_lang === $l_code):
      ?>
        <span style="padding:2px 6px; border-radius:6px; background:var(--accent-blue); color:#fff;" title="<?php echo htmlspecialchars($l_name); ?>"><?php echo htmlspecialchars($l_upper); ?></span>
      <?php else: ?>
        <a href="?<?php echo http_build_query(array_merge($_GET, ['lang' => $l_code])); ?>" style="padding:2px 6px; border-radius:6px; color:var(--text-muted); text-decoration:none;" title="<?php echo htmlspecialchars($l_name); ?>"><?php echo htmlspecialchars($l_upper); ?></a>
      <?php endif; endforeach; ?>
    </div>
    <button type="button" class="theme-toggle" aria-label="Cambia tema" style="margin: 0;"></button>
  </div>
  <div class="auth-box">
    <div class="auth-header">
      <div style="display: flex; justify-content: center; margin-bottom: 12px;">
        <span class="header-brand-logo" style="width: 52px; height: 52px;">
          <?php echo get_foobot_logo_svg(52); ?>
        </span>
      </div>
      <h1><?php echo htmlspecialchars(__('install_main_heading')); ?></h1>
      <p class="card-subtext"><?php echo htmlspecialchars(__('install_subtitle')); ?></p>
    </div>

    <div class="steps-nav">
      <div class="step-item <?php echo $step === 1 ? 'active' : ''; ?>">
        <span class="step-number">1</span> <?php echo htmlspecialchars(__('install_step1_title')); ?>
      </div>
      <div class="step-item <?php echo $step === 2 ? 'active' : ''; ?>">
        <span class="step-number">2</span> <?php echo htmlspecialchars(__('install_step2_title')); ?>
      </div>
    </div>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($step === 1): ?>
      <!-- STEP 1: CONFIGURAZIONE DATABASE -->
      <form method="POST">
        <input type="hidden" name="action" value="setup_db">
        
        <div class="form-group">
          <label for="db_host"><?php echo htmlspecialchars(__('install_db_host_label')); ?></label>
          <input type="text" id="db_host" name="db_host" value="<?php echo defined('DB_HOST') ? htmlspecialchars(DB_HOST) : 'localhost'; ?>" required>
          <div class="form-help"><?php echo htmlspecialchars(__('install_db_host_help')); ?></div>
        </div>

        <div class="form-group">
          <label for="db_name"><?php echo htmlspecialchars(__('install_db_name_label')); ?></label>
          <input type="text" id="db_name" name="db_name" value="<?php echo defined('DB_NAME') ? htmlspecialchars(DB_NAME) : ''; ?>" placeholder="es. cpaneluser_foobot" required>
        </div>

        <div class="form-group">
          <label for="db_user"><?php echo htmlspecialchars(__('install_db_user_label')); ?></label>
          <input type="text" id="db_user" name="db_user" value="<?php echo defined('DB_USER') ? htmlspecialchars(DB_USER) : ''; ?>" placeholder="es. cpaneluser_foouser" required>
        </div>

        <div class="form-group">
          <label for="db_pass"><?php echo htmlspecialchars(__('install_db_pass_label')); ?></label>
          <div class="password-wrapper">
            <input type="password" id="db_pass" name="db_pass" value="<?php echo defined('DB_PASS') ? htmlspecialchars(DB_PASS) : ''; ?>" placeholder="Password del database MySQL">
            <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('db_pass', this)" title="Mostra/Nascondi password">
              <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
            </button>
          </div>
        </div>

        <button type="submit" class="btn-submit"><?php echo __('install_db_btn_submit'); ?></button>
      </form>

    <?php else: ?>
      <!-- STEP 2: REGISTRAZIONE UTENTE, PASSKEY API E DATI RETE -->
      <form method="POST">
        <input type="hidden" name="action" value="setup_user">

        <h3 class="section-title"><?php echo htmlspecialchars(__('install_admin_section_title')); ?></h3>

        <div class="form-group">
          <label for="username"><?php echo htmlspecialchars(__('install_username_label')); ?></label>
          <input type="text" id="username" name="username" placeholder="es. admin" required>
        </div>

        <div class="form-group">
          <label for="email"><?php echo htmlspecialchars(__('install_email_label')); ?></label>
          <input type="email" id="email" name="email" placeholder="es. admin@tuodominio.it">
        </div>

        <div class="form-group">
          <label for="password"><?php echo htmlspecialchars(__('install_password_label')); ?></label>
          <div class="password-wrapper">
            <input type="password" id="password" name="password" placeholder="<?php echo htmlspecialchars(__('install_pwd_min')); ?>" required>
            <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('password', this)" title="Mostra/Nascondi password">
              <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
            </button>
          </div>
        </div>

        <div class="form-group">
          <label for="password_confirm"><?php echo htmlspecialchars(__('install_password_confirm_label')); ?></label>
          <div class="password-wrapper">
            <input type="password" id="password_confirm" name="password_confirm" placeholder="<?php echo htmlspecialchars(__('install_pwd_repeat')); ?>" required>
            <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('password_confirm', this)" title="Mostra/Nascondi password">
              <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
            </button>
          </div>
        </div>

        <hr class="divider">
        <h3 class="section-title"><?php echo htmlspecialchars(__('install_network_section_title')); ?></h3>

        <div class="form-group">
          <label for="default_lang"><?php echo htmlspecialchars(__('install_default_lang_label')); ?></label>
          <select id="default_lang" name="default_lang" style="width:100%; padding:10px 14px; background:var(--input-bg); border:1px solid var(--border-color); border-radius:6px; color:var(--text-main); font-size:0.95rem;">
            <?php foreach (get_available_languages() as $l_code): ?>
              <option value="<?php echo htmlspecialchars($l_code); ?>" <?php echo $l_code === 'en' ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars(get_language_label($l_code)); ?> (<?php echo strtoupper(htmlspecialchars($l_code)); ?>) <?php echo $l_code === 'en' ? '— Consigliato / Default' : ''; ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="form-help"><?php echo htmlspecialchars(__('install_default_lang_help')); ?></div>
        </div>

        <div class="form-group">
          <label for="temp_unit"><?php echo htmlspecialchars(__('install_temp_unit_label', 'Unità di Temperatura Predefinita')); ?></label>
          <select id="temp_unit" name="temp_unit" style="width:100%; padding:10px 14px; background:var(--input-bg); border:1px solid var(--border-color); border-radius:6px; color:var(--text-main); font-size:0.95rem;">
            <option value="C" selected><?php echo htmlspecialchars(__('temp_unit_celsius', 'Celsius (°C)')); ?> — Standard Internazionale</option>
            <option value="F"><?php echo htmlspecialchars(__('temp_unit_fahrenheit', 'Fahrenheit (°F)')); ?> — Standard USA</option>
          </select>
          <div class="form-help"><?php echo htmlspecialchars(__('install_temp_unit_help', 'Scegli se visualizzare la temperatura in gradi Celsius (°C) o Fahrenheit (°F).')); ?></div>
        </div>

        <div class="form-group">
          <label for="api_passkey"><?php echo htmlspecialchars(__('install_api_passkey_label')); ?></label>
          <input type="text" id="api_passkey" name="api_passkey" value="<?php echo htmlspecialchars($suggested_api_key); ?>" placeholder="es. MiaPasskeySegreta_12345" required>
          <div class="form-help"><?php echo htmlspecialchars(__('install_api_passkey_help')); ?></div>
        </div>

        <div class="form-group">
          <label for="oracle_ip"><?php echo htmlspecialchars(__('install_oracle_ip_label')); ?></label>
          <input type="text" id="oracle_ip" name="oracle_ip" value="<?php echo htmlspecialchars($current_oracle_ip); ?>" placeholder="es. 198.51.100.25">
          <div class="form-help"><?php echo htmlspecialchars(__('install_oracle_ip_help')); ?></div>
        </div>

        <div class="form-group">
          <label for="nextdns_ip"><?php echo htmlspecialchars(__('install_nextdns_ip_label')); ?></label>
          <input type="text" id="nextdns_ip" name="nextdns_ip" value="<?php echo htmlspecialchars($current_nextdns_ip); ?>" placeholder="es. 45.90.28.0">
          <div class="form-help"><?php echo htmlspecialchars(__('install_nextdns_ip_help')); ?></div>
        </div>

        <div style="display: flex; gap: 10px; margin-top: 20px;">
          <a href="install.php?step=1" class="btn-secondary"><?php echo __('install_btn_back'); ?></a>
          <button type="submit" class="btn-submit" style="margin-top:0; flex:1;"><?php echo __('install_btn_complete'); ?></button>
        </div>
      </form>
    <?php endif; ?>

  </div>

  <script>
    function togglePasswordVisibility(fieldId, btn) {
      var field = document.getElementById(fieldId);
      if (!field) return;
      if (field.type === 'password') {
        field.type = 'text';
        btn.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';
      } else {
        field.type = 'password';
        btn.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
      }
    }
  </script>
</body>
</html>
