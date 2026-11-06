#!/usr/bin/env python3
"""
NewFoobot Bridge v3.0: Broker MQTT v3.1/v3.1.1 Nativo Integrato -> Web Hosting cPanel
Ascolta direttamente sulla porta TCP 1883 in modo nativo e autonomo,
accetta i pacchetti MQTT v3.1 legacy (MQIsdp) del firmware Foobot che causano
errori di protocollo sui server MQTT moderni,
applica le formule di calibrazione ufficiali, monitora lo stato di connessione/blackout
in tempo reale e inoltra i dati via HTTP/HTTPS POST a ingest.php.
Include il watchdog Zero Ping:
- Notifica evento caduta segnale/blackout dopo 10 minuti di inattività;
- Invia il trigger per l'email di allerta a cPanel dopo 30 minuti di inattività totali (10m + 20m), una sola volta;
- Nessun ping a vuoto verso cPanel quando il Foobot è online (invia solo le normali telemetrie ogni 5 minuti).
"""

import json
import logging
import socket
import struct
import sys
import threading
import time
import requests

# ==========================================
# CONFIGURAZIONE UTENTE (Personalizzare questi 2 campi)
# ==========================================
# Inserire l'URL esatto della cartella del proprio sito web (es. https://tuodominio.it/api/ingest.php)
INGEST_URL = "https://tuodominio.it/api/ingest.php"

# Inserire la stessa Passkey API impostata durante l'installazione del sito
API_KEY = "TUA_PASSKEY_SEGRETA"

# Parametri Server MQTT Integrato (ascolto diretto sulla porta 1883)
MQTT_BIND_HOST = "0.0.0.0"
MQTT_BIND_PORT = 1883

# Secondi di assenza segnale oltre i quali segnalare blackout/caduta linea (10 minuti)
DISCONNECT_THRESHOLD = 600

# Secondi totali di assenza segnale oltre i quali scatenare l'invio dell'email via cPanel (30 minuti = 10m + 20m)
OFFLINE_ALERT_THRESHOLD = 1800

# Abilita l'invio dei log diagnostici dettagliati del Bridge verso la pagina Log del sito PHP
# True = Invia tutti i passaggi (connessioni, handshake, pacchetti) visibili da browser
# False = Invia solo lo stato essenziale (blackout e cambi di connessione)
ENABLE_DETAILED_LOGS = True
# ==========================================

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
    handlers=[logging.StreamHandler(sys.stdout)]
)

# Tracciamento stato dispositivi in memoria: { uuid: { 'last_seen': ts, 'is_connected': bool, 'offline_alert_sent': bool } }
tracked_devices = {}
device_lock = threading.Lock()


def send_http_post(payload):
    """Invia in modo sicuro un payload JSON a ingest.php su cPanel."""
    headers = {
        "Content-Type": "application/json",
        "X-Api-Key": API_KEY,
        "User-Agent": "NewFoobot-OracleBridge/3.0"
    }
    try:
        resp = requests.post(INGEST_URL, json=payload, headers=headers, timeout=10)
        if resp.status_code == 200:
            logging.info("Inoltro a cPanel completato [HTTP 200]: %s", resp.text.strip())
        else:
            logging.warning("Risposta anomala da cPanel [HTTP %d]: %s", resp.status_code, resp.text.strip())
    except Exception as err:
        logging.error("Errore connessione verso cPanel (%s): %s", INGEST_URL, err)


def send_bridge_diagnostic_log(message, level="INFO", device_uuid="foobot"):
    """Invia una riga di log diagnostico alla console web sul sito PHP se abilitato."""
    if not ENABLE_DETAILED_LOGS:
        return
    payload = {
        "api_key": API_KEY,
        "device_uuid": device_uuid,
        "time": int(time.time()),
        "event_type": "bridge_log",
        "level": level,
        "message": message
    }
    threading.Thread(target=send_http_post, args=(payload,), daemon=True).start()


def notify_connection_event(device_uuid, event_type, details=""):
    """Invia evento di connessione, disconnessione o blackout al sito PHP."""
    payload = {
        "api_key": API_KEY,
        "device_uuid": device_uuid,
        "time": int(time.time()),
        "event": event_type,
        "event_type": event_type,
        "details": details
    }
    logging.info("Invio evento stato dispositivo [%s]: %s (%s)", device_uuid, event_type, details)
    threading.Thread(target=send_http_post, args=(payload,), daemon=True).start()


