<?php
/**
 * Diagnostica e Verifica Integrità NewFoobot
 * Esegue un controllo completo su:
 * 1. File e cartelle obbligatorie del sito
 * 2. Permessi di scrittura per config.php
 * 3. Parametri definiti in config.php
 * 4. Connessione attiva al database MySQL
 * 5. Presenza di tutte le tabelle (foobot_readings, foobot_users, foobot_settings)
 * 6. Presenza e tipi di tutte le colonne previste
 * 7. Presenza di almeno un account amministratore registrato
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/nav.php';
header('Content-Type: text/html; charset=UTF-8');

$root_dir = __DIR__;

// Se il sito è già configurato e operativo, richiedi login amministratore per vedere la diagnostica
if (file_exists($root_dir . '/config.php')) {
    require_once $root_dir . '/config.php';
    if (function_exists('get_db_connection')) {
        $test_pdo = get_db_connection();
        if ($test_pdo instanceof PDO) {
            try {
                $u_count = (int)$test_pdo->query("SELECT COUNT(*) FROM foobot_users")->fetchColumn();
                if ($u_count > 0) {
                    require_once $root_dir . '/auth.php';
                    require_admin_role();
                }
            } catch (Exception $e) {}
        }
    }
}

$results = [];

function check_status($label, $passed, $details = '') {
    return [
        'label'   => $label,
        'passed'  => $passed,
        'details' => $details
    ];
}

// 1. Controllo File e Cartelle
$required_items = [
    'config.php'         => 'file',
    'auth.php'           => 'file',
    'index.php'          => 'file',
    'install.php'        => 'file',
    'login.php'          => 'file',
    'logout.php'         => 'file',
    'settings.php'       => 'file',
    'schema.sql'         => 'file',
    'api'                => 'dir',
    'api/ingest.php'     => 'file',
    'api/get_datapoints.php' => 'file',
    'assets'             => 'dir',
    'assets/css/style.css'   => 'file',
    'assets/js/dashboard.js' => 'file'
];

$missing_files = [];
foreach ($required_items as $item => $type) {
    $path = $root_dir . '/' . $item;
    if ($type === 'file' && !is_file($path)) {
        $missing_files[] = $item;
    } elseif ($type === 'dir' && !is_dir($path)) {
        $missing_files[] = $item . '/';
    }
}
$results['filesystem'] = check_status(
    "Integrità File e Cartelle",
    empty($missing_files),
    empty($missing_files) ? "Tutti i 14 file e cartelle essenziali sono presenti sul server." : "Elementi mancanti: " . implode(', ', $missing_files)
);

// 2. Controllo Permessi di Scrittura
$writable = is_writable($root_dir);
$config_writable = file_exists($root_dir . '/config.php') ? is_writable($root_dir . '/config.php') : $writable;
$results['permissions'] = check_status(
    "Permessi di Scrittura Configurazione",
    $config_writable,
    $config_writable ? "La cartella e il file config.php sono scrivibili da PHP." : "Attenzione: permessi di scrittura assenti per config.php."
);

// 3. Controllo file config.php e costanti
$config_exists = file_exists($root_dir . '/config.php');
$pdo = null;
$db_error = '';

if ($config_exists) {
    require_once $root_dir . '/config.php';
    $required_constants = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'API_SECRET_KEY'];
    $missing_const = [];
    foreach ($required_constants as $c) {
        if (!defined($c)) {
            $missing_const[] = $c;
        }
    }
    $results['config'] = check_status(
        "File config.php e Parametri",
        empty($missing_const),
        empty($missing_const) ? "File presente con tutte le costanti di configurazione definite." : "Costanti mancanti: " . implode(', ', $missing_const)
    );

    // 4. Test Connessione Database MySQL
    if (function_exists('get_db_connection')) {
        $pdo = get_db_connection();
        if ($pdo instanceof PDO) {
            $results['db_connection'] = check_status(
                "Connessione Database MySQL",
                true,
                "Connessione stabilita con successo a '" . DB_NAME . "' tramite l'utente '" . DB_USER . "'."
            );
        } else {
            $results['db_connection'] = check_status(
                "Connessione Database MySQL",
                false,
                "Impossibile collegarsi al database MySQL con i parametri correnti."
            );
        }
    } else {
        $results['db_connection'] = check_status(
            "Connessione Database MySQL",
            false,
            "Funzione get_db_connection() non definita in config.php."
        );
    }
} else {
    $results['config'] = check_status("File config.php", false, "Il file config.php non esiste ancora. Esegui prima install.php.");
    $results['db_connection'] = check_status("Connessione Database MySQL", false, "Non verificabile prima della creazione di config.php.");
}

// Azione di aggiornamento/migrazione schema su richiesta (o creata al clic sul pulsante)
$migration_msg = '';
$migration_status = '';
if (!empty($_POST['action']) && $_POST['action'] === 'migrate_db' && !empty($_SESSION['foobot_user'])) {
    if ($pdo instanceof PDO) {
        try {
            $sql_create = "CREATE TABLE IF NOT EXISTS foobot_connection_logs (
              id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
              device_uuid VARCHAR(32) NOT NULL DEFAULT 'foobot',
              event_type ENUM('connected', 'disconnected', 'power_cut') NOT NULL COMMENT 'Tipo evento di rete o alimentazione',
              timestamp BIGINT UNSIGNED NOT NULL COMMENT 'Unix timestamp UTC dell evento',
              details VARCHAR(255) DEFAULT '' COMMENT 'Dettagli tecnici o motivo evento',
              created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              INDEX idx_device_time (device_uuid, timestamp),
              INDEX idx_event_type (event_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            $pdo->exec($sql_create);

            // Verifica o aggiunta colonna language in foobot_users
            $u_cols = $pdo->query("SHOW COLUMNS FROM foobot_users LIKE 'language'")->fetchAll();
            if (empty($u_cols)) {
                $pdo->exec("ALTER TABLE foobot_users ADD COLUMN language VARCHAR(10) NOT NULL DEFAULT 'en' AFTER role");
            }

            $migration_msg = "Aggiornamento tabelle e campi eseguito con successo!";
            $migration_status = "ok";
        } catch (Exception $e) {
            $migration_msg = "Errore durante l'aggiornamento del database: " . $e->getMessage();
            $migration_status = "error";
        }
    }
}

// 5. Verifica Struttura Tabelle e Campi
$expected_schema = [
    'foobot_readings'        => ['id', 'device_uuid', 'timestamp', 'allpollu', 'pm', 'voc', 'co2', 'tmp', 'hum', 'created_at'],
    'foobot_users'           => ['id', 'username', 'password_hash', 'email', 'role', 'created_at', 'updated_at'],
    'foobot_settings'        => ['setting_key', 'setting_value', 'updated_at'],
    'foobot_login_attempts'  => ['ip_address', 'attempts', 'locked_until', 'last_attempt'],
    'foobot_connection_logs' => ['id', 'device_uuid', 'event_type', 'timestamp', 'details', 'created_at'],
    'foobot_user_tokens'     => ['id', 'user_id', 'token_hash', 'expires_at', 'created_at']
];

$schema_checks = [];
$admin_count = 0;
$readings_count = 0;

if ($pdo instanceof PDO) {
    foreach ($expected_schema as $table => $columns) {
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
            $exists = (bool)$stmt->fetch();
            if (!$exists) {
                $schema_checks[$table] = check_status("Tabella `{$table}`", false, "Tabella NON presente nel database.");
                continue;
            }

            // Controlla colonne
            $col_stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
            $existing_cols = $col_stmt->fetchAll(PDO::FETCH_COLUMN);
            $missing_cols = array_diff($columns, $existing_cols);

            if (empty($missing_cols)) {
                $schema_checks[$table] = check_status("Tabella `{$table}`", true, "Presente con tutte le " . count($columns) . " colonne attese.");
            } else {
                $schema_checks[$table] = check_status("Tabella `{$table}`", false, "Colonne mancanti: " . implode(', ', $missing_cols));
            }
        } catch (Exception $e) {
            $schema_checks[$table] = check_status("Tabella `{$table}`", false, "Errore: " . $e->getMessage());
        }
    }

    // Conteggio utenti
    try {
        $admin_count = (int)$pdo->query("SELECT COUNT(*) FROM foobot_users")->fetchColumn();
    } catch (Exception $e) {
        $admin_count = 0;
    }

    // Conteggio letture
    try {
        $readings_count = (int)$pdo->query("SELECT COUNT(*) FROM foobot_readings")->fetchColumn();
    } catch (Exception $e) {
        $readings_count = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Verifica Integrità e Database - NewFoobot</title>
  <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg?v=2">
  <link rel="apple-touch-icon" href="assets/img/favicon.png?v=2">
  <link rel="stylesheet" href="assets/css/style.css?v=24">
  <script src="assets/js/theme.js?v=15"></script>
</head>
<body>
  <button type="button" class="theme-toggle theme-floating" aria-label="Cambia tema"></button>
  <div class="settings-container">
    
    <header class="dashboard-header">
      <div class="header-brand-wrap">
        <a href="index.php" class="header-brand-logo" title="Torna alla Dashboard">
          <?php echo get_foobot_logo_svg(44); ?>
        </a>
        <div>
          <h1>Diagnostica e Integrità Sistema NewFoobot</h1>
          <p class="card-subtext">Verifica automatica di file, permessi, connessione e schema tabelle MySQL</p>
        </div>
      </div>
    </header>

    <!-- Sezione 1: File e Cartelle -->
    <div class="panel" style="margin-bottom:20px;">
      <h2>1. File e Permessi del Server</h2>
      
      <div class="diag-item">
        <div>
          <div class="diag-label"><?php echo htmlspecialchars($results['filesystem']['label']); ?></div>
          <div class="diag-desc"><?php echo htmlspecialchars($results['filesystem']['details']); ?></div>
        </div>
        <span class="badge <?php echo $results['filesystem']['passed'] ? 'badge-ok' : 'badge-fail'; ?>">
          <?php echo $results['filesystem']['passed'] ? 'OK' : 'ERRORE'; ?>
        </span>
      </div>

      <div class="diag-item">
        <div>
          <div class="diag-label"><?php echo htmlspecialchars($results['permissions']['label']); ?></div>
          <div class="diag-desc"><?php echo htmlspecialchars($results['permissions']['details']); ?></div>
        </div>
        <span class="badge <?php echo $results['permissions']['passed'] ? 'badge-ok' : 'badge-fail'; ?>">
          <?php echo $results['permissions']['passed'] ? 'OK' : 'ATTENZIONE'; ?>
        </span>
      </div>

      <div class="diag-item">
        <div>
          <div class="diag-label"><?php echo htmlspecialchars($results['config']['label']); ?></div>
          <div class="diag-desc"><?php echo htmlspecialchars($results['config']['details']); ?></div>
        </div>
        <span class="badge <?php echo $results['config']['passed'] ? 'badge-ok' : 'badge-fail'; ?>">
          <?php echo $results['config']['passed'] ? 'OK' : 'MANCANTE'; ?>
        </span>
      </div>
    </div>

    <!-- Sezione 2: Connessione e Tabelle Database -->
    <div class="panel" style="margin-bottom:20px;">
      <h2>2. Database MySQL e Struttura Tabelle</h2>

      <div class="diag-item">
        <div>
          <div class="diag-label"><?php echo htmlspecialchars($results['db_connection']['label']); ?></div>
          <div class="diag-desc"><?php echo htmlspecialchars($results['db_connection']['details']); ?></div>
        </div>
        <span class="badge <?php echo $results['db_connection']['passed'] ? 'badge-ok' : 'badge-fail'; ?>">
          <?php echo $results['db_connection']['passed'] ? 'CONNESSO' : 'NON CONNESSO'; ?>
        </span>
      </div>

      <?php if (!empty($migration_msg)): ?>
        <div style="padding:12px 16px; margin-bottom:16px; border-radius:12px; font-size:0.9rem; font-weight:500; <?php echo $migration_status === 'ok' ? 'background:rgba(35,134,54,0.15); color:var(--accent-good); border:1px solid var(--accent-good);' : 'background:rgba(218,54,51,0.15); color:var(--accent-danger); border:1px solid var(--accent-danger);'; ?>">
          <?php echo htmlspecialchars($migration_msg); ?>
        </div>
      <?php endif; ?>

      <?php 
        $has_missing_tables = false;
        if (!empty($schema_checks)) {
            foreach ($schema_checks as $chk) {
                if (!$chk['passed']) { $has_missing_tables = true; break; }
            }
        }
      ?>

      <?php if ($has_missing_tables && !empty($_SESSION['foobot_user'])): ?>
        <form method="POST" style="margin-bottom:16px; background:rgba(210,153,34,0.1); border:1px solid var(--accent-warn); padding:14px; border-radius:12px; display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
          <input type="hidden" name="action" value="migrate_db">
          <div>
            <div style="font-weight:600; color:var(--accent-warn);">Struttura database da aggiornare</div>
            <div style="font-size:0.85rem; color:var(--text-secondary);">Sono disponibili le tabelle per il monitoraggio di connessione, blackout e console log del bridge.</div>
          </div>
          <button type="submit" class="btn-action" style="padding:8px 16px; font-size:0.85rem;">Aggiorna Database Adesso</button>
        </form>
      <?php endif; ?>

      <?php if (!empty($schema_checks)): ?>
        <?php foreach ($schema_checks as $sc): ?>
          <div class="diag-item">
            <div>
              <div class="diag-label"><?php echo htmlspecialchars($sc['label']); ?></div>
              <div class="diag-desc"><?php echo htmlspecialchars($sc['details']); ?></div>
            </div>
            <span class="badge <?php echo $sc['passed'] ? 'badge-ok' : 'badge-fail'; ?>">
              <?php echo $sc['passed'] ? 'STRUTTURA OK' : 'MANCANTE'; ?>
            </span>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <?php if ($pdo instanceof PDO): ?>
        <div class="diag-item">
          <div>
            <div class="diag-label">Account Amministratori Registrati</div>
            <div class="diag-desc"><?php echo $admin_count > 0 ? "Presenti {$admin_count} utente/i nel database." : "Nessun account amministratore presente. Completa lo Step 2 dell'installer."; ?></div>
          </div>
          <span class="badge <?php echo $admin_count > 0 ? 'badge-ok' : 'badge-fail'; ?>">
            <?php echo $admin_count > 0 ? $admin_count . ' UTENTE/I' : '0 UTENTI'; ?>
          </span>
        </div>

        <div class="diag-item">
          <div>
            <div class="diag-label">Letture Sensore Registrate</div>
            <div class="diag-desc">Record presenti nella tabella `foobot_readings`: <strong><?php echo $readings_count; ?></strong></div>
          </div>
          <span class="badge badge-ok">
            <?php echo $readings_count; ?> RECORD
          </span>
        </div>
      <?php endif; ?>
    </div>

    <!-- Riepilogo Azioni Rapide -->
    <div class="panel" style="margin-bottom:20px; text-align:center;">
      <h2>Azioni Rapide</h2>
      <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
        <a href="index.php" class="btn-action">Vai alla Dashboard</a>
        <a href="install.php?step=1&reconfigure=1" class="btn-secondary">Avvia Installazione / Ripristino</a>
        <a href="settings.php" class="btn-secondary">Vai a Impostazioni</a>
      </div>
    </div>

  </div>
</body>
</html>

