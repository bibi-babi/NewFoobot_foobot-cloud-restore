-- Schema Database per NewFoobot (cPanel MySQL / phpMyAdmin)
-- Compatibile con PHP 7.4+ e MySQL 5.7+ / MariaDB 10.x

-- Tabella letture del sensore Foobot
CREATE TABLE IF NOT EXISTS foobot_readings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  device_uuid VARCHAR(32) NOT NULL DEFAULT 'foobot',
  timestamp BIGINT UNSIGNED NOT NULL COMMENT 'Data e ora in formato Unix Epoch UTC',
  allpollu INT NOT NULL DEFAULT 0 COMMENT 'Indice globale inquinamento 0-100 (colore LED)',
  pm FLOAT NOT NULL DEFAULT 0.0 COMMENT 'Particolato fine PM2.5 in ug/m3',
  voc INT NOT NULL DEFAULT 0 COMMENT 'Composti organici volatili in ppb',
  co2 INT NOT NULL DEFAULT 0 COMMENT 'CO2 stimata in ppm',
  tmp DECIMAL(4,2) NOT NULL DEFAULT 0.00 COMMENT 'Temperatura in gradi Celsius',
  hum INT NOT NULL DEFAULT 0 COMMENT 'Umidita relativa in %',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_timestamp (timestamp),
  INDEX idx_device_time (device_uuid, timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella utenti (Amministratore o Visualizzatore Standard)
CREATE TABLE IF NOT EXISTS foobot_users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(64) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  email VARCHAR(128) DEFAULT '',
  role ENUM('admin', 'user') NOT NULL DEFAULT 'admin' COMMENT 'Ruolo utente: admin (accesso completo) o user (visualizzatore senza impostazioni e senza log bridge)',
  language VARCHAR(10) NOT NULL DEFAULT 'en' COMMENT 'Lingua preferita dell account (es. it, en, fr, de, es)',
  temp_unit VARCHAR(1) NOT NULL DEFAULT 'C' COMMENT 'Unita temperatura account (C: Celsius, F: Fahrenheit)',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella impostazioni di sistema persistenti
CREATE TABLE IF NOT EXISTS foobot_settings (
  setting_key VARCHAR(64) NOT NULL PRIMARY KEY,
  setting_value TEXT NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella tentativi di accesso e rate limiting anti-brute-force
CREATE TABLE IF NOT EXISTS foobot_login_attempts (
  ip_address VARCHAR(45) NOT NULL PRIMARY KEY,
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  locked_until BIGINT UNSIGNED NOT NULL DEFAULT 0,
  last_attempt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella token temporanei per il recupero password via email
CREATE TABLE IF NOT EXISTS foobot_password_resets (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(128) NOT NULL,
  token VARCHAR(64) NOT NULL UNIQUE,
  expires_at BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella token di accesso persistente (Login valido 1 anno)
CREATE TABLE IF NOT EXISTS foobot_user_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  token_hash VARCHAR(64) NOT NULL UNIQUE,
  expires_at BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user (user_id),
  INDEX idx_token (token_hash),
  INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella registro eventi di connessione e interruzioni (blackout/disconnessioni)
CREATE TABLE IF NOT EXISTS foobot_connection_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  device_uuid VARCHAR(32) NOT NULL DEFAULT 'foobot',
  event_type ENUM('connected', 'disconnected', 'power_cut') NOT NULL COMMENT 'Tipo evento di rete o alimentazione',
  timestamp BIGINT UNSIGNED NOT NULL COMMENT 'Unix timestamp UTC dell evento',
  details VARCHAR(255) DEFAULT '' COMMENT 'Dettagli tecnici o motivo evento',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_device_time (device_uuid, timestamp),
  INDEX idx_event_type (event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella registro diagnostico dettagliato del Bridge (Console di sviluppo)
CREATE TABLE IF NOT EXISTS foobot_bridge_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  device_uuid VARCHAR(32) NOT NULL DEFAULT 'foobot',
  log_level VARCHAR(16) NOT NULL DEFAULT 'INFO' COMMENT 'Livello log: INFO, WARN, ERROR, DEBUG',
  message TEXT NOT NULL COMMENT 'Messaggio descrittivo o evento bridge',
  timestamp BIGINT UNSIGNED NOT NULL COMMENT 'Unix timestamp UTC dell evento',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_timestamp (timestamp),
  INDEX idx_device (device_uuid),
  INDEX idx_level (log_level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella metadati sensori Foobot fisici (Nomi amichevoli es. Salotto, Ufficio per Multi-Foobot)
CREATE TABLE IF NOT EXISTS foobot_devices_meta (
  device_uuid VARCHAR(32) NOT NULL PRIMARY KEY,
  device_name VARCHAR(64) NOT NULL DEFAULT '',
  location VARCHAR(64) NOT NULL DEFAULT '',
  color_badge VARCHAR(16) NOT NULL DEFAULT 'blue',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella dispositivi mobili/desktop autorizzati (App PWA)
CREATE TABLE IF NOT EXISTS foobot_authorized_devices (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  device_name VARCHAR(100) NOT NULL,
  device_serial VARCHAR(64) NOT NULL DEFAULT '',
  device_token_hash VARCHAR(64) NOT NULL UNIQUE,
  platform VARCHAR(64) NOT NULL DEFAULT 'unknown',
  app_mode VARCHAR(32) NOT NULL DEFAULT 'browser',
  user_agent VARCHAR(255) NOT NULL DEFAULT '',
  device_fingerprint VARCHAR(128) NOT NULL DEFAULT '',
  last_ip VARCHAR(45) NOT NULL DEFAULT '',
  last_active_at BIGINT UNSIGNED NOT NULL DEFAULT 0,
  is_revoked TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user (user_id),
  INDEX idx_token (device_token_hash),
  INDEX idx_serial (device_serial)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella ticket monouso di accoppiamento per QR Code (Scadenza 10 minuti)
CREATE TABLE IF NOT EXISTS foobot_pairing_tickets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  ticket_code VARCHAR(32) NOT NULL UNIQUE,
  token_secret VARCHAR(64) NOT NULL,
  device_label VARCHAR(100) NOT NULL DEFAULT '',
  expires_at BIGINT UNSIGNED NOT NULL,
  used_at BIGINT UNSIGNED NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ticket (ticket_code),
  INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