def trigger_cpanel_offline_alert(cron_url):
    """Esegue una singola chiamata mirata a cron_events.php su cPanel per inviare l'email di allerta."""
    try:
        headers = {"X-Api-Key": API_KEY, "User-Agent": "NewFoobot-OracleBridge/3.0"}
        resp = requests.get(f"{cron_url}?key={API_KEY}", headers=headers, timeout=15)
        if resp.status_code == 200:
            data = resp.json()
            alert_res = data.get("offline_alert_result")
            if alert_res and alert_res.get("success"):
                logging.info("[ALLERTA DISCONNESSIONE] Email inviata con successo da cPanel: %s", alert_res.get("message"))
            else:
                logging.info("[ALLERTA DISCONNESSIONE] Risposta cPanel: %s", resp.text.strip())
    except Exception as err:
        logging.error("Errore durante la chiamata ad allerta cron cPanel (%s): %s", cron_url, err)


def calculate_allpollu(pm, voc):
    """
    Calcola l'indice globale di inquinamento (0-100) usando la regressione quadratica Foobot.
    pm in ug/m3, voc in ppb.
    """
    val = 0.9531 * pm + 0.15775 * voc - 0.00007216 * (voc ** 2) - 17.697
    return max(0, min(100, int(round(val))))


def parse_and_forward_sensor_payload(device_uuid, payload_str):
    """
    Supporta sia il formato JSON a dizionario {'temp':..., 'hum':..., 'pm':..., 'voc':..., 'co2':...}
    sia il formato JSON ad array {'val': [ts, temp, hum, co2_eq, pm, voc, co2, allpollu]} del Foobot.
    """
    now_ts = int(time.time())
    data = json.loads(payload_str)

    if isinstance(data, list) and len(data) > 0:
        data = data[0]

    if isinstance(data, dict) and "val" in data and isinstance(data["val"], list):
        raw_values = data["val"]
        if len(raw_values) < 7:
            logging.warning("Array 'val' incompleto da [%s]: %s", device_uuid, raw_values)
            return
        if raw_values[0]:
            now_ts = int(raw_values[0])
        raw_temp = float(raw_values[1] or 0)
        raw_hum = float(raw_values[2] or 0)
        raw_pm = float(raw_values[4] or 0)
        raw_voc = int(raw_values[5] or 0)
        raw_co2 = int(raw_values[6] or 0)
    elif isinstance(data, dict):
        raw_temp = float(data.get("temp", 0))
        raw_hum = float(data.get("hum", 0))
        raw_pm = float(data.get("pm", 0))
        raw_voc = int(data.get("voc", 0))
        raw_co2 = int(data.get("co2", 0))
    else:
        logging.warning("Formato JSON non riconosciuto da [%s]: %s", device_uuid, payload_str)
        return

    tmp = round(raw_temp / 1000.0, 2)
    hum = int(round(raw_hum / 1000.0))
    pm = round(raw_pm / 94.2, 1)
    voc = raw_voc
    co2 = raw_co2
    allpollu = calculate_allpollu(pm, voc)

    payload_to_send = {
        "api_key": API_KEY,
        "device_uuid": device_uuid,
        "time": now_ts,
        "allpollu": allpollu,
        "pm": pm,
        "voc": voc,
        "co2": co2,
        "tmp": tmp,
        "hum": hum
    }

    logging.info(
        "Dati calibrati [%s]: Temp: %.1f C | Hum: %d%% | PM2.5: %.1f ug/m3 | VOC: %d ppb | CO2: %d ppm | allpollu: %d",
        device_uuid, tmp, hum, pm, voc, co2, allpollu
    )
    threading.Thread(target=send_http_post, args=(payload_to_send,), daemon=True).start()


def check_device_heartbeat_loop():
    """
    Watchdog in background (Zero Ping a vuoto):
    1. Rileva caduta linea/blackout dopo 10 minuti di assenza segnale (DISCONNECT_THRESHOLD = 600s);
    2. Se l'inattività continua fino a 30 minuti (10m + 20m = OFFLINE_ALERT_THRESHOLD = 1800s),
       esegue UNA SOLA chiamata HTTP a cron_events.php per inviare l'email di allerta all'Admin;
    3. Quando il Foobot è online, non esegue alcun ping a vuoto verso cPanel.
    """
    cron_url = INGEST_URL.replace("/api/ingest.php", "/cron_events.php")
    while True:
        time.sleep(15)
        now = time.time()
        with device_lock:
            for uuid, info in list(tracked_devices.items()):
                last_seen = info.get("last_seen", 0)
                if last_seen <= 0:
                    continue
                elapsed = now - last_seen

                # Passo 1: Disconnessione / blackout dopo 10 minuti di assenza dati
                if info.get("is_connected", False):
                    if elapsed > DISCONNECT_THRESHOLD:
                        info["is_connected"] = False
                        info["offline_alert_sent"] = False
                        logging.warning(
                            "Nessun segnale dal Foobot [%s] da %d secondi. Rilevata disconnessione/blackout.",
                            uuid, int(elapsed)
                        )
                        notify_connection_event(
                            uuid,
                            "power_cut",
                            "Interruzione linea o alimentazione rilevata (assenza segnale >10 min)"
                        )

                # Passo 2: Allerta email disconnessione dopo 30 minuti totali (10m + 20m) - Eseguita UNA SOLA VOLTA
                elif not info.get("offline_alert_sent", False):
                    if elapsed >= OFFLINE_ALERT_THRESHOLD:
                        info["offline_alert_sent"] = True
                        logging.warning(
                            "[ALLERTA DISCONNESSIONE] Foobot [%s] offline da oltre 30 minuti (%d s). Inoltro trigger email a cPanel.",
                            uuid, int(elapsed)
                        )
                        threading.Thread(
                            target=trigger_cpanel_offline_alert,
                            args=(cron_url,),
                            daemon=True
                        ).start()


