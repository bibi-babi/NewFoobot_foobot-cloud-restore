#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
================================================================================
  NAME:          foobot_manager.py
  PROJECT:       NewFoobot (Open Source Environmental Monitoring)
  AUTHOR:        NewFoobot Project / Barbara Lerici
  LANGUAGES:     Italiano (IT) & English (EN) - Auto-detected + Interactive Choice
  COMPATIBILITY: macOS, Linux (Ubuntu, Debian, Fedora, Arch, Raspberry Pi OS)
  REQUIREMENTS:  Python 3.6+ (Standard library only: socket, sys, time, locale, os)
  LICENSE:       MIT (Free and Open Source Software)

  DESCRIZIONE / DESCRIPTION:
  Utilità di gestione di rete universale bilingue (IT/EN) per i proprietari del
  dispositivo "Foobot Air Quality Monitor" (Airboxlab) tramite porta UDP 48899.
================================================================================
"""

import sys
import os
import time
import socket
import locale

# Costanti di protocollo Foobot Hi-Flying HF-LPB100
FOOBOT_UDP_PORT = 48899
FOOBOT_HOTSPOT_IP = "10.10.100.254"
FOOBOT_ORIGINAL_DNS = "10.10.100.254"
PROTOCOL_HANDSHAKE = b"HF-A11ASSISTHREAD"
PROTOCOL_ENTER_AT = b"+ok"

# Sequenze colori ANSI per terminale
COLOR_CYAN   = "\033[96m"
COLOR_WHITE  = "\033[97m"
COLOR_YELLOW = "\033[93m"
COLOR_GREEN  = "\033[92m"
COLOR_RED    = "\033[91m"
COLOR_GRAY   = "\033[90m"
COLOR_RESET  = "\033[0m"

# Lingua attiva globale ('IT' oppure 'EN')
CURRENT_LANG = "EN"

# Dizionario Bilingue Completo (IT / EN)
I18N = {
    "IT": {
        "BANNER_TITLE": "                 STRUMENTO DI GESTIONE E MODIFICA FOOBOT                       ",
        "BANNER_SUB": "                    Configurazione Universale e Aperta                          ",
        "LOCAL_NOTICE_1": "Questo programma opera unicamente all'interno della tua rete locale.",
        "LOCAL_NOTICE_2": "Nessun indirizzo IP o credenziale e memorizzato nel codice sorgente.",
        "KB_INT": "Interruzione da tastiera rilevata. Uscita...",
        "DISCOVERY_TITLE": "RICERCA DISPOSITIVO FOOBOT IN CORSO",
        "DISC_PHASE1": f"Fase 1: Invio segnale broadcast UDP (255.255.255.255:{FOOBOT_UDP_PORT}) sulla rete LAN...",
        "DISC_FOUND_LAN": "Foobot rilevato sulla RETE LOCALE (LAN)!",
        "DISC_CURR_IP": "   - Indirizzo IP Attuale : ",
        "DISC_MAC": "   - Indirizzo MAC Rete   : ",
        "DISC_MID": "   - Codice Seriale (MID) : ",
        "DISC_PHASE2": f"Fase 2: Nessuna risposta su LAN. Verifica Hotspot di fabbrica ({FOOBOT_HOTSPOT_IP}:{FOOBOT_UDP_PORT})...",
        "DISC_FOUND_HOT": f"Foobot rilevato in modalita HOTSPOT DI FABBRICA ({FOOBOT_HOTSPOT_IP})!",
        "DISC_MODE_HOT": "   - Modalita Operativa   : ",
        "DISC_MODE_HOT_VAL": "Hotspot / Access Point",
        "DISC_GW_IP": "   - Indirizzo IP Gateway : ",
        "DISC_NOT_FOUND": "Nessun Foobot rilevato automaticamente (ne su LAN, ne su Hotspot).",
        "DISC_GUIDE_HEAD": "GUIDA RAPIDA DI COLLEGAMENTO:",
        "DISC_GUIDE_1": " 1. Se hai cambiato router o Wi-Fi, metti il Foobot a TESTA IN GIU per circa 5 secondi.",
        "DISC_GUIDE_2": "    I LED lampeggeranno e il Foobot creera una rete Wi-Fi ad-hoc (es. Foobot_xxxx o HF-LPB100).",
        "DISC_GUIDE_3": " 2. Connetti il tuo computer/notebook a quella rete Wi-Fi, poi riesegui questo script.",
        "DISC_GUIDE_4": " 3. Se il Foobot e gia connesso al tuo Wi-Fi, verifica che il PC sia sulla stessa rete 2.4 GHz.",
        "DISC_MANUAL_PROMPT": "Se conosci l'indirizzo IP del Foobot, inseriscilo ora (oppure premi INVIO per uscire)",
        "ERR_INVALID_IP": "ERRORE DI VALIDAZIONE: ========== '{0}' non e un indirizzo IPv4 valido. ==========",
        "ERR_IP_FORMAT": "Un indirizzo IP deve essere composto da 4 numeri separati da punti (da 0.0.0.0 a 255.255.255.255).",
        "ERR_NO_DEVICE": "Operazione terminata: nessun dispositivo Foobot collegato o raggiungibile.",
        "PRESS_KEY_EXIT": "Premi INVIO per uscire...",
        "PRESS_KEY_MENU": "Premi INVIO per tornare al menu...",
        "SAFETY_BLOCK_1": "BLOCCO DI SICUREZZA ATTIVO: Il comando '{0}' e bloccato per proteggere il dispositivo.",
        "SAFETY_BLOCK_2": "Il comando rientra nella blacklist di protezione hardware/firmware del Foobot.",
        "AT_STEP1": "[Fase 1/3] Apertura canale UDP persistente (Porta locale PC: {0} -> Foobot: {1}:48899)",
        "AT_WAKE_RETRY": "           Tentativo handshake {0}/3 scaduto, nuovo invio...",
        "AT_WAKE_ERR": "Nessuna risposta dal Foobot a {0}:48899. Dispositivo non raggiungibile.",
        "AT_STEP2": "[Fase 2/3] Foobot agganciato! Risposta modulo: {0}",
        "AT_MODE_ERR": "Impossibile entrare nella modalita comandi AT del modulo Wi-Fi.",
        "AT_STEP3": "[Fase 3/3] Modalita comandi AT attivata. Esecuzione comandi in corso...",
        "AT_Z_SEND": "  [CMD] -> AT+Z (Riavvio del modulo Wi-Fi in corso...)",
        "AT_CMD_RESP": "  [CMD] -> {0} | [RISPOSTA] -> {1}",
        "AT_CMD_WAIT": "           [Attesa 5s scaduta] Nessuna risposta immediata per '{0}', nuovo tentativo ({1}/3)...",
        "AT_CMD_TIMEOUT": "           Nessuna risposta entro il tempo limite per '{0}' (TIMEOUT)",
        "AT_WAIT_PING": "           [Attesa fino a 5s] Risoluzione DNS e test raggiungibilita verso il server in corso...",
        "AT_WAIT_TCP_OFF": "           [Attesa 2.5s] Chiusura ordinata del vecchio socket TCP Cloud in corso...",
        "AT_WAIT_SOCK_NONE": "           [Attesa 2.0s] Azzeramento buffer del canale Socket B nel modulo Wi-Fi...",
        "AT_WAIT_SOCK_TCP": "           [Attesa 2.5s] Inizializzazione nuovo endpoint TCP (porta 1883)...",
        "AT_WAIT_TCP_ON": "           [Attesa 2.5s] Negoziazione handshake TCP col server Bridge in corso...",
        "WAIT_FINAL_TCPLKB": "[Attesa 5s] Stabilizzazione finale del collegamento TCP Cloud prima della rilettura stato...",
        "WAIT_REBOOT_STEP": "           [Attesa in corso: {0}s / {1}s] Riavvio modulo Wi-Fi e negoziazione IP col router...",
        "AT_CLOSE": "[Chiusura] Canale UDP chiuso correttamente.",
        "MENU_TITLE": "MENU PRINCIPALE DI CONTROLLO - FOOBOT ({0} - Modo: {1})",
        "MENU_OPT_1": "Leggi la configurazione attuale del Foobot (SOLO LETTURA - 100% SICURO)",
        "MENU_OPT_2": "Configura server DNS personalizzato (NextDNS, Pi-hole, IP locale)",
        "MENU_OPT_3": "Riattivazione dopo sospensione corrente con lo stesso IP registrato in precedenza",
        "MENU_OPT_4": "Configura Nuova Rete Wi-Fi di Casa (Pairing SSID e Password)",
        "MENU_OPT_5": "Ripristina le impostazioni DNS e DHCP originali di fabbrica (10.10.100.254)",
        "MENU_OPT_6": "Riavvia il modulo Wi-Fi del Foobot (AT+Z)",
        "MENU_OPT_7": "Esci dal programma",
        "MENU_PROMPT": "Digita il numero dell'operazione desiderata [1-7]",
        "MENU_INVALID": "Scelta '{0}' non valida. Selezionare un'opzione tra 1 e 7.",
        "OPT1_TITLE": "LETTURA REGISTRI HARDWARE E FIRMWARE (OPERAZIONE SICURA)",
        "OPT1_START": "Avvio sessione di interrogazione dei registri interni del Foobot...",
        "OPT1_SUMMARY": "RIEPILOGO DIAGNOSTICA FOOBOT:",
        "OPT1_FW": "  - Versione Firmware Modulo Wi-Fi : ",
        "OPT1_MID": "  - Seriale Macchina (Device ID)   : ",
        "OPT1_SSID": "  - Rete Wi-Fi Connessa (SSID)     : ",
        "OPT1_WSLQ": "  - Qualita del Segnale Wi-Fi      : ",
        "OPT1_WANN": "  - Parametri IP Rete (WANN)       : ",
        "OPT1_WSDNS": "  - Server DNS Configurato (WSDNS) : ",
        "OPT1_SOCKB": "  - Canale MQTT di Fabbrica        : ",
        "OPT1_TCPLKB": "  - Connessione TCP Cloud (TCPLKB) : ",
        "OPT1_DNS_CHECK": "VERIFICA REALE RISOLUZIONE DNS E DESTINAZIONE CLOUD:",
        "OPT1_DNS_AWS_WARN": " [ATTENZIONE: Punta ancora al vecchio server AWS Foobot!]",
        "OPT1_DNS_AWS_MSG": "Il DNS {0} non sta reindirizzando broker-gw-nc.foobot.io al tuo Bridge (controlla IP associato su NextDNS).",
        "OPT1_DNS_OK_LABEL": "  - IP Reale Destinazione Bridge   : ",
        "OPT1_DNS_OK_MSG": "{0} (Riscrittura DNS attiva correttamente)",
        "OPT1_PORT_LABEL": "  - Stato Porta 1883 sul Bridge    : ",
        "OPT1_PORT_OPEN": "APERTA E FUNZIONANTE ({0}:1883)",
        "OPT1_PORT_CLOSED": "NON RAGGIUNGIBILE ({0}:1883)",
        "OPT1_PORT_ERR": "ERRORE CONNESSIONE ({0}:1883)",
        "OPT1_DNS_UNREACH": "Impossibile interrogare la risoluzione DNS per {0}",
        "OPT1_DONE": "Lettura completata. I valori mostrati sopra rappresentano lo stato attuale della memoria.",
        "OPT2_TITLE": "MODIFICA SERVER DNS DEL FOOBOT (REINDIRIZZAMENTO TELEMETRIA)",
        "OPT2_INFO1": "Questa operazione imposta il server DNS interrogato dal Foobot e fissa l'assetto IP",
        "OPT2_INFO2": "(modalita static) affinche il DHCP del router non sovrascriva il DNS personalizzato.",
        "OPT2_ORIG_DNS": f"Valore DNS originale di fabbrica: {FOOBOT_ORIGINAL_DNS}",
        "OPT2_PROMPT_IP": "Inserisci l'indirizzo IP del Server DNS desiderato (es. NextDNS 45.90.28.xxx o IP router/Pi-hole)",
        "OPT2_CANCEL_EMPTY": "Nessun indirizzo inserito. Operazione annullata.",
        "OPT2_CONFIRM_HEAD": "CONFERMA DI SICUREZZA (OPERAZIONE REVERSIBILE):",
        "OPT2_CONFIRM_TARGET": "Stai per impostare il DNS del Foobot su: '{0}'",
        "OPT2_CONFIRM_NOTE1": "NOTA BENE: Questa modifica e totalmente reversibile in qualsiasi momento.",
        "OPT2_CONFIRM_NOTE2": "Se vorrai ripristinare il Foobot ai valori originali di fabbrica (DHCP + DNS originale),",
        "OPT2_CONFIRM_NOTE3": "ti bastera avviare nuovamente questo script e scegliere l'Opzione 5 dal menu principale.",
        "OPT2_CONFIRM_ASK": "Vuoi procedere con l'aggiornamento del server DNS? [S = Si / N = No]",
        "OPT2_PRE_READ": "Fase Preliminare: Lettura configurazione di rete attuale del Foobot (AT+WANN)...",
        "OPT2_NEW_IP": "Indirizzo IP assegnato dal router: {0}",
        "OPT2_LAN_DETECTED": "Parametri LAN rilevati: IP={0} | Mask={1} | Gateway={2}",
        "OPT2_LAN_DEDUCED": "Parametri LAN dedotti da IP attivo: IP={0} | Mask=255.255.255.0 | Gateway={1}",
        "OPT2_STATIC_APPLY": "Verra applicato '{0}' per impedire al router di sovrascrivere il DNS.",
        "OPT2_WRITE_START": "Avvio procedura lineare di scrittura e riconnessione sul Foobot...",
        "OPT2_STEP1": "[Passo 1/2] Fissaggio configurazione IP statica in Flash NVRAM...",
        "OPT2_WANN_SAVED": "Parametri WANN memorizzati: {0}",
        "OPT2_STEP2": "[Passo 2/2] Scrittura DNS ({0}), risoluzione dominio e aggancio diretto al Bridge...",
        "OPT2_OK_1": "Modifica accettata dal Foobot! (Risposta scrittura DNS: {0})",
        "OPT2_OK_2": "Verifica registro DNS attivo        : {0}",
        "OPT2_OK_3": "Verifica canale MQTT configurato    : {0}",
        "OPT2_OK_4": "Stato connessione socket cloud      : {0}",
        "OPT2_OK_5": "OPERAZIONE COMPLETATA! Il Foobot ha interrogato il DNS ed e collegato al Bridge.",
        "OPT2_ERR_WRITE": "Il Foobot non ha confermato la modifica. Risposta ricevuta: {0}",
        "OPT2_CANCELLED": "Modifica annullata. Nessun parametro e stato toccato.",
        "OPT_REACT_TITLE": "RIATTIVAZIONE DOPO SOSPENSIONE CORRENTE (CON LO STESSO IP REGISTRATO)",
        "OPT_REACT_DESC_HEAD": "COSA FA QUESTA PROCEDURA E PERCHE SERVE:",
        "OPT_REACT_DESC_1": "Quando manca la corrente elettrica, il Foobot si riaccende in pochi secondi mentre il router",
        "OPT_REACT_DESC_2": "di casa impiega alcuni minuti. Al riavvio il router azzera la lista dei dispositivi collegati",
        "OPT_REACT_DESC_3": "e blocca l'uscita su Internet del Foobot rimasto con IP statico (lasciando TCPLKB su 'off').",
        "OPT_REACT_STEPS_HD": "OPERAZIONI ESEGUITE IN AUTOMATICO DAL PROGRAMMA:",
        "OPT_REACT_STEP_1": " 1. Legge dalla memoria del Foobot l'IP del Server DNS gia registrato in precedenza.",
        "OPT_REACT_STEP_2": " 2. Attiva temporaneamente il DHCP e riavvia il Foobot (AT+Z per 16 secondi) affinche",
        "OPT_REACT_STEP_3": "    il router di casa registri e autorizzi nuovamente il sensore sulla rete locale.",
        "OPT_REACT_STEP_4": " 3. Rileva l'IP locale assegnato dal router e lo blocca nuovamente come IP statico.",
        "OPT_REACT_STEP_5": " 4. Ripristina lo stesso IP DNS salvato in precedenza e riapre il collegamento Cloud (TCPLKB=on).",
        "OPT_REACT_SAVED_DNS": "  -> Server DNS registrato rilevato nel Foobot : {0}",
        "OPT_REACT_NO_DNS": "ATTENZIONE: Nel Foobot e presente il DNS di fabbrica ({0}). Configura prima il DNS con l'Opzione 2.",
        "OPT_REACT_CONFIRM": "Confermi l'avvio della riattivazione post-sospensione corrente? [S = Si / N = No]",
        "OPT_REACT_WAIT": "Fase 1/3: Passaggio a DHCP e riavvio del Foobot per registrazione sul router (attesa 16s)...",
        "OPT_REACT_DISC": "Fase 2/3: Ricerca del Foobot sulla rete locale per verificare l'IP assegnato dal router...",
        "OPT_REACT_RESYNC": "Fase 3/3: Fissaggio IP statico, riattivazione DNS ({0}) e apertura connessione Cloud...",
        "OPT_REACT_DONE": "RIATTIVAZIONE COMPLETATA! Il Foobot e nuovamente connesso al Cloud (TCPLKB: {0}).",
        "OPT_REACT_CANCEL": "Riattivazione annullata. Nessun parametro modificato.",
        "OPT3_TITLE": "CONFIGURAZIONE NUOVA RETE WI-FI (PAIRING SSID & PASSWORD)",
        "OPT3_INFO1": "Questa procedura consente di associare il Foobot a una nuova rete Wi-Fi di casa,",
        "OPT3_INFO2": "sostituendo completamente la vecchia app ufficiale Airboxlab dismessa.",
        "OPT3_WARN_24": "NOTA: Il modulo Wi-Fi Foobot HF-LPB100 supporta reti a 2.4 GHz con sicurezza WPA2-PSK.",
        "OPT3_PROMPT_SSID": "Inserisci il nome esatto della tua rete Wi-Fi (SSID a 2.4 GHz)",
        "OPT3_NO_SSID": "Nome rete non inserito. Operazione annullata.",
        "OPT3_PROMPT_PASS": "Inserisci la Password della tua rete Wi-Fi WPA2",
        "OPT3_NO_PASS": "Password non inserita. Operazione annullata.",
        "OPT3_PROMPT_DNS": "Server DNS opzionale da impostare subito (premi INVIO per mantenere attuale)",
        "OPT3_SUMMARY": "RIEPILOGO PARAMETRI DA INVIARE AL FOOBOT (OPERAZIONE REVERSIBILE):",
        "OPT3_SUM_SSID": "  - Rete Wi-Fi (SSID) : {0}",
        "OPT3_SUM_PASS": "  - Chiave di Rete    : ********",
        "OPT3_SUM_DNS": "  - Server DNS Nuovo  : {0}",
        "OPT3_NOTE": "NOTA BENE: Potrai cambiare nuovamente rete o DNS in qualsiasi momento da questo menu.",
        "OPT3_CONFIRM_ASK": "Vuoi scrivere questa configurazione nel modulo Foobot? [S = Si / N = No]",
        "OPT3_START": "Avvio sequenza di programmazione Wi-Fi sul Foobot...",
        "OPT3_DONE_1": "CONFIGURAZIONE INVIATA CON SUCCESSO!",
        "OPT3_DONE_2": "Il Foobot si sta riavviando e si colleghera alla rete '{0}'.",
        "OPT3_DONE_3": "Entro 30-60 secondi i LED del sensore diventeranno stabili (blu o arancio).",
        "OPT3_CANCELLED": "Configurazione Wi-Fi annullata. Nessuna modifica salvata.",
        "OPT4_TITLE": "RIPRISTINO IMPOSTAZIONI ORIGINALI DI FABBRICA",
        "OPT4_INFO": "Questa operazione reimposta il Foobot ai valori di rete originali di fabbrica:",
        "OPT4_WANN": "  -> Modalita IP (WANN) : DHCP dinamico",
        "OPT4_DNS": f"  -> Server DNS (WSDNS) : {FOOBOT_ORIGINAL_DNS}",
        "OPT4_CONFIRM_HEAD": "CONFERMA DI SICUREZZA:",
        "OPT4_CONFIRM_ASK": "Vuoi ripristinare il Foobot alle impostazioni originali di fabbrica? [S = Si / N = No]",
        "OPT4_START": "Avvio ripristino impostazioni DNS e DHCP di fabbrica...",
        "OPT4_OK_1": "Registri di fabbrica ripristinati con successo!",
        "OPT4_OK_2": "  - Verifica WANN : {0}",
        "OPT4_OK_3": "  - Verifica DNS  : {0}",
        "OPT4_OK_4": "OPERAZIONE COMPLETATA! Il dispositivo e tornato al 100% nel suo stato originale di fabbrica.",
        "OPT4_ERR": "Impossibile ripristinare il registro. Risposta: {0}",
        "OPT4_CANCELLED": "Ripristino annullato. Il dispositivo non e stato modificato.",
        "OPT5_TITLE": "RIAVVIO DEL MODULO WI-FI FOOBOT (SOLO COMANDO AT+Z)",
        "OPT5_INFO": "Invia esclusivamente il comando di riavvio hardware (AT+Z) al modulo Wi-Fi del Foobot.",
        "OPT5_CONFIRM_ASK": "Desideri inviare il comando di riavvio AT+Z al Foobot? [S = Si / N = No]",
        "OPT5_DONE": "OPERAZIONE COMPLETATA! Comando di riavvio (AT+Z) inviato al Foobot.",
        "OPT5_CANCELLED": "Riavvio annullato.",
        "OPT6_EXIT": "Chiusura del programma. Arrivederci!"
    },
    "EN": {
        "BANNER_TITLE": "                 FOOBOT MANAGEMENT & CONFIGURATION TOOL                        ",
        "BANNER_SUB": "                     Universal Open Source Utility                             ",
        "LOCAL_NOTICE_1": "This program operates exclusively within your local network.",
        "LOCAL_NOTICE_2": "No IP addresses or credentials are hardcoded in the source code.",
        "KB_INT": "Keyboard interrupt detected. Exiting...",
        "DISCOVERY_TITLE": "SEARCHING FOR FOOBOT DEVICE ON THE NETWORK",
        "DISC_PHASE1": f"Step 1: Sending UDP broadcast signal (255.255.255.255:{FOOBOT_UDP_PORT}) on LAN...",
        "DISC_FOUND_LAN": "Foobot detected on your LOCAL NETWORK (LAN)!",
        "DISC_CURR_IP": "   - Current IP Address  : ",
        "DISC_MAC": "   - Network MAC Address : ",
        "DISC_MID": "   - Serial Number (MID) : ",
        "DISC_PHASE2": f"Step 2: No response on LAN. Checking Factory Hotspot ({FOOBOT_HOTSPOT_IP}:{FOOBOT_UDP_PORT})...",
        "DISC_FOUND_HOT": f"Foobot detected in FACTORY HOTSPOT mode ({FOOBOT_HOTSPOT_IP})!",
        "DISC_MODE_HOT": "   - Operating Mode      : ",
        "DISC_MODE_HOT_VAL": "Hotspot / Access Point",
        "DISC_GW_IP": "   - Gateway IP Address  : ",
        "DISC_NOT_FOUND": "No Foobot automatically detected (neither on LAN nor on Hotspot).",
        "DISC_GUIDE_HEAD": "QUICK CONNECTION GUIDE:",
        "DISC_GUIDE_1": " 1. If you changed your router or Wi-Fi, turn your Foobot UPSIDE DOWN for 5 seconds.",
        "DISC_GUIDE_2": "    The LEDs will blink and Foobot will broadcast a Wi-Fi network (e.g. Foobot_xxxx or HF-LPB100).",
        "DISC_GUIDE_3": " 2. Connect your computer/laptop to that Wi-Fi network, then run this script again.",
        "DISC_GUIDE_4": " 3. If Foobot is already on your Wi-Fi, ensure your PC is on the same 2.4 GHz network.",
        "DISC_MANUAL_PROMPT": "If you know your Foobot's IP address, enter it now (or press ENTER to exit)",
        "ERR_INVALID_IP": "VALIDATION ERROR: ========== '{0}' is not a valid IPv4 address. ==========",
        "ERR_IP_FORMAT": "An IP address must consist of 4 numbers separated by dots (from 0.0.0.0 to 255.255.255.255).",
        "ERR_NO_DEVICE": "Operation ended: no Foobot device connected or reachable.",
        "PRESS_KEY_EXIT": "Press ENTER to exit...",
        "PRESS_KEY_MENU": "Press ENTER to return to the menu...",
        "SAFETY_BLOCK_1": "SAFETY BLOCK ACTIVE: Command '{0}' is blocked to protect the hardware.",
        "SAFETY_BLOCK_2": "This command is on the Foobot hardware/firmware protection blacklist.",
        "AT_STEP1": "[Step 1/3] Opening persistent UDP channel (Local PC Port: {0} -> Foobot: {1}:48899)",
        "AT_WAKE_RETRY": "           Handshake attempt {0}/3 timed out, retrying...",
        "AT_WAKE_ERR": "No response from Foobot at {0}:48899. Device unreachable.",
        "AT_STEP2": "[Step 2/3] Foobot connected! Module reply: {0}",
        "AT_MODE_ERR": "Unable to enter AT command mode on the Wi-Fi module.",
        "AT_STEP3": "[Step 3/3] AT command mode activated. Executing commands...",
        "AT_Z_SEND": "  [CMD] -> AT+Z (Rebooting Wi-Fi module...)",
        "AT_CMD_RESP": "  [CMD] -> {0} | [REPLY] -> {1}",
        "AT_CMD_WAIT": "           [5s Wait elapsed] No immediate response for '{0}', retrying ({1}/3)...",
        "AT_CMD_TIMEOUT": "           No response within time limit for '{0}' (TIMEOUT)",
        "AT_WAIT_PING": "           [Wait up to 5s] Resolving DNS and testing server reachability...",
        "AT_WAIT_TCP_OFF": "           [Wait 2.5s] Gracefully closing previous Cloud TCP socket...",
        "AT_WAIT_SOCK_NONE": "           [Wait 2.0s] Clearing Socket B channel buffer in Wi-Fi module...",
        "AT_WAIT_SOCK_TCP": "           [Wait 2.5s] Initializing new TCP endpoint (port 1883)...",
        "AT_WAIT_TCP_ON": "           [Wait 2.5s] Negotiating TCP handshake with Bridge server...",
        "WAIT_FINAL_TCPLKB": "[Wait 5s] Final stabilization of Cloud TCP link before re-reading status...",
        "WAIT_REBOOT_STEP": "           [Waiting: {0}s / {1}s] Wi-Fi module reboot and router IP negotiation in progress...",
        "AT_CLOSE": "[Closing] UDP channel closed properly.",
        "MENU_TITLE": "MAIN CONTROL MENU - FOOBOT ({0} - Mode: {1})",
        "MENU_OPT_1": "Read current Foobot configuration (READ ONLY - 100% SAFE)",
        "MENU_OPT_2": "Configure custom DNS server (NextDNS, Pi-hole, Local IP)",
        "MENU_OPT_3": "Reactivation after power outage with the previously saved IP",
        "MENU_OPT_4": "Configure New Home Wi-Fi Network (SSID & Password Pairing)",
        "MENU_OPT_5": "Restore original factory DNS & DHCP settings (10.10.100.254)",
        "MENU_OPT_6": "Reboot Foobot Wi-Fi module (AT+Z)",
        "MENU_OPT_7": "Exit program",
        "MENU_PROMPT": "Enter the number of the desired operation [1-7]",
        "MENU_INVALID": "Invalid choice '{0}'. Please select an option between 1 and 7.",
        "OPT1_TITLE": "READING HARDWARE & FIRMWARE REGISTERS (SAFE OPERATION)",
        "OPT1_START": "Starting query session for Foobot internal registers...",
        "OPT1_SUMMARY": "FOOBOT DIAGNOSTIC SUMMARY:",
        "OPT1_FW": "  - Wi-Fi Module Firmware Version  : ",
        "OPT1_MID": "  - Device Serial Number (MID)     : ",
        "OPT1_SSID": "  - Connected Wi-Fi Network (SSID) : ",
        "OPT1_WSLQ": "  - Wi-Fi Signal Quality           : ",
        "OPT1_WANN": "  - Network IP Settings (WANN)     : ",
        "OPT1_WSDNS": "  - Configured DNS Server (WSDNS)  : ",
        "OPT1_SOCKB": "  - Factory MQTT Channel           : ",
        "OPT1_TCPLKB": "  - Cloud TCP Connection (TCPLKB)  : ",
        "OPT1_DNS_CHECK": "LIVE DNS RESOLUTION & CLOUD DESTINATION CHECK:",
        "OPT1_DNS_AWS_WARN": " [WARNING: Still pointing to old dead AWS Foobot server!]",
        "OPT1_DNS_AWS_MSG": "DNS {0} is not redirecting broker-gw-nc.foobot.io to your Bridge (check linked IP on NextDNS).",
        "OPT1_DNS_OK_LABEL": "  - Actual Bridge Destination IP   : ",
        "OPT1_DNS_OK_MSG": "{0} (DNS rewrite active properly)",
        "OPT1_PORT_LABEL": "  - Bridge Port 1883 Status        : ",
        "OPT1_PORT_OPEN": "OPEN AND WORKING ({0}:1883)",
        "OPT1_PORT_CLOSED": "UNREACHABLE ({0}:1883)",
        "OPT1_PORT_ERR": "CONNECTION ERROR ({0}:1883)",
        "OPT1_DNS_UNREACH": "Unable to query DNS resolution for {0}",
        "OPT1_DONE": "Read completed. The values above represent the current memory state.",
        "OPT2_TITLE": "MODIFY FOOBOT DNS SERVER (TELEMETRY REDIRECTION)",
        "OPT2_INFO1": "This operation sets the DNS server queried by Foobot and locks the IP settings",
        "OPT2_INFO2": "(static mode) so the router's DHCP cannot overwrite your custom DNS.",
        "OPT2_ORIG_DNS": f"Original factory DNS value: {FOOBOT_ORIGINAL_DNS}",
        "OPT2_PROMPT_IP": "Enter the desired DNS Server IP address (e.g. NextDNS 45.90.28.xxx or Router/Pi-hole IP)",
        "OPT2_CANCEL_EMPTY": "No address entered. Operation cancelled.",
        "OPT2_CONFIRM_HEAD": "SAFETY CONFIRMATION (REVERSIBLE OPERATION):",
        "OPT2_CONFIRM_TARGET": "You are about to set Foobot's DNS to: '{0}'",
        "OPT2_CONFIRM_NOTE1": "NOTE: This change is completely reversible at any time.",
        "OPT2_CONFIRM_NOTE2": "If you wish to restore Foobot to factory settings (DHCP + original DNS),",
        "OPT2_CONFIRM_NOTE3": "simply run this script again and select Option 5 from the main menu.",
        "OPT2_CONFIRM_ASK": "Do you want to proceed with updating the DNS server? [Y = Yes / N = No]",
        "OPT2_PRE_READ": "Preliminary Step: Reading current Foobot network configuration (AT+WANN)...",
        "OPT2_NEW_IP": "IP address assigned by router: {0}",
        "OPT2_LAN_DETECTED": "Detected LAN parameters: IP={0} | Mask={1} | Gateway={2}",
        "OPT2_LAN_DEDUCED": "Deduced LAN parameters from active IP: IP={0} | Mask=255.255.255.0 | Gateway={1}",
        "OPT2_STATIC_APPLY": "Will apply '{0}' to prevent the router from overwriting the DNS.",
        "OPT2_WRITE_START": "Starting linear write and reconnection procedure on Foobot...",
        "OPT2_STEP1": "[Step 1/2] Locking static IP configuration in Flash NVRAM...",
        "OPT2_WANN_SAVED": "Stored WANN parameters: {0}",
        "OPT2_STEP2": "[Step 2/2] Writing DNS ({0}), resolving domain and connecting to Bridge...",
        "OPT2_OK_1": "Change accepted by Foobot! (DNS write response: {0})",
        "OPT2_OK_2": "Active DNS register check           : {0}",
        "OPT2_OK_3": "Configured MQTT channel check       : {0}",
        "OPT2_OK_4": "Cloud socket connection status      : {0}",
        "OPT2_OK_5": "OPERATION COMPLETED! Foobot queried the DNS and is connected to your Bridge.",
        "OPT2_ERR_WRITE": "Foobot did not confirm the change. Response received: {0}",
        "OPT2_CANCELLED": "Change cancelled. No parameters were modified.",
        "OPT_REACT_TITLE": "REACTIVATION AFTER POWER OUTAGE (USING PREVIOUSLY SAVED IP)",
        "OPT_REACT_DESC_HEAD": "WHAT THIS PROCEDURE DOES AND WHY IT IS NEEDED:",
        "OPT_REACT_DESC_1": "After a power outage, Foobot turns back on in a few seconds while your home router takes",
        "OPT_REACT_DESC_2": "several minutes. Upon rebooting, the router clears its connected device table and blocks",
        "OPT_REACT_DESC_3": "Internet access for Foobot's static IP (leaving the Cloud connection TCPLKB on 'off').",
        "OPT_REACT_STEPS_HD": "AUTOMATIC STEPS PERFORMED BY THIS PROGRAM:",
        "OPT_REACT_STEP_1": " 1. Reads the previously registered DNS Server IP from Foobot's internal memory.",
        "OPT_REACT_STEP_2": " 2. Temporarily enables DHCP and reboots Foobot (AT+Z for 16 seconds) so your home",
        "OPT_REACT_STEP_3": "    router registers and re-authorizes the sensor on the local network.",
        "OPT_REACT_STEP_4": " 3. Detects the local IP assigned by the router and locks it again as a static IP.",
        "OPT_REACT_STEP_5": " 4. Reapplies the previously saved DNS IP and reopens the Cloud connection (TCPLKB=on).",
        "OPT_REACT_SAVED_DNS": "  -> Previously saved DNS Server detected in Foobot : {0}",
        "OPT_REACT_NO_DNS": "WARNING: Factory DNS ({0}) is currently set in Foobot. Please configure your DNS with Option 2 first.",
        "OPT_REACT_CONFIRM": "Do you confirm starting the post-power-outage reactivation? [Y = Yes / N = No]",
        "OPT_REACT_WAIT": "Step 1/3: Switching to DHCP and rebooting Foobot for router registration (waiting 16s)...",
        "OPT_REACT_DISC": "Step 2/3: Searching for Foobot on local network to verify the router-assigned IP...",
        "OPT_REACT_RESYNC": "Step 3/3: Locking static IP, restoring DNS ({0}) and opening Cloud connection...",
        "OPT_REACT_DONE": "REACTIVATION COMPLETED! Foobot is connected to the Cloud again (TCPLKB: {0}).",
        "OPT_REACT_CANCEL": "Reactivation cancelled. No parameters were modified.",
        "OPT3_TITLE": "CONFIGURE NEW HOME WI-FI NETWORK (SSID & PASSWORD PAIRING)",
        "OPT3_INFO1": "This procedure connects your Foobot to a new home Wi-Fi network,",
        "OPT3_INFO2": "completely replacing the discontinued official Airboxlab mobile app.",
        "OPT3_WARN_24": "NOTE: The Foobot HF-LPB100 Wi-Fi module supports 2.4 GHz networks with WPA2-PSK security.",
        "OPT3_PROMPT_SSID": "Enter the exact name of your Wi-Fi network (2.4 GHz SSID)",
        "OPT3_NO_SSID": "Network name not entered. Operation cancelled.",
        "OPT3_PROMPT_PASS": "Enter your WPA2 Wi-Fi network Password",
        "OPT3_NO_PASS": "Password not entered. Operation cancelled.",
        "OPT3_PROMPT_DNS": "Optional DNS server to set immediately (press ENTER to keep current)",
        "OPT3_SUMMARY": "SUMMARY OF PARAMETERS TO SEND TO FOOBOT (REVERSIBLE OPERATION):",
        "OPT3_SUM_SSID": "  - Wi-Fi Network (SSID) : {0}",
        "OPT3_SUM_PASS": "  - Network Password     : ********",
        "OPT3_SUM_DNS": "  - New DNS Server       : {0}",
        "OPT3_NOTE": "NOTE: You can change the network or DNS again at any time from this menu.",
        "OPT3_CONFIRM_ASK": "Do you want to write this configuration to the Foobot module? [Y = Yes / N = No]",
        "OPT3_START": "Starting Wi-Fi programming sequence on Foobot...",
        "OPT3_DONE_1": "CONFIGURATION SENT SUCCESSFULLY!",
        "OPT3_DONE_2": "Foobot is rebooting and will connect to the '{0}' network.",
        "OPT3_DONE_3": "Within 30-60 seconds the sensor LEDs will become solid (blue or orange).",
        "OPT3_CANCELLED": "Wi-Fi configuration cancelled. No changes saved.",
        "OPT4_TITLE": "RESTORE ORIGINAL FACTORY SETTINGS",
        "OPT4_INFO": "This operation resets Foobot to its original factory network values:",
        "OPT4_WANN": "  -> IP Mode (WANN)     : Dynamic DHCP",
        "OPT4_DNS": f"  -> DNS Server (WSDNS) : {FOOBOT_ORIGINAL_DNS}",
        "OPT4_CONFIRM_HEAD": "SAFETY CONFIRMATION:",
        "OPT4_CONFIRM_ASK": "Do you want to restore Foobot to original factory settings? [Y = Yes / N = No]",
        "OPT4_START": "Starting factory DNS and DHCP restore...",
        "OPT4_OK_1": "Factory registers restored successfully!",
        "OPT4_OK_2": "  - WANN Check : {0}",
        "OPT4_OK_3": "  - DNS Check  : {0}",
        "OPT4_OK_4": "OPERATION COMPLETED! The device is 100% back to its original factory state.",
        "OPT4_ERR": "Unable to restore register. Response: {0}",
        "OPT4_CANCELLED": "Restore cancelled. The device was not modified.",
        "OPT5_TITLE": "REBOOT FOOBOT WI-FI MODULE (AT+Z COMMAND ONLY)",
        "OPT5_INFO": "Sends exclusively the hardware reboot command (AT+Z) to Foobot's Wi-Fi module.",
        "OPT5_CONFIRM_ASK": "Do you want to send the AT+Z reboot command to Foobot? [Y = Yes / N = No]",
        "OPT5_DONE": "OPERATION COMPLETED! Reboot command (AT+Z) sent to Foobot.",
        "OPT5_CANCELLED": "Reboot cancelled.",
        "OPT6_EXIT": "Closing program. Goodbye!"
    }
}


def t(key, *args):
    """Restituisce la stringa tradotta nella lingua corrente con eventuale formattazione."""
    lang_dict = I18N.get(CURRENT_LANG, I18N["EN"])
    raw = lang_dict.get(key, key)
    if args:
        try:
            return raw.format(*args)
        except Exception:
            return raw
    return raw


def is_confirmed(val_str):
    """Verifica se l'utente ha confermato (S/SI in Italiano, Y/YES in Inglese)."""
    if not val_str:
        return False
    v = val_str.strip().upper()
    return v in ("S", "SI", "Y", "YES")


