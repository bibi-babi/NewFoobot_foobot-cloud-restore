<?php
/**
 * API REST per lettura dati Foobot (compatibile con Web Dashboard e App Android)
 * Restituisce i dati storici o l'ultima lettura nel formato nativo Foobot 'DatapointStrip'
 * Parametri GET: range = live | 24h | 7d | 30d, uuid (opzionale)
 */

require_once __DIR__ . '/common.php';
require_once dirname(__DIR__) . '/auth.php';

$pdo = api_db();
// Protezione totale: accessibile da sessione web, API_SECRET_KEY o token App Mobile autorizzato
$auth_client = api_authenticate_client($pdo);

$range = $_GET['range'] ?? '24h';
$offset = max(0, (int)($_GET['offset'] ?? 0));
$start_date_in = trim($_GET['start_date'] ?? '');
$end_date_in   = trim($_GET['end_date'] ?? '');
$now = time();
$window = 86400;

// Gestione filtro personalizzato per intervallo di date e orari (es. da 2026-10-20 a 2026-10-25, oppure con orario esatto 2026-10-20T10:30)
$has_custom_dates = false;
$custom_start = 0;
$custom_end   = 0;
if ($start_date_in !== '' && preg_match('/^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}(?::\d{2})?)?$/', $start_date_in)) {
    $clean_start = str_replace('T', ' ', $start_date_in);
    $has_start_time = (bool)preg_match('/\d{2}:\d{2}/', $clean_start);
    $custom_start = strtotime($has_start_time ? $clean_start : ($clean_start . ' 00:00:00'));

    if ($end_date_in !== '' && preg_match('/^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}(?::\d{2})?)?$/', $end_date_in)) {
        $clean_end = str_replace('T', ' ', $end_date_in);
        $has_end_time = (bool)preg_match('/\d{2}:\d{2}/', $clean_end);
        $custom_end = strtotime($has_end_time ? $clean_end : ($clean_end . ' 23:59:59'));
    } else {
        $custom_end = strtotime($has_start_time ? $clean_start : ($clean_start . ' 23:59:59'));
    }
    if ($custom_start > 0 && $custom_end >= $custom_start) {
        $has_custom_dates = true;
    }
}

switch ($range) {
    case 'live':
        $window = 0;
        $limit = 1;
        break;
    case 'minuti':
    case '1h':
        $range = 'minuti';
        $window = 3 * 3600; // Finestra di 3 ore (fino a 36 rilevazioni da 5 minuti) per garantire curva dettagliata e snella
        $limit = 60;
        break;
    case 'ore':
    case '6h':
        $range = 'ore';
        $window = 24 * 3600; // Finestra di 24 ore (1 giorno intero)
        $limit = 400;
        break;
    case 'giorni':
        $range = 'giorni';
        $window = 7 * 86400; // Finestra di default di 7 giorni per la vista a giorni
        $limit = 100;
        break;
    case 'settimane':
        $range = 'settimane';
        $window = 28 * 86400; // Finestra di 4 settimane (28 giorni)
        $limit = 100;
        break;
    case '3d':
        $range = '3d';
        $window = 3 * 86400;
        $limit = 100;
        break;
    case '7d':
        $range = '7d';
        $window = 7 * 86400;
        $limit = 100;
        break;
    case '10d':
        $range = '10d';
        $window = 10 * 86400;
        $limit = 100;
        break;
    case '30d':
        $range = '30d';
        $window = 30 * 86400;
        $limit = 100;
        break;
    case '24h':
        $range = '24h';
        $window = 86400;
        $limit = 300; // 1 punto ogni 5 minuti
        break;
    default:
        if (preg_match('/^(\d+)d$/', $range, $m)) {
            $days = max(1, min(90, (int)$m[1]));
            $window = $days * 86400;
            $limit = 100;
        } else {
            $range = '24h';
            $window = 86400;
            $limit = 300;
        }
        break;
}

