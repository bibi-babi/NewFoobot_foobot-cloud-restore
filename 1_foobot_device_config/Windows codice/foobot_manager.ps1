<#
================================================================================
  NAME:         foobot_manager.ps1
  PROJECT:      NewFoobot (Open Source Environmental Monitoring)
  AUTHOR:       NewFoobot Project / Barbara Lerici
  LANGUAGES:    Italiano (IT) & English (EN) - Auto-detected + Interactive Choice
  COMPATIBILITY: Windows 10, Windows 11, Windows Server (PowerShell 5.1 / 7+)
  LICENSE:      MIT (Free and Open Source Software)

  DESCRIZIONE / DESCRIPTION:
  Utilità di gestione di rete universale bilingue (IT/EN) per i proprietari del
  dispositivo "Foobot Air Quality Monitor" (Airboxlab) tramite porta UDP 48899.
================================================================================
#>

[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

# Costanti del Protocollo Foobot Hi-Flying HF-LPB100
Set-Variable -Name FOOBOT_UDP_PORT -Value 48899 -Option ReadOnly
Set-Variable -Name FOOBOT_HOTSPOT_IP -Value "10.10.100.254" -Option ReadOnly
Set-Variable -Name FOOBOT_ORIGINAL_DNS -Value "10.10.100.254" -Option ReadOnly
Set-Variable -Name PROTOCOL_HANDSHAKE -Value "HF-A11ASSISTHREAD" -Option ReadOnly
Set-Variable -Name PROTOCOL_ENTER_AT -Value "+ok" -Option ReadOnly

# Lingua attiva globale ('IT' oppure 'EN')
$script:CurrentLang = "EN"

# Dizionario Bilingue Completo (IT / EN)
$script:I18N = @{
    "IT" = @{
        "BANNER_TITLE"       = "                 STRUMENTO DI GESTIONE E MODIFICA FOOBOT                       "
        "BANNER_SUB"         = "                    Configurazione Universale e Aperta                          "
        "LOCAL_NOTICE_1"     = "Questo programma opera unicamente all'interno della tua rete locale."
        "LOCAL_NOTICE_2"     = "Nessun indirizzo IP o credenziale e memorizzato nel codice sorgente."
        "DISCOVERY_TITLE"    = "RICERCA DISPOSITIVO FOOBOT IN CORSO"
        "DISC_PHASE1"        = "Fase 1: Invio segnale broadcast UDP (255.255.255.255:48899) sulla rete LAN..."
        "DISC_FOUND_LAN"     = "Foobot rilevato sulla RETE LOCALE (LAN)!"
        "DISC_CURR_IP"       = "   - Indirizzo IP Attuale : "
        "DISC_MAC"           = "   - Indirizzo MAC Rete   : "
        "DISC_MID"           = "   - Codice Seriale (MID) : "
        "DISC_PHASE2"        = "Fase 2: Nessuna risposta su LAN. Verifica Hotspot di fabbrica (10.10.100.254:48899)..."
        "DISC_FOUND_HOT"     = "Foobot rilevato in modalita HOTSPOT DI FABBRICA (10.10.100.254)!"
        "DISC_MODE_HOT"      = "   - Modalita Operativa   : Hotspot / Access Point"
        "DISC_GW_IP"         = "   - Indirizzo IP Gateway : "
        "DISC_NOT_FOUND"     = "Nessun Foobot rilevato automaticamente (ne su LAN, ne su Hotspot)."
        "DISC_GUIDE_HEAD"    = "GUIDA RAPIDA DI COLLEGAMENTO:"
        "DISC_GUIDE_1"       = " 1. Se hai cambiato router o Wi-Fi, metti il Foobot a TESTA IN GIU per circa 5 secondi."
        "DISC_GUIDE_2"       = "    I LED lampeggeranno e il Foobot creera una rete Wi-Fi ad-hoc (es. Foobot_xxxx o HF-LPB100)."
        "DISC_GUIDE_3"       = " 2. Connetti il tuo computer/notebook a quella rete Wi-Fi, poi riesegui questo script."
        "DISC_GUIDE_4"       = " 3. Se il Foobot e gia connesso al tuo Wi-Fi, verifica che il PC sia sulla stessa rete 2.4 GHz."
        "DISC_MANUAL_PROMPT" = "Se conosci l'indirizzo IP del Foobot, inseriscilo ora (oppure premi INVIO per uscire)"
        "ERR_INVALID_IP"     = "ERRORE DI VALIDAZIONE: ========== '{0}' non e un indirizzo IPv4 valido. =========="
        "ERR_IP_FORMAT"      = "Un indirizzo IP deve essere composto da 4 numeri separati da punti (da 0.0.0.0 a 255.255.255.255)."
        "ERR_NO_DEVICE"      = "Operazione terminata: nessun dispositivo Foobot collegato o raggiungibile."
        "PRESS_KEY_EXIT"     = "Premi un tasto per uscire..."
        "PRESS_KEY_MENU"     = "Premi un tasto per tornare al menu..."
        "SAFETY_BLOCK_1"     = "BLOCCO DI SICUREZZA ATTIVO: Il comando '{0}' e bloccato per proteggere il dispositivo."
        "SAFETY_BLOCK_2"     = "Il comando rientra nella blacklist di protezione hardware/firmware del Foobot."
        "AT_STEP1"           = "[Fase 1/3] Apertura canale UDP persistente (Porta locale PC: {0} -> Foobot: {1}:48899)"
        "AT_WAKE_SEND"       = "           Invio pacchetto di risveglio (Wakeup): 'HF-A11ASSISTHREAD'..."
        "AT_WAKE_RECV"       = "           Risposta ricevuta dal Foobot: '{0}'"
        "AT_WAKE_ERR"        = "Nessuna risposta al pacchetto di risveglio (HF-A11ASSISTHREAD). Verificare che il Foobot sia acceso."
        "AT_STEP2"           = "[Fase 2/3] Sblocco sessione comandi AT: invio conferma '+ok'..."
        "AT_STEP3"           = "[Fase 3/3] Sessione AT sbloccata. Esecuzione comandi sul modulo Foobot..."
        "AT_CMD_SEND"        = "           Invio comando: '{0}\r' ..."
        "AT_CMD_RETRY"       = "           Nuovo tentativo ({0}/3) per '{1}\r' ..."
        "AT_CMD_RECV"        = "           Risposta dal Foobot per '{0}': '{1}'"
        "AT_CMD_TIMEOUT"     = "           Nessuna risposta entro il tempo limite per '{0}' (TIMEOUT)"
        "AT_WAIT_PING"       = "           [Attesa fino a 5s] Risoluzione DNS e test raggiungibilita verso il server in corso..."
        "AT_WAIT_TCP_OFF"    = "           [Attesa 2.5s] Chiusura ordinata del vecchio socket TCP Cloud in corso..."
        "AT_WAIT_SOCK_NONE"  = "           [Attesa 2.0s] Azzeramento buffer del canale Socket B nel modulo Wi-Fi..."
        "AT_WAIT_SOCK_TCP"   = "           [Attesa 2.5s] Inizializzazione nuovo endpoint TCP (porta 1883)..."
        "AT_WAIT_TCP_ON"     = "           [Attesa 2.5s] Negoziazione handshake TCP col server Bridge in corso..."
        "AT_WAIT_RETRY_5S"   = "           [Attesa 5s scaduta] Nessuna risposta immediata dal Foobot, rinnovo sessione AT..."
        "WAIT_FINAL_TCPLKB"  = "[Attesa 5s] Stabilizzazione finale del collegamento TCP Cloud prima della rilettura stato..."
        "WAIT_REBOOT_STEP"   = "           [Attesa in corso: {0}s / {1}s] Riavvio modulo Wi-Fi e negoziazione IP col router..."
        "AT_Z_OUTCOME"       = "           Esito 'AT+Z': {0}"
        "AT_CLOSE"           = "[Chiusura] Canale UDP chiuso correttamente."
        "MENU_TITLE"         = "MENU PRINCIPALE DI CONTROLLO - FOOBOT ({0} - Modo: {1})"
        "MENU_OPT_1"         = "Leggi la configurazione attuale del Foobot (SOLO LETTURA - 100% SICURO)"
        "MENU_OPT_2"         = "Configura server DNS personalizzato (NextDNS, Pi-hole, IP locale)"
        "MENU_OPT_3"         = "Riattivazione dopo sospensione corrente con lo stesso IP registrato in precedenza"
        "MENU_OPT_4"         = "Configura Nuova Rete Wi-Fi di Casa (Pairing SSID e Password)"
        "MENU_OPT_5"         = "Ripristina le impostazioni DNS e DHCP originali di fabbrica (10.10.100.254)"
        "MENU_OPT_6"         = "Riavvia il modulo Wi-Fi del Foobot (AT+Z)"
        "MENU_OPT_7"         = "Esci dal programma"
        "MENU_PROMPT"        = "Digita il numero dell'operazione desiderata [1-7]"
        "MENU_INVALID"       = "Scelta '{0}' non valida. Selezionare un'opzione tra 1 e 7."
        "OPT1_TITLE"         = "LETTURA REGISTRI HARDWARE E FIRMWARE (OPERAZIONE SICURA)"
        "OPT1_START"         = "Avvio sessione di interrogazione dei registri interni del Foobot..."
        "OPT1_SUMMARY"       = "RIEPILOGO DIAGNOSTICA FOOBOT:"
        "OPT1_FW"            = "  - Versione Firmware Modulo Wi-Fi : "
        "OPT1_MID"           = "  - Seriale Macchina (Device ID)   : "
        "OPT1_SSID"          = "  - Rete Wi-Fi Connessa (SSID)     : "
        "OPT1_WSLQ"          = "  - Qualita del Segnale Wi-Fi      : "
        "OPT1_WANN"          = "  - Parametri IP Rete (WANN)       : "
        "OPT1_WSDNS"         = "  - Server DNS Configurato (WSDNS) : "
        "OPT1_SOCKB"         = "  - Canale MQTT di Fabbrica        : "
        "OPT1_TCPLKB"        = "  - Connessione TCP Cloud (TCPLKB) : "
        "OPT1_DNS_CHECK"     = "VERIFICA REALE RISOLUZIONE DNS E DESTINAZIONE CLOUD:"
        "OPT1_DNS_AWS_WARN"  = " [ATTENZIONE: Punta ancora al vecchio server AWS Foobot!]"
        "OPT1_DNS_AWS_MSG"   = "Il DNS {0} non sta reindirizzando broker-gw-nc.foobot.io al tuo Bridge (controlla IP associato su NextDNS)."
        "OPT1_DNS_OK_LABEL"  = "  - IP Reale Destinazione Bridge   : "
        "OPT1_DNS_OK_MSG"    = "{0} (Riscrittura DNS attiva correttamente)"
        "OPT1_PORT_LABEL"    = "  - Stato Porta 1883 sul Bridge    : "
        "OPT1_PORT_OPEN"     = "APERTA E FUNZIONANTE ({0}:1883)"
        "OPT1_PORT_CLOSED"   = "NON RAGGIUNGIBILE ({0}:1883)"
        "OPT1_PORT_ERR"      = "ERRORE CONNESSIONE ({0}:1883)"
        "OPT1_DNS_UNREACH"   = "Impossibile interrogare il server DNS {0}"
        "OPT1_DONE"          = "Lettura completata. I valori mostrati sopra rappresentano lo stato attuale della memoria."
        "OPT2_TITLE"         = "MODIFICA SERVER DNS DEL FOOBOT (REINDIRIZZAMENTO TELEMETRIA)"
        "OPT2_INFO1"         = "Questa operazione imposta il server DNS interrogato dal Foobot e fissa l'assetto IP"
        "OPT2_INFO2"         = "(modalita static) affinche il DHCP del router non sovrascriva il DNS personalizzato."
        "OPT2_ORIG_DNS"      = "Valore DNS originale di fabbrica: 10.10.100.254"
        "OPT2_PROMPT_IP"     = "Inserisci l'indirizzo IP del Server DNS desiderato (es. NextDNS 45.90.28.xxx o IP router/Pi-hole)"
        "OPT2_CANCEL_EMPTY"  = "Nessun indirizzo inserito. Operazione annullata."
        "OPT2_CONFIRM_HEAD"  = "CONFERMA DI SICUREZZA (OPERAZIONE REVERSIBILE):"
        "OPT2_CONFIRM_TARGET"= "Stai per impostare il DNS del Foobot su: '{0}'"
        "OPT2_CONFIRM_NOTE1" = "NOTA BENE: Questa modifica e totalmente reversibile in qualsiasi momento."
        "OPT2_CONFIRM_NOTE2" = "Se vorrai ripristinare il Foobot ai valori originali di fabbrica (DHCP + DNS originale),"
        "OPT2_CONFIRM_NOTE3" = "ti bastera avviare nuovamente questo script e scegliere l'Opzione 5 dal menu principale."
        "OPT2_CONFIRM_ASK"   = "Vuoi procedere con l'aggiornamento del server DNS? [S = Si / N = No]"
        "OPT2_PRE_READ"      = "Fase Preliminare: Lettura configurazione di rete attuale del Foobot (AT+WANN)..."
        "OPT2_NEW_IP"        = "Indirizzo IP assegnato dal router: {0}"
        "OPT2_LAN_DETECTED"  = "Parametri LAN rilevati: IP={0} | Mask={1} | Gateway={2}"
        "OPT2_LAN_DEDUCED"   = "Parametri LAN dedotti da IP attivo: IP={0} | Mask=255.255.255.0 | Gateway={1}"
        "OPT2_STATIC_APPLY"  = "Verra applicato '{0}' per impedire al router di sovrascrivere il DNS."
        "OPT2_WRITE_START"   = "Avvio procedura lineare di scrittura e riconnessione sul Foobot..."
        "OPT2_STEP1"         = "[Passo 1/2] Fissaggio configurazione IP statica in Flash NVRAM..."
        "OPT2_WANN_SAVED"    = "Parametri WANN memorizzati: {0}"
        "OPT2_STEP2"         = "[Passo 2/2] Scrittura DNS ({0}), risoluzione dominio e aggancio diretto al Bridge..."
        "OPT2_OK_1"          = "Modifica accettata dal Foobot! (Risposta scrittura DNS: {0})"
        "OPT2_OK_2"          = "Verifica registro DNS attivo        : {0}"
        "OPT2_OK_3"          = "Verifica canale MQTT configurato    : {0}"
        "OPT2_OK_4"          = "Stato connessione socket cloud      : {0}"
        "OPT2_OK_5"          = "OPERAZIONE COMPLETATA! Il Foobot ha interrogato il DNS ed e collegato al Bridge."
        "OPT2_ERR_WRITE"     = "Il Foobot non ha confermato la modifica. Risposta ricevuta: {0}"
        "OPT2_CANCELLED"     = "Modifica annullata. Nessun parametro e stato toccato."
        "OPT_REACT_TITLE"    = "RIATTIVAZIONE DOPO SOSPENSIONE CORRENTE (CON LO STESSO IP REGISTRATO)"
        "OPT_REACT_DESC_HEAD"= "COSA FA QUESTA PROCEDURA E PERCHE SERVE:"
        "OPT_REACT_DESC_1"   = "Quando manca la corrente elettrica, il Foobot si riaccende in pochi secondi mentre il router"
        "OPT_REACT_DESC_2"   = "di casa impiega alcuni minuti. Al riavvio il router azzera la lista dei dispositivi collegati"
        "OPT_REACT_DESC_3"   = "e blocca l'uscita su Internet del Foobot rimasto con IP statico (lasciando TCPLKB su 'off')."
        "OPT_REACT_STEPS_HD" = "OPERAZIONI ESEGUITE IN AUTOMATICO DAL PROGRAMMA:"
        "OPT_REACT_STEP_1"   = " 1. Legge dalla memoria del Foobot l'IP del Server DNS gia registrato in precedenza."
        "OPT_REACT_STEP_2"   = " 2. Attiva temporaneamente il DHCP e riavvia il Foobot (AT+Z per 16 secondi) affinche"
        "OPT_REACT_STEP_3"   = "    il router di casa registri e autorizzi nuovamente il sensore sulla rete locale."
        "OPT_REACT_STEP_4"   = " 3. Rileva l'IP locale assegnato dal router e lo blocca nuovamente come IP statico."
        "OPT_REACT_STEP_5"   = " 4. Ripristina lo stesso IP DNS salvato in precedenza e riapre il collegamento Cloud (TCPLKB=on)."
        "OPT_REACT_SAVED_DNS"= "  -> Server DNS registrato rilevato nel Foobot : {0}"
        "OPT_REACT_NO_DNS"   = "ATTENZIONE: Nel Foobot e presente il DNS di fabbrica ({0}). Configura prima il DNS con l'Opzione 2."
        "OPT_REACT_CONFIRM"  = "Confermi l'avvio della riattivazione post-sospensione corrente? [S = Si / N = No]"
        "OPT_REACT_WAIT"     = "Fase 1/3: Passaggio a DHCP e riavvio del Foobot per registrazione sul router (attesa 16s)..."
        "OPT_REACT_DISC"     = "Fase 2/3: Ricerca del Foobot sulla rete locale per verificare l'IP assegnato dal router..."
        "OPT_REACT_RESYNC"   = "Fase 3/3: Fissaggio IP statico, riattivazione DNS ({0}) e apertura connessione Cloud..."
        "OPT_REACT_DONE"     = "RIATTIVAZIONE COMPLETATA! Il Foobot e nuovamente connesso al Cloud (TCPLKB: {0})."
        "OPT_REACT_CANCEL"   = "Riattivazione annullata. Nessun parametro modificato."
        "OPT3_TITLE"         = "CONFIGURAZIONE NUOVA RETE WI-FI (PAIRING SSID & PASSWORD)"
        "OPT3_INFO1"         = "Questa procedura consente di associare il Foobot a una nuova rete Wi-Fi di casa,"
        "OPT3_INFO2"         = "sostituendo completamente la vecchia app ufficiale Airboxlab dismessa."
        "OPT3_WARN_24"       = "NOTA: Il modulo Wi-Fi Foobot HF-LPB100 supporta reti a 2.4 GHz con sicurezza WPA2-PSK."
        "OPT3_PROMPT_SSID"   = "Inserisci il nome esatto della tua rete Wi-Fi (SSID a 2.4 GHz)"
        "OPT3_NO_SSID"       = "Nome rete non inserito. Operazione annullata."
        "OPT3_PROMPT_PASS"   = "Inserisci la Password della tua rete Wi-Fi WPA2"
        "OPT3_NO_PASS"       = "Password non inserita. Operazione annullata."
        "OPT3_PROMPT_DNS"    = "Server DNS opzionale da impostare subito (premi INVIO per mantenere attuale)"
        "OPT3_SUMMARY"       = "RIEPILOGO PARAMETRI DA INVIARE AL FOOBOT (OPERAZIONE REVERSIBILE):"
        "OPT3_SUM_SSID"      = "  - Rete Wi-Fi (SSID) : {0}"
        "OPT3_SUM_PASS"      = "  - Chiave di Rete    : ********"
        "OPT3_SUM_DNS"       = "  - Server DNS Nuovo  : {0}"
        "OPT3_NOTE"          = "NOTA BENE: Potrai cambiare nuovamente rete o DNS in qualsiasi momento da questo menu."
        "OPT3_CONFIRM_ASK"   = "Vuoi scrivere questa configurazione nel modulo Foobot? [S = Si / N = No]"
        "OPT3_START"         = "Avvio sequenza di programmazione Wi-Fi sul Foobot..."
        "OPT3_DONE_1"        = "CONFIGURAZIONE INVIATA CON SUCCESSO!"
        "OPT3_DONE_2"        = "Il Foobot si sta riavviando e si colleghera alla rete '{0}'."
        "OPT3_DONE_3"        = "Entro 30-60 secondi i LED del sensore diventeranno stabili (blu o arancio)."
        "OPT3_CANCELLED"     = "Configurazione Wi-Fi annullata. Nessuna modifica salvata."
        "OPT4_TITLE"         = "RIPRISTINO IMPOSTAZIONI ORIGINALI DI FABBRICA"
        "OPT4_INFO"          = "Questa operazione reimposta il Foobot ai valori di rete originali di fabbrica:"
        "OPT4_WANN"          = "  -> Modalita IP (WANN) : DHCP dinamico"
        "OPT4_DNS"           = "  -> Server DNS (WSDNS) : 10.10.100.254"
        "OPT4_CONFIRM_HEAD"  = "CONFERMA DI SICUREZZA:"
        "OPT4_CONFIRM_ASK"   = "Vuoi ripristinare il Foobot alle impostazioni originali di fabbrica? [S = Si / N = No]"
        "OPT4_START"         = "Avvio ripristino impostazioni DNS e DHCP di fabbrica..."
        "OPT4_OK_1"          = "Registri di fabbrica ripristinati con successo!"
        "OPT4_OK_2"          = "  - Verifica WANN : {0}"
        "OPT4_OK_3"          = "  - Verifica DNS  : {0}"
        "OPT4_OK_4"          = "OPERAZIONE COMPLETATA! Il dispositivo e tornato al 100% nel suo stato originale di fabbrica."
        "OPT4_ERR"           = "Impossibile ripristinare il registro. Risposta: {0}"
        "OPT4_CANCELLED"     = "Ripristino annullato. Il dispositivo non e stato modificato."
        "OPT5_TITLE"         = "RIAVVIO DEL MODULO WI-FI FOOBOT (SOLO COMANDO AT+Z)"
        "OPT5_INFO"          = "Invia esclusivamente il comando di riavvio hardware (AT+Z) al modulo Wi-Fi del Foobot."
        "OPT5_CONFIRM_ASK"   = "Desideri inviare il comando di riavvio AT+Z al Foobot? [S = Si / N = No]"
        "OPT5_DONE"          = "OPERAZIONE COMPLETATA! Comando di riavvio (AT+Z) inviato al Foobot."
        "OPT5_CANCELLED"     = "Riavvio annullato."
        "OPT6_EXIT"          = "Chiusura del programma. Arrivederci!"
    }
    "EN" = @{
        "BANNER_TITLE"       = "                 FOOBOT MANAGEMENT & CONFIGURATION TOOL                        "
        "BANNER_SUB"         = "                     Universal Open Source Utility                             "
        "LOCAL_NOTICE_1"     = "This program operates exclusively within your local network."
        "LOCAL_NOTICE_2"     = "No IP addresses or credentials are hardcoded in the source code."
        "DISCOVERY_TITLE"    = "SEARCHING FOR FOOBOT DEVICE ON THE NETWORK"
        "DISC_PHASE1"        = "Step 1: Sending UDP broadcast signal (255.255.255.255:48899) on LAN..."
        "DISC_FOUND_LAN"     = "Foobot detected on your LOCAL NETWORK (LAN)!"
        "DISC_CURR_IP"       = "   - Current IP Address  : "
        "DISC_MAC"           = "   - Network MAC Address : "
        "DISC_MID"           = "   - Serial Number (MID) : "
        "DISC_PHASE2"        = "Step 2: No response on LAN. Checking Factory Hotspot (10.10.100.254:48899)..."
        "DISC_FOUND_HOT"     = "Foobot detected in FACTORY HOTSPOT mode (10.10.100.254)!"
        "DISC_MODE_HOT"      = "   - Operating Mode      : Hotspot / Access Point"
        "DISC_GW_IP"         = "   - Gateway IP Address  : "
        "DISC_NOT_FOUND"     = "No Foobot automatically detected (neither on LAN nor on Hotspot)."
        "DISC_GUIDE_HEAD"    = "QUICK CONNECTION GUIDE:"
        "DISC_GUIDE_1"       = " 1. If you changed your router or Wi-Fi, turn your Foobot UPSIDE DOWN for 5 seconds."
        "DISC_GUIDE_2"       = "    The LEDs will blink and Foobot will broadcast a Wi-Fi network (e.g. Foobot_xxxx or HF-LPB100)."
        "DISC_GUIDE_3"       = " 2. Connect your computer/laptop to that Wi-Fi network, then run this script again."
        "DISC_GUIDE_4"       = " 3. If Foobot is already on your Wi-Fi, ensure your PC is on the same 2.4 GHz network."
        "DISC_MANUAL_PROMPT" = "If you know your Foobot's IP address, enter it now (or press ENTER to exit)"
        "ERR_INVALID_IP"     = "VALIDATION ERROR: ========== '{0}' is not a valid IPv4 address. =========="
        "ERR_IP_FORMAT"      = "An IP address must consist of 4 numbers separated by dots (from 0.0.0.0 to 255.255.255.255)."
        "ERR_NO_DEVICE"      = "Operation ended: no Foobot device connected or reachable."
        "PRESS_KEY_EXIT"     = "Press any key to exit..."
        "PRESS_KEY_MENU"     = "Press any key to return to the menu..."
        "SAFETY_BLOCK_1"     = "SAFETY BLOCK ACTIVE: Command '{0}' is blocked to protect the hardware."
        "SAFETY_BLOCK_2"     = "This command is on the Foobot hardware/firmware protection blacklist."
        "AT_STEP1"           = "[Step 1/3] Opening persistent UDP channel (Local PC Port: {0} -> Foobot: {1}:48899)"
        "AT_WAKE_SEND"       = "           Sending wakeup packet: 'HF-A11ASSISTHREAD'..."
        "AT_WAKE_RECV"       = "           Response received from Foobot: '{0}'"
        "AT_WAKE_ERR"        = "No response to wakeup packet (HF-A11ASSISTHREAD). Please check that Foobot is powered on."
        "AT_STEP2"           = "[Step 2/3] Unlocking AT command session: sending confirmation '+ok'..."
        "AT_STEP3"           = "[Step 3/3] AT session unlocked. Executing commands on Foobot module..."
        "AT_CMD_SEND"        = "           Sending command: '{0}\r' ..."
        "AT_CMD_RETRY"       = "           Retrying ({0}/3) for '{1}\r' ..."
        "AT_CMD_RECV"        = "           Response from Foobot for '{0}': '{1}'"
        "AT_CMD_TIMEOUT"     = "           No response within time limit for '{0}' (TIMEOUT)"
        "AT_WAIT_PING"       = "           [Wait up to 5s] Resolving DNS and testing server reachability..."
        "AT_WAIT_TCP_OFF"    = "           [Wait 2.5s] Gracefully closing previous Cloud TCP socket..."
        "AT_WAIT_SOCK_NONE"  = "           [Wait 2.0s] Clearing Socket B channel buffer in Wi-Fi module..."
        "AT_WAIT_SOCK_TCP"   = "           [Wait 2.5s] Initializing new TCP endpoint (port 1883)..."
        "AT_WAIT_TCP_ON"     = "           [Wait 2.5s] Negotiating TCP handshake with Bridge server..."
        "AT_WAIT_RETRY_5S"   = "           [5s Wait elapsed] No immediate response from Foobot, refreshing AT session..."
        "WAIT_FINAL_TCPLKB"  = "[Wait 5s] Final stabilization of Cloud TCP link before re-reading status..."
        "WAIT_REBOOT_STEP"   = "           [Waiting: {0}s / {1}s] Wi-Fi module reboot and router IP negotiation in progress..."
        "AT_Z_OUTCOME"       = "           Result 'AT+Z': {0}"
        "AT_CLOSE"           = "[Closing] UDP channel closed properly."
        "MENU_TITLE"         = "MAIN CONTROL MENU - FOOBOT ({0} - Mode: {1})"
        "MENU_OPT_1"         = "Read current Foobot configuration (READ ONLY - 100% SAFE)"
        "MENU_OPT_2"         = "Configure custom DNS server (NextDNS, Pi-hole, Local IP)"
        "MENU_OPT_3"         = "Reactivation after power outage with the previously saved IP"
        "MENU_OPT_4"         = "Configure New Home Wi-Fi Network (SSID & Password Pairing)"
        "MENU_OPT_5"         = "Restore original factory DNS & DHCP settings (10.10.100.254)"
        "MENU_OPT_6"         = "Reboot Foobot Wi-Fi module (AT+Z)"
        "MENU_OPT_7"         = "Exit program"
        "MENU_PROMPT"        = "Enter the number of the desired operation [1-7]"
        "MENU_INVALID"       = "Invalid choice '{0}'. Please select an option between 1 and 7."
        "OPT1_TITLE"         = "READING HARDWARE & FIRMWARE REGISTERS (SAFE OPERATION)"
        "OPT1_START"         = "Starting query session for Foobot internal registers..."
        "OPT1_SUMMARY"       = "FOOBOT DIAGNOSTIC SUMMARY:"
        "OPT1_FW"            = "  - Wi-Fi Module Firmware Version  : "
        "OPT1_MID"           = "  - Device Serial Number (MID)     : "
        "OPT1_SSID"          = "  - Connected Wi-Fi Network (SSID) : "
        "OPT1_WSLQ"          = "  - Wi-Fi Signal Quality           : "
        "OPT1_WANN"          = "  - Network IP Settings (WANN)     : "
        "OPT1_WSDNS"         = "  - Configured DNS Server (WSDNS)  : "
        "OPT1_SOCKB"         = "  - Factory MQTT Channel           : "
        "OPT1_TCPLKB"        = "  - Cloud TCP Connection (TCPLKB)  : "
        "OPT1_DNS_CHECK"     = "LIVE DNS RESOLUTION & CLOUD DESTINATION CHECK:"
        "OPT1_DNS_AWS_WARN"  = " [WARNING: Still pointing to old dead AWS Foobot server!]"
        "OPT1_DNS_AWS_MSG"   = "DNS {0} is not redirecting broker-gw-nc.foobot.io to your Bridge (check linked IP on NextDNS)."
        "OPT1_DNS_OK_LABEL"  = "  - Actual Bridge Destination IP   : "
        "OPT1_DNS_OK_MSG"    = "{0} (DNS rewrite active properly)"
        "OPT1_PORT_LABEL"    = "  - Bridge Port 1883 Status        : "
        "OPT1_PORT_OPEN"     = "OPEN AND WORKING ({0}:1883)"
        "OPT1_PORT_CLOSED"   = "UNREACHABLE ({0}:1883)"
        "OPT1_PORT_ERR"      = "CONNECTION ERROR ({0}:1883)"
        "OPT1_DNS_UNREACH"   = "Unable to query DNS server {0}"
        "OPT1_DONE"          = "Read completed. The values above represent the current memory state."
        "OPT2_TITLE"         = "MODIFY FOOBOT DNS SERVER (TELEMETRY REDIRECTION)"
        "OPT2_INFO1"         = "This operation sets the DNS server queried by Foobot and locks the IP settings"
        "OPT2_INFO2"         = "(static mode) so the router's DHCP cannot overwrite your custom DNS."
        "OPT2_ORIG_DNS"      = "Original factory DNS value: 10.10.100.254"
        "OPT2_PROMPT_IP"     = "Enter the desired DNS Server IP address (e.g. NextDNS 45.90.28.xxx or Router/Pi-hole IP)"
        "OPT2_CANCEL_EMPTY"  = "No address entered. Operation cancelled."
        "OPT2_CONFIRM_HEAD"  = "SAFETY CONFIRMATION (REVERSIBLE OPERATION):"
        "OPT2_CONFIRM_TARGET"= "You are about to set Foobot's DNS to: '{0}'"
        "OPT2_CONFIRM_NOTE1" = "NOTE: This change is completely reversible at any time."
        "OPT2_CONFIRM_NOTE2" = "If you wish to restore Foobot to factory settings (DHCP + original DNS),"
        "OPT2_CONFIRM_NOTE3" = "simply run this script again and select Option 5 from the main menu."
        "OPT2_CONFIRM_ASK"   = "Do you want to proceed with updating the DNS server? [Y = Yes / N = No]"
        "OPT2_PRE_READ"      = "Preliminary Step: Reading current Foobot network configuration (AT+WANN)..."
        "OPT2_NEW_IP"        = "IP address assigned by router: {0}"
        "OPT2_LAN_DETECTED"  = "Detected LAN parameters: IP={0} | Mask={1} | Gateway={2}"
        "OPT2_LAN_DEDUCED"   = "Deduced LAN parameters from active IP: IP={0} | Mask=255.255.255.0 | Gateway={1}"
        "OPT2_STATIC_APPLY"  = "Will apply '{0}' to prevent the router from overwriting the DNS."
        "OPT2_WRITE_START"   = "Starting linear write and reconnection procedure on Foobot..."
        "OPT2_STEP1"         = "[Step 1/2] Locking static IP configuration in Flash NVRAM..."
        "OPT2_WANN_SAVED"    = "Stored WANN parameters: {0}"
        "OPT2_STEP2"         = "[Step 2/2] Writing DNS ({0}), resolving domain and connecting to Bridge..."
        "OPT2_OK_1"          = "Change accepted by Foobot! (DNS write response: {0})"
        "OPT2_OK_2"          = "Active DNS register check           : {0}"
        "OPT2_OK_3"          = "Configured MQTT channel check       : {0}"
        "OPT2_OK_4"          = "Cloud socket connection status      : {0}"
        "OPT2_OK_5"          = "OPERATION COMPLETED! Foobot queried the DNS and is connected to your Bridge."
        "OPT2_ERR_WRITE"     = "Foobot did not confirm the change. Response received: {0}"
        "OPT2_CANCELLED"     = "Change cancelled. No parameters were modified."
        "OPT_REACT_TITLE"    = "REACTIVATION AFTER POWER OUTAGE (USING PREVIOUSLY SAVED IP)"
        "OPT_REACT_DESC_HEAD"= "WHAT THIS PROCEDURE DOES AND WHY IT IS NEEDED:"
        "OPT_REACT_DESC_1"   = "After a power outage, Foobot turns back on in a few seconds while your home router takes"
        "OPT_REACT_DESC_2"   = "several minutes. Upon rebooting, the router clears its connected device table and blocks"
        "OPT_REACT_DESC_3"   = "Internet access for Foobot's static IP (leaving the Cloud connection TCPLKB on 'off')."
        "OPT_REACT_STEPS_HD" = "AUTOMATIC STEPS PERFORMED BY THIS PROGRAM:"
        "OPT_REACT_STEP_1"   = " 1. Reads the previously registered DNS Server IP from Foobot's internal memory."
        "OPT_REACT_STEP_2"   = " 2. Temporarily enables DHCP and reboots Foobot (AT+Z for 16 seconds) so your home"
        "OPT_REACT_STEP_3"   = "    router registers and re-authorizes the sensor on the local network."
        "OPT_REACT_STEP_4"   = " 3. Detects the local IP assigned by the router and locks it again as a static IP."
        "OPT_REACT_STEP_5"   = " 4. Reapplies the previously saved DNS IP and reopens the Cloud connection (TCPLKB=on)."
        "OPT_REACT_SAVED_DNS"= "  -> Previously saved DNS Server detected in Foobot : {0}"
        "OPT_REACT_NO_DNS"   = "WARNING: Factory DNS ({0}) is currently set in Foobot. Please configure your DNS with Option 2 first."
        "OPT_REACT_CONFIRM"  = "Do you confirm starting the post-power-outage reactivation? [Y = Yes / N = No]"
        "OPT_REACT_WAIT"     = "Step 1/3: Switching to DHCP and rebooting Foobot for router registration (waiting 16s)..."
        "OPT_REACT_DISC"     = "Step 2/3: Searching for Foobot on local network to verify the router-assigned IP..."
        "OPT_REACT_RESYNC"   = "Step 3/3: Locking static IP, restoring DNS ({0}) and opening Cloud connection..."
        "OPT_REACT_DONE"     = "REACTIVATION COMPLETED! Foobot is connected to the Cloud again (TCPLKB: {0})."
        "OPT_REACT_CANCEL"   = "Reactivation cancelled. No parameters were modified."
        "OPT3_TITLE"         = "CONFIGURE NEW HOME WI-FI NETWORK (SSID & PASSWORD PAIRING)"
        "OPT3_INFO1"         = "This procedure connects your Foobot to a new home Wi-Fi network,"
        "OPT3_INFO2"         = "completely replacing the discontinued official Airboxlab mobile app."
        "OPT3_WARN_24"       = "NOTE: The Foobot HF-LPB100 Wi-Fi module supports 2.4 GHz networks with WPA2-PSK security."
        "OPT3_PROMPT_SSID"   = "Enter the exact name of your Wi-Fi network (2.4 GHz SSID)"
        "OPT3_NO_SSID"       = "Network name not entered. Operation cancelled."
        "OPT3_PROMPT_PASS"   = "Enter your WPA2 Wi-Fi network Password"
        "OPT3_NO_PASS"       = "Password not entered. Operation cancelled."
        "OPT3_PROMPT_DNS"    = "Optional DNS server to set immediately (press ENTER to keep current)"
        "OPT3_SUMMARY"       = "SUMMARY OF PARAMETERS TO SEND TO FOOBOT (REVERSIBLE OPERATION):"
        "OPT3_SUM_SSID"      = "  - Wi-Fi Network (SSID) : {0}"
        "OPT3_SUM_PASS"      = "  - Network Password     : ********"
        "OPT3_SUM_DNS"       = "  - New DNS Server       : {0}"
        "OPT3_NOTE"          = "NOTE: You can change the network or DNS again at any time from this menu."
        "OPT3_CONFIRM_ASK"   = "Do you want to write this configuration to the Foobot module? [Y = Yes / N = No]"
        "OPT3_START"         = "Starting Wi-Fi programming sequence on Foobot..."
        "OPT3_DONE_1"        = "CONFIGURATION SENT SUCCESSFULLY!"
        "OPT3_DONE_2"        = "Foobot is rebooting and will connect to the '{0}' network."
        "OPT3_DONE_3"        = "Within 30-60 seconds the sensor LEDs will become solid (blue or orange)."
        "OPT3_CANCELLED"     = "Wi-Fi configuration cancelled. No changes saved."
        "OPT4_TITLE"         = "RESTORE ORIGINAL FACTORY SETTINGS"
        "OPT4_INFO"          = "This operation resets Foobot to its original factory network values:"
        "OPT4_WANN"          = "  -> IP Mode (WANN)     : Dynamic DHCP"
        "OPT4_DNS"           = "  -> DNS Server (WSDNS) : 10.10.100.254"
        "OPT4_CONFIRM_HEAD"  = "SAFETY CONFIRMATION:"
        "OPT4_CONFIRM_ASK"   = "Do you want to restore Foobot to original factory settings? [Y = Yes / N = No]"
        "OPT4_START"         = "Starting factory DNS and DHCP restore..."
        "OPT4_OK_1"          = "Factory registers restored successfully!"
        "OPT4_OK_2"          = "  - WANN Check : {0}"
        "OPT4_OK_3"          = "  - DNS Check  : {0}"
        "OPT4_OK_4"          = "OPERATION COMPLETED! The device is 100% back to its original factory state."
        "OPT4_ERR"           = "Unable to restore register. Response: {0}"
        "OPT4_CANCELLED"     = "Restore cancelled. The device was not modified."
        "OPT5_TITLE"         = "REBOOT FOOBOT WI-FI MODULE (AT+Z COMMAND ONLY)"
        "OPT5_INFO"          = "Sends exclusively the hardware reboot command (AT+Z) to Foobot's Wi-Fi module."
        "OPT5_CONFIRM_ASK"   = "Do you want to send the AT+Z reboot command to Foobot? [Y = Yes / N = No]"
        "OPT5_DONE"          = "OPERATION COMPLETED! Reboot command (AT+Z) sent to Foobot."
        "OPT5_CANCELLED"     = "Reboot cancelled."
        "OPT6_EXIT"          = "Closing program. Goodbye!"
    }
}

# Funzione di traduzione stringa con parametri opzionali
function T {
    param(
        [string]$Key,
        [object[]]$ArgsList = @()
    )
    $dict = $script:I18N[$script:CurrentLang]
    $raw = if ($dict.ContainsKey($Key)) { $dict[$Key] } else { $Key }
    if ($ArgsList.Count -gt 0) {
        return [string]::Format($raw, $ArgsList)
    }
    return $raw
}

# Verifica risposta affermativa (S in Italiano, Y o S in Inglese)
function Test-IsConfirmed {
    param([string]$InputStr)
    if ([string]::IsNullOrWhiteSpace($InputStr)) { return $false }
    $v = $InputStr.Trim().ToUpper()
    return ($v -eq "S" -or $v -eq "SI" -or $v -eq "Y" -or $v -eq "YES")
}

function Write-BoxTitle {
    param([string]$Title)
    Write-Host ""
    Write-Host "================================================================================" -ForegroundColor Cyan
    Write-Host "  $Title" -ForegroundColor White
    Write-Host "================================================================================" -ForegroundColor Cyan
    Write-Host ""
}

function Write-InfoMessage {
    param([string]$Message)
    Write-Host "[INFO] $Message" -ForegroundColor Gray
}

function Write-StepLog {
    param([string]$StepText)
    Write-Host "  --> $StepText" -ForegroundColor DarkCyan
}

function Write-SuccessMessage {
    param([string]$Message)
    Write-Host "[OK]   $Message" -ForegroundColor Green
}

function Write-WarningMessage {
    param([string]$Message)
    Write-Host "[WARN] $Message" -ForegroundColor Yellow
}

function Write-ErrorMessage {
    param([string]$Message)
    Write-Host "[ERR]  $Message" -ForegroundColor Red
}

function Test-IsValidIPv4 {
    param([string]$IPString)
    if ([string]::IsNullOrWhiteSpace($IPString)) { return $false }
    $trimmed = $IPString.Trim()
    if ($trimmed -notmatch '^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$') { return $false }
    $parsedIP = $null
    if ([System.Net.IPAddress]::TryParse($trimmed, [ref]$parsedIP)) {
        return ($parsedIP.AddressFamily -eq [System.Net.Sockets.AddressFamily]::InterNetwork)
    }
    return $false
}

function Clear-ConsoleInputBuffer {
    try {
        while ([System.Console]::KeyAvailable) {
            [void][System.Console]::ReadKey($true)
        }
    } catch {}
}

function Read-HostWithPadding {
    param([string]$Prompt)
    try {
        Write-Host "`n`n"
        $pos = $Host.UI.RawUI.CursorPosition
        $pos.Y = [Math]::Max(0, $pos.Y - 2)
        $Host.UI.RawUI.CursorPosition = $pos
    } catch {}
    return (Read-Host $Prompt)
}

# ==============================================================================
# SELEZIONE E CONFERMA LINGUA ALL'AVVIO (AUTO-DETECT + CONFERMA S/N)
# ==============================================================================
function Select-StartupLanguage {
    $osLang = ""
    try {
        $osLang = (Get-UICulture).TwoLetterISOLanguageName.ToLower()
    } catch {
        $osLang = "en"
    }

    $detectedLang = if ($osLang -eq "it") { "IT" } else { "EN" }

    while ($true) {
        Clear-Host
        Write-Host "================================================================================" -ForegroundColor Cyan
        Write-Host "  NEWFOOBOT - LANGUAGE SELECTION / SELEZIONE LINGUA" -ForegroundColor White
        Write-Host "================================================================================" -ForegroundColor Cyan
        Write-Host ""
        Write-Host "  Supported languages / Lingue supportate : " -NoNewline -ForegroundColor Gray
        Write-Host "English (EN) - Italiano (IT)" -ForegroundColor Yellow

        if ($detectedLang -eq "IT") {
            Write-Host "  Detected system language / Lingua rilevata : " -NoNewline -ForegroundColor Gray
            Write-Host "ITALIANO (IT)" -ForegroundColor Green
            Write-Host ""
            Clear-ConsoleInputBuffer
            $ans = Read-HostWithPadding "  Confermi la lingua ITALIANO? [S = Si (Invio) / N = No, passa a Inglese]"
            $cleanAns = if ($null -ne $ans) { $ans.Trim().ToUpper() } else { "" }

            if ($cleanAns -eq "" -or $cleanAns -eq "S" -or $cleanAns -eq "SI" -or $cleanAns -eq "Y" -or $cleanAns -eq "YES") {
                $script:CurrentLang = "IT"
                break
            } elseif ($cleanAns -eq "N" -or $cleanAns -eq "NO") {
                Write-Host ""
                Clear-ConsoleInputBuffer
                $ansEn = Read-HostWithPadding "  Vuoi impostare la lingua INGLESE (English)?Confermi? [Y/S = Yes (Enter) / N = No]"
                $cleanEn = if ($null -ne $ansEn) { $ansEn.Trim().ToUpper() } else { "" }
                if ($cleanEn -eq "" -or $cleanEn -eq "Y" -or $cleanEn -eq "YES" -or $cleanEn -eq "S" -or $cleanEn -eq "SI") {
                    $script:CurrentLang = "EN"
                    break
                }
            }
        } else {
            Write-Host "  Detected system language / Lingua rilevata : " -NoNewline -ForegroundColor Gray
            Write-Host "ENGLISH (EN)" -ForegroundColor Green
            Write-Host ""
            Clear-ConsoleInputBuffer
            $ans = Read-HostWithPadding "  Confirm ENGLISH language? [Y = Yes (Enter) / N = No, switch to Italiano]"
            $cleanAns = if ($null -ne $ans) { $ans.Trim().ToUpper() } else { "" }

            if ($cleanAns -eq "" -or $cleanAns -eq "Y" -or $cleanAns -eq "YES" -or $cleanAns -eq "S" -or $cleanAns -eq "SI") {
                $script:CurrentLang = "EN"
                break
            } elseif ($cleanAns -eq "N" -or $cleanAns -eq "NO") {
                Write-Host ""
                Clear-ConsoleInputBuffer
                $ansIt = Read-HostWithPadding "  Do you want to set ITALIAN (Italiano) language? Confirm? [S/Y = Si (Enter) / N = No]"
                $cleanIt = if ($null -ne $ansIt) { $ansIt.Trim().ToUpper() } else { "" }
                if ($cleanIt -eq "" -or $cleanIt -eq "S" -or $cleanIt -eq "SI" -or $cleanIt -eq "Y" -or $cleanIt -eq "YES") {
                    $script:CurrentLang = "IT"
                    break
                }
            }
        }
    }
}

function Send-DiscoveryBroadcast {
    param(
        [string]$IPAddress,
        [int]$Port = 48899,
        [int]$TimeoutMilliseconds = 5000
    )

    $socket = New-Object System.Net.Sockets.UdpClient(0)
    $socket.Client.ReceiveTimeout = $TimeoutMilliseconds
    $socket.Client.SendTimeout = $TimeoutMilliseconds

    try {
        if ($IPAddress -eq "255.255.255.255") {
            $socket.EnableBroadcast = $true
        }

        $targetEndpoint = New-Object System.Net.IPEndPoint([System.Net.IPAddress]::Parse($IPAddress), $Port)
        $payloadBytes = [System.Text.Encoding]::ASCII.GetBytes($PROTOCOL_HANDSHAKE)
        [void]$socket.Send($payloadBytes, $payloadBytes.Length, $targetEndpoint)

        $receivedEndpoint = New-Object System.Net.IPEndPoint([System.Net.IPAddress]::Any, 0)
        $responseBytes = $socket.Receive([ref]$receivedEndpoint)
        $replyStr = [System.Text.Encoding]::ASCII.GetString($responseBytes).Trim()

        try {
            $ackBytes = [System.Text.Encoding]::ASCII.GetBytes($PROTOCOL_ENTER_AT)
            [void]$socket.Send($ackBytes, $ackBytes.Length, $receivedEndpoint)
            Start-Sleep -Milliseconds 150
            $quitBytes = [System.Text.Encoding]::ASCII.GetBytes("AT+Q`r")
            [void]$socket.Send($quitBytes, $quitBytes.Length, $receivedEndpoint)
            Start-Sleep -Milliseconds 100
        } catch {}

        return $replyStr
    }
    catch {
        return "TIMEOUT"
    }
    finally {
        $socket.Close()
        $socket.Dispose()
    }
}

function Find-FoobotDevice {
    Write-BoxTitle (T "DISCOVERY_TITLE")
    Write-InfoMessage (T "DISC_PHASE1")

    $lanResponse = Send-DiscoveryBroadcast -IPAddress "255.255.255.255" -Port $FOOBOT_UDP_PORT -TimeoutMilliseconds 5000

    if ($lanResponse -ne "TIMEOUT" -and $lanResponse -notlike "ERROR*") {
        $elements = $lanResponse.Split(',')
        if ($elements.Count -ge 3) {
            $detectedIP  = $elements[0].Trim()
            $detectedMAC = $elements[1].Trim()
            $detectedMID = $elements[2].Trim()

            Write-SuccessMessage (T "DISC_FOUND_LAN")
            Write-Host "$(T 'DISC_CURR_IP')$detectedIP" -ForegroundColor White
            Write-Host "$(T 'DISC_MAC')$detectedMAC" -ForegroundColor White
            Write-Host "$(T 'DISC_MID')$detectedMID" -ForegroundColor White
            Write-Host ""
            return @{ IP = $detectedIP; Mode = "LAN"; MAC = $detectedMAC; MID = $detectedMID }
        }
    }

    Write-InfoMessage (T "DISC_PHASE2")
    $hotspotResponse = Send-DiscoveryBroadcast -IPAddress $FOOBOT_HOTSPOT_IP -Port $FOOBOT_UDP_PORT -TimeoutMilliseconds 5000

    if ($hotspotResponse -ne "TIMEOUT" -and $hotspotResponse -notlike "ERROR*") {
        $elements = $hotspotResponse.Split(',')
        $detectedMAC = if ($elements.Count -ge 2) { $elements[1].Trim() } else { "N/D" }
        $detectedMID = if ($elements.Count -ge 3) { $elements[2].Trim() } else { "N/D" }

        Write-SuccessMessage (T "DISC_FOUND_HOT")
        Write-Host (T "DISC_MODE_HOT") -ForegroundColor Yellow
        Write-Host "$(T 'DISC_GW_IP')$FOOBOT_HOTSPOT_IP" -ForegroundColor White
        Write-Host "$(T 'DISC_MAC')$detectedMAC" -ForegroundColor White
        Write-Host "$(T 'DISC_MID')$detectedMID" -ForegroundColor White
        Write-Host ""
        return @{ IP = $FOOBOT_HOTSPOT_IP; Mode = "HOTSPOT"; MAC = $detectedMAC; MID = $detectedMID }
    }

    Write-WarningMessage (T "DISC_NOT_FOUND")
    Write-Host ""
    Write-InfoMessage (T "DISC_GUIDE_HEAD")
    Write-InfoMessage (T "DISC_GUIDE_1")
    Write-InfoMessage (T "DISC_GUIDE_2")
    Write-InfoMessage (T "DISC_GUIDE_3")
    Write-InfoMessage (T "DISC_GUIDE_4")
    Write-Host ""

    Clear-ConsoleInputBuffer
    $manualIP = Read-HostWithPadding (T "DISC_MANUAL_PROMPT")
    if ([string]::IsNullOrWhiteSpace($manualIP)) {
        return $null
    }
    $cleanManualIP = $manualIP.Trim()
    if (-not (Test-IsValidIPv4 -IPString $cleanManualIP)) {
        $displayVal = if ($cleanManualIP.Length -gt 15) { $cleanManualIP.Substring(0, 15) + "..." } else { $cleanManualIP }
        Write-ErrorMessage (T "ERR_INVALID_IP" @($displayVal))
        return $null
    }
    return @{ IP = $cleanManualIP; Mode = "MANUAL"; MAC = "N/D"; MID = "N/D" }
}

function Invoke-FoobotAtSession {
    param(
        [string]$DeviceIP,
        [string[]]$CommandList,
        [bool]$ShowVerboseSteps = $true
    )

    $results = @{}

    # 1. Controllo preventivo di sicurezza su tutti i comandi richiesti
    $forbiddenCommands = @(
        "AT+UART=", "AT+UARTF", "AT+UARTFT", "AT+UARTFL", "AT+UARTTE",
        "AT+RELD", "AT+FCLR", "AT+UPURL", "AT+UPFILE", "AT+UPST", "AT+UPWEB", "AT+UPCFG", "AT+UPAUTO",
        "AT+UPMCU", "AT+FLPBOTA", "AT+FMCUOTA", "AT+MCUOTA", "AT+LPBOTA", "AT+ENTERBTL", "AT+STOPBTL", "AT+BTLTEST",
        "AT+FAILSAFE", "AT+INITEEPROM", "AT+FRESET", "AT+STRESET", "AT+STRESET2", "AT+RESETCALIB",
        "AT+HUMCALIB=", "AT+TEMPCALIB=", "AT+PMCALIB=",
        "AT+NETP=", "AT+WAP=", "AT+WAKEY=", "AT+LANN="
    )
    foreach ($cmd in $CommandList) {
        foreach ($forbidden in $forbiddenCommands) {
            if ($cmd -like "$forbidden*") {
                Write-ErrorMessage (T "SAFETY_BLOCK_1" @($cmd))
                Write-InfoMessage (T "SAFETY_BLOCK_2")
                $results[$cmd] = "BLOCKED_BY_SAFETY_POLICY"
                return $results
            }
        }
    }

    # 2. Apertura di UN UNICO socket UDP persistente (timeout 5000ms per attendere AT+PING senza interferenze)
    $udp = New-Object System.Net.Sockets.UdpClient(0)
    $udp.Client.ReceiveTimeout = 5000
    $udp.Client.SendTimeout = 5000
    $targetEp = New-Object System.Net.IPEndPoint([System.Net.IPAddress]::Parse($DeviceIP), $FOOBOT_UDP_PORT)
    $remoteEp = New-Object System.Net.IPEndPoint([System.Net.IPAddress]::Any, 0)

    try {
        $localPort = $udp.Client.LocalEndPoint.Port
        if ($ShowVerboseSteps) {
            Write-StepLog (T "AT_STEP1" @($localPort, $DeviceIP))
            Write-StepLog (T "AT_WAKE_SEND")
        }

        $wakeBytes = [System.Text.Encoding]::ASCII.GetBytes($PROTOCOL_HANDSHAKE)
        [void]$udp.Send($wakeBytes, $wakeBytes.Length, $targetEp)

        $wakeReply = ""
        try {
            $rcvWake = $udp.Receive([ref]$remoteEp)
            $wakeReply = [System.Text.Encoding]::ASCII.GetString($rcvWake).Trim()
            if ($ShowVerboseSteps) {
                Write-StepLog (T "AT_WAKE_RECV" @($wakeReply))
            }
        }
        catch {
            Write-ErrorMessage (T "AT_WAKE_ERR")
            foreach ($cmd in $CommandList) { $results[$cmd] = "ERRORE_HANDSHAKE" }
            return $results
        }

        # 3. Invio immediato di '+ok' sullo STESSO socket senza chiudere la porta
        if ($ShowVerboseSteps) {
            Write-StepLog (T "AT_STEP2")
        }
        $ackBytes = [System.Text.Encoding]::ASCII.GetBytes($PROTOCOL_ENTER_AT)
        [void]$udp.Send($ackBytes, $ackBytes.Length, $targetEp)

        Start-Sleep -Milliseconds 200

        while ($udp.Available -gt 0) {
            [void]$udp.Receive([ref]$remoteEp)
        }

        if ($ShowVerboseSteps) {
            Write-StepLog (T "AT_STEP3")
        }

        # 4. Esecuzione sequenziale dei comandi AT sulla stessa porta UDP autorizzata
        $didReboot = $false
        foreach ($cmd in $CommandList) {
            $cmdPayload = "$cmd`r"
            $cmdBytes = [System.Text.Encoding]::ASCII.GetBytes($cmdPayload)

            if ($cmd -eq "AT+Z") {
                $didReboot = $true
                if ($ShowVerboseSteps) {
                    Write-StepLog (T "AT_CMD_SEND" @($cmd))
                }
                [void]$udp.Send($cmdBytes, $cmdBytes.Length, $targetEp)
                Start-Sleep -Milliseconds 150
                $zReply = "+ok (Rebooting)"
                if ($udp.Available -gt 0) {
                    try {
                        $rcvZ = $udp.Receive([ref]$remoteEp)
                        $zReply = [System.Text.Encoding]::ASCII.GetString($rcvZ).Trim()
                    } catch {}
                }
                if ($ShowVerboseSteps) {
                    Write-StepLog (T "AT_Z_OUTCOME" @($zReply))
                }
                $results[$cmd] = $zReply
                continue
            }

            for ($attempt = 1; $attempt -le 3; $attempt++) {
                if ($ShowVerboseSteps) {
                    if ($attempt -eq 1) {
                        Write-StepLog (T "AT_CMD_SEND" @($cmd))
                        if ($cmd -like "AT+PING=*") {
                            Write-StepLog (T "AT_WAIT_PING")
                        }
                    } else {
                        Write-StepLog (T "AT_CMD_RETRY" @($attempt, $cmd))
                    }
                }

                if ($attempt -gt 1) {
                    [void]$udp.Send($wakeBytes, $wakeBytes.Length, $targetEp)
                    Start-Sleep -Milliseconds 150
                    while ($udp.Available -gt 0) { [void]$udp.Receive([ref]$remoteEp) }
                    [void]$udp.Send($ackBytes, $ackBytes.Length, $targetEp)
                    Start-Sleep -Milliseconds 250
                    while ($udp.Available -gt 0) { [void]$udp.Receive([ref]$remoteEp) }
                }

                [void]$udp.Send($cmdBytes, $cmdBytes.Length, $targetEp)

                try {
                    $rcvCmd = $udp.Receive([ref]$remoteEp)
                    $cmdReply = [System.Text.Encoding]::ASCII.GetString($rcvCmd).Trim()
                    if ($ShowVerboseSteps) {
                        Write-StepLog (T "AT_CMD_RECV" @($cmd, $cmdReply))
                    }
                    # Svuota eventuali pacchetti ritardati rimasti nel buffer UDP (es. dopo AT+PING)
                    Start-Sleep -Milliseconds 120
                    while ($udp.Available -gt 0) {
                        [void]$udp.Receive([ref]$remoteEp)
                    }
                    $results[$cmd] = $cmdReply
                    break
                }
                catch {
                    if ($attempt -lt 3) {
                        if ($ShowVerboseSteps) {
                            Write-StepLog (T "AT_WAIT_RETRY_5S")
                        }
                    } else {
                        if ($ShowVerboseSteps) {
                            Write-StepLog (T "AT_CMD_TIMEOUT" @($cmd))
                        }
                        $results[$cmd] = "TIMEOUT"
                    }
                }
            }

            if ($cmd -eq "AT+TCPDISB=off") {
                if ($ShowVerboseSteps) { Write-StepLog (T "AT_WAIT_TCP_OFF") }
                Start-Sleep -Milliseconds 1500
            } elseif ($cmd -eq "AT+SOCKB=NONE") {
                if ($ShowVerboseSteps) { Write-StepLog (T "AT_WAIT_SOCK_NONE") }
                Start-Sleep -Milliseconds 1000
            } elseif ($cmd -like "AT+SOCKB=*") {
                if ($ShowVerboseSteps) { Write-StepLog (T "AT_WAIT_SOCK_TCP") }
                Start-Sleep -Milliseconds 1500
            } elseif ($cmd -eq "AT+TCPDISB=on") {
                if ($ShowVerboseSteps) { Write-StepLog (T "AT_WAIT_TCP_ON") }
                Start-Sleep -Milliseconds 2500
            } else {
                Start-Sleep -Milliseconds 200
            }
        }

        if (-not $didReboot) {
            try {
                $quitBytes = [System.Text.Encoding]::ASCII.GetBytes("AT+Q`r")
                [void]$udp.Send($quitBytes, $quitBytes.Length, $targetEp)
                Start-Sleep -Milliseconds 100
            } catch {}
        }
    }
    finally {
        $udp.Close()
        $udp.Dispose()
        if ($ShowVerboseSteps) {
            Write-StepLog (T "AT_CLOSE")
            Write-Host ""
        }
    }

    return $results
}

# ==============================================================================
# AVVIO SELEZIONE LINGUA E INTERFACCIA UTENTE PRINCIPALE
# ==============================================================================
Select-StartupLanguage

Clear-Host
Write-Host "================================================================================" -ForegroundColor Cyan
Write-Host (T "BANNER_TITLE") -ForegroundColor White
Write-Host (T "BANNER_SUB") -ForegroundColor Yellow
Write-Host "================================================================================" -ForegroundColor Cyan
Write-Host ""
Write-InfoMessage (T "LOCAL_NOTICE_1")
Write-InfoMessage (T "LOCAL_NOTICE_2")
Write-Host ""

$device = Find-FoobotDevice

if ($null -eq $device -or [string]::IsNullOrWhiteSpace($device.IP)) {
    Write-ErrorMessage (T "ERR_NO_DEVICE")
    Write-Host "`n$(T 'PRESS_KEY_EXIT')"
    Clear-ConsoleInputBuffer
    [void][System.Console]::ReadKey($true)
    exit
}

$targetIP = $device.IP

do {
    Write-BoxTitle (T "MENU_TITLE" @($targetIP, $device.Mode))
    Write-Host "1. " -NoNewline -ForegroundColor Yellow; Write-Host (T "MENU_OPT_1")
    Write-Host "2. " -NoNewline -ForegroundColor Yellow; Write-Host (T "MENU_OPT_2")
    Write-Host "3. " -NoNewline -ForegroundColor Yellow; Write-Host (T "MENU_OPT_3")
    Write-Host "4. " -NoNewline -ForegroundColor Yellow; Write-Host (T "MENU_OPT_4")
    Write-Host "5. " -NoNewline -ForegroundColor Yellow; Write-Host (T "MENU_OPT_5")
    Write-Host "6. " -NoNewline -ForegroundColor Yellow; Write-Host (T "MENU_OPT_6")
    Write-Host "7. " -NoNewline -ForegroundColor Yellow; Write-Host (T "MENU_OPT_7")
    Write-Host ""
    Clear-ConsoleInputBuffer
    $userChoice = Read-HostWithPadding (T "MENU_PROMPT")

    switch ($userChoice) {
        "1" {
            Write-BoxTitle (T "OPT1_TITLE")
            Write-InfoMessage (T "OPT1_START")
            Write-Host ""

            $commandsToRead = @("AT+VER", "AT+MID", "AT+WSSSID", "AT+WSLQ", "AT+WANN", "AT+WSDNS", "AT+SOCKB", "AT+TCPLKB")
            $diagResults = Invoke-FoobotAtSession -DeviceIP $targetIP -CommandList $commandsToRead -ShowVerboseSteps $true

            Write-Host (T "OPT1_SUMMARY") -ForegroundColor White
            Write-Host (T "OPT1_FW")    -NoNewline; Write-Host "$($diagResults['AT+VER'])" -ForegroundColor Green
            Write-Host (T "OPT1_MID")   -NoNewline; Write-Host "$($diagResults['AT+MID'])" -ForegroundColor Green
            Write-Host (T "OPT1_SSID")  -NoNewline; Write-Host "$($diagResults['AT+WSSSID'])" -ForegroundColor Green
            Write-Host (T "OPT1_WSLQ")  -NoNewline; Write-Host "$($diagResults['AT+WSLQ'])" -ForegroundColor Green
            Write-Host (T "OPT1_WANN")  -NoNewline; Write-Host "$($diagResults['AT+WANN'])" -ForegroundColor Green
            Write-Host (T "OPT1_WSDNS") -NoNewline; Write-Host "$($diagResults['AT+WSDNS'])" -ForegroundColor Green
            Write-Host (T "OPT1_SOCKB") -NoNewline; Write-Host "$($diagResults['AT+SOCKB'])" -ForegroundColor Green
            Write-Host (T "OPT1_TCPLKB")-NoNewline; Write-Host "$($diagResults['AT+TCPLKB'])" -ForegroundColor Green
            Write-Host ""

            $rawDnsVal = $diagResults['AT+WSDNS']
            if ($rawDnsVal -like "+ok=*") {
                $activeDnsIp = $rawDnsVal.Substring(4).Trim()
                if (Test-IsValidIPv4 $activeDnsIp) {
                    Write-Host (T "OPT1_DNS_CHECK") -ForegroundColor White
                    try {
                        $dnsRes = Resolve-DnsName -Name "broker-gw-nc.foobot.io" -Server $activeDnsIp -Type A -ErrorAction Stop | Where-Object { $_.Type -eq 'A' } | Select-Object -First 1
                        $resolvedBrokerIp = $dnsRes.IPAddress
                        $awsKnownIps = @("3.223.61.228", "35.170.134.203", "52.87.92.129", "54.172.185.79")
                        if ($awsKnownIps -contains $resolvedBrokerIp) {
                            Write-Host "  - DNS ($activeDnsIp) : " -NoNewline
                            Write-Host "$resolvedBrokerIp$(T 'OPT1_DNS_AWS_WARN')" -ForegroundColor Red
                            Write-WarningMessage (T "OPT1_DNS_AWS_MSG" @($activeDnsIp))
                        } else {
                            Write-Host (T "OPT1_DNS_OK_LABEL") -NoNewline
                            Write-Host (T "OPT1_DNS_OK_MSG" @($resolvedBrokerIp)) -ForegroundColor Green
                        }
                    } catch {
                        Write-Host "  - DNS ($activeDnsIp) : " -NoNewline
                        Write-Host (T "OPT1_DNS_UNREACH" @($activeDnsIp)) -ForegroundColor Yellow
                    }
                    Write-Host ""
                }
            }

            Write-SuccessMessage (T "OPT1_DONE")
            Write-Host "`n$(T 'PRESS_KEY_MENU')"
            Clear-ConsoleInputBuffer
            [void][System.Console]::ReadKey($true)
        }

        "2" {
            Write-BoxTitle (T "OPT2_TITLE")
            Write-InfoMessage (T "OPT2_INFO1")
            Write-InfoMessage (T "OPT2_INFO2")
            Write-Host ""
            Write-WarningMessage (T "OPT2_ORIG_DNS")
            Write-Host ""

            Clear-ConsoleInputBuffer
            $rawInput = Read-HostWithPadding (T "OPT2_PROMPT_IP")
            $newDnsIp = if ($null -ne $rawInput) { $rawInput.Trim() } else { "" }

            if ([string]::IsNullOrWhiteSpace($newDnsIp)) {
                Write-WarningMessage (T "OPT2_CANCEL_EMPTY")
                Start-Sleep -Seconds 2
                break
            }

            if (-not (Test-IsValidIPv4 -IPString $newDnsIp)) {
                $displayVal = if ($newDnsIp.Length -gt 15) { $newDnsIp.Substring(0, 15) + "..." } else { $newDnsIp }
                Write-ErrorMessage (T "ERR_INVALID_IP" @($displayVal))
                Write-InfoMessage (T "ERR_IP_FORMAT")
                Write-Host "`n$(T 'PRESS_KEY_MENU')"
                Clear-ConsoleInputBuffer
                [void][System.Console]::ReadKey($true)
                break
            }

            Write-Host ""
            Write-WarningMessage (T "OPT2_CONFIRM_HEAD")
            Write-Host (T "OPT2_CONFIRM_TARGET" @($newDnsIp)) -ForegroundColor Yellow
            Write-InfoMessage (T "OPT2_CONFIRM_NOTE1")
            Write-InfoMessage (T "OPT2_CONFIRM_NOTE2")
            Write-InfoMessage (T "OPT2_CONFIRM_NOTE3")
            Write-Host ""

            Clear-ConsoleInputBuffer
            $confirm = Read-HostWithPadding (T "OPT2_CONFIRM_ASK")

            if (Test-IsConfirmed $confirm) {
                Write-Host ""
                Write-InfoMessage (T "OPT2_PRE_READ")
                $preRead = Invoke-FoobotAtSession -DeviceIP $targetIP -CommandList @("AT+WANN") -ShowVerboseSteps $true
                $wannRaw = $preRead["AT+WANN"]

                $cmdStaticWann = $null
                if ($wannRaw -like "+ok=*") {
                    $cleanWann = $wannRaw.Substring(4)
                    $wannParts = $cleanWann.Split(',')
                    if ($wannParts.Count -ge 4) {
                        $currIp   = $wannParts[1].Trim()
                        $currMask = $wannParts[2].Trim()
                        $currGw   = $wannParts[3].Trim()
                        if ((Test-IsValidIPv4 $currIp) -and (Test-IsValidIPv4 $currMask) -and (Test-IsValidIPv4 $currGw)) {
                            $cmdStaticWann = "AT+WANN=static,$currIp,$currMask,$currGw"
                            Write-InfoMessage (T "OPT2_LAN_DETECTED" @($currIp, $currMask, $currGw))
                            Write-InfoMessage (T "OPT2_STATIC_APPLY" @($cmdStaticWann))
                        }
                    }
                }

                if ($null -eq $cmdStaticWann -and (Test-IsValidIPv4 $targetIP) -and $targetIP -ne $FOOBOT_HOTSPOT_IP) {
                    $octets = $targetIP.Split('.')
                    $fallbackGw = "$($octets[0]).$($octets[1]).$($octets[2]).1"
                    $cmdStaticWann = "AT+WANN=static,$targetIP,255.255.255.0,$fallbackGw"
                    Write-InfoMessage (T "OPT2_LAN_DEDUCED" @($targetIP, $fallbackGw))
                    Write-InfoMessage (T "OPT2_STATIC_APPLY" @($cmdStaticWann))
                }

                Write-Host ""
                Write-InfoMessage (T "OPT2_WRITE_START")
                $cmdWriteDns = "AT+WSDNS=$newDnsIp"
                $cmdVerifyDns = "AT+WSDNS"

                if ($null -ne $cmdStaticWann) {
                    Write-InfoMessage (T "OPT2_STEP1")
                    $resWann = Invoke-FoobotAtSession -DeviceIP $targetIP -CommandList @($cmdStaticWann, "AT+WANN") -ShowVerboseSteps $true
                    Write-SuccessMessage (T "OPT2_WANN_SAVED" @($resWann['AT+WANN']))
                    Start-Sleep -Milliseconds 500
                }

                Write-InfoMessage (T "OPT2_STEP2" @($newDnsIp))
                $reconnectSocketCmds = @(
                    "AT+TCPDISB=off",
                    $cmdWriteDns,
                    "AT+SOCKB=NONE",
                    "AT+SOCKB=TCP,1883,broker-gw-nc.foobot.io",
                    "AT+TCPDISB=on",
                    $cmdVerifyDns,
                    "AT+SOCKB",
                    "AT+TCPLKB"
                )
                $writeSession = Invoke-FoobotAtSession -DeviceIP $targetIP -CommandList $reconnectSocketCmds -ShowVerboseSteps $true

                $writeOutcome = $writeSession[$cmdWriteDns]
                $verifyDnsOutcome = $writeSession[$cmdVerifyDns]
                $verifySockOutcome = $writeSession["AT+SOCKB"]
                $verifyTcpOutcome = $writeSession["AT+TCPLKB"]

                if ($verifyTcpOutcome -notlike "*+ok=on*") {
                    Write-StepLog (T "WAIT_FINAL_TCPLKB")
                    Start-Sleep -Seconds 5
                    $recheckSession = Invoke-FoobotAtSession -DeviceIP $targetIP -CommandList @("AT+TCPLKB") -ShowVerboseSteps $true
                    if ($recheckSession["AT+TCPLKB"] -like "*+ok*") {
                        $verifyTcpOutcome = $recheckSession["AT+TCPLKB"]
                    }
                }

                if ($writeOutcome -like "*+ok*") {
                    Write-SuccessMessage (T "OPT2_OK_1" @($writeOutcome))
                    Write-SuccessMessage (T "OPT2_OK_2" @($verifyDnsOutcome))
                    Write-SuccessMessage (T "OPT2_OK_3" @($verifySockOutcome))
                    Write-SuccessMessage (T "OPT2_OK_4" @($verifyTcpOutcome))
                    Write-SuccessMessage (T "OPT2_OK_5")
                } else {
                    Write-ErrorMessage (T "OPT2_ERR_WRITE" @($writeOutcome))
                }
            } else {
                Write-InfoMessage (T "OPT2_CANCELLED")
            }
            Write-Host "`n$(T 'PRESS_KEY_MENU')"
            Clear-ConsoleInputBuffer
            [void][System.Console]::ReadKey($true)
        }

        "3" {
            Write-BoxTitle (T "OPT_REACT_TITLE")
            Write-Host (T "OPT_REACT_DESC_HEAD") -ForegroundColor Yellow
            Write-InfoMessage (T "OPT_REACT_DESC_1")
            Write-InfoMessage (T "OPT_REACT_DESC_2")
            Write-InfoMessage (T "OPT_REACT_DESC_3")
            Write-Host ""
            Write-Host (T "OPT_REACT_STEPS_HD") -ForegroundColor Cyan
            Write-InfoMessage (T "OPT_REACT_STEP_1")
            Write-InfoMessage (T "OPT_REACT_STEP_2")
            Write-InfoMessage (T "OPT_REACT_STEP_3")
            Write-InfoMessage (T "OPT_REACT_STEP_4")
            Write-InfoMessage (T "OPT_REACT_STEP_5")
            Write-Host ""

            $preDns = Invoke-FoobotAtSession -DeviceIP $targetIP -CommandList @("AT+WSDNS") -ShowVerboseSteps $false
            $savedDnsVal = ""
            if ($preDns["AT+WSDNS"] -like "+ok=*") {
                $savedDnsVal = $preDns["AT+WSDNS"].Substring(4).Trim()
            }

            if (-not (Test-IsValidIPv4 $savedDnsVal) -or $savedDnsVal -eq $FOOBOT_ORIGINAL_DNS) {
                $dispDns = if ([string]::IsNullOrWhiteSpace($savedDnsVal)) { $FOOBOT_ORIGINAL_DNS } else { $savedDnsVal }
                Write-WarningMessage (T "OPT_REACT_NO_DNS" @($dispDns))
                Write-Host "`n$(T 'PRESS_KEY_MENU')"
                Clear-ConsoleInputBuffer
                [void][System.Console]::ReadKey($true)
                break
            }

            Write-Host (T "OPT_REACT_SAVED_DNS" @($savedDnsVal)) -ForegroundColor Green
            Write-Host ""

            Clear-ConsoleInputBuffer
            $confirm = Read-HostWithPadding (T "OPT_REACT_CONFIRM")

            if (Test-IsConfirmed $confirm) {
                Write-Host ""
                Write-InfoMessage (T "OPT_REACT_WAIT")
                [void](Invoke-FoobotAtSession -DeviceIP $targetIP -CommandList @("AT+WANN=DHCP", "AT+Z") -ShowVerboseSteps $true)
                for ($sec = 1; $sec -le 16; $sec++) {
                    if ($sec -eq 1 -or $sec % 4 -eq 0) {
                        Write-StepLog (T "WAIT_REBOOT_STEP" @($sec, 16))
                    }
                    Start-Sleep -Seconds 1
                }
                Write-Host ""

                Write-InfoMessage (T "OPT_REACT_DISC")
                $renewedLan = Send-DiscoveryBroadcast -IPAddress "255.255.255.255" -Port $FOOBOT_UDP_PORT -TimeoutMilliseconds 5000
                if ($renewedLan -ne "TIMEOUT" -and $renewedLan -notlike "ERROR*") {
                    $rParts = $renewedLan.Split(',')
                    if ($rParts.Count -ge 1 -and (Test-IsValidIPv4 $rParts[0].Trim())) {
                        $targetIP = $rParts[0].Trim()
                        $device.IP = $targetIP
                        Write-SuccessMessage (T "OPT2_NEW_IP" @($targetIP))
                    }
                }

                Write-InfoMessage (T "OPT_REACT_RESYNC" @($savedDnsVal))
                $wannCheck = Invoke-FoobotAtSession -DeviceIP $targetIP -CommandList @("AT+WANN") -ShowVerboseSteps $true
                $staticCmd = $null
                if ($wannCheck["AT+WANN"] -like "+ok=*") {
                    $wParts = $wannCheck["AT+WANN"].Substring(4).Split(',')
                    if ($wParts.Count -ge 4) {
                        $cIp   = $wParts[1].Trim()
                        $cMask = $wParts[2].Trim()
                        $cGw   = $wParts[3].Trim()
                        if ((Test-IsValidIPv4 $cIp) -and (Test-IsValidIPv4 $cMask) -and (Test-IsValidIPv4 $cGw)) {
                            $staticCmd = "AT+WANN=static,$cIp,$cMask,$cGw"
                        }
                    }
                }
                if ($null -eq $staticCmd -and (Test-IsValidIPv4 $targetIP) -and $targetIP -ne $FOOBOT_HOTSPOT_IP) {
                    $octs = $targetIP.Split('.')
                    $fbGw = "$($octs[0]).$($octs[1]).$($octs[2]).1"
                    $staticCmd = "AT+WANN=static,$targetIP,255.255.255.0,$fbGw"
                }

                $syncCmds = @()
                if ($null -ne $staticCmd) { $syncCmds += $staticCmd }
                $syncCmds += @(
                    "AT+TCPDISB=off",
                    "AT+WSDNS=$savedDnsVal",
                    "AT+SOCKB=NONE",
                    "AT+SOCKB=TCP,1883,broker-gw-nc.foobot.io",
                    "AT+TCPDISB=on",
                    "AT+WANN",
                    "AT+WSDNS",
                    "AT+SOCKB",
                    "AT+TCPLKB"
                )
                $syncRes = Invoke-FoobotAtSession -DeviceIP $targetIP -CommandList $syncCmds -ShowVerboseSteps $true
                $finalTcp = $syncRes["AT+TCPLKB"]
                if ($finalTcp -notlike "*+ok=on*") {
                    Write-StepLog (T "WAIT_FINAL_TCPLKB")
                    Start-Sleep -Seconds 5
                    $recheckTcp = Invoke-FoobotAtSession -DeviceIP $targetIP -CommandList @("AT+TCPLKB") -ShowVerboseSteps $true
                    if ($recheckTcp["AT+TCPLKB"] -like "*+ok*") {
                        $finalTcp = $recheckTcp["AT+TCPLKB"]
                    }
                }
                Write-SuccessMessage (T "OPT_REACT_DONE" @($finalTcp))
            } else {
                Write-InfoMessage (T "OPT_REACT_CANCEL")
            }
            Write-Host "`n$(T 'PRESS_KEY_MENU')"
            Clear-ConsoleInputBuffer
            [void][System.Console]::ReadKey($true)
        }

        "4" {
            Write-BoxTitle (T "OPT3_TITLE")
            Write-InfoMessage (T "OPT3_INFO1")
            Write-InfoMessage (T "OPT3_INFO2")
            Write-Host ""
            Write-WarningMessage (T "OPT3_WARN_24")
            Write-Host ""

            Clear-ConsoleInputBuffer
            $newSsid = Read-HostWithPadding (T "OPT3_PROMPT_SSID")
            if ([string]::IsNullOrWhiteSpace($newSsid)) {
                Write-WarningMessage (T "OPT3_NO_SSID")
                Start-Sleep -Seconds 2
                break
            }

            Clear-ConsoleInputBuffer
            $newKey = Read-HostWithPadding (T "OPT3_PROMPT_PASS")
            if ([string]::IsNullOrWhiteSpace($newKey)) {
                Write-WarningMessage (T "OPT3_NO_PASS")
                Start-Sleep -Seconds 2
                break
            }

            Clear-ConsoleInputBuffer
            $rawCustomDns = Read-HostWithPadding (T "OPT3_PROMPT_DNS")
            $customDns = if ($null -ne $rawCustomDns) { $rawCustomDns.Trim() } else { "" }

            if (-not [string]::IsNullOrWhiteSpace($customDns)) {
                if (-not (Test-IsValidIPv4 -IPString $customDns)) {
                    $displayVal = if ($customDns.Length -gt 15) { $customDns.Substring(0, 15) + "..." } else { $customDns }
                    Write-ErrorMessage (T "ERR_INVALID_IP" @($displayVal))
                    Write-Host "`n$(T 'PRESS_KEY_MENU')"
                    Clear-ConsoleInputBuffer
                    [void][System.Console]::ReadKey($true)
                    break
                }
            }

            Write-Host ""
            Write-WarningMessage (T "OPT3_SUMMARY")
            Write-Host (T "OPT3_SUM_SSID" @($newSsid)) -ForegroundColor Yellow
            Write-Host (T "OPT3_SUM_PASS") -ForegroundColor Yellow
            if (-not [string]::IsNullOrWhiteSpace($customDns)) {
                Write-Host (T "OPT3_SUM_DNS" @($customDns)) -ForegroundColor Yellow
            }
            Write-InfoMessage (T "OPT3_NOTE")
            Write-Host ""
            Clear-ConsoleInputBuffer
            $confirm = Read-HostWithPadding (T "OPT3_CONFIRM_ASK")

            if (Test-IsConfirmed $confirm) {
                Write-Host ""
                Write-InfoMessage (T "OPT3_START")
                $wifiCmds = @("AT+WSSSID=$newSsid", "AT+WSKEY=WPA2PSK,AES,$newKey")
                if (-not [string]::IsNullOrWhiteSpace($customDns)) {
                    $wifiCmds += "AT+WSDNS=$customDns"
                } else {
                    $wifiCmds += "AT+WANN=DHCP"
                }
                $wifiCmds += "AT+Z"

                [void](Invoke-FoobotAtSession -DeviceIP $targetIP -CommandList $wifiCmds -ShowVerboseSteps $true)

                Write-SuccessMessage (T "OPT3_DONE_1")
                Write-InfoMessage (T "OPT3_DONE_2" @($newSsid))
                Write-InfoMessage (T "OPT3_DONE_3")
            } else {
                Write-InfoMessage (T "OPT3_CANCELLED")
            }
            Write-Host "`n$(T 'PRESS_KEY_MENU')"
            Clear-ConsoleInputBuffer
            [void][System.Console]::ReadKey($true)
        }

        "5" {
            Write-BoxTitle (T "OPT4_TITLE")
            Write-InfoMessage (T "OPT4_INFO")
            Write-Host (T "OPT4_WANN") -ForegroundColor Green
            Write-Host (T "OPT4_DNS") -ForegroundColor Green
            Write-Host ""
            Write-WarningMessage (T "OPT4_CONFIRM_HEAD")
            Clear-ConsoleInputBuffer
            $confirm = Read-HostWithPadding (T "OPT4_CONFIRM_ASK")

            if (Test-IsConfirmed $confirm) {
                Write-Host ""
                Write-InfoMessage (T "OPT4_START")
                $cmdRestoreWann = "AT+WANN=DHCP"
                $cmdRestoreDns  = "AT+WSDNS=$FOOBOT_ORIGINAL_DNS"
                $restoreSession = Invoke-FoobotAtSession -DeviceIP $targetIP -CommandList @($cmdRestoreWann, $cmdRestoreDns, "AT+WANN", "AT+WSDNS", "AT+Z") -ShowVerboseSteps $true

                if ($restoreSession[$cmdRestoreDns] -like "*+ok*") {
                    Write-SuccessMessage (T "OPT4_OK_1")
                    Write-SuccessMessage (T "OPT4_OK_2" @($restoreSession['AT+WANN']))
                    Write-SuccessMessage (T "OPT4_OK_3" @($restoreSession['AT+WSDNS']))
                    Write-SuccessMessage (T "OPT4_OK_4")
                } else {
                    Write-ErrorMessage (T "OPT4_ERR" @($restoreSession[$cmdRestoreDns]))
                }
            } else {
                Write-InfoMessage (T "OPT4_CANCELLED")
            }
            Write-Host "`n$(T 'PRESS_KEY_MENU')"
            Clear-ConsoleInputBuffer
            [void][System.Console]::ReadKey($true)
        }

        "6" {
            Write-BoxTitle (T "OPT5_TITLE")
            Write-InfoMessage (T "OPT5_INFO")
            Clear-ConsoleInputBuffer
            $confirm = Read-HostWithPadding (T "OPT5_CONFIRM_ASK")

            if (Test-IsConfirmed $confirm) {
                Write-Host ""
                [void](Invoke-FoobotAtSession -DeviceIP $targetIP -CommandList @("AT+Z") -ShowVerboseSteps $true)
                Write-SuccessMessage (T "OPT5_DONE")
            } else {
                Write-InfoMessage (T "OPT5_CANCELLED")
            }
            Write-Host "`n$(T 'PRESS_KEY_MENU')"
            Clear-ConsoleInputBuffer
            [void][System.Console]::ReadKey($true)
        }

        "7" {
            Write-InfoMessage (T "OPT6_EXIT")
            Start-Sleep -Seconds 1
            break
        }

        default {
            Write-WarningMessage (T "MENU_INVALID" @($userChoice))
            Start-Sleep -Seconds 1
        }
    }

} while ($userChoice -ne "7")