def detect_system_language():
    """Rileva se il sistema operativo è impostato su Italiano, altrimenti ritorna EN."""
    try:
        env_lang = os.environ.get("LANG", "") or os.environ.get("LC_ALL", "") or os.environ.get("LC_MESSAGES", "")
        if env_lang.lower().startswith("it"):
            return "IT"
        loc = locale.getdefaultlocale()[0]
        if loc and loc.lower().startswith("it"):
            return "IT"
    except Exception:
        pass
    return "EN"


def select_startup_language():
    """Mostra la schermata iniziale di conferma lingua (IT / EN)."""
    global CURRENT_LANG
    detected = detect_system_language()

    while True:
        print("\033[H\033[J", end="")
        print(f"{COLOR_CYAN}================================================================================{COLOR_RESET}")
        print(f"{COLOR_WHITE}  NEWFOOBOT - LANGUAGE SELECTION / SELEZIONE LINGUA{COLOR_RESET}")
        print(f"{COLOR_CYAN}================================================================================{COLOR_RESET}")
        print("")
        print(f"  {COLOR_GRAY}Supported languages / Lingue supportate :{COLOR_RESET} {COLOR_YELLOW}English (EN) - Italiano (IT){COLOR_RESET}")

        if detected == "IT":
            print(f"  {COLOR_GRAY}Detected system language / Lingua rilevata :{COLOR_RESET} {COLOR_GREEN}ITALIANO (IT){COLOR_RESET}")
            print("")
            ans = read_input("  Confermi la lingua ITALIANO? [S = Si (Invio) / N = No, passa a Inglese]").upper()
            if ans in ("", "S", "SI", "Y", "YES"):
                CURRENT_LANG = "IT"
                break
            elif ans in ("N", "NO"):
                ans_en = read_input("  Vuoi impostare la lingua INGLESE (English)? Confermi? [Y/S = Yes (Enter) / N = No]").upper()
                if ans_en in ("", "Y", "YES", "S", "SI"):
                    CURRENT_LANG = "EN"
                    break
        else:
            print(f"  {COLOR_GRAY}Detected system language / Lingua rilevata :{COLOR_RESET} {COLOR_GREEN}ENGLISH (EN){COLOR_RESET}")
            print("")
            ans = read_input("  Confirm ENGLISH language? [Y = Yes (Enter) / N = No, switch to Italiano]").upper()
            if ans in ("", "Y", "YES", "S", "SI"):
                CURRENT_LANG = "EN"
                break
            elif ans in ("N", "NO"):
                ans_it = read_input("  Do you want to set ITALIAN (Italiano) language? Confirm? [S/Y = Si (Enter) / N = No]").upper()
                if ans_it in ("", "S", "SI", "Y", "YES"):
                    CURRENT_LANG = "IT"
                    break