if ($has_custom_dates) {
    $start = $custom_start;
    $end   = $custom_end;
    $window = max(3600, $end - $start);
    $diffDays = ($end - $start) / 86400;

    // Aggrega per giorno SOLO se è stato esplicitamente scelto un pulsante a giorni/settimane ('7d', '30d', 'giorni', 'settimane').
    // Se invece è selezionato 'minuti', 'ore' o '24h', mantiene sempre i dati ad alta risoluzione dall'inizio alla fine del range!
    if (in_array($range, ['giorni', 'settimane', '3d', '7d', '10d', '30d'], true) || preg_match('/^\d+d$/', $range)) {
        $is_daily_aggregation = true;
        $limit = max(100, (int)ceil($diffDays) + 5);
    } else {
        $is_daily_aggregation = false;
        $limit = 15000;
    }
} else {
    if ($range === 'live') {
        $end = $now;
        $start = 0;
    } else {
        $end = $now - ($offset * $window);
        $start = $end - $window;
    }

    // Rileva se il range richiede aggregazione a medie giornaliere
    $is_daily_aggregation = in_array($range, ['giorni', 'settimane', '3d', '7d', '10d', '30d'], true) || preg_match('/^\d+d$/', $range);
}

try {
    $uuid = api_resolve_uuid($pdo, $_GET['uuid'] ?? '');

    // Filtro minuti: la finestra è ancorata all'ultima lettura registrata per garantire la visione anche con sensore offline o clock skew
    if (!$has_custom_dates && $range === 'minuti') {
        $stmtLast = $pdo->prepare('SELECT MAX(timestamp) AS last_ts FROM foobot_readings WHERE device_uuid = :uuid');
        $stmtLast->execute([':uuid' => $uuid]);
        $lastTs = (int)($stmtLast->fetch()['last_ts'] ?? 0);
        if ($lastTs > 0) {
            // Se l'ultima lettura è più vecchia di $window o nel futuro (clock skew), àncora a $lastTs
            $ref = ($now - $lastTs > $window || $lastTs > $now) ? $lastTs : $now;
            $end = $ref - ($offset * $window);
            $start = $end - $window;
        }
    }

    // Filtro ore: finestra giornaliera naturale dalle 00:00:00 alle 23:59:59 (della giornata odierna o scalata di N giorni con offset)
    // Se la giornata odierna non ha ancora rilevazioni, la giornata di riferimento si àncora alla data dell'ultimo dato utile registrato
    if (!$has_custom_dates && $range === 'ore') {
        $stmtLast = $pdo->prepare('SELECT MAX(timestamp) AS last_ts FROM foobot_readings WHERE device_uuid = :uuid');
        $stmtLast->execute([':uuid' => $uuid]);
        $lastTs = (int)($stmtLast->fetch()['last_ts'] ?? 0);
        
        $todayStart = strtotime(date('Y-m-d 00:00:00', $now));
        $refTime = $now;
        if ($lastTs > 0 && $lastTs < $todayStart) {
            $refTime = $lastTs;
        }
        $targetDayTs = $refTime - ($offset * 86400);
        $start = strtotime(date('Y-m-d 00:00:00', $targetDayTs));
        $end = strtotime(date('Y-m-d 23:59:59', $targetDayTs));
    }

    if ($range === 'live') {
        $stmt = $pdo->prepare('SELECT timestamp, allpollu, pm, voc, co2, tmp, hum
                               FROM foobot_readings
                               WHERE device_uuid = :uuid AND NOT (hum = 0 AND co2 = 0 AND tmp = 0)
                               ORDER BY timestamp DESC LIMIT 1');
        $stmt->execute([':uuid' => $uuid]);
    } elseif ($is_daily_aggregation) {
        // Aggregazione con media matematica giornaliera calcolata per ogni singola data (1 punto per ciascun giorno)
        // NULLIF esclude i record di disconnessione con 0 dal calcolo della media
        $stmt = $pdo->prepare('SELECT 
                                 UNIX_TIMESTAMP(DATE(FROM_UNIXTIME(timestamp))) as timestamp,
                                 ROUND(AVG(allpollu)) as allpollu,
                                 ROUND(AVG(pm), 1) as pm,
                                 ROUND(AVG(voc)) as voc,
                                 ROUND(AVG(NULLIF(co2, 0))) as co2,
                                 ROUND(AVG(NULLIF(tmp, 0)), 1) as tmp,
                                 ROUND(AVG(NULLIF(hum, 0))) as hum,
                                 DATE(FROM_UNIXTIME(timestamp)) as day_date
                               FROM foobot_readings
                               WHERE device_uuid = :uuid AND timestamp >= :start AND timestamp <= :end
                                 AND NOT (hum = 0 AND co2 = 0 AND tmp = 0)
                               GROUP BY day_date
                               ORDER BY day_date ASC LIMIT ' . (int)$limit);
        $stmt->execute([':uuid' => $uuid, ':start' => $start, ':end' => $end]);
    } else {
        $stmt = $pdo->prepare('SELECT timestamp, allpollu, pm, voc, co2, tmp, hum
                               FROM foobot_readings
                               WHERE device_uuid = :uuid AND timestamp >= :start AND timestamp <= :end
                                 AND NOT (hum = 0 AND co2 = 0 AND tmp = 0)
                               ORDER BY timestamp ASC LIMIT ' . (int)$limit);
        $stmt->execute([':uuid' => $uuid, ':start' => $start, ':end' => $end]);
    }

    $datapoints = [];
    foreach ($stmt->fetchAll() as $row) {
        $allpollu = (int)$row['allpollu'];
        $pm       = (float)$row['pm'];
        $voc      = (int)$row['voc'];
        $co2      = (int)$row['co2'];
        $tmp      = (float)$row['tmp'];
        $hum      = (int)$row['hum'];

        // Se tutti i parametri sono 0, si tratta di un pacchetto nullo/warm-up non valido
        $isBlank = ($allpollu === 0 && $voc === 0 && abs($pm) < 0.001 && $co2 === 0 && $hum === 0);

        $datapoints[] = [
            (int)$row['timestamp'],
            $isBlank ? null : $allpollu,
            $isBlank ? null : $pm,
            $isBlank ? null : $voc,
            ($co2 >= 250 && !$isBlank) ? $co2 : null,
            (abs($tmp) > 0.001 && !$isBlank) ? $tmp : null,
            ($hum > 0 && !$isBlank) ? $hum : null
        ];
    }

    $resolved_dp_user = $auth_client['user'] ?? null;
    if (empty($resolved_dp_user)) {
        try {
            $stmt_owner = $pdo->query("SELECT username FROM foobot_users WHERE username != '' ORDER BY (role = 'admin') DESC, id ASC LIMIT 1");
            $resolved_dp_user = $stmt_owner ? trim((string)$stmt_owner->fetchColumn()) : null;
        } catch (Exception $e_u) {}
    }

    $i18n_file = dirname(__DIR__) . '/i18n.php';
    if (file_exists($i18n_file)) {
        require_once $i18n_file;
    }

    echo json_encode([
        'compatibility'          => 'foobot',
        'uuid'                   => $uuid,
        'start'                  => $start,
        'end'                    => $end,
        'offset'                 => $offset,
        'window'                 => $window,
        'sensors'                => ['time', 'allpollu', 'pm', 'voc', 'co2', 'tmp', 'hum'],
        'units'                  => ['s', '%', 'ug/m3', 'ppb', 'ppm', 'C', '%'],
        'connected_user'         => $resolved_dp_user,
        'user'                   => $resolved_dp_user,
        'device_reg_id'          => $auth_client['device_id'] ?? null,
        'device_id'              => $auth_client['device_id'] ?? null,
        'device_serial'          => $auth_client['device_serial'] ?? null,
        'settings_device_reg_id' => function_exists('__') ? __('settings_device_reg_id', 'ID Dispositivo App:') : 'ID Dispositivo App:',
        'i18n_labels'            => [
            'it' => ['settings_device_reg_id' => 'ID Dispositivo App:'],
            'en' => ['settings_device_reg_id' => 'App Device ID:']
        ],
        'datapoints'             => $datapoints
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Errore nel recupero dati: ' . $e->getMessage()]);
}
