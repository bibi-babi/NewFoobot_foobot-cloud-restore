<?php
/**
 * Pagina Impostazioni NewFoobot
 * Gestione credenziali utente, sicurezza (Whitelist IP, Filtro Paese, Brute-Force), Passkey API, Backup e Manutenzione Dati
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/nav.php';
$pdo = require_admin_role();

$config_file = __DIR__ . '/config.php';
$username = get_current_user_name();
$client_ip = get_client_ip();
$client_country = get_client_country($client_ip);
$current_lang = get_current_lang();

// Carica dati utente dal database (assicurando esistenza colonna language)
try {
    $col_check = $pdo->query("SHOW COLUMNS FROM foobot_users LIKE 'language'")->fetchAll();
    if (empty($col_check)) {
        $pdo->exec("ALTER TABLE foobot_users ADD COLUMN language VARCHAR(10) NOT NULL DEFAULT 'en' AFTER role");
    }
} catch (Exception $e) {}

$stmt = $pdo->prepare("SELECT * FROM foobot_users WHERE username = ? LIMIT 1");
$stmt->execute([$username]);
$user_data = $stmt->fetch();

// Identifica l'amministratore primario di sistema (primo account admin creato con id piu basso)
$primary_admin_id = 1;
try {
    $p_stmt = $pdo->query("SELECT id FROM foobot_users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
    if ($p_id = $p_stmt->fetchColumn()) {
        $primary_admin_id = (int)$p_id;
    }
} catch (Exception $e) {}

$msg = '';
$error = '';

// Controllo automatico Web-Cron al caricamento: esegue backup se dovuto e verifica stato disconnessione
if (file_exists(__DIR__ . '/cron_events.php')) {
    require_once __DIR__ . '/cron_events.php';
    if (function_exists('maybe_trigger_auto_backup')) {
        maybe_trigger_auto_backup($pdo);
    }
    if (function_exists('maybe_check_offline_email_alert')) {
        maybe_check_offline_email_alert($pdo);
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'installed') {
    $msg = __('settings_installed_success');
}

// Helper per salvare la configurazione in config.php
function update_full_config($file, $host, $name, $user, $pass, $api_key, $oracle_ip, $nextdns_ip) {
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

// Download file di backup (se richiesto dall'amministratore autenticato)
if (isset($_GET['download_backup'])) {
    $dl_file = basename($_GET['download_backup']);
    $dl_path = __DIR__ . '/backups/' . $dl_file;
    if (preg_match('/^(foobot_backup|foobot_connection_logs)_[\w\-]+\.csv$/', $dl_file) && file_exists($dl_path)) {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $dl_file . '"');
        header('Content-Length: ' . filesize($dl_path));
        readfile($dl_path);
        exit;
    } else {
        $error = "File di backup non trovato o nome file non valido.";
    }
}

// Elaborazione azioni POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';

    if (!verify_csrf_token($csrf)) {
        $error = "Token di sicurezza CSRF non valido. Ricarica la pagina e riprova.";
    } elseif ($action === 'update_profile') {
        $email = trim($_POST['email'] ?? '');
        $new_pwd = $_POST['new_password'] ?? '';
        $confirm_pwd = $_POST['confirm_password'] ?? '';
        $profile_lang = strtolower(trim($_POST['profile_language'] ?? 'en'));
        $profile_temp_unit = strtoupper(trim($_POST['profile_temp_unit'] ?? 'C')) === 'F' ? 'F' : 'C';
        $avail = get_available_languages();
        if (!in_array($profile_lang, $avail, true)) {
            $profile_lang = 'en';
        }

        if (!empty($new_pwd)) {
            if ($new_pwd !== $confirm_pwd) {
                $error = "La nuova password e la conferma non corrispondono.";
            } elseif (strlen($new_pwd) < 6) {
                $error = "La nuova password deve contenere almeno 6 caratteri.";
            } else {
                $new_hash = password_hash($new_pwd, PASSWORD_DEFAULT);
                $update = $pdo->prepare("UPDATE foobot_users SET email = ?, password_hash = ?, language = ?, temp_unit = ? WHERE username = ?");
                $update->execute([$email, $new_hash, $profile_lang, $profile_temp_unit, $username]);
                $_SESSION['foobot_lang'] = $profile_lang;
                if (function_exists('set_app_lang_cookie')) {
                    set_app_lang_cookie($profile_lang);
                } else {
                    setcookie('foobot_lang', $profile_lang, time() + (365 * 86400), '/', '', false, false);
                }
                $user_data['email'] = $email;
                $user_data['language'] = $profile_lang;
                $user_data['temp_unit'] = $profile_temp_unit;
                $current_lang = $profile_lang;
                $msg = "Dati del profilo, lingua, unità di temperatura e nuova password aggiornati con successo!";
            }
        } else {
            $update = $pdo->prepare("UPDATE foobot_users SET email = ?, language = ?, temp_unit = ? WHERE username = ?");
            $update->execute([$email, $profile_lang, $profile_temp_unit, $username]);
            $_SESSION['foobot_lang'] = $profile_lang;
            if (function_exists('set_app_lang_cookie')) {
                set_app_lang_cookie($profile_lang);
            } else {
                setcookie('foobot_lang', $profile_lang, time() + (365 * 86400), '/', '', false, false);
            }
            $user_data['email'] = $email;
            $user_data['language'] = $profile_lang;
            $user_data['temp_unit'] = $profile_temp_unit;
            $current_lang = $profile_lang;
            $msg = "Dati del profilo, lingua ed unità di temperatura aggiornati con successo!";
        }

    } elseif ($action === 'update_infra') {
        $new_key = trim($_POST['api_key'] ?? '');
        $oracle_ip = trim($_POST['oracle_ip'] ?? '');
        $nextdns_ip = trim($_POST['nextdns_ip'] ?? '');

        if (empty($new_key)) {
            $error = "La Passkey API non puo essere vuota.";
        } else {
            save_setting($pdo, 'oracle_broker_ip', $oracle_ip);
            save_setting($pdo, 'nextdns_server_ip', $nextdns_ip);

            if (update_full_config($config_file, DB_HOST, DB_NAME, DB_USER, DB_PASS, $new_key, $oracle_ip, $nextdns_ip)) {
                $msg = __('infra_msg_saved', 'Parametri di rete e Passkey API salvati con successo!');
            } else {
                $error = __('infra_err_save_config', 'Errore durante il salvataggio nel file config.php.');
            }
        }

    } elseif ($action === 'update_foobot_names') {
        $names_input = $_POST['foobot_names'] ?? [];
        $loc_input = $_POST['foobot_locations'] ?? [];
        if (is_array($names_input)) {
            foreach ($names_input as $uuid => $c_name) {
                $c_loc = $loc_input[$uuid] ?? '';
                set_foobot_device_name($pdo, $uuid, $c_name, $c_loc);
            }
            $msg = __('settings_devices_msg_saved', 'Nomi e ambienti dei sensori Foobot aggiornati con successo!');
        }

    } elseif ($action === 'update_security') {
        $whitelist_ips = trim($_POST['admin_ip_whitelist'] ?? '');
        $allowed_country = strtoupper(trim($_POST['allowed_countries'] ?? ''));
        $enforce_oracle_api = isset($_POST['enforce_oracle_ip_api']) ? '1' : '0';
        $def_site_lang = strtolower(trim($_POST['default_language'] ?? 'en'));
        $def_site_temp_unit = strtoupper(trim($_POST['default_temp_unit'] ?? 'C')) === 'F' ? 'F' : 'C';
        $avail = get_available_languages();
        if (in_array($def_site_lang, $avail, true)) {
            save_setting($pdo, 'default_language', $def_site_lang);
            $_SESSION['foobot_lang'] = $def_site_lang;
            if (function_exists('set_app_lang_cookie')) {
                set_app_lang_cookie($def_site_lang);
            }
        }

        save_setting($pdo, 'temperature_unit', $def_site_temp_unit);
        save_setting($pdo, 'admin_ip_whitelist', $whitelist_ips);
        save_setting($pdo, 'allowed_countries', $allowed_country);
        save_setting($pdo, 'enforce_oracle_ip_api', $enforce_oracle_api);

        $msg = "Impostazioni di sicurezza, unità di temperatura, lingua predefinita e filtro geografico aggiornati con successo!";

    } elseif ($action === 'update_offline_alert') {
        $alert_enabled = isset($_POST['offline_email_alert_enabled']) && $_POST['offline_email_alert_enabled'] === '1' ? '1' : '0';
        $email_driver  = ($_POST['email_driver'] ?? 'php_mail') === 'smtp' ? 'smtp' : 'php_mail';
        $smtp_host     = trim($_POST['smtp_host'] ?? '');
        $smtp_port     = max(1, min(65535, (int)($_POST['smtp_port'] ?? 465)));
        $smtp_enc      = in_array($_POST['smtp_encryption'] ?? '', ['ssl', 'tls', 'none'], true) ? $_POST['smtp_encryption'] : 'ssl';
        $smtp_user     = trim($_POST['smtp_user'] ?? '');
        $smtp_pass     = (string)($_POST['smtp_pass'] ?? '');
        $smtp_from_email = trim($_POST['smtp_from_email'] ?? '');
        $smtp_from_name  = trim($_POST['smtp_from_name'] ?? '');

        save_setting($pdo, 'offline_email_alert_enabled', $alert_enabled);
        save_setting($pdo, 'email_driver', $email_driver);
        save_setting($pdo, 'smtp_host', $smtp_host);
        save_setting($pdo, 'smtp_port', (string)$smtp_port);
        save_setting($pdo, 'smtp_encryption', $smtp_enc);
        save_setting($pdo, 'smtp_user', $smtp_user);
        if ($smtp_pass !== '') {
            save_setting($pdo, 'smtp_pass', $smtp_pass);
        }
        save_setting($pdo, 'smtp_from_email', $smtp_from_email);
        save_setting($pdo, 'smtp_from_name', $smtp_from_name);

        // Reset stato di invio quando si modifica l'impostazione
        if ($alert_enabled === '0') {
            save_setting($pdo, 'offline_email_alert_sent', '0');
            $msg = "Impostazioni email e parametri SMTP salvati. Avviso disconnessione DISATTIVATO.";
        } else {
            $msg = "Impostazioni email e parametri SMTP salvati. Avviso disconnessione Foobot (>30 min) ATTIVATO!";
        }

    } elseif ($action === 'test_offline_alert_email') {
        require_once __DIR__ . '/cron_events.php';
        $test_res = send_offline_alert_email($pdo, true);
        if (!empty($test_res['success'])) {
            $msg = $test_res['message'];
        } else {
            $error = $test_res['message'];
        }

    } elseif ($action === 'clear_email_logs') {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS foobot_email_logs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                recipient VARCHAR(191) NOT NULL,
                subject VARCHAR(255) NOT NULL,
                email_type VARCHAR(32) NOT NULL DEFAULT 'offline_alert',
                status VARCHAR(16) NOT NULL DEFAULT 'sent',
                details TEXT NULL,
                timestamp BIGINT UNSIGNED NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_ts (timestamp),
                INDEX idx_type (email_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $pdo->exec("TRUNCATE TABLE foobot_email_logs");
            $msg = "Registro storico invii email svuotato con successo!";
        } catch (Exception $e) {
            $error = "Errore durante lo svuotamento del registro email: " . $e->getMessage();
        }

    } elseif ($action === 'update_backup_schedule') {
        $interval_days = max(1, min(90, (int)($_POST['backup_interval_days'] ?? 1)));
        $target_hour = max(0, min(23, (int)($_POST['backup_target_hour'] ?? 4)));

        save_setting($pdo, 'backup_interval_days', (string)$interval_days);
        save_setting($pdo, 'backup_target_hour', (string)$target_hour);

        // Verifica se con i nuovi parametri il backup e dovuto immediatamente per oggi
        require_once __DIR__ . '/cron_events.php';
        $bk_auto_res = maybe_trigger_auto_backup($pdo);
        if ($bk_auto_res !== null && !empty($bk_auto_res['success'])) {
            $msg = sprintf("Pianificazione salvata! Il backup per la giornata odierna e stato eseguito all'istante (%s, %d righe).", $bk_auto_res['filename'], (int)($bk_auto_res['rows_exported'] ?? 0));
        } else {
            $msg = sprintf("Pianificazione salvata! Il backup automatico verra eseguito ogni %d giorno/i alle ore %02d:00.", $interval_days, $target_hour);
        }

    } elseif ($action === 'run_manual_backup') {
        // Esegue backup istantaneo manuale (non altera la schedulazione del backup automatico)
        require_once __DIR__ . '/cron_events.php';
        $bk_res = perform_foobot_backup(30, false);
        if ($bk_res['success']) {
            $msg = $bk_res['message'];
        } else {
            $error = $bk_res['message'];
        }

    } elseif ($action === 'run_manual_conn_backup') {
        // Esegue backup manuale del registro connessioni
        require_once __DIR__ . '/cron_events.php';
        $c_res = perform_connection_logs_backup(30);
        if ($c_res['success']) {
            $msg = $c_res['message'];
        } else {
            $error = $c_res['message'];
        }

    } elseif ($action === 'delete_backup_file') {
        // Eliminazione manuale singolo file di backup su hosting
        $file_to_del = basename($_POST['backup_filename'] ?? '');
        $bk_dir = __DIR__ . '/backups/';
        $full_path = $bk_dir . $file_to_del;
        if (preg_match('/^(foobot_backup|foobot_connection_logs)_[\w\-]+\.csv$/', $file_to_del) && file_exists($full_path) && is_file($full_path)) {
            if (@unlink($full_path)) {
                $msg = sprintf(__('backup_delete_success', 'File di backup "%s" eliminato con successo.'), htmlspecialchars($file_to_del));
            } else {
                $error = sprintf(__('backup_delete_error', 'Impossibile eliminare il file di backup "%s".'), htmlspecialchars($file_to_del));
            }
        } else {
            $error = __('backup_delete_not_found', 'File di backup non trovato o non valido.');
        }

    } elseif ($action === 'add_user') {
        $new_u_username = trim($_POST['new_username'] ?? '');
        $new_u_email = trim($_POST['new_email'] ?? '');
        $new_u_password = $_POST['new_password'] ?? '';
        $new_u_role = ($_POST['new_role'] ?? 'user') === 'admin' ? 'admin' : 'user';
        $new_u_lang = strtolower(trim($_POST['new_language'] ?? 'en'));
        $new_u_temp = strtoupper(trim($_POST['new_temp_unit'] ?? 'C')) === 'F' ? 'F' : 'C';
        $avail = get_available_languages();
        if (!in_array($new_u_lang, $avail, true)) {
            $new_u_lang = 'en';
        }

        if (empty($new_u_username) || empty($new_u_password)) {
            $error = 'Nome utente e password sono obbligatori per creare un nuovo account.';
        } elseif (strlen($new_u_password) < 6) {
            $error = 'La password deve contenere almeno 6 caratteri.';
        } else {
            try {
                $check_stmt = $pdo->prepare('SELECT id FROM foobot_users WHERE username = ? LIMIT 1');
                $check_stmt->execute([$new_u_username]);
                if ($check_stmt->fetch()) {
                    $error = "Nome utente '" . htmlspecialchars($new_u_username) . "' già esistente. Scegli un nome diverso.";
                } else {
                    $u_hash = password_hash($new_u_password, PASSWORD_DEFAULT);
                    $ins = $pdo->prepare('INSERT INTO foobot_users (username, password_hash, email, role, language, temp_unit) VALUES (?, ?, ?, ?, ?, ?)');
                    $ins->execute([$new_u_username, $u_hash, $new_u_email, $new_u_role, $new_u_lang, $new_u_temp]);
                    $role_text = ($new_u_role === 'admin') ? 'Amministratore' : 'Visualizzatore';
                    $msg = "Nuovo account '" . htmlspecialchars($new_u_username) . "' (" . $role_text . ") creato con successo!";
                }
            } catch (Exception $e) {
                $error = 'Errore creazione account: ' . $e->getMessage();
            }
        }

    } elseif ($action === 'edit_user') {
        // Modifica dati utente (Username, Email, Ruolo, Lingua, Unità Temp, Password opzionale)
        $edit_uid = (int)($_POST['user_id'] ?? 0);
        $edit_username = trim($_POST['username'] ?? '');
        $edit_email = trim($_POST['email'] ?? '');
        $edit_role = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
        $edit_lang = strtolower(trim($_POST['language'] ?? 'en'));
        $edit_temp = strtoupper(trim($_POST['temp_unit'] ?? 'C')) === 'F' ? 'F' : 'C';
        $avail = get_available_languages();
        if (!in_array($edit_lang, $avail, true)) {
            $edit_lang = 'en';
        }
        $edit_password = $_POST['new_password'] ?? '';

        try {
            $u_stmt = $pdo->prepare('SELECT id, username, role FROM foobot_users WHERE id = ? LIMIT 1');
            $u_stmt->execute([$edit_uid]);
            $target_user = $u_stmt->fetch();

            if (!$target_user) {
                $error = 'Utente selezionato non trovato.';
            } elseif ($edit_uid === $primary_admin_id && $edit_role !== 'admin') {
                $error = __('users_primary_protected', 'Non è consentito degradare il ruolo dell\'amministratore primario.');
            } elseif (empty($edit_username)) {
                $error = 'Il nome utente (username) non può essere vuoto.';
            } else {
                // Controllo unicità del nuovo username su altri utenti
                $check_uname = $pdo->prepare('SELECT id FROM foobot_users WHERE username = ? AND id != ? LIMIT 1');
                $check_uname->execute([$edit_username, $edit_uid]);
                if ($check_uname->fetch()) {
                    $error = sprintf('Il nome utente "%s" è già utilizzato da un altro account.', htmlspecialchars($edit_username));
                } else {
                    if (!empty($edit_password)) {
                        if (strlen($edit_password) < 6) {
                            $error = 'La nuova password deve contenere almeno 6 caratteri.';
                        } else {
                            $new_pwd_hash = password_hash($edit_password, PASSWORD_DEFAULT);
                            $upd_stmt = $pdo->prepare('UPDATE foobot_users SET username = ?, email = ?, role = ?, language = ?, temp_unit = ?, password_hash = ? WHERE id = ?');
                            $upd_stmt->execute([$edit_username, $edit_email, $edit_role, $edit_lang, $edit_temp, $new_pwd_hash, $edit_uid]);
                            if ($target_user['username'] === $username) {
                                $_SESSION['foobot_user'] = $edit_username;
                                $_SESSION['foobot_lang'] = $edit_lang;
                                if (function_exists('set_app_lang_cookie')) {
                                    set_app_lang_cookie($edit_lang);
                                } else {
                                    setcookie('foobot_lang', $edit_lang, time() + (365 * 86400), '/', '', false, false);
                                }
                                $username = $edit_username;
                                $user_data['language'] = $edit_lang;
                                $user_data['temp_unit'] = $edit_temp;
                                $current_lang = $edit_lang;
                            }
                            $msg = sprintf(__('users_edit_pwd_success', 'Dati e password dell\'utente "%s" aggiornati con successo.'), htmlspecialchars($edit_username));
                        }
                    } else {
                        $upd_stmt = $pdo->prepare('UPDATE foobot_users SET username = ?, email = ?, role = ?, language = ?, temp_unit = ? WHERE id = ?');
                        $upd_stmt->execute([$edit_username, $edit_email, $edit_role, $edit_lang, $edit_temp, $edit_uid]);
                        if ($target_user['username'] === $username) {
                            $_SESSION['foobot_user'] = $edit_username;
                            $_SESSION['foobot_lang'] = $edit_lang;
                            if (function_exists('set_app_lang_cookie')) {
                                set_app_lang_cookie($edit_lang);
                            } else {
                                setcookie('foobot_lang', $edit_lang, time() + (365 * 86400), '/', '', false, false);
                            }
                            $username = $edit_username;
                            $user_data['language'] = $edit_lang;
                            $user_data['temp_unit'] = $edit_temp;
                            $current_lang = $edit_lang;
                        }
                        $msg = sprintf(__('users_edit_success', 'Dati dell\'utente "%s" aggiornati con successo.'), htmlspecialchars($edit_username));
                    }
                }
            }
        } catch (Exception $e) {
            $error = 'Errore durante la modifica dell\'utente: ' . $e->getMessage();
        }

    } elseif ($action === 'delete_user') {
        $del_uid = (int)($_POST['user_id'] ?? 0);
        try {
            $u_to_del = $pdo->prepare('SELECT username FROM foobot_users WHERE id = ? LIMIT 1');
            $u_to_del->execute([$del_uid]);
            $del_uname = $u_to_del->fetchColumn();

            if (!$del_uname) {
                $error = 'Utente selezionato non trovato.';
            } elseif ($del_uid === $primary_admin_id) {
                $error = __('users_primary_protected', 'Non è consentito eliminare l\'amministratore primario.');
            } elseif ($del_uname === $username) {
                $error = 'Non puoi eliminare il tuo stesso account mentre sei collegato.';
            } else {
                $del_stmt = $pdo->prepare('DELETE FROM foobot_users WHERE id = ?');
                $del_stmt->execute([$del_uid]);
                $msg = "Utente '" . htmlspecialchars($del_uname) . "' eliminato con successo.";
            }
        } catch (Exception $e) {
            $error = 'Errore eliminazione utente: ' . $e->getMessage();
        }

    } elseif ($action === 'delete_readings') {
        $delete_mode = $_POST['delete_mode'] ?? '';
        $confirm = trim($_POST['confirm_delete'] ?? '');

        if ($confirm !== 'CONFERMA') {
            $error = "Cancellazione annullata: devi digitare esattamente la parola 'CONFERMA' nel campo di testo di verifica per procedere.";
        } else {
            $now = time();
            $deleted_count = 0;

            if ($delete_mode === 'all') {
                $del_stmt = $pdo->query("DELETE FROM foobot_readings");
                $deleted_count = $del_stmt->rowCount();
                $msg = sprintf("Cancellazione completata: eliminate tutte le %d letture dal database.", $deleted_count);
            } elseif ($delete_mode === 'older_30d') {
                $threshold = $now - (30 * 86400);
                $del_stmt = $pdo->prepare("DELETE FROM foobot_readings WHERE timestamp < ?");
                $del_stmt->execute([$threshold]);
                $deleted_count = $del_stmt->rowCount();
                $msg = sprintf("Cancellazione completata: eliminate %d letture antecedenti a 30 giorni fa.", $deleted_count);
            } elseif ($delete_mode === 'older_7d') {
                $threshold = $now - (7 * 86400);
                $del_stmt = $pdo->prepare("DELETE FROM foobot_readings WHERE timestamp < ?");
                $del_stmt->execute([$threshold]);
                $deleted_count = $del_stmt->rowCount();
                $msg = sprintf("Cancellazione completata: eliminate %d letture antecedenti a 7 giorni fa.", $deleted_count);
            } elseif ($delete_mode === 'range') {
                $date_from = trim($_POST['date_from'] ?? '');
                $date_to = trim($_POST['date_to'] ?? '');
                $ts_from = $date_from ? strtotime($date_from . ' 00:00:00') : 0;
                $ts_to = $date_to ? strtotime($date_to . ' 23:59:59') : 0;

                if ($ts_from > 0 && $ts_to > 0 && $ts_to >= $ts_from) {
                    $del_stmt = $pdo->prepare("DELETE FROM foobot_readings WHERE timestamp >= ? AND timestamp <= ?");
                    $del_stmt->execute([$ts_from, $ts_to]);
                    $deleted_count = $del_stmt->rowCount();
                    $msg = sprintf("Cancellazione intervallo completata: eliminate %d letture comprese tra %s e %s.", $deleted_count, htmlspecialchars($date_from), htmlspecialchars($date_to));
                } else {
                    $error = "Intervallo date non valido. Assicurati che la data iniziale sia antecedente o uguale alla data finale.";
                }
            } else {
                $error = "Modalita di cancellazione non riconosciuta.";
            }
        }
    }
}

// Sincronizza sempre i dati del profilo utente loggato con lo stato attuale del database
try {
    $stmt_refresh = $pdo->prepare("SELECT * FROM foobot_users WHERE username = ? LIMIT 1");
    $stmt_refresh->execute([$username]);
    $fresh_u = $stmt_refresh->fetch();
    if ($fresh_u) {
        $user_data = $fresh_u;
        if (!empty($user_data['language'])) {
            $current_lang = strtolower($user_data['language']);
        }
    }
} catch (Exception $e) {}

// Recupera statistiche attuali del database
$stat_count_total = (int)$pdo->query("SELECT COUNT(*) FROM foobot_readings")->fetchColumn();
$stat_oldest = $pdo->query("SELECT MIN(timestamp) FROM foobot_readings")->fetchColumn();
$stat_newest = $pdo->query("SELECT MAX(timestamp) FROM foobot_readings")->fetchColumn();

// Recupera elenco file di backup presenti sul server
$backup_dir = __DIR__ . '/backups';
$backup_files_list = [];
$conn_backup_files_list = [];
if (is_dir($backup_dir)) {
    $glob = glob($backup_dir . '/foobot_backup_*.csv') ?: [];
    usort($glob, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    foreach ($glob as $f) {
        $backup_files_list[] = [
            'name' => basename($f),
            'size' => filesize($f),
            'mtime' => filemtime($f)
        ];
    }

    $glob_conn = glob($backup_dir . '/foobot_connection_logs_*.csv') ?: [];
    usort($glob_conn, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    foreach ($glob_conn as $fc) {
        $conn_backup_files_list[] = [
            'name' => basename($fc),
            'size' => filesize($fc),
            'mtime' => filemtime($fc)
        ];
    }
}

// Carica elenco completo di tutti gli utenti del sistema
$all_users_list = [];
try {
    $u_stmt = $pdo->query('SELECT id, username, email, role, language, temp_unit, created_at FROM foobot_users ORDER BY id ASC');
    $all_users_list = $u_stmt->fetchAll();
} catch (Exception $e) {
    try {
        $u_stmt = $pdo->query('SELECT id, username, email, role, language, created_at FROM foobot_users ORDER BY id ASC');
        $all_users_list = $u_stmt->fetchAll();
    } catch (Exception $e2) {
        $all_users_list = [];
    }
}

// Recupera impostazioni aggiornate
$all_settings = get_all_settings($pdo);
$current_api_key = defined('API_SECRET_KEY') ? API_SECRET_KEY : '';
$current_oracle_ip = $all_settings['oracle_broker_ip'] ?: (defined('ORACLE_BROKER_IP') ? ORACLE_BROKER_IP : '');
$current_nextdns_ip = $all_settings['nextdns_server_ip'] ?: (defined('NEXTDNS_SERVER_IP') ? NEXTDNS_SERVER_IP : '');
$admin_ip_whitelist = $all_settings['admin_ip_whitelist'] ?? '';
$allowed_countries = $all_settings['allowed_countries'] ?? '';
$iso_country_list = get_all_iso_countries(get_current_lang());
$enforce_oracle_ip_api = ($all_settings['enforce_oracle_ip_api'] ?? '0') === '1';
$default_site_language = $all_settings['default_language'] ?? get_system_default_lang();
$default_site_temp_unit = strtoupper($all_settings['temperature_unit'] ?? 'C') === 'F' ? 'F' : 'C';

// Pianificazione backup programmabile
$backup_interval_days = (int)($all_settings['backup_interval_days'] ?? '1');
if ($backup_interval_days < 1) $backup_interval_days = 1;
$backup_target_hour = (int)($all_settings['backup_target_hour'] ?? '4');
$last_backup_ts = (int)($all_settings['last_auto_backup_ts'] ?? '0');

// Notifica email disconnessione (>30 minuti) - Default disattivato ('0')
$offline_email_alert_enabled = ($all_settings['offline_email_alert_enabled'] ?? '0') === '1';
$offline_email_last_sent_ts = (int)($all_settings['offline_email_last_sent_ts'] ?? '0');
$current_email_driver = $all_settings['email_driver'] ?? 'php_mail';
$current_smtp_host = $all_settings['smtp_host'] ?? '';
$current_smtp_port = (int)($all_settings['smtp_port'] ?? '465');
$current_smtp_enc  = $all_settings['smtp_encryption'] ?? 'ssl';
$current_smtp_user = $all_settings['smtp_user'] ?? '';
$current_smtp_pass = $all_settings['smtp_pass'] ?? '';
$current_smtp_from_email = $all_settings['smtp_from_email'] ?? '';
$current_smtp_from_name  = $all_settings['smtp_from_name'] ?? 'NewFoobot Air Monitor';

// Recupera storico invii email (ultimi 20 record)
$email_logs_list = [];
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS foobot_email_logs (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        recipient VARCHAR(191) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        email_type VARCHAR(32) NOT NULL DEFAULT 'offline_alert',
        status VARCHAR(16) NOT NULL DEFAULT 'sent',
        details TEXT NULL,
        timestamp BIGINT UNSIGNED NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ts (timestamp),
        INDEX idx_type (email_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $stmt_el = $pdo->query("SELECT * FROM foobot_email_logs ORDER BY timestamp DESC, id DESC LIMIT 20");
    $email_logs_list = $stmt_el->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

/**
 * Traduce dinamicamente l'oggetto dell'email nel registro storico
 */