def write_box_title(title):
    print("")
    print(f"{COLOR_CYAN}================================================================================{COLOR_RESET}")
    print(f"{COLOR_WHITE}  {title}{COLOR_RESET}")
    print(f"{COLOR_CYAN}================================================================================{COLOR_RESET}")
    print("")


def write_info(msg):
    print(f"{COLOR_GRAY}[INFO] {msg}{COLOR_RESET}")


def write_step(msg):
    print(f"{COLOR_CYAN}  --> {msg}{COLOR_RESET}")


def write_success(msg):
    print(f"{COLOR_GREEN}[OK]   {msg}{COLOR_RESET}")


def write_warning(msg):
    print(f"{COLOR_YELLOW}[WARN] {msg}{COLOR_RESET}")


def write_error(msg):
    print(f"{COLOR_RED}[ERR]  {msg}{COLOR_RESET}")


def test_is_valid_ipv4(ip_string):
    if not ip_string or not isinstance(ip_string, str):
        return False
    trimmed = ip_string.strip()
    parts = trimmed.split(".")
    if len(parts) != 4:
        return False
    for p in parts:
        if not p.isdigit():
            return False
        val = int(p)
        if val < 0 or val > 255:
            return False
    return True


def read_input(prompt_text):
    print("\n")
    try:
        val = input(f"{prompt_text}: ")
        return val.strip()
    except (KeyboardInterrupt, EOFError):
        print("\n")
        write_info(t("KB_INT"))
        sys.exit(0)