def mark_device_active(device_uuid, source_desc="Pacchetto MQTT ricevuto"):
    """Aggiorna il timestamp di attività del Foobot e invia l'evento 'connected' se era offline."""
    if not device_uuid:
        return
    now_ts = time.time()
    notify_now = False
    with device_lock:
        dev_info = tracked_devices.setdefault(device_uuid, {"last_seen": 0, "is_connected": False, "offline_alert_sent": False})
        was_connected = dev_info["is_connected"]
        dev_info["last_seen"] = now_ts
        if not was_connected:
            dev_info["is_connected"] = True
            dev_info["offline_alert_sent"] = False
            notify_now = True

    if notify_now:
        logging.info("Connessione attiva rilevata per il Foobot [%s].", device_uuid)
        notify_connection_event(device_uuid, "connected", source_desc)


def read_mqtt_packet(sock):
    """Legge un singolo pacchetto MQTT dal socket TCP decodificando il Remaining Length."""
    first_byte = sock.recv(1)
    if not first_byte:
        return None, None

    header = first_byte[0]
    multiplier = 1
    remaining_length = 0

    while True:
        encoded_byte = sock.recv(1)
        if not encoded_byte:
            return None, None
        byte_val = encoded_byte[0]
        remaining_length += (byte_val & 0x7F) * multiplier
        if (byte_val & 0x80) == 0:
            break
        multiplier *= 128
        if multiplier > 128 * 128 * 128:
            raise ValueError("Remaining Length MQTT non valido")

    body = b""
    while len(body) < remaining_length:
        chunk = sock.recv(remaining_length - len(body))
        if not chunk:
            return None, None
        body += chunk

    return header, body


def extract_client_id_from_connect(body):
    """Estrae il Client ID (UUID del Foobot) dal pacchetto MQTT CONNECT (v3.1 MQIsdp o v3.1.1)."""
    try:
        if len(body) < 2:
            return None
        proto_len = struct.unpack("!H", body[0:2])[0]
        pos = 2 + proto_len + 4
        if len(body) < pos + 2:
            return None
        client_id_len = struct.unpack("!H", body[pos:pos + 2])[0]
        pos += 2
        if len(body) < pos + client_id_len:
            return None
        client_id = body[pos:pos + client_id_len].decode("utf-8", errors="ignore").strip()
        return client_id if client_id else None
    except Exception:
        return None