function translate_email_subject($subject, $lang = null) {
    if (empty($subject)) return '';
    if ($lang === null) $lang = get_current_lang();
    if ($lang === 'it') return $subject;

    if (preg_match('/^\[(TEST|ALLERTA)\]\s*Foobot\s+Disconnesso\s*\(([^)]+)\)\s*-\s*NewFoobot/i', $subject, $m)) {
        $tag = strtoupper($m[1]);
        $range = $m[2];
        $tags = [
            'TEST' => ['en' => 'TEST', 'es' => 'PRUEBA', 'fr' => 'TEST', 'de' => 'TEST'],
            'ALLERTA' => ['en' => 'ALERT', 'es' => 'ALERTA', 'fr' => 'ALERTE', 'de' => 'WARNUNG']
        ];
        $tag_tr = $tags[$tag][$lang] ?? $tag;
        $disc_labels = [
            'en' => "Foobot Disconnected (%s) - NewFoobot",
            'es' => "Foobot Desconectado (%s) - NewFoobot",
            'fr' => "Foobot Déconnecté (%s) - NewFoobot",
            'de' => "Foobot Getrennt (%s) - NewFoobot"
        ];
        return sprintf("[%s] " . ($disc_labels[$lang] ?? "Foobot Disconnected (%s) - NewFoobot"), $tag_tr, $range);
    }
    return $subject;
}