def send_discovery_broadcast(ip_address, port=FOOBOT_UDP_PORT, timeout_sec=5.0):
    sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    try:
        sock.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
        if ip_address == "255.255.255.255":
            sock.setsockopt(socket.SOL_SOCKET, socket.SO_BROADCAST, 1)
        sock.settimeout(timeout_sec)

        sock.sendto(PROTOCOL_HANDSHAKE, (ip_address, port))
        data, remote_addr = sock.recvfrom(1024)
        reply_str = data.decode("ascii", errors="ignore").strip()

        try:
            sock.sendto(PROTOCOL_ENTER_AT, remote_addr)
            time.sleep(0.15)
            sock.sendto(b"AT+Q\r", remote_addr)
            time.sleep(0.10)
        except Exception:
            pass

        return reply_str
    except socket.timeout:
        return "TIMEOUT"
    except Exception:
        return "TIMEOUT"
    finally:
        sock.close()


def find_foobot_device():
    write_box_title(t("DISCOVERY_TITLE"))
    write_info(t("DISC_PHASE1"))

    lan_response = send_discovery_broadcast("255.255.255.255", FOOBOT_UDP_PORT, 5.0)

    if lan_response != "TIMEOUT" and not lan_response.startswith("ERROR"):
        elements = lan_response.split(",")
        if len(elements) >= 3:
            detected_ip  = elements[0].strip()
            detected_mac = elements[1].strip()
            detected_mid = elements[2].strip()

            write_success(t("DISC_FOUND_LAN"))
            print(f"{t('DISC_CURR_IP')}{COLOR_WHITE}{detected_ip}{COLOR_RESET}")
            print(f"{t('DISC_MAC')}{COLOR_WHITE}{detected_mac}{COLOR_RESET}")
            print(f"{t('DISC_MID')}{COLOR_WHITE}{detected_mid}{COLOR_RESET}")
            print("")
            return {"IP": detected_ip, "Mode": "LAN", "MAC": detected_mac, "MID": detected_mid}

    write_info(t("DISC_PHASE2"))
    hotspot_response = send_discovery_broadcast(FOOBOT_HOTSPOT_IP, FOOBOT_UDP_PORT, 5.0)

    if hotspot_response != "TIMEOUT" and not hotspot_response.startswith("ERROR"):
        elements = hotspot_response.split(",")
        detected_mac = elements[1].strip() if len(elements) >= 2 else "N/D"
        detected_mid = elements[2].strip() if len(elements) >= 3 else "N/D"

        write_success(t("DISC_FOUND_HOT"))
        print(f"{t('DISC_MODE_HOT')}{COLOR_YELLOW}{t('DISC_MODE_HOT_VAL')}{COLOR_RESET}")
        print(f"{t('DISC_GW_IP')}{COLOR_WHITE}{FOOBOT_HOTSPOT_IP}{COLOR_RESET}")
        print(f"{t('DISC_MAC')}{COLOR_WHITE}{detected_mac}{COLOR_RESET}")
        print(f"{t('DISC_MID')}{COLOR_WHITE}{detected_mid}{COLOR_RESET}")
        print("")
        return {"IP": FOOBOT_HOTSPOT_IP, "Mode": "HOTSPOT", "MAC": detected_mac, "MID": detected_mid}

    write_warning(t("DISC_NOT_FOUND"))
    print("")
    write_info(t("DISC_GUIDE_HEAD"))
    write_info(t("DISC_GUIDE_1"))
    write_info(t("DISC_GUIDE_2"))
    write_info(t("DISC_GUIDE_3"))
    write_info(t("DISC_GUIDE_4"))
    print("")

    manual_ip = read_input(t("DISC_MANUAL_PROMPT"))
    if not manual_ip:
        return None

    clean_manual_ip = manual_ip.strip()
    if not test_is_valid_ipv4(clean_manual_ip):
        display_val = clean_manual_ip[:15] + "..." if len(clean_manual_ip) > 15 else clean_manual_ip
        write_error(t("ERR_INVALID_IP", display_val))
        return None

    return {"IP": clean_manual_ip, "Mode": "MANUAL", "MAC": "N/D", "MID": "N/D"}