def handle_mqtt_client(conn, addr):
    """Gestisce la sessione TCP/MQTT di un Foobot accettando i flag legacy MQTT v3.1."""
    conn.settimeout(120)
    client_uuid = None
    logging.info("Nuova connessione TCP in ingresso da %s:%d", addr[0], addr[1])
    send_bridge_diagnostic_log(f"Nuova connessione TCP da {addr[0]}:{addr[1]}", "INFO")

    try:
        while True:
            header, body = read_mqtt_packet(conn)
            if header is None:
                break

            ptype = (header >> 4) & 0x0F

            # 1 = CONNECT
            if ptype == 1:
                extracted_id = extract_client_id_from_connect(body)
                if extracted_id:
                    client_uuid = extracted_id
                # Invia CONNACK (0x20, 0x02, 0x00, 0x00 = Connection Accepted)
                conn.sendall(bytes([0x20, 0x02, 0x00, 0x00]))
                logging.info("Foobot autenticato via MQTT CONNECT: UUID=%s (%s)", client_uuid or "sconosciuto", addr[0])
                send_bridge_diagnostic_log(f"Autenticazione Foobot MQTT CONNECT riuscita: ClientID={client_uuid or 'sconosciuto'} ({addr[0]})", "INFO", client_uuid or "foobot")
                if client_uuid:
                    mark_device_active(client_uuid, "Connessione stabilita con il Broker Bridge Oracle")

            # 3 = PUBLISH
            elif ptype == 3:
                qos = (header >> 1) & 0x03
                if len(body) < 2:
                    continue
                topic_len = struct.unpack("!H", body[0:2])[0]
                topic = body[2:2 + topic_len].decode("utf-8", errors="ignore")
                pos = 2 + topic_len

                if qos > 0:
                    if len(body) < pos + 2:
                        continue
                    pkt_id = body[pos:pos + 2]
                    pos += 2
                    if qos == 1:
                        conn.sendall(bytes([0x40, 0x02]) + pkt_id)

                payload_str = body[pos:].decode("utf-8", errors="ignore").strip()

                topic_parts = topic.strip("/").split("/")
                if len(topic_parts) >= 2 and topic_parts[0] == "device":
                    client_uuid = topic_parts[1]

                if client_uuid:
                    mark_device_active(client_uuid, "Ricezione telemetria MQTT")

                if topic.endswith("/sensor/push") and payload_str:
                    logging.info("Messaggio MQTT dati ricevuto per [%s]: %s", client_uuid or "foobot", payload_str)
                    send_bridge_diagnostic_log(f"Ricevuto pacchetto telemetria sensori ({len(payload_str)} byte)", "INFO", client_uuid or "foobot")
                    try:
                        parse_and_forward_sensor_payload(client_uuid or "foobot", payload_str)
                    except Exception as parse_err:
                        logging.error("Errore elaborazione telemetria [%s]: %s", client_uuid, parse_err)
                else:
                    logging.info("Messaggio MQTT su topic '%s': %s", topic, payload_str[:120])

            # 8 = SUBSCRIBE
            elif ptype == 8:
                pkt_id = body[0:2] if len(body) >= 2 else b"\x00\x01"
                conn.sendall(bytes([0x90, 0x03]) + pkt_id + bytes([0x00]))
                if client_uuid:
                    mark_device_active(client_uuid, "Sottoscrizione MQTT completata")

            # 10 = UNSUBSCRIBE
            elif ptype == 10:
                pkt_id = body[0:2] if len(body) >= 2 else b"\x00\x01"
                conn.sendall(bytes([0xB0, 0x02]) + pkt_id)

            # 12 = PINGREQ (Battito cardiaco inviato dal Foobot ogni 10 secondi)
            elif ptype == 12:
                conn.sendall(bytes([0xD0, 0x00]))
                if client_uuid:
                    with device_lock:
                        if client_uuid in tracked_devices:
                            tracked_devices[client_uuid]["last_seen"] = time.time()

            # 14 = DISCONNECT
            elif ptype == 14:
                logging.info("Disconnessione MQTT ricevuta da [%s]", client_uuid or addr[0])
                break

    except socket.timeout:
        logging.info("Timeout sessione TCP per [%s]", client_uuid or addr[0])
    except Exception as conn_err:
        logging.warning("Connessione TCP chiusa per [%s]: %s", client_uuid or addr[0], conn_err)
    finally:
        try:
            conn.close()
        except Exception:
            pass


def main():
    hb_thread = threading.Thread(target=check_device_heartbeat_loop, daemon=True)
    hb_thread.start()

    server_sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    server_sock.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)

    try:
        server_sock.bind((MQTT_BIND_HOST, MQTT_BIND_PORT))
    except OSError as bind_err:
        logging.error("Impossibile aprire la porta TCP %d: %s", MQTT_BIND_PORT, bind_err)
        sys.exit(1)

    server_sock.listen(10)
    logging.info("=================================================================")
    logging.info(" NewFoobot Standalone MQTT Broker & Bridge v3.0 avviato!")
    logging.info(" In ascolto diretto su : %s:%d (Server MQTT Nativo)", MQTT_BIND_HOST, MQTT_BIND_PORT)
    logging.info(" Inoltro dati verso    : %s", INGEST_URL)
    logging.info(" Watchdog Blackout     : %d sec (10 min) | Allerta Email: %d sec (30 min)", DISCONNECT_THRESHOLD, OFFLINE_ALERT_THRESHOLD)
    logging.info("=================================================================")

    try:
        while True:
            conn, addr = server_sock.accept()
            client_thread = threading.Thread(target=handle_mqtt_client, args=(conn, addr), daemon=True)
            client_thread.start()
    except KeyboardInterrupt:
        logging.info("Interruzione manuale ricevuta. Chiusura del Bridge...")
    finally:
        server_sock.close()


if __name__ == "__main__":
    main()