/**
 * Traduce dinamicamente i dettagli tecnici e lo stato di invio nel registro storico email
 */
function translate_email_log_details($details, $lang = null) {
    if (empty($details)) return '';
    if ($lang === null) $lang = get_current_lang();
    if ($lang === 'it') return $details;

    $notes = [
        'Email inviata e accettata con successo dal server SMTP.' => [
            'en' => 'Email sent and successfully accepted by the SMTP server.',
            'es' => 'Correo enviado y aceptado con éxito por el servidor SMTP.',
            'fr' => 'E-mail envoyé et accepté avec succès par le serveur SMTP.',
            'de' => 'E-Mail gesendet und vom SMTP-Server erfolgreich akzeptiert.'
        ],
        'Fallita funzione mail() hosting' => [
            'en' => 'Hosting mail() function failed',
            'es' => 'Función mail() del alojamiento fallida',
            'fr' => "Échec de la fonction mail() de l'hébergement",
            'de' => 'Hosting-Funktion mail() fehlgeschlagen'
        ]
    ];
    foreach ($notes as $orig => $translations) {
        if (strpos($details, $orig) !== false && isset($translations[$lang])) {
            $details = str_replace($orig, $translations[$lang], $details);
        }
    }

    if (preg_match('/Inviata a\s+([^\s]+)\s+da\s+([^\s]+)/i', $details, $m_mail)) {
        $to = $m_mail[1];
        $from = $m_mail[2];
        $mail_lbls = [
            'en' => "Sent to $to from $from",
            'es' => "Enviado a $to desde $from",
            'fr' => "Envoyé à $to de $from",
            'de' => "Gesendet an $to von $from"
        ];
        $details = str_replace($m_mail[0], $mail_lbls[$lang] ?? "Sent to $to from $from", $details);
    }

    if (preg_match('/^Allerta disconnessione\s*\(([^)]+)\)\s*-\s*(.+)$/i', $details, $m)) {
        $meta = $m[1];
        $rest = $m[2];
        $labels = [
            'en' => "Disconnection alert (%s) - %s",
            'es' => "Alerta de desconexión (%s) - %s",
            'fr' => "Alerte de déconnexion (%s) - %s",
            'de' => "Verbindungsabbruch-Warnung (%s) - %s"
        ];
        return sprintf($labels[$lang] ?? "Disconnection alert (%s) - %s", $meta, $rest);
    }

    if (preg_match('/^Email di test inviata dal pannello Impostazioni\s*\(([^)]+)\)\s*-\s*(.+)$/i', $details, $m)) {
        $driver = $m[1];
        $rest = $m[2];
        $labels = [
            'en' => "Test email sent from Settings panel (%s) - %s",
            'es' => "Correo de prueba enviado desde el panel de Configuración (%s) - %s",
            'fr' => "E-mail de test envoyé depuis le panneau Paramètres (%s) - %s",
            'de' => "Test-E-Mail über das Einstellungsmenü gesendet (%s) - %s"
        ];
        return sprintf($labels[$lang] ?? "Test email sent from Settings panel (%s) - %s", $driver, $rest);
    }

    return $details;
}

$csrf_token = get_csrf_token();
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($current_lang); ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars(__('settings_page_title')); ?> - <?php echo htmlspecialchars(__('app_title')); ?></title>
  <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg?v=2">
  <link rel="apple-touch-icon" href="assets/img/favicon.png?v=2">
  <link rel="stylesheet" href="assets/css/style.css?v=28">
  <script src="assets/js/theme.js?v=8"></script>