def invoke_foobot_at_session(device_ip, command_list, show_verbose=True):
    results = {}

    forbidden_prefixes = (
        "AT+UART=", "AT+UARTF", "AT+UARTFT", "AT+UARTFL", "AT+UARTTE",
        "AT+RELD", "AT+FCLR", "AT+UPURL", "AT+UPFILE", "AT+UPST", "AT+UPWEB", "AT+UPCFG", "AT+UPAUTO",
        "AT+UPMCU", "AT+FLPBOTA", "AT+FMCUOTA", "AT+MCUOTA", "AT+LPBOTA", "AT+ENTERBTL", "AT+STOPBTL", "AT+BTLTEST",
        "AT+FAILSAFE", "AT+INITEEPROM", "AT+FRESET", "AT+STRESET", "AT+STRESET2", "AT+RESETCALIB",
        "AT+HUMCALIB=", "AT+TEMPCALIB=", "AT+PMCALIB=",
        "AT+NETP=", "AT+WAP=", "AT+WAKEY=", "AT+LANN="
    )
    for cmd in command_list:
        for forbidden in forbidden_prefixes:
            if cmd.startswith(forbidden):
                write_error(t("SAFETY_BLOCK_1", cmd))
                write_info(t("SAFETY_BLOCK_2"))
                results[cmd] = "BLOCKED_BY_SAFETY_POLICY"
                return results

    sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    try:
        sock.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
        sock.bind(("", 0))
        sock.settimeout(5.0)

        local_port = sock.getsockname()[1]
        target_ep = (device_ip, FOOBOT_UDP_PORT)

        if show_verbose:
            write_step(t("AT_STEP1", local_port, device_ip))

        sock.sendto(PROTOCOL_HANDSHAKE, target_ep)

        discovery_reply = ""
        for attempt in range(1, 4):
            try:
                data, _ = sock.recvfrom(1024)
                discovery_reply = data.decode("ascii", errors="ignore").strip()
                break
            except socket.timeout:
                if attempt < 3:
                    if show_verbose:
                        write_step(t("AT_WAKE_RETRY", attempt))
                    sock.sendto(PROTOCOL_HANDSHAKE, target_ep)
                else:
                    write_error(t("AT_WAKE_ERR", device_ip))
                    return results

        if show_verbose:
            write_step(t("AT_STEP2", discovery_reply))

        time.sleep(0.10)

        sock.sendto(PROTOCOL_ENTER_AT, target_ep)

        at_mode_ok = False
        for attempt in range(1, 4):
            try:
                data, _ = sock.recvfrom(1024)
                ack_str = data.decode("ascii", errors="ignore").strip()
                if "+ok" in ack_str:
                    at_mode_ok = True
                    break
            except socket.timeout:
                if attempt < 3:
                    sock.sendto(PROTOCOL_ENTER_AT, target_ep)

        if not at_mode_ok:
            write_error(t("AT_MODE_ERR"))
            return results

        if show_verbose:
            write_step(t("AT_STEP3"))

        time.sleep(0.15)
        did_reboot = False

        for cmd in command_list:
            if any(cmd.startswith(prefix) for prefix in ("AT+WSSSID", "AT+WSKEY", "AT+WANN", "AT+WSDNS")):
                time.sleep(0.40)

            if cmd == "AT+Z":
                did_reboot = True
                if show_verbose:
                    write_step(t("AT_Z_SEND"))
                sock.sendto(b"AT+Z\r", target_ep)
                time.sleep(0.50)
                results["AT+Z"] = "+ok"
                break

            cmd_bytes = (cmd + "\r").encode("ascii")
            if show_verbose and cmd.startswith("AT+PING="):
                write_step(t("AT_WAIT_PING"))
            sock.sendto(cmd_bytes, target_ep)

            for attempt in range(1, 4):
                try:
                    data, _ = sock.recvfrom(1024)
                    cmd_reply = data.decode("ascii", errors="ignore").strip()
                    results[cmd] = cmd_reply
                    if show_verbose:
                        write_step(t("AT_CMD_RESP", cmd, cmd_reply))
                    # Svuota eventuali pacchetti ritardati rimasti nel buffer UDP
                    sock.settimeout(0.12)
                    while True:
                        try:
                            sock.recvfrom(1024)
                        except Exception:
                            break
                    sock.settimeout(5.0)
                    break
                except socket.timeout:
                    if attempt < 3:
                        if show_verbose:
                            write_step(t("AT_CMD_WAIT", cmd, attempt + 1))
                        try:
                            sock.sendto(PROTOCOL_HANDSHAKE, target_ep)
                            time.sleep(0.15)
                            sock.settimeout(0.15)
                            while True:
                                try:
                                    sock.recvfrom(1024)
                                except Exception:
                                    break
                            sock.sendto(PROTOCOL_ENTER_AT, target_ep)
                            time.sleep(0.20)
                            while True:
                                try:
                                    sock.recvfrom(1024)
                                except Exception:
                                    break
                            sock.settimeout(5.0)
                        except Exception:
                            sock.settimeout(5.0)
                        sock.sendto(cmd_bytes, target_ep)
                    else:
                        if show_verbose:
                            write_step(t("AT_CMD_TIMEOUT", cmd))
                        results[cmd] = "TIMEOUT"

            if cmd == "AT+TCPDISB=off":
                if show_verbose:
                    write_step(t("AT_WAIT_TCP_OFF"))
                time.sleep(1.50)
            elif cmd == "AT+SOCKB=NONE":
                if show_verbose:
                    write_step(t("AT_WAIT_SOCK_NONE"))
                time.sleep(1.00)
            elif cmd.startswith("AT+SOCKB="):
                if show_verbose:
                    write_step(t("AT_WAIT_SOCK_TCP"))
                time.sleep(1.50)
            elif cmd == "AT+TCPDISB=on":
                if show_verbose:
                    write_step(t("AT_WAIT_TCP_ON"))
                time.sleep(2.50)
            else:
                time.sleep(0.20)

        if not did_reboot:
            try:
                sock.sendto(b"AT+Q\r", target_ep)
                time.sleep(0.10)
            except Exception:
                pass

    finally:
        sock.close()
        if show_verbose:
            write_step(t("AT_CLOSE"))
            print("")

    return results


