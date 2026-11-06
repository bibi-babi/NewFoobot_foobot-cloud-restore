<?php
/**
 * Cron Eventi e Automazioni NewFoobot (Web-Cron & CLI)
 * Gestisce:
 * 1. Verifica e allerta email tempestiva disconnessione sensore (>30 min)
 * 2. Backup automatico pianificato letture sensore (rotazione max 30 CSV)
 * 3. Sincronizzazione heartbeat eventi da bridge
 */

$config_path = __DIR__ . '/config.php';
if (!file_exists($config_path)) {
    return;
}
require_once $config_path;
require_once __DIR__ . '/auth.php';

// Fuso orario di riferimento italiano (Europe/Rome)
if (function_exists('date_default_timezone_set')) {
    @date_default_timezone_set('Europe/Rome');
}

/**
 * Esegue il backup delle letture e mantiene al massimo $keep_max file.
 * @param int $keep_max Numero massimo di file CSV da conservare (rotazione)
 * @param bool $is_auto Se true, aggiorna last_auto_backup_ts; se false (manuale), aggiorna last_manual_backup_ts
 */
/**
 * Esegue il backup delle letture separato per ogni sensore Foobot (con UUID nel nome file)
 * e mantiene al massimo $keep_max file per ciascun sensore.
 * @param int $keep_max Numero massimo di file CSV da conservare per sensore (rotazione)
 * @param bool $is_auto Se true, aggiorna last_auto_backup_ts; se false (manuale), aggiorna last_manual_backup_ts
 */