</head>
<body class="bg-blue-50 text-slate-900 min-h-screen p-3 md:p-8">
  <div class="settings-container">
    
    <header class="dashboard-header mb-6">
      <div class="header-brand-wrap">
        <a href="index.php" class="header-brand-logo" title="<?php echo htmlspecialchars(__('app_title')); ?>">
          <?php echo get_foobot_logo_svg(44); ?>
        </a>
        <div>
          <h1><?php echo htmlspecialchars(__('settings_page_title')); ?></h1>
          <p class="card-subtext"><?php echo htmlspecialchars(__('settings_page_subtitle')); ?></p>
        </div>
      </div>
      <div class="header-right">
        <?php render_main_nav('settings', $username); ?>
      </div>
    </header>

    <?php if (!empty($msg)): ?>
      <div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="settings-grid">
      
      <!-- Scheda Profilo e Password -->
      <div class="settings-card">
        <h2><?php echo htmlspecialchars(__('profile_title')); ?></h2>
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
          <input type="hidden" name="action" value="update_profile">

          <div class="form-group">
            <label><?php echo htmlspecialchars(__('profile_username')); ?></label>
            <input type="text" value="<?php echo htmlspecialchars($username); ?>" disabled style="opacity: 0.7; cursor: not-allowed;">
          </div>

          <div class="form-group">
            <label for="email"><?php echo htmlspecialchars(__('profile_email')); ?></label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" required>
          </div>

          <div class="form-group">
            <label for="profile_language"><?php echo htmlspecialchars(__('profile_language_label')); ?></label>
            <select id="profile_language" name="profile_language" style="width: 100%; padding: 10px 14px; background: var(--input-bg); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-main); font-size: 0.95rem;">
              <?php 
                $my_lang = strtolower($user_data['language'] ?? $current_lang);
                foreach (get_available_languages() as $l_code): 
              ?>
                <option value="<?php echo htmlspecialchars($l_code); ?>" <?php echo $my_lang === $l_code ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars(get_language_label($l_code)); ?> (<?php echo strtoupper(htmlspecialchars($l_code)); ?>)
                </option>
              <?php endforeach; ?>
            </select>
            <div class="form-help"><?php echo htmlspecialchars(__('profile_language_help')); ?></div>
          </div>

          <div class="form-group">
            <label for="profile_temp_unit"><?php echo htmlspecialchars(__('temp_unit_label', 'Unità di Temperatura')); ?></label>
            <select id="profile_temp_unit" name="profile_temp_unit" style="width: 100%; padding: 10px 14px; background: var(--input-bg); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-main); font-size: 0.95rem;">
              <?php $my_temp_unit = strtoupper($user_data['temp_unit'] ?? 'C'); ?>
              <option value="C" <?php echo $my_temp_unit === 'C' ? 'selected' : ''; ?>><?php echo htmlspecialchars(__('temp_unit_celsius', 'Celsius (°C)')); ?></option>
              <option value="F" <?php echo $my_temp_unit === 'F' ? 'selected' : ''; ?>><?php echo htmlspecialchars(__('temp_unit_fahrenheit', 'Fahrenheit (°F)')); ?></option>
            </select>
            <div class="form-help"><?php echo htmlspecialchars(__('temp_unit_help', 'Scegli la scala di misura per la visualizzazione della temperatura su grafici, card e metriche.')); ?></div>
          </div>

          <div class="form-group">
            <label for="new_password"><?php echo htmlspecialchars(__('profile_new_pass')); ?></label>
            <div style="position: relative;">
              <input type="password" id="new_password" name="new_password" placeholder="<?php echo htmlspecialchars(__('profile_new_pass_ph')); ?>" autocomplete="new-password">
              <button type="button" class="btn-toggle-eye" onclick="togglePasswordVisibility('new_password', this)" title="<?php echo htmlspecialchars(__('pwd_toggle_title', 'Mostra/Nascondi password')); ?>" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--text-muted); cursor: pointer; padding: 4px;">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
              </button>
            </div>
          </div>

          <div class="form-group">
            <label for="confirm_password"><?php echo htmlspecialchars(__('profile_confirm_pass')); ?></label>
            <div style="position: relative;">
              <input type="password" id="confirm_password" name="confirm_password" placeholder="<?php echo htmlspecialchars(__('profile_confirm_pass_ph')); ?>" autocomplete="new-password">
              <button type="button" class="btn-toggle-eye" onclick="togglePasswordVisibility('confirm_password', this)" title="<?php echo htmlspecialchars(__('pwd_toggle_title', 'Mostra/Nascondi password')); ?>" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--text-muted); cursor: pointer; padding: 4px;">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
              </button>
            </div>
          </div>

          <button type="submit" class="btn-action"><?php echo htmlspecialchars(__('profile_btn_save')); ?></button>
        </form>
      </div>

      <!-- Scheda Parametri Rete & Passkey API -->
      <div class="settings-card">
        <h2><?php echo htmlspecialchars(__('infra_title')); ?></h2>
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
          <input type="hidden" name="action" value="update_infra">

          <div class="form-group">
            <label for="api_key"><?php echo htmlspecialchars(__('infra_api_key')); ?></label>
            <div style="position: relative;">
              <input type="password" id="api_key" name="api_key" value="<?php echo htmlspecialchars($current_api_key); ?>" required>
              <button type="button" class="btn-toggle-eye" onclick="togglePasswordVisibility('api_key', this)" title="<?php echo htmlspecialchars(__('pwd_toggle_key_title', 'Mostra/Nascondi chiave')); ?>" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--text-muted); cursor: pointer; padding: 4px;">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
              </button>
            </div>
            <div class="form-help">
              <?php echo htmlspecialchars(__('infra_api_key_help')); ?>
            </div>
          </div>

          <div class="form-group">
            <label for="oracle_ip"><?php echo htmlspecialchars(__('infra_oracle_ip')); ?></label>
            <input type="text" id="oracle_ip" name="oracle_ip" value="<?php echo htmlspecialchars($current_oracle_ip); ?>" placeholder="<?php echo htmlspecialchars(__('infra_oracle_ip_ph')); ?>">
            <div class="form-help">
              <?php echo htmlspecialchars(__('infra_oracle_ip_help')); ?>
            </div>
          </div>

          <div class="form-group">
            <label for="nextdns_ip"><?php echo htmlspecialchars(__('infra_nextdns_ip')); ?></label>
            <input type="text" id="nextdns_ip" name="nextdns_ip" value="<?php echo htmlspecialchars($current_nextdns_ip); ?>" placeholder="<?php echo htmlspecialchars(__('infra_nextdns_ip_ph')); ?>">
            <div class="form-help">
              <?php echo htmlspecialchars(__('infra_nextdns_ip_help')); ?>
            </div>
          </div>

          <button type="submit" class="btn-action"><?php echo htmlspecialchars(__('infra_btn_save')); ?></button>
        </form>
      </div>

      <!-- Scheda Sensori Foobot Fisici (Multi-Foobot) -->
      <div class="settings-card">
        <h2><?php echo htmlspecialchars(__('settings_devices_title', 'Sensori Foobot Fisici Rilevati (Multi-Foobot)')); ?></h2>
        <p class="form-help" style="margin-bottom: 16px;">
          <?php echo htmlspecialchars(__('settings_devices_desc', "Assegna un nome riconoscibile (es. Salotto, Ufficio, Camera) e l'ambiente di riferimento a ciascun Foobot collegato al broker. I nomi appariranno nello switch della Dashboard e nell'App Mobile.")); ?>
        </p>

        <?php 
        $settings_foobots = get_available_foobot_devices($pdo);
        ?>
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
          <input type="hidden" name="action" value="update_foobot_names">

          <div style="display: flex; flex-direction: column; gap: 14px; margin-bottom: 16px;">
            <?php if (empty($settings_foobots)): ?>
              <div style="background: var(--bg-hover); padding: 14px; border-radius: 8px; border: 1px dashed var(--border-color); text-align: center; color: var(--text-muted); font-size: 0.85rem;">
                <?php echo htmlspecialchars(__('settings_devices_empty', 'Nessun sensore Foobot rilevato finora. I dispositivi appariranno automaticamente non appena si connettono al Broker Bridge.')); ?>
              </div>
            <?php else: ?>
              <?php foreach ($settings_foobots as $s_fb): 
                $s_uuid = $s_fb['device_uuid'];
              ?>
              <div style="background: var(--bg-hover); padding: 14px; border-radius: 8px; border: 1px solid var(--border-color);">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                  <span style="font-weight: 700; font-size: 0.95rem;">
                    <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: <?php echo $s_fb['online'] ? '#10b981' : '#94a3b8'; ?>; margin-right: 6px;"></span>
                    UUID: <code><?php echo htmlspecialchars($s_uuid); ?></code>
                  </span>
                  <span class="badge <?php echo $s_fb['online'] ? 'badge-ok' : ''; ?>" style="font-size: 0.72rem;">
                    <?php echo htmlspecialchars($s_fb['online'] ? __('badge_online', 'ONLINE') : __('badge_offline', 'OFFLINE')); ?>
                  </span>
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; margin-top: 10px;">
                  <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.85rem; font-weight: 500; margin-bottom: 6px;"><?php echo htmlspecialchars(__('settings_devices_display_name', 'Nome Display:')); ?></label>
                    <input type="text" name="foobot_names[<?php echo htmlspecialchars($s_uuid); ?>]" value="<?php echo htmlspecialchars($s_fb['device_name']); ?>" placeholder="<?php echo htmlspecialchars(__('settings_devices_name_ph', 'Es. Foobot Casa')); ?>">
                  </div>
                  <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.85rem; font-weight: 500; margin-bottom: 6px;"><?php echo htmlspecialchars(__('settings_devices_location', 'Ambiente / Stanza:')); ?></label>
                    <input type="text" name="foobot_locations[<?php echo htmlspecialchars($s_uuid); ?>]" value="<?php echo htmlspecialchars($s_fb['location']); ?>" placeholder="<?php echo htmlspecialchars(__('settings_devices_location_ph', 'Es. Soggiorno / Studio')); ?>">
                  </div>
                </div>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <?php if (!empty($settings_foobots)): ?>
            <button type="submit" class="btn-action"><?php echo htmlspecialchars(__('settings_devices_btn_save', 'Salva Nomi Sensori')); ?></button>
          <?php endif; ?>
        </form>
      </div>

      <!-- Scheda Sicurezza: Whitelist IP, Filtro Paese e Protezione Ingest -->
      <div class="settings-card">
        <h2><?php echo htmlspecialchars(__('sec_title')); ?></h2>
        
        <div style="background: var(--bg-hover); padding: 12px 14px; border-radius: 6px; margin-bottom: 16px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
          <div>
            <span style="font-size: 0.85rem; color: var(--text-muted);"><?php echo htmlspecialchars(__('sec_current_ip')); ?></span>
            <strong style="color: var(--accent-good); margin-left: 6px;"><?php echo htmlspecialchars($client_ip); ?></strong>
            <?php if ($client_country): ?>
              <span class="badge badge-ok" style="margin-left: 8px;"><?php echo htmlspecialchars(__('sec_country')); ?> <?php echo htmlspecialchars($client_country); ?></span>
            <?php endif; ?>
          </div>
          <button type="button" class="btn-secondary" style="padding: 6px 12px; font-size: 0.85rem;" onclick="addCurrentIpToWhitelist('<?php echo htmlspecialchars($client_ip); ?>')">
            <?php echo htmlspecialchars(__('sec_btn_paste_ip')); ?>
          </button>
        </div>

        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
          <input type="hidden" name="action" value="update_security">

          <div class="form-group">
            <label for="default_language"><?php echo htmlspecialchars(__('sec_default_lang_label')); ?></label>
            <select id="default_language" name="default_language" style="width: 100%; padding: 10px 14px; background: var(--input-bg); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-main); font-size: 0.95rem;">
              <?php foreach (get_available_languages() as $l_code): ?>
                <option value="<?php echo htmlspecialchars($l_code); ?>" <?php echo $default_site_language === $l_code ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars(get_language_label($l_code)); ?> (<?php echo strtoupper(htmlspecialchars($l_code)); ?>)
                </option>
              <?php endforeach; ?>
            </select>
            <div class="form-help">
              <?php echo htmlspecialchars(__('sec_default_lang_help')); ?>
            </div>
          </div>

          <div class="form-group">
            <label for="default_temp_unit"><?php echo htmlspecialchars(__('sec_default_temp_unit_label', 'Unità di Temperatura Predefinita del Sito')); ?></label>
            <select id="default_temp_unit" name="default_temp_unit" style="width: 100%; padding: 10px 14px; background: var(--input-bg); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-main); font-size: 0.95rem;">
              <option value="C" <?php echo ($default_site_temp_unit ?? 'C') === 'C' ? 'selected' : ''; ?>><?php echo htmlspecialchars(__('temp_unit_celsius', 'Celsius (°C)')); ?> — Standard Internazionale</option>
              <option value="F" <?php echo ($default_site_temp_unit ?? 'C') === 'F' ? 'selected' : ''; ?>><?php echo htmlspecialchars(__('temp_unit_fahrenheit', 'Fahrenheit (°F)')); ?> — Standard USA</option>
            </select>
            <div class="form-help">
              <?php echo htmlspecialchars(__('sec_default_temp_unit_help', 'Scala di temperatura standard utilizzata per i visitatori, le nuove utenze e il display principale del sito.')); ?>
            </div>
          </div>

          <div class="form-group">
            <label for="allowed_countries_select"><?php echo htmlspecialchars(__('sec_limit_country')); ?></label>
            <div style="margin-bottom: 8px;">
              <select id="allowed_countries_select" style="width: 100%; padding: 10px 14px; background: var(--input-bg); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-main); font-size: 0.95rem;">
                <optgroup label="<?php echo htmlspecialchars(__('sec_country_optgroup_presets')); ?>">
                  <option value="" <?php echo $allowed_countries === '' ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars(__('sec_country_all')); ?>
                  </option>
                  <option value="AT,BE,BG,CY,CZ,DE,DK,EE,ES,FI,FR,GR,HR,HU,IE,IT,LT,LU,LV,MT,NL,PL,PT,RO,SE,SI,SK" <?php echo $allowed_countries === 'AT,BE,BG,CY,CZ,DE,DK,EE,ES,FI,FR,GR,HR,HU,IE,IT,LT,LU,LV,MT,NL,PL,PT,RO,SE,SI,SK' ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars(__('sec_country_eu')); ?>
                  </option>
                  <option value="IT,CH,SM,VA" <?php echo $allowed_countries === 'IT,CH,SM,VA' ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars(__('sec_country_extended')); ?>
                  </option>
                  <option value="__custom__" <?php echo ($allowed_countries !== '' && $allowed_countries !== 'AT,BE,BG,CY,CZ,DE,DK,EE,ES,FI,FR,GR,HR,HU,IE,IT,LT,LU,LV,MT,NL,PL,PT,RO,SE,SI,SK' && $allowed_countries !== 'IT,CH,SM,VA' && !isset($iso_country_list[$allowed_countries])) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars(__('sec_country_custom')); ?>
                  </option>
                </optgroup>
                <optgroup label="<?php echo htmlspecialchars(__('sec_country_optgroup_world')); ?>">
                  <?php foreach ($iso_country_list as $c_code => $c_name): ?>
                    <option value="<?php echo htmlspecialchars($c_code); ?>" <?php echo $allowed_countries === $c_code ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($c_name . ' (' . $c_code . ')'); ?>
                    </option>
                  <?php endforeach; ?>
                </optgroup>
              </select>
            </div>
            <div>
              <label for="allowed_countries" style="font-size: 0.82rem; color: var(--text-muted); display: block; margin-bottom: 4px;">
                <?php echo htmlspecialchars(__('sec_country_custom_label')); ?>
              </label>
              <input type="text" id="allowed_countries" name="allowed_countries" value="<?php echo htmlspecialchars($allowed_countries); ?>" placeholder="<?php echo htmlspecialchars(__('sec_country_custom_ph')); ?>" style="width: 100%; text-transform: uppercase;">
            </div>
            <div class="form-help">
              <?php echo htmlspecialchars(__('sec_country_help')); ?>
            </div>
          </div>

          <div class="form-group">
            <label for="admin_ip_whitelist"><?php echo htmlspecialchars(__('sec_whitelist_label')); ?></label>
            <input type="text" id="admin_ip_whitelist" name="admin_ip_whitelist" value="<?php echo htmlspecialchars($admin_ip_whitelist); ?>" placeholder="<?php echo htmlspecialchars(__('sec_whitelist_ph')); ?>">
            <div class="form-help">
              <?php echo htmlspecialchars(__('sec_whitelist_help')); ?>
            </div>
          </div>

          <div class="form-group" style="display: flex; align-items: flex-start; gap: 10px; margin-top: 14px;">
            <input type="checkbox" id="enforce_oracle_ip_api" name="enforce_oracle_ip_api" value="1" <?php echo $enforce_oracle_ip_api ? 'checked' : ''; ?> style="width: 18px; height: 18px; margin-top: 3px;">
            <div>
              <label for="enforce_oracle_ip_api" style="margin-bottom: 2px; cursor: pointer;">
                <?php echo htmlspecialchars(__('sec_enforce_oracle_label')); ?>
              </label>
              <div class="form-help">
                <?php echo htmlspecialchars(__('sec_enforce_oracle_help')); ?>
              </div>
            </div>
          </div>

          <button type="submit" class="btn-action" style="margin-top: 8px;"><?php echo htmlspecialchars(__('sec_btn_save')); ?></button>
        </form>
      </div>

      <!-- Scheda Avviso Email Disconnessione Foobot (>30 minuti) -->
      <div class="settings-card">
        <h2 style="display: flex; align-items: center; gap: 8px;">
          <svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
          <span><?php echo htmlspecialchars(__('alert_email_card_title', 'Notifica Email Disconnessione')); ?></span>
        </h2>
        <p class="card-subtext" style="margin-bottom: 16px;">
          <?php echo htmlspecialchars(__('alert_email_card_desc', 'Invia automaticamente un\'email di avviso all\'amministratore se il Foobot perde il segnale e non trasmette dati per oltre 30 minuti consecutivi. L\'email include i dati della disconnessione e gli ultimi log ricevuti dal Bridge.')); ?>
        </p>

        <form method="POST" id="form-offline-alert" style="background: var(--bg-hover); padding: 16px; border-radius: 8px; margin-bottom: 16px;">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
          <input type="hidden" name="action" value="update_offline_alert">

          <div style="display: flex; align-items: center; justify-content: space-between; gap: 14px; margin-bottom: 14px;">
            <div>
              <div style="font-weight: 600; font-size: 0.95rem; color: var(--text-main);">
                <?php echo htmlspecialchars(__('alert_email_toggle_label', 'Avviso Email Offline (>30 min)')); ?>
              </div>
              <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 2px;">
                <?php echo $offline_email_alert_enabled
                  ? '<span style="color: var(--accent-good); font-weight: 600;">' . htmlspecialchars(__('alert_email_status_active', 'Attivo')) . '</span>'
                  : '<span>' . htmlspecialchars(__('alert_email_status_inactive', 'Disattivato (Default)')) . '</span>'; ?>
              </div>
            </div>
            <label class="toggle-switch" title="<?php echo htmlspecialchars(__('alert_email_toggle_title', 'Attiva o disattiva la notifica email')); ?>">
              <input type="checkbox" name="offline_email_alert_enabled" value="1" <?php echo $offline_email_alert_enabled ? 'checked' : ''; ?> onchange="document.getElementById('form-offline-alert').submit();">
              <span class="toggle-slider"></span>
            </label>
          </div>

          <div style="font-size: 0.85rem; color: var(--text-muted); border-top: 1px solid var(--border-color); padding-top: 12px; display: flex; flex-direction: column; gap: 6px;">
            <div>
              <?php echo htmlspecialchars(__('alert_email_recipient_label', 'Email destinatario (Admin):')); ?>
              <strong style="color: var(--text-main); margin-left: 4px;"><?php echo htmlspecialchars(!empty($user_data['email']) ? $user_data['email'] : __('alert_email_not_set', 'Non impostata nel profilo')); ?></strong>
            </div>
            <div>
              <?php echo htmlspecialchars(__('alert_email_last_sent_label', 'Ultimo avviso inviato:')); ?>
              <strong style="color: var(--text-main); margin-left: 4px;">
                <?php echo $offline_email_last_sent_ts > 0 ? date('d/m/Y H:i:s', $offline_email_last_sent_ts) : htmlspecialchars(__('alert_email_none_sent', 'Nessun avviso inviato finora')); ?>
              </strong>
            </div>
            <div style="margin-top: 6px; padding: 8px 10px; background: var(--bg-hover); border-radius: 6px; font-size: 0.78rem; line-height: 1.4;">
              <strong><?php echo htmlspecialchars(__('smtp_how_it_works')); ?></strong> <?php echo htmlspecialchars(__('smtp_how_it_works_desc')); ?>
            </div>
          </div>

          <!-- Metodo di Invio e Parametri SMTP Cifrati -->
          <div style="border-top: 1px solid var(--border-color); margin-top: 14px; padding-top: 14px;">
            <div class="form-group" style="margin-bottom: 12px;">
              <label for="email_driver" style="font-weight: 600; font-size: 0.88rem;"><?php echo htmlspecialchars(__('smtp_method_label')); ?></label>
              <select id="email_driver" name="email_driver" onchange="toggleSmtpFields(this.value)" style="width: 100%; padding: 8px 12px; background: var(--input-bg); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-main); font-size: 0.9rem;">
                <option value="smtp" <?php echo $current_email_driver === 'smtp' ? 'selected' : ''; ?>><?php echo htmlspecialchars(__('smtp_opt_authenticated')); ?></option>
                <option value="php_mail" <?php echo $current_email_driver === 'php_mail' ? 'selected' : ''; ?>><?php echo htmlspecialchars(__('smtp_opt_php_mail')); ?></option>
              </select>
              <div id="php-mail-warning" style="display: <?php echo $current_email_driver === 'php_mail' ? 'block' : 'none'; ?>; margin-top: 10px; padding: 10px 14px; background: rgba(234, 88, 12, 0.08); border-left: 4px solid #ea580c; border-radius: 4px; color: var(--text-main); font-size: 0.83rem; line-height: 1.45;">
                <strong style="color: #ea580c;">(!) <?php echo htmlspecialchars(__('smtp_warning_title')); ?></strong> <?php echo htmlspecialchars(__('smtp_warning_desc')); ?>
              </div>
              <div class="form-help" style="margin-top: 8px;">
                <?php echo htmlspecialchars(__('smtp_help_text')); ?>
              </div>
            </div>

            <div id="smtp-fields-box" style="display: <?php echo $current_email_driver === 'smtp' ? 'block' : 'none'; ?>; background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 6px; padding: 12px; margin-bottom: 12px;">
              <div class="smtp-grid-row smtp-grid-3">
                <div class="form-group" style="margin-bottom: 0;">
                  <label for="smtp_host" style="font-size: 0.8rem;"><?php echo htmlspecialchars(__('smtp_host_label')); ?></label>
                  <input type="text" id="smtp_host" name="smtp_host" value="<?php echo htmlspecialchars($current_smtp_host); ?>" placeholder="es. mail.tuodominio.it oppure smtp.gmail.com" style="width: 100%; padding: 7px 10px; font-size: 0.85rem;">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                  <label for="smtp_port" style="font-size: 0.8rem;"><?php echo htmlspecialchars(__('smtp_port_label')); ?></label>
                  <input type="number" id="smtp_port" name="smtp_port" value="<?php echo htmlspecialchars((string)$current_smtp_port); ?>" placeholder="465 o 587" style="width: 100%; padding: 7px 10px; font-size: 0.85rem;">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                  <label for="smtp_encryption" style="font-size: 0.8rem;"><?php echo htmlspecialchars(__('smtp_encryption_label')); ?></label>
                  <select id="smtp_encryption" name="smtp_encryption" style="width: 100%; padding: 7px 10px; font-size: 0.85rem; background: var(--input-bg); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-main);">
                    <option value="ssl" <?php echo $current_smtp_enc === 'ssl' ? 'selected' : ''; ?>><?php echo htmlspecialchars(__('smtp_enc_ssl')); ?></option>
                    <option value="tls" <?php echo $current_smtp_enc === 'tls' ? 'selected' : ''; ?>><?php echo htmlspecialchars(__('smtp_enc_tls')); ?></option>
                    <option value="none" <?php echo $current_smtp_enc === 'none' ? 'selected' : ''; ?>><?php echo htmlspecialchars(__('smtp_enc_none')); ?></option>
                  </select>
                </div>
              </div>

              <div class="smtp-grid-row smtp-grid-2">
                <div class="form-group" style="margin-bottom: 0;">
                  <label for="smtp_user" style="font-size: 0.8rem;"><?php echo htmlspecialchars(__('smtp_user_label')); ?></label>
                  <input type="text" id="smtp_user" name="smtp_user" value="<?php echo htmlspecialchars($current_smtp_user); ?>" placeholder="es. foobot@tuodominio.it" style="width: 100%; padding: 7px 10px; font-size: 0.85rem;">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                  <label for="smtp_pass" style="font-size: 0.8rem;"><?php echo htmlspecialchars(__('smtp_pass_label')); ?></label>
                  <div style="position: relative; display: flex; align-items: center;">
                    <input type="password" id="smtp_pass" name="smtp_pass" value="<?php echo htmlspecialchars($current_smtp_pass); ?>" placeholder="<?php echo htmlspecialchars(__('smtp_pass_placeholder')); ?>" autocomplete="new-password" style="width: 100%; padding: 7px 34px 7px 10px; font-size: 0.85rem;">
                    <button type="button" onclick="togglePasswordVisibility('smtp_pass', this)" aria-label="Mostra o nascondi password" style="position: absolute; right: 6px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--text-muted); cursor: pointer; padding: 3px; display: flex; align-items: center;">
                      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    </button>
                  </div>
                </div>
              </div>

              <div class="smtp-grid-row smtp-grid-2">
                <div class="form-group" style="margin-bottom: 0;">
                  <label for="smtp_from_email" style="font-size: 0.8rem;"><?php echo htmlspecialchars(__('smtp_from_email_label')); ?></label>
                  <input type="email" id="smtp_from_email" name="smtp_from_email" value="<?php echo htmlspecialchars($current_smtp_from_email); ?>" placeholder="es. foobot@barbaralerici.net" style="width: 100%; padding: 7px 10px; font-size: 0.85rem;">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                  <label for="smtp_from_name" style="font-size: 0.8rem;"><?php echo htmlspecialchars(__('smtp_from_name_label')); ?></label>
                  <input type="text" id="smtp_from_name" name="smtp_from_name" value="<?php echo htmlspecialchars($current_smtp_from_name); ?>" placeholder="NewFoobot Air Monitor" style="width: 100%; padding: 7px 10px; font-size: 0.85rem;">
                </div>
              </div>
            </div>
          </div>

          <div style="margin-top: 14px; display: flex; justify-content: flex-end;">
            <button type="submit" class="btn-action" style="margin: 0; padding: 6px 14px; font-size: 0.85rem;">
              <?php echo htmlspecialchars(__('alert_email_btn_save', 'Salva Impostazione')); ?>
            </button>
          </div>
        </form>

        <form method="POST" style="margin-bottom: 16px;">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
          <input type="hidden" name="action" value="test_offline_alert_email">
          <button type="submit" class="btn-secondary" style="width: 100%; padding: 9px 14px; font-size: 0.85rem; display: flex; align-items: center; justify-content: center; gap: 8px;">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
            <span><?php echo htmlspecialchars(__('alert_email_btn_test', 'Invia Email di Prova Adesso')); ?></span>
          </button>
        </form>

        <!-- Sezione Registro Storico Invii Email -->
        <div style="border-top: 1px solid var(--border-color); padding-top: 14px;">
          <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; flex-wrap: wrap; gap: 6px;">
            <h3 style="font-size: 0.88rem; margin: 0; font-weight: 600;">
              <?php echo htmlspecialchars(__('alert_email_logs_title', 'Registro Invii Email (Storico)')); ?>
            </h3>
            <?php if (!empty($email_logs_list)): ?>
              <form method="POST" onsubmit="return confirm('<?php echo htmlspecialchars(__('alert_email_confirm_clear', 'Vuoi davvero cancellare lo storico degli invii email?')); ?>');" style="margin: 0;">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="action" value="clear_email_logs">
                <button type="submit" style="background: none; border: 1px solid rgba(239, 68, 68, 0.4); color: #ef4444; border-radius: 4px; padding: 2px 8px; font-size: 0.75rem; cursor: pointer;">
                  <?php echo htmlspecialchars(__('alert_email_btn_clear_logs', 'Svuota Registro')); ?>
                </button>
              </form>
            <?php endif; ?>
          </div>

          <?php if (empty($email_logs_list)): ?>
            <div style="background: var(--bg-hover); padding: 12px; border-radius: 6px; text-align: center; color: var(--text-muted); font-size: 0.8rem;">
              <?php echo htmlspecialchars(__('alert_email_logs_empty', 'Nessuna email registrata nello storico.')); ?>
            </div>
          <?php else: ?>
            <div style="max-height: 220px; overflow-y: auto; overflow-x: auto; border: 1px solid var(--border-color); border-radius: 6px; font-size: 0.78rem;">
              <table style="width: 100%; border-collapse: collapse; table-layout: fixed;">
                <thead style="background: var(--bg-hover); position: sticky; top: 0;">
                  <tr>
                    <th style="padding: 6px 8px; text-align: left; width: 105px;"><?php echo htmlspecialchars(__('email_th_datetime')); ?></th>
                    <th style="padding: 6px 8px; text-align: left; width: 68px;"><?php echo htmlspecialchars(__('email_th_type')); ?></th>
                    <th style="padding: 6px 8px; text-align: left; width: 68px;"><?php echo htmlspecialchars(__('email_th_outcome')); ?></th>
                    <th style="padding: 6px 8px; text-align: left;"><?php echo htmlspecialchars(__('email_th_details')); ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($email_logs_list as $el): 
                    $is_sent = ($el['status'] === 'sent');
                    $is_test_log = ($el['email_type'] === 'test');
                  ?>
                    <tr style="border-top: 1px solid var(--border-color); vertical-align: top;">
                      <td style="padding: 6px 8px; white-space: nowrap; color: var(--text-muted);">
                        <?php 
                          $date_log_fmt = ($current_lang === 'en') ? 'm/d/Y H:i' : (($current_lang === 'de') ? 'd.m.Y H:i' : 'd/m/Y H:i');
                          echo date($date_log_fmt, (int)$el['timestamp']); 
                        ?>
                      </td>
                      <td style="padding: 6px 8px;">
                        <span style="display: inline-block; padding: 1px 5px; border-radius: 4px; font-size: 0.7rem; font-weight: 600; background: <?php echo $is_test_log ? 'rgba(59,130,246,0.15)' : 'rgba(239,68,68,0.15)'; ?>; color: <?php echo $is_test_log ? '#2563eb' : '#dc2626'; ?>;">
                          <?php echo htmlspecialchars($is_test_log ? __('email_badge_test', 'TEST') : __('email_badge_alert', 'ALLERTA')); ?>
                        </span>
                      </td>
                      <td style="padding: 6px 8px;">
                        <span style="font-weight: 600; color: <?php echo $is_sent ? 'var(--accent-good)' : 'var(--accent-danger)'; ?>;">
                          <?php echo htmlspecialchars($is_sent ? __('email_badge_sent', 'INVIATA') : __('email_badge_failed', 'FALLITA')); ?>
                        </span>
                      </td>
                      <td style="padding: 6px 8px; color: var(--text-main); word-break: break-word; white-space: normal; line-height: 1.35;" title="<?php echo htmlspecialchars(translate_email_subject($el['subject'], $current_lang)); ?>">
                        <?php echo htmlspecialchars(translate_email_log_details($el['details'] ?: $el['subject'], $current_lang)); ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Scheda Backup Automatico Programmato (Orario e Frequenza con rotazione a 30 file) -->
      <div class="settings-card">
        <h2><?php echo htmlspecialchars(__('backup_card_title')); ?></h2>
        <p class="card-subtext" style="margin-bottom: 14px;">
          <?php echo htmlspecialchars(__('backup_card_desc')); ?>
        </p>

        <!-- Form Impostazione Frequenza e Orario -->
        <form method="POST" style="background: var(--bg-hover); padding: 14px; border-radius: 6px; margin-bottom: 18px;">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
          <input type="hidden" name="action" value="update_backup_schedule">

          <div style="display: flex; flex-direction: column; gap: 14px; margin-bottom: 14px;">
            <div class="form-group" style="margin-bottom: 0; width: 100%;">
              <label for="backup_interval_days"><?php echo htmlspecialchars(__('backup_interval_label')); ?></label>
              <select id="backup_interval_days" name="backup_interval_days" style="width: 100%; padding: 8px 12px; background: var(--input-bg); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-main); font-size: 0.9rem;">
                <option value="1" <?php echo $backup_interval_days === 1 ? 'selected' : ''; ?>><?php echo htmlspecialchars(__('backup_every_1d')); ?></option>
                <option value="2" <?php echo $backup_interval_days === 2 ? 'selected' : ''; ?>><?php echo htmlspecialchars(__('backup_every_2d')); ?></option>
                <option value="3" <?php echo $backup_interval_days === 3 ? 'selected' : ''; ?>><?php echo htmlspecialchars(__('backup_every_3d')); ?></option>
                <option value="7" <?php echo $backup_interval_days === 7 ? 'selected' : ''; ?>><?php echo htmlspecialchars(__('backup_every_7d')); ?></option>
                <option value="10" <?php echo $backup_interval_days === 10 ? 'selected' : ''; ?>><?php echo htmlspecialchars(__('backup_every_10d')); ?></option>
                <option value="15" <?php echo $backup_interval_days === 15 ? 'selected' : ''; ?>><?php echo htmlspecialchars(__('backup_every_15d')); ?></option>
                <option value="30" <?php echo $backup_interval_days === 30 ? 'selected' : ''; ?>><?php echo htmlspecialchars(__('backup_every_30d')); ?></option>
              </select>
            </div>

            <div class="form-group" style="margin-bottom: 0; width: 100%;">
              <label for="backup_target_hour"><?php echo htmlspecialchars(__('backup_hour_label')); ?></label>
              <select id="backup_target_hour" name="backup_target_hour" style="width: 100%; padding: 8px 12px; background: var(--input-bg); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-main); font-size: 0.9rem;">
                <?php for ($h = 0; $h <= 23; $h++): ?>
                  <option value="<?php echo $h; ?>" <?php echo $backup_target_hour === $h ? 'selected' : ''; ?>>
                    <?php echo sprintf(__('backup_hour_text'), $h); ?> <?php echo $h === 4 ? __('backup_hour_default') : ''; ?>
                  </option>
                <?php endfor; ?>
              </select>
            </div>
          </div>

          <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
            <span style="font-size: 0.85rem; color: var(--text-muted);">
              <?php echo htmlspecialchars(__('backup_last_auto')); ?> <strong><?php echo $last_backup_ts > 0 ? date('d/m/Y H:i:s', $last_backup_ts) : htmlspecialchars(__('backup_none_yet')); ?></strong>
            </span>
            <button type="submit" class="btn-action" style="padding: 6px 14px; font-size: 0.85rem; margin: 0;"><?php echo htmlspecialchars(__('backup_btn_save_schedule')); ?></button>
          </div>
        </form>

        <div style="display:flex; flex-wrap:wrap; gap:10px; margin-bottom: 18px;">
          <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="action" value="run_manual_backup">
            <button type="submit" class="btn-secondary" style="padding: 8px 16px;">
              <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 6px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
              <?php echo htmlspecialchars(__('backup_btn_manual_now', 'Backup manuale dati sensore')); ?>
            </button>
          </form>

          <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="action" value="run_manual_conn_backup">
            <button type="submit" class="btn-secondary" style="padding: 8px 16px; border-color: var(--accent-blue); color: var(--accent-blue);">
              <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 6px;"><polyline points="4 17 10 11 4 5"></polyline><line x1="12" y1="19" x2="20" y2="19"></line></svg>
              <?php echo htmlspecialchars(__('backup_btn_manual_conn', 'Backup manuale connessioni / eventi')); ?>
            </button>
          </form>
        </div>

        <!-- Istruzioni Cron Job cPanel / Esecuzione Notturna e Disconnessione 30 Minuti -->
        <?php
          $is_https_cron = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
          $cron_proto = $is_https_cron ? 'https://' : 'http://';
          $cron_host = $_SERVER['HTTP_HOST'] ?? 'foobot-sensore.barbaralerici.net';
          $cron_dir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
          $cron_url = $cron_proto . $cron_host . ($cron_dir !== '' ? $cron_dir : '') . '/cron_events.php?key=' . urlencode(defined('API_SECRET_KEY') ? API_SECRET_KEY : '');
        ?>
        <div style="margin-bottom: 18px; padding: 12px 14px; background: var(--bg-hover); border: 1px solid var(--border-color); border-radius: 6px;">
          <div style="font-weight: 600; font-size: 0.88rem; margin-bottom: 4px; color: var(--text-main);">
            <?php echo htmlspecialchars(__('cron_box_title', 'Automazione a Computer Spento (Operazioni Pianificate / Cron Job cPanel)')); ?>
          </div>
          <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0 0 8px 0; line-height: 1.45;">
            <?php echo __('cron_box_desc', 'Su hosting cPanel, affinché l\'avviso di disconnessione arrivi tempestivamente a circa <strong>30 minuti</strong> di inattività e i backup notturni siano eseguiti anche a browser chiuso, imposta un Cron Job ogni 5 o 10 minuti (<code>*/5 * * * *</code>):'); ?>
          </p>
          <div style="display: flex; align-items: center; gap: 8px;">
            <input type="text" readonly value="curl -s &quot;<?php echo htmlspecialchars($cron_url); ?>&quot; &gt; /dev/null 2&gt;&amp;1" style="flex: 1; min-width: 0; padding: 6px 10px; font-family: monospace; font-size: 0.78rem; background: var(--input-bg); border: 1px solid var(--border-color); border-radius: 4px; color: var(--text-main);" id="cron-job-command-input">
            <button type="button" class="btn-secondary" style="padding: 6px 12px; font-size: 0.8rem; white-space: nowrap;" onclick="navigator.clipboard.writeText(document.getElementById('cron-job-command-input').value); alert(<?php echo htmlspecialchars(json_encode(__('cron_copied_alert')), ENT_QUOTES, 'UTF-8'); ?>);"><?php echo htmlspecialchars(__('cron_copy_btn')); ?></button>
          </div>
        </div>

        <h3 style="font-size: 0.95rem; margin-top: 10px; margin-bottom: 4px;"><?php echo htmlspecialchars(__('backup_files_title')); ?></h3>
        <?php
          $backup_real_path = str_replace('\\', '/', __DIR__ . '/backups/');
          $host_domain = $_SERVER['HTTP_HOST'] ?? 'tuo-dominio';
        ?>
        <div style="font-size: 0.78rem; color: var(--text-muted); margin-bottom: 10px; padding: 6px 10px; background: var(--bg-hover); border: 1px dashed var(--border-color); border-radius: 6px; word-break: break-all;">
          <strong><?php echo htmlspecialchars(__('backup_ftp_path_label')); ?></strong> <code><?php echo htmlspecialchars($backup_real_path); ?></code>
        </div>
        <?php 
        $all_bk_files = array_merge($backup_files_list, $conn_backup_files_list);
        usort($all_bk_files, function($a, $b) { return $b['mtime'] - $a['mtime']; });
        if (empty($all_bk_files)): ?>
          <p style="color: var(--text-muted); font-size: 0.9rem;"><?php echo htmlspecialchars(__('backup_none_present')); ?></p>
        <?php else: ?>
          <div style="max-height: 240px; overflow-y: auto; overflow-x: auto; border: 1px solid var(--border-color); border-radius: 6px; margin-bottom: 14px;">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
              <thead style="background: var(--bg-hover);">
                <tr>
                  <th style="padding: 8px; text-align: left;"><?php echo htmlspecialchars(__('backup_th_type_filename')); ?></th>
                  <th style="padding: 8px; text-align: left;"><?php echo htmlspecialchars(__('backup_th_date')); ?></th>
                  <th style="padding: 8px; text-align: right;"><?php echo htmlspecialchars(__('backup_th_size')); ?></th>
                  <th style="padding: 8px; text-align: center;"><?php echo htmlspecialchars(__('backup_th_actions', 'Azioni')); ?></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($all_bk_files as $bf): 
                  $is_conn = strpos($bf['name'], 'connection_logs') !== false;
                ?>
                  <tr style="border-top: 1px solid var(--border-color);">
                    <td style="padding: 8px;">
                      <span style="display:inline-block; padding:2px 6px; border-radius:4px; font-size:0.7rem; font-weight:700; margin-right:6px; background:<?php echo $is_conn ? 'rgba(59,130,246,0.15)' : 'rgba(16,185,129,0.15)'; ?>; color:<?php echo $is_conn ? '#2563eb' : '#059669'; ?>;">
                        <?php echo htmlspecialchars($is_conn ? __('backup_badge_conn_logs', 'CONN LOGS') : __('backup_badge_sensor_data', 'DATI SENSORI')); ?>
                      </span>
                      <code><?php echo htmlspecialchars($bf['name']); ?></code>
                    </td>
                    <td style="padding: 8px; color: var(--text-muted);"><?php echo date('d/m/Y H:i:s', $bf['mtime']); ?></td>
                    <td style="padding: 8px; text-align: right;"><?php echo round($bf['size'] / 1024, 1); ?> KB</td>
                    <td style="padding: 8px; text-align: center;">
                      <div style="display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
                        <a href="settings.php?download_backup=<?php echo urlencode($bf['name']); ?>" class="btn-secondary" style="padding: 3px 8px; font-size: 0.75rem; text-decoration: none;" title="<?php echo htmlspecialchars(__('backup_btn_download')); ?>">
                          <?php echo htmlspecialchars(__('backup_btn_download')); ?>
                        </a>
                        <form method="POST" style="display: inline; margin: 0;" onsubmit="return confirm('<?php echo htmlspecialchars(__('backup_confirm_delete', 'Sei sicuro di voler eliminare definitivamente questo file di backup?')); ?>');">
                          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                          <input type="hidden" name="action" value="delete_backup_file">
                          <input type="hidden" name="backup_filename" value="<?php echo htmlspecialchars($bf['name']); ?>">
                          <button type="submit" class="btn-trash btn-trash-sm" title="<?php echo htmlspecialchars(__('backup_btn_delete', 'Elimina backup')); ?> <?php echo htmlspecialchars($bf['name']); ?>" aria-label="<?php echo htmlspecialchars(__('backup_btn_delete', 'Elimina backup')); ?>" style="display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 6px; border: 1px solid rgba(239, 68, 68, 0.4); background: rgba(239, 68, 68, 0.1); color: #ef4444; cursor: pointer;">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:block;"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                          </button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <!-- Scheda Gestione Utenti e Permessi di Accesso -->
      <div class="settings-card">
        <h2 style="display: flex; align-items: center; gap: 8px;">
          <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
          <span><?php echo htmlspecialchars(__('users_card_title')); ?></span>
        </h2>
        <p class="card-subtext" style="margin-bottom: 16px;">
          <?php echo htmlspecialchars(__('users_card_desc')); ?>
        </p>

        <!-- Form Aggiunta Nuovo Utente -->
        <form method="POST" style="background: var(--bg-hover); padding: 16px; border-radius: 8px; margin-bottom: 20px;">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
          <input type="hidden" name="action" value="add_user">
          <h3 style="font-size: 0.95rem; margin-bottom: 12px; font-weight:600;"><?php echo htmlspecialchars(__('users_add_account_title')); ?></h3>

          <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 12px;">
            <div class="form-group" style="margin-bottom:0;">
              <label for="new_username"><?php echo htmlspecialchars(__('users_username_label')); ?></label>
              <input type="text" id="new_username" name="new_username" required placeholder="es. collaboratore" style="width:100%; padding:8px 12px; background:var(--input-bg); border:1px solid var(--border-color); border-radius:6px; color:var(--text-main);">
            </div>
            <div class="form-group" style="margin-bottom:0;">
              <label for="new_email"><?php echo htmlspecialchars(__('users_email_optional')); ?></label>
              <input type="email" id="new_email" name="new_email" placeholder="utente@example.com" style="width:100%; padding:8px 12px; background:var(--input-bg); border:1px solid var(--border-color); border-radius:6px; color:var(--text-main);">
            </div>
            <div class="form-group" style="margin-bottom:0;">
              <label for="create_user_password"><?php echo htmlspecialchars(__('users_password_label')); ?></label>
              <div class="password-wrapper" style="position:relative; display:flex; align-items:center;">
                <input type="password" id="create_user_password" name="new_password" required minlength="6" placeholder="••••••••" style="width:100%; padding:8px 38px 8px 12px; background:var(--input-bg); border:1px solid var(--border-color); border-radius:6px; color:var(--text-main);">
                <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('create_user_password', this)" aria-label="Mostra o nascondi password" style="position:absolute; right:8px; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--text-muted); cursor:pointer; padding:4px; display:flex; align-items:center;">
                  <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                </button>
              </div>
            </div>
            <div class="form-group" style="margin-bottom:0;">
              <label for="new_role"><?php echo htmlspecialchars(__('users_role_label')); ?></label>
              <select id="new_role" name="new_role" style="width:100%; padding:8px 12px; background:var(--input-bg); border:1px solid var(--border-color); border-radius:6px; color:var(--text-main);">
                <option value="user" selected><?php echo htmlspecialchars(__('users_role_user')); ?></option>
                <option value="admin"><?php echo htmlspecialchars(__('users_role_admin')); ?></option>
              </select>
            </div>
            <div class="form-group" style="margin-bottom:0;">
              <label for="new_language"><?php echo htmlspecialchars(__('users_preferred_lang_label')); ?></label>
              <select id="new_language" name="new_language" style="width:100%; padding:8px 12px; background:var(--input-bg); border:1px solid var(--border-color); border-radius:6px; color:var(--text-main);">
                <?php foreach (get_available_languages() as $l_code): ?>
                  <option value="<?php echo htmlspecialchars($l_code); ?>" <?php echo $l_code === 'en' ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars(get_language_label($l_code)); ?> (<?php echo strtoupper(htmlspecialchars($l_code)); ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group" style="margin-bottom:0;">
              <label for="new_temp_unit"><?php echo htmlspecialchars(__('temp_unit_label', 'Unità di Temperatura')); ?></label>
              <select id="new_temp_unit" name="new_temp_unit" style="width:100%; padding:8px 12px; background:var(--input-bg); border:1px solid var(--border-color); border-radius:6px; color:var(--text-main);">
                <option value="C" selected><?php echo htmlspecialchars(__('temp_unit_celsius', 'Celsius (°C)')); ?></option>
                <option value="F"><?php echo htmlspecialchars(__('temp_unit_fahrenheit', 'Fahrenheit (°F)')); ?></option>
              </select>
            </div>
          </div>

          <button type="submit" class="btn-action" style="margin:0; padding:8px 16px; font-size:0.9rem;"><?php echo htmlspecialchars(__('users_btn_create')); ?></button>
        </form>

        <!-- Elenco Utenti Secondari con Accordion di Modifica e Cancellazione -->
        <h3 style="font-size: 0.95rem; margin-bottom: 8px; font-weight:600;"><?php echo htmlspecialchars(__('users_registered_title')); ?></h3>
        <?php 
          // Filtra la lista escludendo l'amministratore primario (i cui dati sono sotto Profilo Amministratore)
          $secondary_users = array_filter($all_users_list, function($u) use ($primary_admin_id) {
              return (int)$u['id'] !== $primary_admin_id;
          });
        ?>
        <?php if (empty($secondary_users)): ?>
          <div style="border: 1px solid var(--border-color); border-radius: 6px; padding: 16px; text-align: center; color: var(--text-muted); font-size: 0.85rem;">
            <?php echo htmlspecialchars(__('users_empty_list')); ?>
          </div>
        <?php else: ?>
          <div style="display: flex; flex-direction: column; gap: 10px;">
            <?php foreach ($secondary_users as $u): 
              $u_id = (int)$u['id'];
              $u_role = $u['role'] ?? 'user';
              $is_self = ($u['username'] === $username);
            ?>
              <div style="border: 1px solid var(--border-color); border-radius: 8px; background: var(--card-bg); overflow: hidden;">
                <!-- Riga Riepilogo Utente + Pulsanti Azione -->
                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; padding: 10px 14px; background: var(--bg-hover);">
                  <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 8px;">
                    <span style="font-weight: 700; font-size: 0.92rem; color: var(--text-main);">
                      <?php echo htmlspecialchars($u['username']); ?>
                    </span>
                    <?php if ($is_self): ?>
                      <span style="font-size: 0.72rem; color: var(--text-muted);"><?php echo htmlspecialchars(__('users_badge_you')); ?></span>
                    <?php endif; ?>
                    <span style="display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 0.72rem; font-weight: 600; background: <?php echo $u_role === 'admin' ? 'rgba(239,68,68,0.15)' : 'rgba(59,130,246,0.15)'; ?>; color: <?php echo $u_role === 'admin' ? '#dc2626' : '#2563eb'; ?>;">
                      <?php echo $u_role === 'admin' ? htmlspecialchars(__('users_badge_admin')) : htmlspecialchars(__('users_badge_viewer')); ?>
                    </span>
                    <span style="display: inline-block; padding: 2px 7px; border-radius: 4px; font-size: 0.72rem; font-weight: 600; background: var(--surface-alt); color: var(--text-main); border: 1px solid var(--border-color);" title="<?php echo htmlspecialchars(__('users_edit_account_lang')); ?>">
                      <?php echo strtoupper(htmlspecialchars($u['language'] ?? 'en')); ?>
                    </span>
                    <?php if (!empty($u['email'])): ?>
                      <span style="font-size: 0.78rem; color: var(--text-muted);">• <?php echo htmlspecialchars($u['email']); ?></span>
                    <?php endif; ?>
                  </div>

                  <div style="display: flex; align-items: center; gap: 8px;">
                    <!-- Pulsante Apri/Chiudi Accordion Modifica -->
                    <button type="button" onclick="toggleUserAccordion(<?php echo $u_id; ?>)" style="display: inline-flex; align-items: center; gap: 5px; padding: 5px 10px; border-radius: 6px; border: 1px solid rgba(59, 130, 246, 0.4); background: rgba(59, 130, 246, 0.1); color: var(--accent-blue); font-size: 0.78rem; font-weight: 600; cursor: pointer;">
                      <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                      <span><?php echo htmlspecialchars(__('users_btn_edit_label')); ?></span>
                      <svg id="acc-chevron-<?php echo $u_id; ?>" viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="transition: transform 0.2s ease;"><polyline points="6 9 12 15 18 9"></polyline></svg>
                    </button>

                    <!-- Pulsante Cancella Utente con Icona Cestino -->
                    <?php if (!$is_self): ?>
                      <form method="POST" onsubmit="return confirm(<?php echo htmlspecialchars(json_encode(sprintf(__('users_confirm_delete'), $u['username'])), ENT_QUOTES, 'UTF-8'); ?>);" style="display: inline; margin: 0;">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="action" value="delete_user">
                        <input type="hidden" name="user_id" value="<?php echo $u_id; ?>">
                        <button type="submit" title="<?php echo htmlspecialchars(__('users_btn_delete')); ?>" aria-label="<?php echo htmlspecialchars(__('users_btn_delete')); ?>" style="display: inline-flex; align-items: center; justify-content: center; gap: 4px; padding: 5px 9px; border-radius: 6px; border: 1px solid rgba(239, 68, 68, 0.4); background: rgba(239, 68, 68, 0.1); color: #ef4444; font-size: 0.78rem; font-weight: 600; cursor: pointer;">
                          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                        </button>
                      </form>
                    <?php endif; ?>
                  </div>
                </div>

                <!-- Tendina Accordion per Modificare Campi Utente -->
                <div id="user-accordion-<?php echo $u_id; ?>" style="display: none; padding: 14px; border-top: 1px solid var(--border-color); background: var(--card-bg);">
                  <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="action" value="edit_user">
                    <input type="hidden" name="user_id" value="<?php echo $u_id; ?>">

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; margin-bottom: 12px;">
                      <div class="form-group" style="margin-bottom: 0;">
                        <label for="edit_uname_<?php echo $u_id; ?>" style="font-size: 0.8rem;"><?php echo htmlspecialchars(__('users_username_label')); ?></label>
                        <input type="text" id="edit_uname_<?php echo $u_id; ?>" name="username" value="<?php echo htmlspecialchars($u['username']); ?>" required style="width: 100%; padding: 7px 10px; background: var(--input-bg); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-main); font-size: 0.85rem;">
                      </div>

                      <div class="form-group" style="margin-bottom: 0;">
                        <label for="edit_email_<?php echo $u_id; ?>" style="font-size: 0.8rem;"><?php echo htmlspecialchars(__('users_email_optional')); ?></label>
                        <input type="email" id="edit_email_<?php echo $u_id; ?>" name="email" value="<?php echo htmlspecialchars($u['email'] ?? ''); ?>" placeholder="utente@example.com" style="width: 100%; padding: 7px 10px; background: var(--input-bg); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-main); font-size: 0.85rem;">
                      </div>

                      <div class="form-group" style="margin-bottom: 0;">
                        <label for="edit_role_<?php echo $u_id; ?>" style="font-size: 0.8rem;"><?php echo htmlspecialchars(__('users_role_label')); ?></label>
                        <select id="edit_role_<?php echo $u_id; ?>" name="role" style="width: 100%; padding: 7px 10px; background: var(--input-bg); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-main); font-size: 0.85rem;">
                          <option value="user" <?php echo $u_role === 'user' ? 'selected' : ''; ?>><?php echo htmlspecialchars(__('users_badge_viewer')); ?></option>
                          <option value="admin" <?php echo $u_role === 'admin' ? 'selected' : ''; ?>><?php echo htmlspecialchars(__('users_badge_admin')); ?></option>
                        </select>
                      </div>

                      <div class="form-group" style="margin-bottom: 0;">
                        <label for="edit_lang_<?php echo $u_id; ?>" style="font-size: 0.8rem;"><?php echo htmlspecialchars(__('users_edit_account_lang')); ?></label>
                        <select id="edit_lang_<?php echo $u_id; ?>" name="language" style="width: 100%; padding: 7px 10px; background: var(--input-bg); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-main); font-size: 0.85rem;">
                          <?php 
                            $u_lang_val = strtolower($u['language'] ?? 'en');
                            foreach (get_available_languages() as $l_code): 
                          ?>
                            <option value="<?php echo htmlspecialchars($l_code); ?>" <?php echo $u_lang_val === $l_code ? 'selected' : ''; ?>>
                              <?php echo htmlspecialchars(get_language_label($l_code)); ?> (<?php echo strtoupper(htmlspecialchars($l_code)); ?>)
                            </option>
                          <?php endforeach; ?>
                        </select>
                      </div>

                      <div class="form-group" style="margin-bottom: 0;">
                        <label for="edit_temp_<?php echo $u_id; ?>" style="font-size: 0.8rem;"><?php echo htmlspecialchars(__('temp_unit_label', 'Unità di Temperatura')); ?></label>
                        <select id="edit_temp_<?php echo $u_id; ?>" name="temp_unit" style="width: 100%; padding: 7px 10px; background: var(--input-bg); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-main); font-size: 0.85rem;">
                          <?php $u_temp_val = strtoupper($u['temp_unit'] ?? 'C'); ?>
                          <option value="C" <?php echo $u_temp_val === 'C' ? 'selected' : ''; ?>><?php echo htmlspecialchars(__('temp_unit_celsius', 'Celsius (°C)')); ?></option>
                          <option value="F" <?php echo $u_temp_val === 'F' ? 'selected' : ''; ?>><?php echo htmlspecialchars(__('temp_unit_fahrenheit', 'Fahrenheit (°F)')); ?></option>
                        </select>
                      </div>

                      <div class="form-group" style="margin-bottom: 0;">
                        <label for="edit_pwd_<?php echo $u_id; ?>" style="font-size: 0.8rem;"><?php echo htmlspecialchars(__('users_edit_new_pass_optional')); ?></label>
                        <div style="position: relative; display: flex; align-items: center;">
                          <input type="password" id="edit_pwd_<?php echo $u_id; ?>" name="new_password" minlength="6" placeholder="<?php echo htmlspecialchars(__('smtp_pass_placeholder')); ?>" autocomplete="new-password" style="width: 100%; padding: 7px 34px 7px 10px; background: var(--input-bg); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-main); font-size: 0.85rem;">
                          <button type="button" onclick="togglePasswordVisibility('edit_pwd_<?php echo $u_id; ?>', this)" aria-label="Mostra o nascondi password" style="position: absolute; right: 6px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--text-muted); cursor: pointer; padding: 3px; display: flex; align-items: center;">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                          </button>
                        </div>
                      </div>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                      <span style="font-size: 0.75rem; color: var(--text-muted);"><?php echo htmlspecialchars(__('users_created_at_label')); ?> <?php echo htmlspecialchars($u['created_at'] ?? '—'); ?></span>
                      <div style="display: flex; gap: 8px;">
                        <button type="button" class="btn-secondary" onclick="toggleUserAccordion(<?php echo $u_id; ?>)" style="padding: 6px 12px; font-size: 0.8rem; cursor: pointer;"><?php echo htmlspecialchars(__('users_btn_close')); ?></button>
                        <button type="submit" class="btn-action" style="margin: 0; padding: 6px 14px; font-size: 0.8rem; cursor: pointer;"><?php echo htmlspecialchars(__('users_btn_save_changes')); ?></button>
                      </div>
                    </div>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Scheda Manutenzione e Cancellazione Dati Database -->
      <div class="settings-card" style="border: 2px solid var(--accent-danger); padding: 28px;">
        <h2 style="color: var(--accent-danger); display: flex; align-items: center; gap: 8px;">
          <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
          <span><?php echo htmlspecialchars(__('delete_card_title')); ?></span>
        </h2>
        
        <div style="background: var(--bg-hover); padding: 10px 14px; border-radius: 6px; margin-bottom: 16px; font-size: 0.9rem;">
          <div><?php echo htmlspecialchars(__('delete_total_readings')); ?> <strong><?php echo number_format($stat_count_total, 0, ',', '.'); ?></strong></div>
          <?php if ($stat_count_total > 0): ?>
            <div style="color: var(--text-muted); font-size: 0.85rem; margin-top: 4px;">
              <?php echo sprintf(__('delete_recorded_period'), date('d/m/Y H:i', (int)$stat_oldest), date('d/m/Y H:i', (int)$stat_newest)); ?>
            </div>
          <?php endif; ?>
        </div>

        <form method="POST" onsubmit="return confirm('<?php echo addslashes(__('delete_confirm_alert')); ?>');">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
          <input type="hidden" name="action" value="delete_readings">

          <div class="form-group">
            <label for="delete_mode"><?php echo htmlspecialchars(__('delete_select_label')); ?></label>
            <select id="delete_mode" name="delete_mode" onchange="toggleDateRangeInputs(this.value)" style="width: 100%; padding: 10px 14px; background: var(--input-bg); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-main); font-size: 0.95rem;">
              <option value="older_30d"><?php echo htmlspecialchars(__('delete_older_30d')); ?></option>
              <option value="older_7d"><?php echo htmlspecialchars(__('delete_older_7d')); ?></option>
              <option value="range"><?php echo htmlspecialchars(__('delete_range_dates')); ?></option>
              <option value="all"><?php echo htmlspecialchars(__('delete_all_data')); ?></option>
            </select>
          </div>

          <div id="range-inputs" style="display: none; margin-bottom: 14px;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
              <div class="form-group" style="margin-bottom: 0;">
                <label for="date_from"><?php echo htmlspecialchars(__('delete_start_date')); ?></label>
                <input type="date" id="date_from" name="date_from">
              </div>
              <div class="form-group" style="margin-bottom: 0;">
                <label for="date_to"><?php echo htmlspecialchars(__('delete_end_date')); ?></label>
                <input type="date" id="date_to" name="date_to">
              </div>
            </div>
          </div>

          <div class="form-group">
            <label for="confirm_delete" style="color: var(--accent-danger);">
              <?php echo htmlspecialchars(__('delete_security_type')); ?>
            </label>
            <input type="text" id="confirm_delete" name="confirm_delete" placeholder="<?php echo htmlspecialchars(__('delete_security_ph')); ?>" required autocomplete="off">
          </div>

          <button type="submit" class="btn-action" style="background: var(--accent-danger);">
            <?php echo htmlspecialchars(__('delete_btn_submit')); ?>
          </button>
        </form>
      </div>

    </div>

  </div>

  <script>
    function toggleUserAccordion(userId) {
      var panel = document.getElementById('user-accordion-' + userId);
      var chevron = document.getElementById('acc-chevron-' + userId);
      if (!panel) return;
      if (panel.style.display === 'none' || panel.style.display === '') {
        panel.style.display = 'block';
        if (chevron) chevron.style.transform = 'rotate(180deg)';
      } else {
        panel.style.display = 'none';
        if (chevron) chevron.style.transform = 'rotate(0deg)';
      }
    }
    function togglePasswordVisibility(fieldId, btn) {
      var field = document.getElementById(fieldId);
      if (!field) return;
      if (field.type === 'password') {
        field.type = 'text';
        btn.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></line><line x1="1" y1="1" x2="23" y2="23"></line></svg>';
      } else {
        field.type = 'password';
        btn.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
      }
    }

    function addCurrentIpToWhitelist(ip) {
      var input = document.getElementById('admin_ip_whitelist');
      if (!input || !ip) return;
      var current = input.value.trim();
      if (current === '') {
        input.value = ip;
      } else {
        var list = current.split(/[\s,;]+/).filter(Boolean);
        if (list.indexOf(ip) === -1) {
          list.push(ip);
        }
        input.value = list.join(', ');
      }
      input.focus();
    }

    function toggleDateRangeInputs(mode) {
      var rangeBox = document.getElementById('range-inputs');
      if (rangeBox) {
        rangeBox.style.display = (mode === 'range') ? 'block' : 'none';
      }
    }

    function toggleSmtpFields(driver) {
      var box = document.getElementById('smtp-fields-box');
      var warn = document.getElementById('php-mail-warning');
      if (box) {
        box.style.display = (driver === 'smtp') ? 'block' : 'none';
      }
      if (warn) {
        warn.style.display = (driver === 'php_mail') ? 'block' : 'none';
      }
    }

    document.addEventListener('DOMContentLoaded', function() {
      var countrySelect = document.getElementById('allowed_countries_select');
      var countryInput = document.getElementById('allowed_countries');
      if (countrySelect && countryInput) {
        countrySelect.addEventListener('change', function() {
          if (this.value !== '__custom__') {
            countryInput.value = this.value;
          }
        });
        countryInput.addEventListener('input', function() {
          var val = this.value.trim().toUpperCase();
          var matched = false;
          for (var i = 0; i < countrySelect.options.length; i++) {
            if (countrySelect.options[i].value === val) {
              countrySelect.selectedIndex = i;
              matched = true;
              break;
            }
          }
          if (!matched) {
            for (var j = 0; j < countrySelect.options.length; j++) {
              if (countrySelect.options[j].value === '__custom__') {
                countrySelect.selectedIndex = j;
                break;
              }
            }
          }
        });
      }
    });
  </script>
</body>
</html>