def wait_for_key(prompt=None):
    if prompt is None:
        prompt = t("PRESS_KEY_MENU")
    print("")
    try:
        input(f"{COLOR_GRAY}{prompt}{COLOR_RESET}")
    except (KeyboardInterrupt, EOFError):
        pass


def main():
    select_startup_language()

    print("\033[H\033[J", end="")
    print(f"{COLOR_CYAN}================================================================================{COLOR_RESET}")
    print(f"{COLOR_WHITE}{t('BANNER_TITLE')}{COLOR_RESET}")
    print(f"{COLOR_YELLOW}{t('BANNER_SUB')}{COLOR_RESET}")
    print(f"{COLOR_CYAN}================================================================================{COLOR_RESET}")
    print("")
    write_info(t("LOCAL_NOTICE_1"))
    write_info(t("LOCAL_NOTICE_2"))
    print("")

    device = find_foobot_device()

    if not device or not device.get("IP"):
        write_error(t("ERR_NO_DEVICE"))
        wait_for_key(t("PRESS_KEY_EXIT"))
        sys.exit(1)

    target_ip = device["IP"]

    while True:
        write_box_title(t("MENU_TITLE", target_ip, device["Mode"]))
        print(f"{COLOR_YELLOW}1. {COLOR_RESET}{t('MENU_OPT_1')}")
        print(f"{COLOR_YELLOW}2. {COLOR_RESET}{t('MENU_OPT_2')}")
        print(f"{COLOR_YELLOW}3. {COLOR_RESET}{t('MENU_OPT_3')}")
        print(f"{COLOR_YELLOW}4. {COLOR_RESET}{t('MENU_OPT_4')}")
        print(f"{COLOR_YELLOW}5. {COLOR_RESET}{t('MENU_OPT_5')}")
        print(f"{COLOR_YELLOW}6. {COLOR_RESET}{t('MENU_OPT_6')}")
        print(f"{COLOR_YELLOW}7. {COLOR_RESET}{t('MENU_OPT_7')}")
        print("")

        user_choice = read_input(t("MENU_PROMPT"))

        if user_choice == "1":
            write_box_title(t("OPT1_TITLE"))
            write_info(t("OPT1_START"))
            print("")

            commands_to_read = ["AT+VER", "AT+MID", "AT+WSSSID", "AT+WSLQ", "AT+WANN", "AT+WSDNS", "AT+SOCKB", "AT+TCPLKB"]
            diag_results = invoke_foobot_at_session(target_ip, commands_to_read, show_verbose=True)

            print(f"{COLOR_WHITE}{t('OPT1_SUMMARY')}{COLOR_RESET}")
            print(f"{t('OPT1_FW')}{COLOR_GREEN}{diag_results.get('AT+VER', 'N/D')}{COLOR_RESET}")
            print(f"{t('OPT1_MID')}{COLOR_GREEN}{diag_results.get('AT+MID', 'N/D')}{COLOR_RESET}")
            print(f"{t('OPT1_SSID')}{COLOR_GREEN}{diag_results.get('AT+WSSSID', 'N/D')}{COLOR_RESET}")
            print(f"{t('OPT1_WSLQ')}{COLOR_GREEN}{diag_results.get('AT+WSLQ', 'N/D')}{COLOR_RESET}")
            print(f"{t('OPT1_WANN')}{COLOR_GREEN}{diag_results.get('AT+WANN', 'N/D')}{COLOR_RESET}")
            print(f"{t('OPT1_WSDNS')}{COLOR_GREEN}{diag_results.get('AT+WSDNS', 'N/D')}{COLOR_RESET}")
            print(f"{t('OPT1_SOCKB')}{COLOR_GREEN}{diag_results.get('AT+SOCKB', 'N/D')}{COLOR_RESET}")
            print(f"{t('OPT1_TCPLKB')}{COLOR_GREEN}{diag_results.get('AT+TCPLKB', 'N/D')}{COLOR_RESET}")
            print("")

            raw_dns_val = diag_results.get("AT+WSDNS", "")
            if raw_dns_val.startswith("+ok="):
                active_dns_ip = raw_dns_val[4:].strip()
                if test_is_valid_ipv4(active_dns_ip):
                    print(f"{COLOR_WHITE}{t('OPT1_DNS_CHECK')}{COLOR_RESET}")
                    try:
                        dns_sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
                        dns_sock.settimeout(3.0)
                        dns_query = (
                            b"\x12\x34\x01\x00\x00\x01\x00\x00\x00\x00\x00\x00"
                            b"\x0cbroker-gw-nc\x06foobot\x02io\x00\x00\x01\x00\x01"
                        )
                        dns_sock.sendto(dns_query, (active_dns_ip, 53))
                        dns_resp, _ = dns_sock.recvfrom(512)
                        dns_sock.close()
                        resolved_broker_ip = f"{dns_resp[-4]}.{dns_resp[-3]}.{dns_resp[-2]}.{dns_resp[-1]}"
                        aws_known_ips = ("3.223.61.228", "35.170.134.203", "52.87.92.129", "54.172.185.79")
                        if resolved_broker_ip in aws_known_ips:
                            print(f"  - DNS ({active_dns_ip}) : {COLOR_RED}{resolved_broker_ip}{t('OPT1_DNS_AWS_WARN')}{COLOR_RESET}")
                            write_warning(t("OPT1_DNS_AWS_MSG", active_dns_ip))
                        else:
                            print(f"{t('OPT1_DNS_OK_LABEL')}{COLOR_GREEN}{t('OPT1_DNS_OK_MSG', resolved_broker_ip)}{COLOR_RESET}")
                    except Exception:
                        print(f"  - DNS ({active_dns_ip}) : {COLOR_YELLOW}{t('OPT1_DNS_UNREACH', active_dns_ip)}{COLOR_RESET}")
                    print("")

            write_success(t("OPT1_DONE"))
            wait_for_key()

        elif user_choice == "2":
            write_box_title(t("OPT2_TITLE"))
            write_info(t("OPT2_INFO1"))
            write_info(t("OPT2_INFO2"))
            print("")
            write_warning(t("OPT2_ORIG_DNS"))
            print("")

            raw_input_dns = read_input(t("OPT2_PROMPT_IP"))
            new_dns_ip = raw_input_dns.strip() if raw_input_dns else ""

            if not new_dns_ip:
                write_warning(t("OPT2_CANCEL_EMPTY"))
                time.sleep(2)
                continue

            if not test_is_valid_ipv4(new_dns_ip):
                display_val = new_dns_ip[:15] + "..." if len(new_dns_ip) > 15 else new_dns_ip
                write_error(t("ERR_INVALID_IP", display_val))
                write_info(t("ERR_IP_FORMAT"))
                wait_for_key()
                continue

            print("")
            write_warning(t("OPT2_CONFIRM_HEAD"))
            print(f"{COLOR_YELLOW}{t('OPT2_CONFIRM_TARGET', new_dns_ip)}{COLOR_RESET}")
            write_info(t("OPT2_CONFIRM_NOTE1"))
            write_info(t("OPT2_CONFIRM_NOTE2"))
            write_info(t("OPT2_CONFIRM_NOTE3"))
            print("")

            confirm = read_input(t("OPT2_CONFIRM_ASK"))

            if is_confirmed(confirm):
                print("")
                write_info(t("OPT2_PRE_READ"))
                pre_read = invoke_foobot_at_session(target_ip, ["AT+WANN"], show_verbose=True)
                wann_raw = pre_read.get("AT+WANN", "")

                cmd_static_wann = None
                if wann_raw.startswith("+ok="):
                    clean_wann = wann_raw[4:]
                    wann_parts = clean_wann.split(",")
                    if len(wann_parts) >= 4:
                        curr_ip   = wann_parts[1].strip()
                        curr_mask = wann_parts[2].strip()
                        curr_gw   = wann_parts[3].strip()
                        if test_is_valid_ipv4(curr_ip) and test_is_valid_ipv4(curr_mask) and test_is_valid_ipv4(curr_gw):
                            cmd_static_wann = f"AT+WANN=static,{curr_ip},{curr_mask},{curr_gw}"
                            write_info(t("OPT2_LAN_DETECTED", curr_ip, curr_mask, curr_gw))
                            write_info(t("OPT2_STATIC_APPLY", cmd_static_wann))

                if not cmd_static_wann and test_is_valid_ipv4(target_ip) and target_ip != FOOBOT_HOTSPOT_IP:
                    octets = target_ip.split(".")
                    fallback_gw = f"{octets[0]}.{octets[1]}.{octets[2]}.1"
                    cmd_static_wann = f"AT+WANN=static,{target_ip},255.255.255.0,{fallback_gw}"
                    write_info(t("OPT2_LAN_DEDUCED", target_ip, fallback_gw))
                    write_info(t("OPT2_STATIC_APPLY", cmd_static_wann))

                print("")
                write_info(t("OPT2_WRITE_START"))
                cmd_write_dns = f"AT+WSDNS={new_dns_ip}"
                cmd_verify_dns = "AT+WSDNS"

                if cmd_static_wann:
                    write_info(t("OPT2_STEP1"))
                    res_wann = invoke_foobot_at_session(target_ip, [cmd_static_wann, "AT+WANN"], show_verbose=True)
                    write_success(t("OPT2_WANN_SAVED", res_wann.get("AT+WANN", "N/D")))
                    time.sleep(0.50)

                write_info(t("OPT2_STEP2", new_dns_ip))
                reconnect_cmds = [
                    "AT+TCPDISB=off",
                    cmd_write_dns,
                    "AT+SOCKB=NONE",
                    "AT+SOCKB=TCP,1883,broker-gw-nc.foobot.io",
                    "AT+TCPDISB=on",
                    cmd_verify_dns,
                    "AT+SOCKB",
                    "AT+TCPLKB"
                ]
                write_session = invoke_foobot_at_session(target_ip, reconnect_cmds, show_verbose=True)

                write_outcome = write_session.get(cmd_write_dns, "")
                verify_dns_outcome = write_session.get(cmd_verify_dns, "")
                verify_sock_outcome = write_session.get("AT+SOCKB", "")
                verify_tcp_outcome = write_session.get("AT+TCPLKB", "")

                if "+ok=on" not in verify_tcp_outcome:
                    write_step(t("WAIT_FINAL_TCPLKB"))
                    time.sleep(5)
                    recheck = invoke_foobot_at_session(target_ip, ["AT+TCPLKB"], show_verbose=True)
                    if "+ok" in recheck.get("AT+TCPLKB", ""):
                        verify_tcp_outcome = recheck["AT+TCPLKB"]

                if "+ok" in write_outcome:
                    write_success(t("OPT2_OK_1", write_outcome))
                    write_success(t("OPT2_OK_2", verify_dns_outcome))
                    write_success(t("OPT2_OK_3", verify_sock_outcome))
                    write_success(t("OPT2_OK_4", verify_tcp_outcome))
                    write_success(t("OPT2_OK_5"))
                else:
                    write_error(t("OPT2_ERR_WRITE", write_outcome))
            else:
                write_info(t("OPT2_CANCELLED"))

            wait_for_key()

        elif user_choice == "3":
            write_box_title(t("OPT_REACT_TITLE"))
            print(f"{COLOR_YELLOW}{t('OPT_REACT_DESC_HEAD')}{COLOR_RESET}")
            write_info(t("OPT_REACT_DESC_1"))
            write_info(t("OPT_REACT_DESC_2"))
            write_info(t("OPT_REACT_DESC_3"))
            print("")
            print(f"{COLOR_CYAN}{t('OPT_REACT_STEPS_HD')}{COLOR_RESET}")
            write_info(t("OPT_REACT_STEP_1"))
            write_info(t("OPT_REACT_STEP_2"))
            write_info(t("OPT_REACT_STEP_3"))
            write_info(t("OPT_REACT_STEP_4"))
            write_info(t("OPT_REACT_STEP_5"))
            print("")

            pre_dns = invoke_foobot_at_session(target_ip, ["AT+WSDNS"], show_verbose=False)
            saved_dns_val = ""
            raw_dns = pre_dns.get("AT+WSDNS", "")
            if raw_dns.startswith("+ok="):
                saved_dns_val = raw_dns[4:].strip()

            if not test_is_valid_ipv4(saved_dns_val) or saved_dns_val == FOOBOT_ORIGINAL_DNS:
                disp_dns = saved_dns_val if saved_dns_val else FOOBOT_ORIGINAL_DNS
                write_warning(t("OPT_REACT_NO_DNS", disp_dns))
                wait_for_key()
                continue

            print(f"{COLOR_GREEN}{t('OPT_REACT_SAVED_DNS', saved_dns_val)}{COLOR_RESET}")
            print("")

            confirm = read_input(t("OPT_REACT_CONFIRM"))

            if is_confirmed(confirm):
                print("")
                write_info(t("OPT_REACT_WAIT"))
                invoke_foobot_at_session(target_ip, ["AT+WANN=DHCP", "AT+Z"], show_verbose=True)
                for sec in range(1, 17):
                    if sec == 1 or sec % 4 == 0:
                        write_step(t("WAIT_REBOOT_STEP", sec, 16))
                    time.sleep(1)
                print("")

                write_info(t("OPT_REACT_DISC"))
                renewed_lan = send_discovery_broadcast("255.255.255.255", FOOBOT_UDP_PORT, 5.0)
                if renewed_lan != "TIMEOUT" and not renewed_lan.startswith("ERROR"):
                    r_parts = renewed_lan.split(",")
                    if len(r_parts) >= 1 and test_is_valid_ipv4(r_parts[0].strip()):
                        target_ip = r_parts[0].strip()
                        device["IP"] = target_ip
                        write_success(t("OPT2_NEW_IP", target_ip))

                write_info(t("OPT_REACT_RESYNC", saved_dns_val))
                wann_check = invoke_foobot_at_session(target_ip, ["AT+WANN"], show_verbose=True)
                static_cmd = None
                raw_w = wann_check.get("AT+WANN", "")
                if raw_w.startswith("+ok="):
                    w_parts = raw_w[4:].split(",")
                    if len(w_parts) >= 4:
                        c_ip   = w_parts[1].strip()
                        c_mask = w_parts[2].strip()
                        c_gw   = w_parts[3].strip()
                        if test_is_valid_ipv4(c_ip) and test_is_valid_ipv4(c_mask) and test_is_valid_ipv4(c_gw):
                            static_cmd = f"AT+WANN=static,{c_ip},{c_mask},{c_gw}"
                if not static_cmd and test_is_valid_ipv4(target_ip) and target_ip != FOOBOT_HOTSPOT_IP:
                    octs = target_ip.split(".")
                    fb_gw = f"{octs[0]}.{octs[1]}.{octs[2]}.1"
                    static_cmd = f"AT+WANN=static,{target_ip},255.255.255.0,{fb_gw}"

                sync_cmds = []
                if static_cmd:
                    sync_cmds.append(static_cmd)
                sync_cmds.extend([
                    "AT+TCPDISB=off",
                    f"AT+WSDNS={saved_dns_val}",
                    "AT+SOCKB=NONE",
                    "AT+SOCKB=TCP,1883,broker-gw-nc.foobot.io",
                    "AT+TCPDISB=on",
                    "AT+WANN",
                    "AT+WSDNS",
                    "AT+SOCKB",
                    "AT+TCPLKB"
                ])
                sync_res = invoke_foobot_at_session(target_ip, sync_cmds, show_verbose=True)
                final_tcp = sync_res.get("AT+TCPLKB", "N/D")
                if "+ok=on" not in final_tcp:
                    write_step(t("WAIT_FINAL_TCPLKB"))
                    time.sleep(5)
                    recheck_tcp = invoke_foobot_at_session(target_ip, ["AT+TCPLKB"], show_verbose=True)
                    if "+ok" in recheck_tcp.get("AT+TCPLKB", ""):
                        final_tcp = recheck_tcp["AT+TCPLKB"]
                write_success(t("OPT_REACT_DONE", final_tcp))
            else:
                write_info(t("OPT_REACT_CANCEL"))

            wait_for_key()

        elif user_choice == "4":
            write_box_title(t("OPT3_TITLE"))
            write_info(t("OPT3_INFO1"))
            write_info(t("OPT3_INFO2"))
            print("")
            write_warning(t("OPT3_WARN_24"))
            print("")

            new_ssid = read_input(t("OPT3_PROMPT_SSID"))
            if not new_ssid:
                write_warning(t("OPT3_NO_SSID"))
                time.sleep(2)
                continue

            new_key = read_input(t("OPT3_PROMPT_PASS"))
            if not new_key:
                write_warning(t("OPT3_NO_PASS"))
                time.sleep(2)
                continue

            raw_custom_dns = read_input(t("OPT3_PROMPT_DNS"))
            custom_dns = raw_custom_dns.strip() if raw_custom_dns else ""

            if custom_dns:
                if not test_is_valid_ipv4(custom_dns):
                    display_val = custom_dns[:15] + "..." if len(custom_dns) > 15 else custom_dns
                    write_error(t("ERR_INVALID_IP", display_val))
                    wait_for_key()
                    continue

            print("")
            write_warning(t("OPT3_SUMMARY"))
            print(f"{COLOR_YELLOW}{t('OPT3_SUM_SSID', new_ssid)}{COLOR_RESET}")
            print(f"{COLOR_YELLOW}{t('OPT3_SUM_PASS')}{COLOR_RESET}")
            if custom_dns:
                print(f"{COLOR_YELLOW}{t('OPT3_SUM_DNS', custom_dns)}{COLOR_RESET}")
            write_info(t("OPT3_NOTE"))
            print("")

            confirm = read_input(t("OPT3_CONFIRM_ASK"))

            if is_confirmed(confirm):
                print("")
                write_info(t("OPT3_START"))
                wifi_cmds = [f"AT+WSSSID={new_ssid}", f"AT+WSKEY=WPA2PSK,AES,{new_key}"]
                if custom_dns:
                    wifi_cmds.append(f"AT+WSDNS={custom_dns}")
                else:
                    wifi_cmds.append("AT+WANN=DHCP")
                wifi_cmds.append("AT+Z")

                invoke_foobot_at_session(target_ip, wifi_cmds, show_verbose=True)

                write_success(t("OPT3_DONE_1"))
                write_info(t("OPT3_DONE_2", new_ssid))
                write_info(t("OPT3_DONE_3"))
            else:
                write_info(t("OPT3_CANCELLED"))

            wait_for_key()

        elif user_choice == "5":
            write_box_title(t("OPT4_TITLE"))
            write_info(t("OPT4_INFO"))
            print(f"{COLOR_GREEN}{t('OPT4_WANN')}{COLOR_RESET}")
            print(f"{COLOR_GREEN}{t('OPT4_DNS')}{COLOR_RESET}")
            print("")
            write_warning(t("OPT4_CONFIRM_HEAD"))

            confirm = read_input(t("OPT4_CONFIRM_ASK"))

            if is_confirmed(confirm):
                print("")
                write_info(t("OPT4_START"))
                cmd_restore_wann = "AT+WANN=DHCP"
                cmd_restore_dns  = f"AT+WSDNS={FOOBOT_ORIGINAL_DNS}"
                restore_session = invoke_foobot_at_session(target_ip, [cmd_restore_wann, cmd_restore_dns, "AT+WANN", "AT+WSDNS", "AT+Z"], show_verbose=True)

                if "+ok" in restore_session.get(cmd_restore_dns, ""):
                    write_success(t("OPT4_OK_1"))
                    write_success(t("OPT4_OK_2", restore_session.get("AT+WANN", "N/D")))
                    write_success(t("OPT4_OK_3", restore_session.get("AT+WSDNS", "N/D")))
                    write_success(t("OPT4_OK_4"))
                else:
                    write_error(t("OPT4_ERR", restore_session.get(cmd_restore_dns, "N/D")))
            else:
                write_info(t("OPT4_CANCELLED"))

            wait_for_key()

        elif user_choice == "6":
            write_box_title(t("OPT5_TITLE"))
            write_info(t("OPT5_INFO"))

            confirm = read_input(t("OPT5_CONFIRM_ASK"))

            if is_confirmed(confirm):
                print("")
                invoke_foobot_at_session(target_ip, ["AT+Z"], show_verbose=True)
                write_success(t("OPT5_DONE"))
            else:
                write_info(t("OPT5_CANCELLED"))

            wait_for_key()

        elif user_choice == "7":
            write_info(t("OPT6_EXIT"))
            time.sleep(1)
            break

        else:
            write_warning(t("MENU_INVALID", user_choice))
            time.sleep(1)


if __name__ == "__main__":
    main()