function perform_foobot_backup($keep_max = 30, $is_auto = false) {
    $pdo = get_db_connection();
    if (!$pdo instanceof PDO) {
        return ['success' => false, 'message' => 'Impossibile connettersi al database.'];
    }

    $backup_dir = __DIR__ . '/backups';
    if (!is_dir($backup_dir)) {
        if (!mkdir($backup_dir, 0755, true)) {
            return ['success' => false, 'message' => 'Impossibile creare la cartella backups.'];
        }
    }

    $htaccess_file = $backup_dir . '/.htaccess';
    if (!file_exists($htaccess_file)) {
        @file_put_contents($htaccess_file, "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder deny,allow\nDeny from all\n</IfModule>\n");
    }

    $timestamp = date('Y-m-d_H-i-s');

    // Rileva tutti i device_uuid distinti presenti nel database (Multi-Foobot)
    $stmt_devs = $pdo->query("SELECT DISTINCT device_uuid FROM foobot_readings");
    $uuids = $stmt_devs ? $stmt_devs->fetchAll(PDO::FETCH_COLUMN) : [];
    if (empty($uuids)) {
        $uuids = ['foobot'];
    }

    $created_files = [];
    $total_exported_all = 0;

    foreach ($uuids as $raw_uuid) {
        $safe_uuid = preg_replace('/[^A-Za-z0-9_-]/', '', (string)$raw_uuid) ?: 'foobot';
        $filename = sprintf('foobot_backup_%s_%s.csv', $safe_uuid, $timestamp);
        $filepath = $backup_dir . '/' . $filename;

        $fp = fopen($filepath, 'w');
        if (!$fp) {
            continue;
        }

        // Intestazione CSV standard IT (punto e virgola per Excel)
        fputcsv($fp, ['id', 'device_uuid', 'timestamp', 'datetime_utc', 'allpollu', 'pm', 'voc', 'co2', 'tmp', 'hum'], ';');

        $stmt = $pdo->prepare("SELECT id, device_uuid, timestamp, allpollu, pm, voc, co2, tmp, hum 
                               FROM foobot_readings 
                               WHERE device_uuid = ? 
                               ORDER BY timestamp ASC");
        $stmt->execute([$raw_uuid]);
        $device_rows = 0;

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $dt = gmdate('Y-m-d H:i:s', (int)$row['timestamp']);
            fputcsv($fp, [
                $row['id'],
                $row['device_uuid'],
                $row['timestamp'],
                $dt,
                $row['allpollu'],
                $row['pm'],
                $row['voc'],
                $row['co2'],
                $row['tmp'],
                $row['hum']
            ], ';');
            $device_rows++;
        }
        fclose($fp);

        $total_exported_all += $device_rows;
        $created_files[] = $filename;

        // Rotazione indipendente per questo specifico sensore (conserva solo i $keep_max più recenti)
        $pattern = $backup_dir . '/foobot_backup_' . $safe_uuid . '_*.csv';
        $sensor_files = glob($pattern) ?: [];
        if (count($sensor_files) > $keep_max) {
            usort($sensor_files, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            $to_delete = count($sensor_files) - $keep_max;
            for ($i = 0; $i < $to_delete; $i++) {
                if (isset($sensor_files[$i]) && is_file($sensor_files[$i])) {
                    @unlink($sensor_files[$i]);
                }
            }
        }
    }

    // Esegui anche il backup contestuale degli eventi di connessione (anch'essi divisi per sensore)
    $conn_res = perform_connection_logs_backup($keep_max);

    // Aggiorna il timestamp dell'ultimo backup eseguito
    if ($is_auto) {
        save_setting($pdo, 'last_auto_backup_ts', (string)time());
    } else {
        save_setting($pdo, 'last_manual_backup_ts', (string)time());
    }

    $remaining_all = glob($backup_dir . '/foobot_backup_*.csv') ?: [];

    return [
        'success'        => true,
        'filename'       => implode(', ', $created_files),
        'files_created'  => $created_files,
        'rows_exported'  => $total_exported_all,
        'files_kept'     => count($remaining_all),
        'conn_backup'    => $conn_res,
        'message'        => sprintf('Backup completato con successo per %d sensori (%d letture esportate). File creati: %s.', count($uuids), $total_exported_all, implode(', ', $created_files))
    ];
}

/**
 * Esegue il backup dedicato del registro eventi di connessione (foobot_connection_logs)
 * suddiviso per ciascun sensore Foobot (con UUID nel nome file)
 */
function perform_connection_logs_backup($keep_max = 30) {
    $pdo = get_db_connection();
    if (!$pdo instanceof PDO) {
        return ['success' => false, 'message' => 'Impossibile connettersi al database.'];
    }

    $backup_dir = __DIR__ . '/backups';
    if (!is_dir($backup_dir)) {
        if (!mkdir($backup_dir, 0755, true)) {
            return ['success' => false, 'message' => 'Impossibile creare cartella backups.'];
        }
    }

    $timestamp = date('Y-m-d_H-i-s');

    // Rileva tutti i device_uuid distinti presenti nella tabella connection_logs
    $stmt_devs = $pdo->query("SELECT DISTINCT device_uuid FROM foobot_connection_logs");
    $uuids = $stmt_devs ? $stmt_devs->fetchAll(PDO::FETCH_COLUMN) : [];
    if (empty($uuids)) {
        $uuids = ['foobot'];
    }

    $created_files = [];
    $total_exported_all = 0;

    foreach ($uuids as $raw_uuid) {
        $safe_uuid = preg_replace('/[^A-Za-z0-9_-]/', '', (string)$raw_uuid) ?: 'foobot';
        $filename = sprintf('foobot_connection_logs_%s_%s.csv', $safe_uuid, $timestamp);
        $filepath = $backup_dir . '/' . $filename;

        $fp = fopen($filepath, 'w');
        if (!$fp) {
            continue;
        }

        fputcsv($fp, ['id', 'device_uuid', 'event_type', 'timestamp', 'datetime_utc', 'details', 'created_at'], ';');

        $stmt = $pdo->prepare("SELECT id, device_uuid, event_type, timestamp, details, created_at 
                               FROM foobot_connection_logs 
                               WHERE device_uuid = ? 
                               ORDER BY timestamp ASC");
        $stmt->execute([$raw_uuid]);
        $device_rows = 0;

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $dt = gmdate('Y-m-d H:i:s', (int)$row['timestamp']);
            fputcsv($fp, [
                $row['id'],
                $row['device_uuid'],
                $row['event_type'],
                $row['timestamp'],
                $dt,
                $row['details'],
                $row['created_at']
            ], ';');
            $device_rows++;
        }
        fclose($fp);

        $total_exported_all += $device_rows;
        $created_files[] = $filename;

        // Rotazione per i file di log connessione di questo specifico sensore
        $pattern = $backup_dir . '/foobot_connection_logs_' . $safe_uuid . '_*.csv';
        $sensor_files = glob($pattern) ?: [];
        if (count($sensor_files) > $keep_max) {
            usort($sensor_files, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            $to_delete = count($sensor_files) - $keep_max;
            for ($i = 0; $i < $to_delete; $i++) {
                if (isset($sensor_files[$i]) && is_file($sensor_files[$i])) {
                    @unlink($sensor_files[$i]);
                }
            }
        }
    }

    $remaining_all = glob($backup_dir . '/foobot_connection_logs_*.csv') ?: [];

    return [
        'success'       => true,
        'filename'      => implode(', ', $created_files),
        'files_created' => $created_files,
        'rows_exported' => $total_exported_all,
        'files_kept'    => count($remaining_all),
        'message'       => sprintf('Backup eventi connessione completato per %d sensori (%d righe esportate).', count($uuids), $total_exported_all)
    ];
}

/**
 * Web-Cron Intelligente:
 * Verifica se per la giornata odierna e stata raggiunta o superata l'ora prefissata
 * (es. ore 04:00) e se sono trascorsi gli intervalli di giorni di calendario impostati.
 * Funziona autonomamente da qualunque richiesta al sito senza dipendere da cron esterni.
 */
function maybe_trigger_auto_backup(PDO $pdo) {
    try {
        if (function_exists('date_default_timezone_set')) {
            @date_default_timezone_set('Europe/Rome');
        }

        $interval_days = (int)get_setting($pdo, 'backup_interval_days', '1');
        if ($interval_days < 1) $interval_days = 1;

        $target_hour = (int)get_setting($pdo, 'backup_target_hour', '4');
        if ($target_hour < 0 || $target_hour > 23) $target_hour = 4;

        $now = time();
        $current_hour = (int)date('G', $now); // Ora locale 0-23
        $today_date = date('Y-m-d', $now);

        // 1. Se nella giornata odierna non siamo ancora arrivati all'ora prefissata (es. ore 04:00), attendiamo
        if ($current_hour < $target_hour) {
            return null;
        }

        $last_ts = (int)get_setting($pdo, 'last_auto_backup_ts', '0');

        // 2. Se un backup automatico e gia stato registrato in precedenza:
        if ($last_ts > 0) {
            $last_date = date('Y-m-d', $last_ts);

            // A. Se il backup automatico e gia stato eseguito oggi, non ripeterlo
            if ($last_date === $today_date) {
                return null;
            }

            // B. Calcola i giorni di calendario trascorsi dalla data dell'ultimo backup
            $last_day_start = strtotime($last_date . ' 00:00:00');
            $today_day_start = strtotime($today_date . ' 00:00:00');
            $days_elapsed = (int)round(($today_day_start - $last_day_start) / 86400);

            // Se non sono ancora trascorsi gli intervalli di giorni richiesti, attendiamo
            if ($days_elapsed < $interval_days) {
                return null;
            }
        }

        // 3. Condizioni soddisfatte: esegue il backup automatico con rotazione max 30 file
        return perform_foobot_backup(30, true);

    } catch (Exception $e) {
        // Non blocca l'applicazione
    }
    return null;
}

/**
 * Invia un'email di allerta disconnessione (o test) all'amministratore primario
 * includendo i dettagli dell'interruzione e gli ultimi log della console Bridge.
 * Il messaggio e l'oggetto vengono generati nella lingua preferita dell'utente o del sistema.
 */
function send_offline_alert_email(PDO $pdo, $is_test = false, $preferred_lang = null) {
    try {
        // 1. Individua l'email e la lingua dell'amministratore primario
        $stmt_adm = $pdo->query("SELECT username, email, language FROM foobot_users WHERE role = 'admin' AND email != '' ORDER BY id ASC LIMIT 1");
        $adm = $stmt_adm->fetch(PDO::FETCH_ASSOC);
        if (!$adm || empty($adm['email']) || !filter_var($adm['email'], FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Nessun indirizzo email valido configurato nel profilo Amministratore.'];
        }
        $admin_email = trim($adm['email']);
        $admin_name = $adm['username'] ?? 'Admin';

        // Risoluzione lingua per l'email
        if ($preferred_lang === null) {
            if (function_exists('get_current_lang') && !empty($_SESSION['foobot_lang'])) {
                $preferred_lang = $_SESSION['foobot_lang'];
            } elseif (!empty($adm['language'])) {
                $preferred_lang = strtolower($adm['language']);
            } elseif (function_exists('get_system_default_lang')) {
                $preferred_lang = get_system_default_lang();
            } else {
                $preferred_lang = 'en';
            }
        }
        $lang = in_array($preferred_lang, ['it', 'en', 'es', 'fr', 'de'], true) ? $preferred_lang : 'en';

        // 2. Recupera stato ultimo dato del Foobot
        $stmt_last = $pdo->query("SELECT device_uuid, timestamp, allpollu, pm, voc, co2, tmp, hum FROM foobot_readings ORDER BY timestamp DESC LIMIT 1");
        $last_reading = $stmt_last->fetch(PDO::FETCH_ASSOC);

        $uuid = $last_reading['device_uuid'] ?? 'N/D';
        $last_ts = isset($last_reading['timestamp']) ? (int)$last_reading['timestamp'] : 0;
        $now = time();
        $mins_offline = $last_ts > 0 ? (int)floor(($now - $last_ts) / 60) : 0;

        // 3. Recupera gli ultimi 5 eventi di connessione/disconnessione
        $conn_events = [];
        try {
            $stmt_ce = $pdo->query("SELECT device_uuid, event_type, timestamp, details FROM foobot_connection_logs ORDER BY timestamp DESC LIMIT 5");
            $conn_events = $stmt_ce->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}

        // 4. Recupera gli ultimi 15 punti della Console Diagnostica Bridge
        $bridge_logs = [];
        try {
            $stmt_bl = $pdo->query("SELECT timestamp, log_level, message FROM foobot_bridge_logs ORDER BY timestamp DESC, id DESC LIMIT 15");
            $bridge_logs = $stmt_bl->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}

        $host = $_SERVER['HTTP_HOST'] ?? 'foobot-monitor.local';
        $clean_host = preg_replace('/^www\./i', '', $host);
        if (strpos($clean_host, ':') !== false) {
            $clean_host = explode(':', $clean_host)[0];
        }

        // Formato data in base alla lingua
        $date_fmt = ($lang === 'en') ? 'm/d/Y H:i:s' : (($lang === 'de') ? 'd.m.Y H:i:s' : 'd/m/Y H:i:s');

        // Testi multilingua per l'email
        $t_email = [
            'it' => [
                'subj_test'    => '[TEST] Foobot Disconnesso (>30 min) - NewFoobot',
                'subj_alert'   => sprintf('[ALLERTA] Foobot Disconnesso (>%d min) - NewFoobot', $mins_offline),
                'hello'        => 'Ciao ' . $admin_name . ',',
                'intro_test'   => "Questa e un'email di TEST inviata dal pannello Impostazioni di NewFoobot per verificare la ricezione degli avvisi di disconnessione.\n\n",
                'intro_alert'  => sprintf("Il sensore Foobot non trasmette dati da oltre 30 minuti (assenza segnale rilevata: %d minuti).\n\n", $mins_offline),
                'sec_status'   => "DATI DISCONNESSIONE E STATO SENSORE",
                'dev_uuid'     => "Dispositivo (UUID) : %s\n",
                'det_time'     => "Data/Ora Rilevaz.  : %s\n",
                'last_data'    => "Ultimo Dato Ricev. : %s (%d min fa)\n",
                'no_readings'  => "Ultimo Dato Ricev. : Nessuna lettura presente\n",
                'last_reading' => "Ultima Lettura     : GPI %d%% | PM2.5: %.1f ug/m3 | VOC: %d ppb | CO2: %d ppm | Temp: %.1f C | Hum: %d%%\n",
                'sec_conn'     => "ULTIMI EVENTI DI CONNESSIONE / BLACKOUT",
                'no_conn'      => "Nessun evento di connessione registrato.\n",
                'sec_bridge'   => "ULTIMI LOG DEL BRIDGE (CONSOLE BROKER BRIDGE)",
                'no_bridge'    => "Nessun log presente nella console diagnostica del Bridge.\n",
                'footer'       => "--\nNewFoobot Air Monitor - Sistema di Notifica Automatica",
                'success_msg'  => 'Email inviata con successo a %s (%s).',
                'error_msg'    => 'Errore durante l\'invio a %s (%s): %s',
                'log_test'     => 'Email di test inviata dal pannello Impostazioni (%s)',
                'log_alert'    => 'Allerta disconnessione (>30 min, offline: %d min, %s)',
                'smtp_ok'      => 'Email inviata e accettata con successo dal server SMTP.',
                'mail_ok'      => 'Inviata a %s da %s',
                'mail_fail'    => 'Fallita funzione mail() hosting'
            ],
            'en' => [
                'subj_test'    => '[TEST] Foobot Disconnected (>30 min) - NewFoobot',
                'subj_alert'   => sprintf('[ALERT] Foobot Disconnected (>%d min) - NewFoobot', $mins_offline),
                'hello'        => 'Hello ' . $admin_name . ',',
                'intro_test'   => "This is a TEST email sent from the NewFoobot Settings panel to verify reception of disconnection alerts.\n\n",
                'intro_alert'  => sprintf("The Foobot sensor has not transmitted data for over 30 minutes (no signal detected for: %d minutes).\n\n", $mins_offline),
                'sec_status'   => "DISCONNECTION DATA & SENSOR STATUS",
                'dev_uuid'     => "Device (UUID)      : %s\n",
                'det_time'     => "Detection Date/Time: %s\n",
                'last_data'    => "Last Data Received : %s (%d min ago)\n",
                'no_readings'  => "Last Data Received : No readings available\n",
                'last_reading' => "Last Reading       : GPI %d%% | PM2.5: %.1f ug/m3 | VOC: %d ppb | CO2: %d ppm | Temp: %.1f C | Hum: %d%%\n",
                'sec_conn'     => "LATEST CONNECTION / BLACKOUT EVENTS",
                'no_conn'      => "No connection events recorded.\n",
                'sec_bridge'   => "LATEST BRIDGE LOGS (BROKER BRIDGE CONSOLE)",
                'no_bridge'    => "No logs present in the Bridge diagnostic console.\n",
                'footer'       => "--\nNewFoobot Air Monitor - Automated Notification System",
                'success_msg'  => 'Email sent successfully to %s (%s).',
                'error_msg'    => 'Error sending email to %s (%s): %s',
                'log_test'     => 'Test email sent from Settings panel (%s)',
                'log_alert'    => 'Disconnection alert (>30 min, offline: %d min, %s)',
                'smtp_ok'      => 'Email sent and successfully accepted by the SMTP server.',
                'mail_ok'      => 'Sent to %s from %s',
                'mail_fail'    => 'Hosting mail() function failed'
            ],
            'es' => [
                'subj_test'    => '[PRUEBA] Foobot Desconectado (>30 min) - NewFoobot',
                'subj_alert'   => sprintf('[ALERTA] Foobot Desconectado (>%d min) - NewFoobot', $mins_offline),
                'hello'        => 'Hola ' . $admin_name . ',',
                'intro_test'   => "Este es un correo de PRUEBA enviado desde el panel de Configuración de NewFoobot para verificar la recepción de avisos de desconexión.\n\n",
                'intro_alert'  => sprintf("El sensor Foobot no transmite datos desde hace más de 30 minutos (ausencia de señal detectada: %d minutos).\n\n", $mins_offline),
                'sec_status'   => "DATOS DE DESCONEXIÓN Y ESTADO DEL SENSOR",
                'dev_uuid'     => "Dispositivo (UUID)   : %s\n",
                'det_time'     => "Fecha/Hora Detección : %s\n",
                'last_data'    => "Último Dato Recibido : %s (hace %d min)\n",
                'no_readings'  => "Último Dato Recibido : Ninguna lectura disponible\n",
                'last_reading' => "Última Lectura       : GPI %d%% | PM2.5: %.1f ug/m3 | VOC: %d ppb | CO2: %d ppm | Temp: %.1f C | Hum: %d%%\n",
                'sec_conn'     => "ÚLTIMOS EVENTOS DE CONEXIÓN / APAGÓN",
                'no_conn'      => "Ningún evento de conexión registrado.\n",
                'sec_bridge'   => "ÚLTIMOS REGISTROS DEL BRIDGE (CONSOLA BROKER BRIDGE)",
                'no_bridge'    => "Ningún registro presente en la consola diagnóstica del Bridge.\n",
                'footer'       => "--\nNewFoobot Air Monitor - Sistema de Notificación Automática",
                'success_msg'  => 'Correo enviado con éxito a %s (%s).',
                'error_msg'    => 'Error al enviar el correo a %s (%s): %s',
                'log_test'     => 'Correo de prueba enviado desde el panel de Configuración (%s)',
                'log_alert'    => 'Alerta de desconexión (>30 min, offline: %d min, %s)',
                'smtp_ok'      => 'Correo enviado y aceptado con éxito por el servidor SMTP.',
                'mail_ok'      => 'Enviado a %s desde %s',
                'mail_fail'    => 'Función mail() del alojamiento fallida'
            ],
            'fr' => [
                'subj_test'    => '[TEST] Foobot Déconnecté (>30 min) - NewFoobot',
                'subj_alert'   => sprintf('[ALERTE] Foobot Déconnecté (>%d min) - NewFoobot', $mins_offline),
                'hello'        => 'Bonjour ' . $admin_name . ',',
                'intro_test'   => "Ceci est un e-mail de TEST envoyé depuis le panneau Paramètres de NewFoobot pour vérifier la réception des alertes de déconnexion.\n\n",
                'intro_alert'  => sprintf("Le capteur Foobot ne transmet plus de données depuis plus de 30 minutes (absence de signal détectée : %d minutes).\n\n", $mins_offline),
                'sec_status'   => "DONNÉES DE DÉCONNEXION ET ÉTAT DU CAPTEUR",
                'dev_uuid'     => "Appareil (UUID)      : %s\n",
                'det_time'     => "Date/Heure Détection : %s\n",
                'last_data'    => "Dernière Donnée Reçue: %s (il y a %d min)\n",
                'no_readings'  => "Dernière Donnée Reçue: Aucune mesure disponible\n",
                'last_reading' => "Dernière Mesure      : GPI %d%% | PM2.5: %.1f ug/m3 | VOC: %d ppb | CO2: %d ppm | Temp: %.1f C | Hum: %d%%\n",
                'sec_conn'     => "DERNIERS ÉVÉNEMENTS DE CONNEXION / COUPURE",
                'no_conn'      => "Aucun événement de connexion enregistré.\n",
                'sec_bridge'   => "DERNIERS JOURNAUX DU BRIDGE (CONSOLE BROKER BRIDGE)",
                'no_bridge'    => "Aucun journal présent dans la console de diagnostic du Bridge.\n",
                'footer'       => "--\nNewFoobot Air Monitor - Système de Notification Automatique",
                'success_msg'  => 'E-mail envoyé avec succès à %s (%s).',
                'error_msg'    => 'Erreur lors de l\'envoi de l\'e-mail à %s (%s) : %s',
                'log_test'     => 'E-mail de test envoyé depuis le panneau Paramètres (%s)',
                'log_alert'    => 'Alerte de déconnexion (>30 min, offline: %d min, %s)',
                'smtp_ok'      => 'E-mail envoyé et accepté avec succès par le serveur SMTP.',
                'mail_ok'      => 'Envoyé à %s de %s',
                'mail_fail'    => 'Échec de la fonction mail() de l\'hébergement'
            ],
            'de' => [
                'subj_test'    => '[TEST] Foobot Getrennt (>30 Min.) - NewFoobot',
                'subj_alert'   => sprintf('[WARNUNG] Foobot Getrennt (>%d Min.) - NewFoobot', $mins_offline),
                'hello'        => 'Hallo ' . $admin_name . ',',
                'intro_test'   => "Dies ist eine TEST-E-Mail, die über das NewFoobot-Einstellungsmenü gesendet wurde, um den Empfang von Verbindungsabbruch-Warnungen zu überprüfen.\n\n",
                'intro_alert'  => sprintf("Der Foobot-Sensor überträgt seit über 30 Minuten keine Daten mehr (kein Signal seit: %d Minuten).\n\n", $mins_offline),
                'sec_status'   => "VERBINDUNGSABBRUCH-DATEN UND SENSORSTATUS",
                'dev_uuid'     => "Gerät (UUID)         : %s\n",
                'det_time'     => "Erkennungsdatum/-zeit: %s\n",
                'last_data'    => "Letzte empfangene D. : %s (vor %d Min.)\n",
                'no_readings'  => "Letzte empfangene D. : Keine Messwerte vorhanden\n",
                'last_reading' => "Letzter Messwert     : GPI %d%% | PM2.5: %.1f ug/m3 | VOC: %d ppb | CO2: %d ppm | Temp: %.1f C | Hum: %d%%\n",
                'sec_conn'     => "LETZTE VERBINDUNGS- / STROMAUSFALL-EREIGNISSE",
                'no_conn'      => "Keine Verbindungsereignisse aufgezeichnet.\n",
                'sec_bridge'   => "LETZTE BRIDGE-PROTOKOLLE (BROKER BRIDGE KONSOLE)",
                'no_bridge'    => "Keine Protokolle in der Bridge-Diagnosekonsole vorhanden.\n",
                'footer'       => "--\nNewFoobot Air Monitor - Automatisches Benachrichtigungssystem",
                'success_msg'  => 'E-Mail erfolgreich gesendet an %s (%s).',
                'error_msg'    => 'Fehler beim Senden der E-Mail an %s (%s): %s',
                'log_test'     => 'Test-E-Mail über das Einstellungsmenü gesendet (%s)',
                'log_alert'    => 'Verbindungsabbruch-Warnung (>30 Min., offline: %d Min., %s)',
                'smtp_ok'      => 'E-Mail gesendet und vom SMTP-Server erfolgreich akzeptiert.',
                'mail_ok'      => 'Gesendet an %s von %s',
                'mail_fail'    => 'Hosting-Funktion mail() fehlgeschlagen'
            ]
        ];

        $cur_t = $t_email[$lang] ?? $t_email['en'];
        $subject = $is_test ? $cur_t['subj_test'] : $cur_t['subj_alert'];

        $body = $cur_t['hello'] . "\n\n";
        $body .= $is_test ? $cur_t['intro_test'] : $cur_t['intro_alert'];

        $body .= "==================================================\n";
        $body .= $cur_t['sec_status'] . "\n";
        $body .= "==================================================\n";
        $body .= sprintf($cur_t['dev_uuid'], $uuid);
        $body .= sprintf($cur_t['det_time'], date($date_fmt, $now));
        if ($last_ts > 0) {
            $body .= sprintf($cur_t['last_data'], date($date_fmt, $last_ts), $mins_offline);
            $body .= sprintf(
                $cur_t['last_reading'],
                (int)($last_reading['allpollu'] ?? 0),
                (float)($last_reading['pm'] ?? 0),
                (int)($last_reading['voc'] ?? 0),
                (int)($last_reading['co2'] ?? 0),
                (float)($last_reading['tmp'] ?? 0),
                (int)($last_reading['hum'] ?? 0)
            );
        } else {
            $body .= $cur_t['no_readings'];
        }

        $body .= "\n==================================================\n";
        $body .= $cur_t['sec_conn'] . "\n";
        $body .= "==================================================\n";
        if (empty($conn_events)) {
            $body .= $cur_t['no_conn'];
        } else {
            foreach ($conn_events as $ce) {
                $dt_str = date($date_fmt, (int)$ce['timestamp']);
                $ev_label = strtoupper($ce['event_type']);
                $det_text = function_exists('translate_log_text') ? translate_log_text($ce['details'], $lang) : $ce['details'];
                $body .= sprintf("[%s] %-12s - %s\n", $dt_str, $ev_label, $det_text);
            }
        }

        $body .= "\n==================================================\n";
        $body .= $cur_t['sec_bridge'] . "\n";
        $body .= "==================================================\n";
        if (empty($bridge_logs)) {
            $body .= $cur_t['no_bridge'];
        } else {
            foreach ($bridge_logs as $bl) {
                $dt_str = date($date_fmt, (int)$bl['timestamp']);
                $lvl = strtoupper($bl['log_level'] ?? 'INFO');
                $msg_text = function_exists('translate_log_text') ? translate_log_text($bl['message'], $lang) : $bl['message'];
                $body .= sprintf("[%s] [%s] %s\n", $dt_str, $lvl, $msg_text);
            }
        }

        $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
        $proto = $is_https ? 'https://' : 'http://';
        $body .= "\n--------------------------------------------------\n";
        $body .= "Dashboard : " . $proto . $host . "/index.php\n";
        $body .= "Logs      : " . $proto . $host . "/logs.php\n";
        $body .= $cur_t['footer'];

        // Metodo di invio: SMTP cifrato autenticato oppure mail() di PHP nativa
        $email_driver = get_setting($pdo, 'email_driver', 'php_mail'); // 'smtp' oppure 'php_mail'
        $sent = false;
        $driver_status_note = '';

        if ($email_driver === 'smtp') {
            require_once __DIR__ . '/smtp_client.php';
            $smtp_host   = get_setting($pdo, 'smtp_host', '');
            $smtp_port   = (int)get_setting($pdo, 'smtp_port', '465');
            $smtp_enc    = get_setting($pdo, 'smtp_encryption', 'ssl');
            $smtp_user   = get_setting($pdo, 'smtp_user', '');
            $smtp_pass   = get_setting($pdo, 'smtp_pass', '');
            $from_email  = get_setting($pdo, 'smtp_from_email', '');
            $from_name   = get_setting($pdo, 'smtp_from_name', 'NewFoobot Air Monitor');

            if (empty($from_email)) {
                $from_email = !empty($smtp_user) && filter_var($smtp_user, FILTER_VALIDATE_EMAIL) ? $smtp_user : ('noreply@' . $clean_host);
            }

            $client = new FoobotSMTPClient($smtp_host, $smtp_port, $smtp_enc, $smtp_user, $smtp_pass, 15);
            $smtp_res = $client->send($from_email, $from_name, $admin_email, $subject, $body);
            $sent = !empty($smtp_res['success']);
            $driver_status_note = $sent ? $cur_t['smtp_ok'] : $smtp_res['message'];
        } else {
            // Invio tramite mail() di base: aggiunge il parametro envelope sender (-f) per allineare l'envelope SPF
            $sender_email = !empty($from_email) ? $from_email : ('noreply@' . $clean_host);
            $headers = "From: " . (!empty($from_name) ? ('"' . $from_name . '" <' . $sender_email . '>') : $sender_email) . "\r\n" .
                       "Reply-To: " . $sender_email . "\r\n" .
                       "Content-Type: text/plain; charset=UTF-8\r\n" .
                       "X-Mailer: PHP/" . phpversion();
            $sent = @mail($admin_email, $subject, $body, $headers, "-f" . $sender_email);
            if (!$sent) {
                // Fallback senza quinto parametro (alcuni hosting non consentono -f)
                $sent = @mail($admin_email, $subject, $body, $headers);
            }
            $driver_status_note = $sent ? sprintf($cur_t['mail_ok'], $admin_email, $sender_email) : $cur_t['mail_fail'];
        }

        // Registra l'evento nel registro log delle email inviate
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

            $log_status = $sent ? 'sent' : 'failed';
            $log_type = $is_test ? 'test' : 'offline_alert';
            $log_base = $is_test
                ? sprintf($cur_t['log_test'], $email_driver)
                : sprintf($cur_t['log_alert'], $mins_offline, $email_driver);
            $log_details = $log_base . " - " . $driver_status_note;

            $stmt_log = $pdo->prepare("INSERT INTO foobot_email_logs (recipient, subject, email_type, status, details, timestamp) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt_log->execute([$admin_email, $subject, $log_type, $log_status, $log_details, $now]);
        } catch (Exception $e_elog) {}

        if ($sent) {
            return [
                'success' => true,
                'email'   => $admin_email,
                'message' => sprintf($cur_t['success_msg'], $admin_email, strtoupper($email_driver))
            ];
        } else {
            return [
                'success' => false,
                'email'   => $admin_email,
                'message' => sprintf($cur_t['error_msg'], $admin_email, strtoupper($email_driver), $driver_status_note)
            ];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Errore invio email: ' . $e->getMessage()];
    }
}

/**
 * Controlla automaticamente se il Foobot ha perso il segnale da oltre 30 minuti (1800 secondi).
 * Se lo strumento di notifica email e attivo e non e ancora stata inviata un'email per questa
 * disconnessione, invia l'avviso all'amministratore. Quando il Foobot torna online, riallinea lo stato.
 */
function maybe_check_offline_email_alert(PDO $pdo) {
    try {
        // Verifica se lo strumento e attivato nelle impostazioni (default: '0' disattivato)
        $enabled = get_setting($pdo, 'offline_email_alert_enabled', '0');
        if ($enabled !== '1') {
            return null;
        }

        $threshold_seconds = 1800; // 30 minuti
        $now = time();

        // Recupera il timestamp dell'ultima lettura ricevuta dal sensore
        $last_ts = (int)$pdo->query("SELECT MAX(timestamp) FROM foobot_readings")->fetchColumn();
        if ($last_ts <= 0) {
            return null;
        }

        $elapsed = $now - $last_ts;
        $already_sent = get_setting($pdo, 'offline_email_alert_sent', '0');

        if ($elapsed >= $threshold_seconds) {
            // Il sensore e offline da 30+ minuti: invia solo se non abbiamo gia avvisato per questo blackout
            if ($already_sent !== '1') {
                $res = send_offline_alert_email($pdo, false);
                if (!empty($res['success'])) {
                    save_setting($pdo, 'offline_email_alert_sent', '1');
                    save_setting($pdo, 'offline_email_last_sent_ts', (string)$now);
                }
                return $res;
            }
        } else {
            // Il sensore e tornato online (< 30 min): resetta il flag per future disconnessioni
            if ($already_sent === '1') {
                save_setting($pdo, 'offline_email_alert_sent', '0');
            }
        }
    } catch (Exception $e) {
        // Non blocca il flusso applicativo
    }
    return null;
}

// Esecuzione diretta da URL o da CLI
$is_cli = (php_sapi_name() === 'cli');

if ($is_cli) {
    $pdo_cli = get_db_connection();
    if ($pdo_cli instanceof PDO) {
        maybe_check_offline_email_alert($pdo_cli);
        $bk_res = maybe_trigger_auto_backup($pdo_cli);
        if ($bk_res !== null) {
            echo ($bk_res['message'] ?? 'Backup completato.') . PHP_EOL;
        } else {
            echo "Controllo completato (nessun backup schedulato per quest'ora)." . PHP_EOL;
        }
    }
} elseif ((basename($_SERVER['SCRIPT_FILENAME'] ?? '')) === 'cron_events.php') {
    $provided_key = $_GET['key'] ?? ($_SERVER['HTTP_X_API_KEY'] ?? '');
    $is_logged_admin = is_admin_user();
    $key_matches = (defined('API_SECRET_KEY') && API_SECRET_KEY !== '' && hash_equals((string)API_SECRET_KEY, (string)$provided_key));

    if (!$is_logged_admin && !$key_matches) {
        http_response_code(403);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['status' => 'error', 'message' => 'Accesso non autorizzato. Richiesta sessione admin o chiave API valida.']);
        exit;
    }

    $pdo_web = get_db_connection();
    $offline_res = null;
    $backup_res = null;
    if ($pdo_web instanceof PDO) {
        $offline_res = maybe_check_offline_email_alert($pdo_web);
        if (isset($_GET['force_backup']) && $_GET['force_backup'] === '1') {
            $backup_res = perform_foobot_backup(30);
        } else {
            $backup_res = maybe_trigger_auto_backup($pdo_web);
        }
    }
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'status' => 'ok',
        'offline_alert_checked' => true,
        'offline_alert_result' => $offline_res,
        'backup_result' => $backup_res
    ], JSON_PRETTY_PRINT);
    exit;
}