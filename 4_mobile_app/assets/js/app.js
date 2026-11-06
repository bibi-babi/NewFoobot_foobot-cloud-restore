/**
 * NewFoobot Air Quality PWA — UI, Multilingua & Grafico Storico (v1.0 Stabile)
 * Architettura disaccoppiata dal DB (Modalità Grafica con segnaposto DB espliciti)
 * Libreria grafica locale: Chart.js (assets/js/vendor/chart.min.js)
 */

(function () {
  'use strict';

  // ==========================================================================
  // 1. DIZIONARIO MULTILINGUA (i18n)
  // ==========================================================================
  const I18N_DICTIONARY = {
  "it": {
    "settings_title": "Impostazioni",
    "settings_language": "Lingua Interfaccia",
    "settings_temp_unit": "Unità Temperatura",
    "settings_temp_celsius": "Celsius (°C)",
    "settings_temp_fahrenheit": "Fahrenheit (°F)",
    "settings_active_device": "Dispositivo Foobot",
    "settings_connection": "Collegamento Server DB",
    "settings_connected_user": "Utente Collegato",
    "settings_device_reg_id": "ID Dispositivo App:",
    "settings_server_site": "Sito Web & DB:",
    "settings_status": "Stato:",
    "status_demo_mode": "Modalità Grafica (Demo)",
    "status_great": "OTTIMO",
    "status_good": "BUONO",
    "status_fair": "MEDIO",
    "status_poor": "SCARSO",
    "gpi_full_title": "GPI — Indice Inquinamento",
    "sensor_allpollu_label": "GPI — Indice Inquinamento",
    "sensor_voc_label": "VOC — Composti Volatili",
    "sensor_pm_label": "PM2.5 — Particolato Fine",
    "sensor_co2_label": "eCO2 — Equivalente",
    "sensor_tmp_label": "TMP — Temperatura Ambiente",
    "sensor_hum_label": "HUM — Umidità Relativa",
    "last_data_received": "Ultimo dato ricevuto:",
    "tf_minutes": "Minuti",
    "tf_hours": "Ore",
    "tf_7d": "7 gg",
    "tf_10d": "10 gg",
    "tf_30d": "30 gg",
    "tf_weeks": "Settimane",
    "tf_custom_days_ph": "N° gg",
    "btn_go": "Vai",
    "date_from": "DA:",
    "date_to": "A:",
    "btn_filter": "Filtra",
    "btn_reset": "Reset",
    "desc_allpollu": "Indice sintetico globale della qualità dell'aria interna calcolato dal Foobot combinando particolato e composti volatili.",
    "desc_voc": "I Composti Organici Volatili (VOC) sono emessi da detergenti, vernici, profumi, fumo e cottura dei cibi.",
    "desc_pm": "Il particolato fine (PM2.5) comprende polveri microscopiche in grado di penetrare in profondità nelle vie respiratorie.",
    "desc_co2": "Stima dell'anidride carbonica equivalente legata alla presenza umana negli ambienti chiusi.",
    "desc_tmp": "Temperatura rilevata dal sensore termico integrato nel Foobot.",
    "desc_hum": "Percentuale di vapore acqueo presente nell'aria dell'ambiente domestico.",
    "install_banner_title": "Installa NewFoobot",
    "install_banner_desc": "Scarica l'app sul dispositivo per aprirla a schermo intero",
    "install_banner_btn": "Installa",
    "auth_welcome_title": "NewFoobot Air Quality",
    "auth_welcome_subtitle": "Accedi o collega il tuo monitor Foobot",
    "auth_tab_login": "Accedi al Sito",
    "auth_tab_ticket": "Codice Ticket (QR)",
    "auth_server_url_label": "URL Server Foobot / Sito Web",
    "auth_server_url_hint": "Indirizzo del server dove risiede il database Foobot",
    "auth_username_label": "Nome Utente o Email",
    "auth_password_label": "Password",
    "auth_btn_login": "Accedi e Connetti",
    "auth_ticket_label": "Codice Ticket Accoppiamento",
    "auth_ticket_hint": "Genera il codice dal sito nella sezione \"App Mobile PWA\"",
    "auth_btn_ticket": "Collega con Ticket",
    "auth_btn_logout": "Disconnetti",
    "auth_error_network": "Impossibile contattare il server specificato. Verifica l'URL inserito.",
    "auth_error_creds": "Credenziali non corrette. Verifica username e password.",
    "auth_error_ticket": "Codice ticket non valido o scaduto.",
    "auth_success_connected": "Connessione stabilita con successo!",
    "devices_screen_title": "Elenco Foobot",
    "drop_device_name": "Dispositivo",
    "drop_location": "Posizione",
    "drop_connection_status": "Stato Connessione",
    "drop_device_uuid": "UUID Sensore",
    "drop_last_seen": "Ultimo Dato Ricevuto",
    "drop_last_connected": "Ultima Connessione",
    "drop_last_disconnected": "Ultima Disconnessione",
    "drop_count_24h": "Rilevazioni (24h)",
    "drop_count_total": "Rilevazioni Totali",
    "conn_active_regular": "Attivo (invio regolare)",
    "conn_inactive_15m": "Nessun dato da >15 min",
    "devices_loading": "Caricamento dati di connessione dei dispositivi Foobot..."
  },
  "en": {
    "settings_title": "Settings",
    "settings_language": "Interface Language",
    "settings_temp_unit": "Temperature Unit",
    "settings_temp_celsius": "Celsius (°C)",
    "settings_temp_fahrenheit": "Fahrenheit (°F)",
    "settings_active_device": "Foobot Device",
    "settings_connection": "Database Server Link",
    "settings_connected_user": "Connected User",
    "settings_device_reg_id": "App Device ID:",
    "settings_server_site": "Web Site & DB:",
    "settings_status": "Status:",
    "status_demo_mode": "Graphic Mode (Demo)",
    "status_great": "GREAT",
    "status_good": "GOOD",
    "status_fair": "FAIR",
    "status_poor": "POOR",
    "gpi_full_title": "GPI — Pollution Index",
    "sensor_allpollu_label": "GPI — Pollution Index",
    "sensor_voc_label": "VOC — Volatile Compounds",
    "sensor_pm_label": "PM2.5 — Fine Particles",
    "sensor_co2_label": "eCO2 — Equivalent",
    "sensor_tmp_label": "TMP — Ambient Temperature",
    "sensor_hum_label": "HUM — Relative Humidity",
    "last_data_received": "Last data received:",
    "tf_minutes": "Minutes",
    "tf_hours": "Hours",
    "tf_7d": "7 d",
    "tf_10d": "10 d",
    "tf_30d": "30 d",
    "tf_weeks": "Weeks",
    "tf_custom_days_ph": "# days",
    "btn_go": "Go",
    "date_from": "FROM:",
    "date_to": "TO:",
    "btn_filter": "Filter",
    "btn_reset": "Reset",
    "desc_allpollu": "Global indoor air quality index computed by Foobot combining fine particulate matter and volatile organic compounds.",
    "desc_voc": "Volatile Organic Compounds (VOC) are emitted by cleaning products, paints, perfumes, smoke, and cooking.",
    "desc_pm": "Fine particulate matter (PM2.5) consists of microscopic airborne particles that can reach deep into the respiratory tract.",
    "desc_co2": "Estimated carbon dioxide equivalent correlated with human occupancy in enclosed spaces.",
    "desc_tmp": "Ambient temperature measured by the built-in Foobot thermal sensor.",
    "desc_hum": "Relative humidity percentage in the indoor environment.",
    "install_banner_title": "Install NewFoobot",
    "install_banner_desc": "Download the app on your device for full-screen experience",
    "install_banner_btn": "Install",
    "auth_welcome_title": "NewFoobot Air Quality",
    "auth_welcome_subtitle": "Sign in or pair your Foobot air monitor",
    "auth_tab_login": "Sign In",
    "auth_tab_ticket": "Pairing Ticket (QR)",
    "auth_server_url_label": "Foobot Server / Website URL",
    "auth_server_url_hint": "Server address where the Foobot database is hosted",
    "auth_username_label": "Username or Email",
    "auth_password_label": "Password",
    "auth_btn_login": "Sign In & Connect",
    "auth_ticket_label": "Pairing Ticket Code",
    "auth_ticket_hint": "Generate the code on your website in \"Mobile App PWA\"",
    "auth_btn_ticket": "Connect with Ticket",
    "auth_btn_logout": "Disconnect",
    "auth_error_network": "Unable to reach the server. Please check the URL.",
    "auth_error_creds": "Invalid credentials. Please check your username and password.",
    "auth_error_ticket": "Invalid or expired ticket code.",
    "auth_success_connected": "Successfully connected!",
    "devices_screen_title": "Foobot List",
    "drop_device_name": "Device",
    "drop_location": "Location",
    "drop_connection_status": "Connection Status",
    "drop_device_uuid": "Sensor UUID",
    "drop_last_seen": "Last Data Received",
    "drop_last_connected": "Last Connected",
    "drop_last_disconnected": "Last Disconnected",
    "drop_count_24h": "Readings (24h)",
    "drop_count_total": "Total Readings",
    "conn_active_regular": "Active (regular reporting)",
    "conn_inactive_15m": "No data for >15 min",
    "devices_loading": "Loading Foobot device connection details..."
  },
  "fr": {
    "settings_title": "Paramètres",
    "settings_language": "Langue de l'interface",
    "settings_temp_unit": "Unité de température",
    "settings_temp_celsius": "Celsius (°C)",
    "settings_temp_fahrenheit": "Fahrenheit (°F)",
    "settings_active_device": "Appareil Foobot",
    "settings_connection": "Liaison Serveur DB",
    "settings_connected_user": "Utilisateur Connecté",
    "settings_device_reg_id": "ID Appareil App :",
    "settings_server_site": "Site Web & DB :",
    "settings_status": "État :",
    "status_demo_mode": "Mode Graphique (Démo)",
    "status_great": "EXCELLENT",
    "status_good": "BON",
    "status_fair": "MOYEN",
    "status_poor": "MÉDIOCRE",
    "gpi_full_title": "GPI — Indice de Pollution",
    "sensor_allpollu_label": "GPI — Indice de Pollution",
    "sensor_voc_label": "COV — Composés Volatils",
    "sensor_pm_label": "PM2.5 — Particules Fines",
    "sensor_co2_label": "eCO2 — Équivalent",
    "sensor_tmp_label": "TMP — Température Ambiante",
    "sensor_hum_label": "HUM — Humidité Relative",
    "last_data_received": "Dernière donnée reçue :",
    "tf_minutes": "Minutes",
    "tf_hours": "Heures",
    "tf_7d": "7 j",
    "tf_10d": "10 j",
    "tf_30d": "30 j",
    "tf_weeks": "Semaines",
    "tf_custom_days_ph": "N° jours",
    "btn_go": "Aller",
    "date_from": "DU :",
    "date_to": "AU :",
    "btn_filter": "Filtrer",
    "btn_reset": "Réinitialiser",
    "desc_allpollu": "Indice global de la qualité de l'air intérieur calculé par Foobot combinant particules fines et composés volatils.",
    "desc_voc": "Les composés organiques volatils (COV) sont émis par les détergents, peintures, parfums, fumées et la cuisson.",
    "desc_pm": "Les particules fines (PM2.5) comprennent des poussières microscopiques capables de pénétrer profondément dans les voies respiratoires.",
    "desc_co2": "Estimation du dioxyde de carbone équivalent liée à la présence humaine dans les espaces clos.",
    "desc_tmp": "Température mesurée par le capteur thermique intégré du Foobot.",
    "desc_hum": "Pourcentage de vapeur d'eau présent dans l'air de l'environnement intérieur.",
    "install_banner_title": "Installer NewFoobot",
    "install_banner_desc": "Téléchargez l'application sur votre appareil pour l'ouvrir en plein écran",
    "install_banner_btn": "Installer",
    "auth_welcome_title": "NewFoobot Air Quality",
    "auth_welcome_subtitle": "Connectez-vous ou associez votre moniteur Foobot",
    "auth_tab_login": "Connexion Site",
    "auth_tab_ticket": "Code Ticket (QR)",
    "auth_server_url_label": "URL Serveur Foobot / Site Web",
    "auth_server_url_hint": "Adresse du serveur hébergeant la base de données Foobot",
    "auth_username_label": "Nom d'utilisateur ou Email",
    "auth_password_label": "Mot de passe",
    "auth_btn_login": "Se connecter et lier",
    "auth_ticket_label": "Code Ticket d'Association",
    "auth_ticket_hint": "Générez le code depuis le site dans la rubrique « App Mobile PWA »",
    "auth_btn_ticket": "Associer par Ticket",
    "auth_btn_logout": "Déconnexion",
    "auth_error_network": "Impossible de joindre le serveur spécifié. Veuillez vérifier l'URL saisie.",
    "auth_error_creds": "Identifiants incorrects. Veuillez vérifier votre nom d'utilisateur et votre mot de passe.",
    "auth_error_ticket": "Code ticket invalide ou expiré.",
    "auth_success_connected": "Connexion établie avec succès !",
    "devices_screen_title": "Liste des Foobots",
    "drop_device_name": "Appareil",
    "drop_location": "Emplacement",
    "drop_connection_status": "État de Connexion",
    "drop_device_uuid": "UUID Capteur",
    "drop_last_seen": "Dernière Mesure Reçue",
    "drop_last_connected": "Dernière Connexion",
    "drop_last_disconnected": "Dernière Déconnexion",
    "drop_count_24h": "Mesures (24h)",
    "drop_count_total": "Total des Mesures",
    "conn_active_regular": "Actif (envoi régulier)",
    "conn_inactive_15m": "Aucune donnée depuis >15 min",
    "devices_loading": "Chargement des informations de connexion des appareils Foobot..."
  },
  "de": {
    "settings_title": "Einstellungen",
    "settings_language": "Oberflächensprache",
    "settings_temp_unit": "Temperatureinheit",
    "settings_temp_celsius": "Celsius (°C)",
    "settings_temp_fahrenheit": "Fahrenheit (°F)",
    "settings_active_device": "Foobot-Gerät",
    "settings_connection": "DB-Serververbindung",
    "settings_connected_user": "Verbundener Benutzer",
    "settings_device_reg_id": "App-Geräte-ID:",
    "settings_server_site": "Webseite & DB:",
    "settings_status": "Status:",
    "status_demo_mode": "Grafikmodus (Demo)",
    "status_great": "HERVORRAGEND",
    "status_good": "GUT",
    "status_fair": "MÄSSIG",
    "status_poor": "SCHLECHT",
    "gpi_full_title": "GPI — Verschmutzungsindex",
    "sensor_allpollu_label": "GPI — Verschmutzungsindex",
    "sensor_voc_label": "VOC — Flüchtige Verbindungen",
    "sensor_pm_label": "PM2.5 — Feinstaub",
    "sensor_co2_label": "eCO2 — Äquivalent",
    "sensor_tmp_label": "TMP — Raumtemperatur",
    "sensor_hum_label": "HUM — Relative Feuchtigkeit",
    "last_data_received": "Zuletzt empfangene Daten:",
    "tf_minutes": "Minuten",
    "tf_hours": "Stunden",
    "tf_7d": "7 T.",
    "tf_10d": "10 T.",
    "tf_30d": "30 T.",
    "tf_weeks": "Wochen",
    "tf_custom_days_ph": "Anz. Tage",
    "btn_go": "Los",
    "date_from": "VON:",
    "date_to": "BIS:",
    "btn_filter": "Filtern",
    "btn_reset": "Zurücksetzen",
    "desc_allpollu": "Globaler Innenraumluft-Qualitätsindex, der von Foobot aus Feinstaub und flüchtigen Verbindungen berechnet wird.",
    "desc_voc": "Flüchtige organische Verbindungen (VOC) entstehen durch Reinigungsmittel, Farben, Parfüms, Rauch und Kochen.",
    "desc_pm": "Feinstaub (PM2.5) umfasst mikroskopische Partikel, die tief in die Atemwege eindringen können.",
    "desc_co2": "Geschätztes Kohlendioxid-Äquivalent, das mit der Anwesenheit von Menschen in geschlossenen Räumen korreliert.",
    "desc_tmp": "Vom integrierten Foobot-Thermosensor gemessene Raumtemperatur.",
    "desc_hum": "Prozentsatz der relativen Luftfeuchtigkeit in der Raumluft.",
    "install_banner_title": "NewFoobot installieren",
    "install_banner_desc": "Laden Sie die App auf Ihr Gerät herunter, um sie im Vollbildmodus zu öffnen",
    "install_banner_btn": "Installieren",
    "auth_welcome_title": "NewFoobot Air Quality",
    "auth_welcome_subtitle": "Anmelden oder Foobot-Luftmonitor koppeln",
    "auth_tab_login": "Webseite Login",
    "auth_tab_ticket": "Ticket-Code (QR)",
    "auth_server_url_label": "Foobot Server / Website-URL",
    "auth_server_url_hint": "Serveradresse, auf der die Foobot-Datenbank gehostet wird",
    "auth_username_label": "Benutzername oder E-Mail",
    "auth_password_label": "Passwort",
    "auth_btn_login": "Anmelden & Verbinden",
    "auth_ticket_label": "Kopplungs-Ticketcode",
    "auth_ticket_hint": "Erzeugen Sie den Code auf der Webseite unter „Mobile App PWA“",
    "auth_btn_ticket": "Mit Ticket verbinden",
    "auth_btn_logout": "Abmelden",
    "auth_error_network": "Der angegebene Server konnte nicht erreicht werden. Bitte überprüfen Sie die URL.",
    "auth_error_creds": "Ungültige Anmeldedaten. Bitte überprüfen Sie Benutzername und Passwort.",
    "auth_error_ticket": "Ungültiger oder abgelaufener Ticketcode.",
    "auth_success_connected": "Verbindung erfolgreich hergestellt!",
    "devices_screen_title": "Foobot-Übersicht",
    "drop_device_name": "Gerät",
    "drop_location": "Standort",
    "drop_connection_status": "Verbindungsstatus",
    "drop_device_uuid": "Sensor-UUID",
    "drop_last_seen": "Letzte Messung",
    "drop_last_connected": "Letzte Verbindung",
    "drop_last_disconnected": "Letzte Trennung",
    "drop_count_24h": "Messungen (24h)",
    "drop_count_total": "Gesamtmessungen",
    "conn_active_regular": "Aktiv (regelmäßige Übertragung)",
    "conn_inactive_15m": "Keine Daten seit >15 Min",
    "devices_loading": "Verbindungsdaten der Foobot-Geräte werden geladen..."
  },
  "es": {
    "settings_title": "Ajustes",
    "settings_language": "Idioma de la Interfaz",
    "settings_temp_unit": "Unidad de Temperatura",
    "settings_temp_celsius": "Celsius (°C)",
    "settings_temp_fahrenheit": "Fahrenheit (°F)",
    "settings_active_device": "Dispositivo Foobot",
    "settings_connection": "Enlace Servidor BD",
    "settings_connected_user": "Usuario Conectado",
    "settings_device_reg_id": "ID Dispositivo App:",
    "settings_server_site": "Sitio Web y BD:",
    "settings_status": "Estado:",
    "status_demo_mode": "Modo Gráfico (Demo)",
    "status_great": "EXCELENTE",
    "status_good": "BUENO",
    "status_fair": "MEDIO",
    "status_poor": "DEFICIENTE",
    "gpi_full_title": "GPI — Índice de Contaminación",
    "sensor_allpollu_label": "GPI — Índice de Contaminación",
    "sensor_voc_label": "COV — Compuestos Volátiles",
    "sensor_pm_label": "PM2.5 — Partículas Finas",
    "sensor_co2_label": "eCO2 — Equivalente",
    "sensor_tmp_label": "TMP — Temperatura Ambiente",
    "sensor_hum_label": "HUM — Humedad Relativa",
    "last_data_received": "Último dato recibido:",
    "tf_minutes": "Minutos",
    "tf_hours": "Horas",
    "tf_7d": "7 d",
    "tf_10d": "10 d",
    "tf_30d": "30 d",
    "tf_weeks": "Semanas",
    "tf_custom_days_ph": "N° días",
    "btn_go": "Ir",
    "date_from": "DESDE:",
    "date_to": "HASTA:",
    "btn_filter": "Filtrar",
    "btn_reset": "Restablecer",
    "desc_allpollu": "Índice global de calidad del aire interior calculado por Foobot combinando partículas finas y compuestos volátiles.",
    "desc_voc": "Los compuestos orgánicos volátiles (COV) son emitidos por productos de limpieza, pinturas, perfumes, humo y al cocinar.",
    "desc_pm": "Las partículas finas (PM2.5) comprenden polvo microscópico capaz de penetrar profundamente en las vías respiratorias.",
    "desc_co2": "Estimación del dióxido de carbono equivalente relacionada con la presencia humana en espacios cerrados.",
    "desc_tmp": "Temperatura medida por el sensor térmico integrado en Foobot.",
    "desc_hum": "Porcentaje de humedad relativa en el ambiente interior.",
    "install_banner_title": "Instalar NewFoobot",
    "install_banner_desc": "Descarga la app en tu dispositivo para abrirla a pantalla completa",
    "install_banner_btn": "Instalar",
    "auth_welcome_title": "NewFoobot Air Quality",
    "auth_welcome_subtitle": "Inicia sesión o vincula tu monitor Foobot",
    "auth_tab_login": "Acceso Sitio",
    "auth_tab_ticket": "Código Ticket (QR)",
    "auth_server_url_label": "URL Servidor Foobot / Sitio Web",
    "auth_server_url_hint": "Dirección del servidor donde reside la base de datos Foobot",
    "auth_username_label": "Nombre de Usuario o Email",
    "auth_password_label": "Contraseña",
    "auth_btn_login": "Iniciar Sesión y Conectar",
    "auth_ticket_label": "Código Ticket de Vinculación",
    "auth_ticket_hint": "Genera el código en el sitio dentro de la sección \"App Móvil PWA\"",
    "auth_btn_ticket": "Vincular con Ticket",
    "auth_btn_logout": "Desconectar",
    "auth_error_network": "No se pudo conectar con el servidor especificado. Verifica la URL introducida.",
    "auth_error_creds": "Credenciales incorrectas. Verifica tu usuario y contraseña.",
    "auth_error_ticket": "Código ticket no válido o caducado.",
    "auth_success_connected": "¡Conexión establecida con éxito!",
    "devices_screen_title": "Lista de Foobots",
    "drop_device_name": "Dispositivo",
    "drop_location": "Ubicación",
    "drop_connection_status": "Estado de Conexión",
    "drop_device_uuid": "UUID Sensor",
    "drop_last_seen": "Última Lectura Recibida",
    "drop_last_connected": "Última Conexión",
    "drop_last_disconnected": "Última Desconexión",
    "drop_count_24h": "Lecturas (24h)",
    "drop_count_total": "Total de Lecturas",
    "conn_active_regular": "Activo (envío regular)",
    "conn_inactive_15m": "Sin datos desde >15 min",
    "devices_loading": "Cargando datos de conexión de los dispositivos Foobot..."
  }
};

  // ==========================================================================
  // 2. LIVE INTEGRATION API — COLLEGAMENTO AL DATABASE DEL SITO
  // ==========================================================================
  const API_CONFIG = {
    // Risoluzione dinamica base URL: memorizza o ricava automaticamente
    baseUrl: (function () {
      const stored = localStorage.getItem('nf_custom_server_url');
      if (stored) {
        return stored.replace(/\/+$/, '') + '/api/';
      }
      if (window.location.protocol.startsWith('http')) {
        const cleanPath = window.location.pathname
          .replace(/\/index\.html$/i, '')
          .replace(/\/app\/?$/i, '');
        return window.location.origin + cleanPath + '/api/';
      }
      return 'https://foobot-sensore.barbaralerici.net/api/';
    })(),
    deviceTokenKey: 'nf_device_token',
    activeDeviceKey: 'nf_active_uuid',
    pollIntervalMs: 15000 // Polling live automatico ogni 15 secondi
  };

  // Cache dinamica dei dati caricati dal DB reale
  const liveStore = {
    devices: [],
    activeUuid: localStorage.getItem(API_CONFIG.activeDeviceKey) || '',
    activeDeviceName: 'Foobot',
    connectedUser: localStorage.getItem('nf_connected_user') || '',
    deviceRegId: localStorage.getItem('nf_device_reg_id') || '',
    lastReading: null,
    historySeries: null,
    historyLabels: [],
    historyDates: [],
    currentOffset: 0,
    customStartDate: '',
    customEndDate: '',
    token: (function () {
      // 1. Controlla parametro query '?ticket=NF-...' o '?token=...'
      const params = new URLSearchParams(window.location.search);
      const ticketParam = params.get('ticket') || params.get('ticket_code');
      const tokenParam  = params.get('token') || params.get('device_token');
      if (tokenParam) {
        localStorage.setItem(API_CONFIG.deviceTokenKey, tokenParam);
        return tokenParam;
      }
      return localStorage.getItem(API_CONFIG.deviceTokenKey) || '';
    })(),
    isClaimingTicket: false,
    pollTimer: null
  };

  // Seriale univoco e permanente del client salvato in localStorage
  function getOrCreateDeviceSerial() {
    let serial = localStorage.getItem('nf_device_serial');
    if (!serial || !/^NF-DEV-[A-Z0-9]{4}-[A-Z0-9]{4}$/i.test(serial)) {
      const p1 = Math.random().toString(36).substring(2, 6).toUpperCase();
      const p2 = Math.random().toString(36).substring(2, 6).toUpperCase();
      serial = `NF-DEV-${p1.padEnd(4, 'X')}-${p2.padEnd(4, 'X')}`;
      localStorage.setItem('nf_device_serial', serial);
    }
    return serial;
  }

  // Rileva se l'app è in esecuzione come PWA installata / standalone o in una normale scheda del browser
  function getAppExecutionMode() {
    if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true || (document.referrer && document.referrer.includes('android-app://'))) {
      return 'standalone';
    }
    return 'browser';
  }

  // Memorizza e aggiorna a schermo l'identificativo numerico di registrazione nel database (es. #12)
  // e recepisce eventuale etichetta testuale settings_device_reg_id fornita dal sito PHP
  function setDeviceRegistrationId(regId, phpPayload = null) {
    if (phpPayload && typeof phpPayload === 'object') {
      if (phpPayload.i18n_labels && typeof phpPayload.i18n_labels === 'object') {
        if (phpPayload.i18n_labels.it && phpPayload.i18n_labels.it.settings_device_reg_id) {
          I18N_DICTIONARY.it.settings_device_reg_id = String(phpPayload.i18n_labels.it.settings_device_reg_id);
        }
        if (phpPayload.i18n_labels.en && phpPayload.i18n_labels.en.settings_device_reg_id) {
          I18N_DICTIONARY.en.settings_device_reg_id = String(phpPayload.i18n_labels.en.settings_device_reg_id);
        }
      } else if (phpPayload.settings_device_reg_id && state.lang === 'it') {
        I18N_DICTIONARY.it.settings_device_reg_id = String(phpPayload.settings_device_reg_id);
      }
      document.querySelectorAll('[data-i18n="settings_device_reg_id"]').forEach(el => {
        el.textContent = t('settings_device_reg_id');
      });
    }

    const clean = regId ? String(regId).replace(/[^0-9]/g, '') : '';
    if (clean) {
      liveStore.deviceRegId = clean;
      localStorage.setItem('nf_device_reg_id', clean);
    } else if (regId === null || regId === '' || regId === 0) {
      liveStore.deviceRegId = '';
      localStorage.removeItem('nf_device_reg_id');
    }
    const val = liveStore.deviceRegId || localStorage.getItem('nf_device_reg_id') || '';
    document.querySelectorAll('#sidebarDeviceRegId, [data-db-field="device_reg_id"]').forEach(el => {
      el.textContent = val ? `#${val}` : '--';
    });
  }

  // Helper fetch autenticata (trasmette X-Device-Token o include credenziali sessione cookie)
  async function apiFetch(endpoint, options = {}) {
    let url = endpoint.startsWith('http') ? endpoint : (API_CONFIG.baseUrl + endpoint);
    const headers = options.headers || {};

    if (liveStore.token) {
      headers['X-Device-Token'] = liveStore.token;
      if (!url.includes('token=')) {
        const sep = url.includes('?') ? '&' : '?';
        url += `${sep}token=${encodeURIComponent(liveStore.token)}`;
      }
    }
    headers['X-Device-Serial'] = getOrCreateDeviceSerial();
    headers['X-App-Mode'] = getAppExecutionMode();
    headers['X-Requested-With'] = 'XMLHttpRequest';

    options.headers = headers;
    options.credentials = 'include'; // Consente di riutilizzare il cookie di sessione web se presente

    const res = await fetch(url, options);
    if (!res.ok) {
      const err = new Error(`HTTP Error ${res.status}`);
      err.status = res.status;
      throw err;
    }
    return await res.json();
  }

  // Riscatto ticket di accoppiamento automatico (se presente '?ticket=NF-...' nell'URL)
  async function checkAndClaimPairingTicket() {
    const params = new URLSearchParams(window.location.search);
    const ticket = params.get('ticket') || params.get('ticket_code');
    if (!ticket) return false;

    // Pre-popola e mostra la tab ticket con feedback visivo immediato
    const inputTicket = document.getElementById('inputAuthTicket');
    if (inputTicket) inputTicket.value = ticket;
    const tabBtnTicket = document.getElementById('tabBtnTicket');
    const formAuthTicket = document.getElementById('formAuthTicket');
    const tabBtnCreds = document.getElementById('tabBtnCredentials');
    const formAuthLogin = document.getElementById('formAuthLogin');
    if (tabBtnTicket && formAuthTicket && tabBtnCreds && formAuthLogin) {
      tabBtnCreds.classList.remove('active');
      formAuthLogin.classList.remove('active');
      tabBtnTicket.classList.add('active');
      formAuthTicket.classList.add('active');
    }
    setAuthAlert('info', 'Verifica ticket di accoppiamento in corso...');

    try {
      liveStore.isClaimingTicket = true;
      const serial = getOrCreateDeviceSerial();
      const mode = getAppExecutionMode();
      const res = await fetch(API_CONFIG.baseUrl + 'device_auth.php?action=claim_ticket', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({
          ticket_code: ticket,
          device_serial: serial,
          app_mode: mode,
          device_name: (mode === 'standalone' ? 'App Mobile PWA' : 'Scheda Browser') + ' (' + (navigator.platform || 'Device') + ')',
          platform: navigator.userAgent
        })
      });

      const json = await res.json();
      if (json && json.success && json.device_token) {
        liveStore.token = json.device_token;
        localStorage.setItem(API_CONFIG.deviceTokenKey, liveStore.token);
        if (json.device_serial) {
          localStorage.setItem('nf_device_serial', json.device_serial);
        }
        if (json.device_reg_id || json.device_id) {
          setDeviceRegistrationId(json.device_reg_id || json.device_id, json);
        }

        if (json.user) {
          liveStore.connectedUser = json.user;
          localStorage.setItem('nf_connected_user', json.user);
        }

        if (json.default_uuid) {
          liveStore.activeUuid = json.default_uuid;
          localStorage.setItem(API_CONFIG.activeDeviceKey, json.default_uuid);
        }

        if (json.dashboard_lang) {
          syncDashboardLanguage(json.dashboard_lang);
        }

        setAuthAlert('success', json.message || t('auth_success_connected'));
        showScreen('screenHome');

        // Pulisce l'URL dal ticket per evitare riscatti multipli dello stesso token monouso
        if (window.history.replaceState) {
          const cleanUrl = window.location.pathname;
          window.history.replaceState({ screen: 'screenHome' }, document.title, cleanUrl);
        }
        return true;
      } else {
        const errMsg = (json && json.message) ? json.message : t('auth_error_ticket');
        setAuthAlert('error', errMsg);
      }
    } catch (e) {
      console.warn('Errore claim ticket:', e);
      setAuthAlert('error', t('auth_error_network'));
    } finally {
      liveStore.isClaimingTicket = false;
    }

    // Fallback automatico: se il ticket monouso era già stato consumato in precedenza
    // ma l'utente ha una sessione attiva sul sito nello stesso browser, esegue direct_pair
    if (!liveStore.token) {
      await tryDirectSessionPair();
    }

    if (!liveStore.token) {
      showScreen('screenAuth');
    }
    return false;
  }

  // Accoppiamento diretto tramite sessione web PHP (se aperta nello stesso browser)
  async function tryDirectSessionPair() {
    try {
      const serial = getOrCreateDeviceSerial();
      const mode = getAppExecutionMode();
      const res = await fetch(API_CONFIG.baseUrl + 'device_auth.php?action=direct_pair', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({
          device_serial: serial,
          app_mode: mode,
          device_name: (mode === 'standalone' ? 'App Installata (PWA)' : 'Scheda Browser') + ' (' + (navigator.platform || 'Web') + ')',
          platform: navigator.userAgent
        })
      });
      if (!res.ok) return false;
      const json = await res.json();
      if (json && json.success && json.device_token) {
        liveStore.token = json.device_token;
        localStorage.setItem(API_CONFIG.deviceTokenKey, liveStore.token);
        if (json.device_serial) {
          localStorage.setItem('nf_device_serial', json.device_serial);
        }
        if (json.device_reg_id || json.device_id) {
          setDeviceRegistrationId(json.device_reg_id || json.device_id);
        }
        if (json.user) {
          liveStore.connectedUser = json.user;
          localStorage.setItem('nf_connected_user', json.user);
        }
        if (json.default_uuid && !liveStore.activeUuid) {
          liveStore.activeUuid = json.default_uuid;
          localStorage.setItem(API_CONFIG.activeDeviceKey, json.default_uuid);
        }
        if (window.history.replaceState && window.location.search.includes('ticket')) {
          window.history.replaceState({}, document.title, window.location.pathname);
        }
        return true;
      }
    } catch (e) {
      // Sessione web non attiva o errore di rete: ignora silenziosamente
    }
    return false;
  }

  // Fallback demo qualora il server sia offline o in testing locale senza DB
  const DB_FALLBACK_DATA = {
    device_name: 'Foobot 01',
    device_uuid: '2402000000000000',
    last_reading_datetime: '03/11/2026 16:02:33',
    readings: {
      allpollu: 16,
      voc: 2000,
      pm: 9.5,
      co2: 928,
      tmp: 21.5,
      hum: 51.5
    },
    history: {
      voc:      [1480, 1560, 1650, 1720, 1840, 1930, 2020, 2110, 2130, 2115, 2070, 2010, 1960, 1910, 1850],
      pm:       [6.2, 6.8, 7.5, 8.1, 9.0, 10.4, 11.8, 12.6, 12.9, 12.2, 11.4, 10.5, 9.9, 9.6, 9.5],
      co2:      [680, 710, 750, 790, 840, 890, 940, 980, 995, 985, 960, 945, 935, 930, 928],
      allpollu: [68, 72, 76, 82, 88, 94, 98, 100, 100, 99, 97, 96, 98, 99, 100],
      tmp:      [20.5, 20.8, 21.0, 21.4, 21.8, 22.1, 22.4, 22.6, 22.5, 22.3, 22.0, 21.8, 21.6, 21.5, 21.5],
      hum:      [46.0, 47.2, 48.5, 49.0, 50.2, 51.4, 52.8, 53.5, 53.2, 52.6, 52.0, 51.8, 51.6, 51.5, 51.5]
    },
    labels: ['14:50', '14:55', '15:00', '15:05', '15:10', '15:15', '15:20', '15:25', '15:30', '15:35', '15:40', '15:45', '15:50', '15:55', '16:00']
  };

  // Configurazione sensori, unità, colori SaaS dedicati e soglie
  const SENSOR_METADATA = {
    voc: {
      titleKey: 'sensor_voc_label',
      descKey: 'desc_voc',
      unit: 'ppb',
      capsuleBorder: '#faefb8',
      chartColor: '#eab308',      // Giallo/Ambra vivo per VOC
      chartFill: 'rgba(234, 179, 8, 0.14)',
      minScale: 0,
      maxScale: 450,
      thresholds: [
        { label: '0 — 150 ppb', statusKey: 'status_great', cls: 'th-band-great' },
        { label: '150 — 300 ppb', statusKey: 'status_good', cls: 'th-band-good' },
        { label: '300 — 450 ppb', statusKey: 'status_fair', cls: 'th-band-fair' },
        { label: '> 450 ppb', statusKey: 'status_poor', cls: 'th-band-poor' }
      ]
    },
    pm: {
      titleKey: 'sensor_pm_label',
      descKey: 'desc_pm',
      unit: 'µg/m³',
      capsuleBorder: '#c9d8fe',
      chartColor: '#3b82f6',      // Blu SaaS per PM2.5
      chartFill: 'rgba(59, 130, 246, 0.14)',
      minScale: 0,
      maxScale: 25,
      thresholds: [
        { label: '0 — 12.5 µg/m³', statusKey: 'status_great', cls: 'th-band-great' },
        { label: '12.5 — 25.0 µg/m³', statusKey: 'status_good', cls: 'th-band-good' },
        { label: '25.0 — 37.5 µg/m³', statusKey: 'status_fair', cls: 'th-band-fair' },
        { label: '> 37.5 µg/m³', statusKey: 'status_poor', cls: 'th-band-poor' }
      ]
    },
    co2: {
      titleKey: 'sensor_co2_label',
      descKey: 'desc_co2',
      unit: 'ppm',
      capsuleBorder: '#fad5d8',
      chartColor: '#ec4899',      // Rosa/Magenta vivo per eCO2
      chartFill: 'rgba(236, 72, 153, 0.14)',
      minScale: 350,
      maxScale: 1000,
      thresholds: [
        { label: '450 — 625 ppm', statusKey: 'status_great', cls: 'th-band-great' },
        { label: '625 — 1300 ppm', statusKey: 'status_good', cls: 'th-band-good' },
        { label: '1300 — 1925 ppm', statusKey: 'status_fair', cls: 'th-band-fair' },
        { label: '> 1925 ppm', statusKey: 'status_poor', cls: 'th-band-poor' }
      ]
    },
    allpollu: {
      titleKey: 'sensor_allpollu_label',
      descKey: 'desc_allpollu',
      unit: '%',
      capsuleBorder: '#bbf7d0',
      chartColor: '#10b981',      // Verde smeraldo per GPI
      chartFill: 'rgba(16, 185, 129, 0.14)',
      minScale: 0,
      maxScale: 100,
      thresholds: [
        { label: '0 — 25 %', statusKey: 'status_great', cls: 'th-band-great' },
        { label: '25 — 50 %', statusKey: 'status_good', cls: 'th-band-good' },
        { label: '50 — 75 %', statusKey: 'status_fair', cls: 'th-band-fair' },
        { label: '75 — 100 %', statusKey: 'status_poor', cls: 'th-band-poor' }
      ]
    },
    tmp: {
      titleKey: 'sensor_tmp_label',
      descKey: 'desc_tmp',
      unit: 'C°',
      capsuleBorder: '#c9d8fe',
      chartColor: '#f97316',      // Arancio temperatura
      chartFill: 'rgba(249, 115, 22, 0.14)',
      minScale: 10,
      maxScale: 32,
      thresholds: [
        { label: '18 — 24 °C', statusKey: 'status_great', cls: 'th-band-great' },
        { label: '16 — 18 / 24 — 26 °C', statusKey: 'status_good', cls: 'th-band-good' },
        { label: '14 — 16 / 26 — 28 °C', statusKey: 'status_fair', cls: 'th-band-fair' },
        { label: '< 14 / > 28 °C', statusKey: 'status_poor', cls: 'th-band-poor' }
      ]
    },
    hum: {
      titleKey: 'sensor_hum_label',
      descKey: 'desc_hum',
      unit: '%',
      capsuleBorder: '#c9d8fe',
      chartColor: '#06b6d4',      // Ciano acqua per umidità
      chartFill: 'rgba(6, 182, 212, 0.14)',
      minScale: 0,
      maxScale: 100,
      thresholds: [
        { label: '40 — 60 %', statusKey: 'status_great', cls: 'th-band-great' },
        { label: '30 — 40 / 60 — 70 %', statusKey: 'status_good', cls: 'th-band-good' },
        { label: '20 — 30 / 70 — 80 %', statusKey: 'status_fair', cls: 'th-band-fair' },
        { label: '< 20 / > 80 %', statusKey: 'status_poor', cls: 'th-band-poor' }
      ]
    }
  };

  // Stato interno dell'interfaccia
  const state = {
    lang: localStorage.getItem('nf_pwa_lang') || 'it',
    tempUnit: localStorage.getItem('nf_pwa_temp_unit') || 'C',
    activeDeviceId: 'foobot_01',
    selectedSensor: 'voc',
    activeTimeframe: '24h',
    chartInstance: null
  };

  // ==========================================================================
  // 3. INIZIALIZZAZIONE APPLICAZIONE & COLLEGAMENTO LIVE
  // ==========================================================================
  window.addEventListener('DOMContentLoaded', async () => {
    registerServiceWorker();
    restoreCachedLanguages();
    bindEvents();
    applyTranslations(state.lang);
    syncTempUnitButtons();
    setDeviceRegistrationId(liveStore.deviceRegId);

    const hasTicketParam = (new URLSearchParams(window.location.search).has('ticket') || new URLSearchParams(window.location.search).has('ticket_code'));

    // Mostra immediatamente una schermata iniziale per evitare qualsiasi ritardo o schermo bianco durante i fetch di rete
    if (liveStore.token && !hasTicketParam) {
      showScreen('screenHome');
    } else {
      showScreen('screenAuth');
      populateServerUrlInput();
    }

    // Scansione asincrona delle lingue disponibili nel server
    syncAvailableLanguagesAndDefault();

    // Mostra il banner di installazione in testata per 1 minuto (solo se non è già installata)
    setTimeout(() => {
      if (!isAppInstalledOrStandalone()) {
        showInstallBanner();
      }
    }, 1500);

    // 1. Controlla se c'è un ticket di accoppiamento da riscattare nell'URL
    await checkAndClaimPairingTicket();

    // 2. Carica la lista dei dispositivi e l'ultima lettura reale dal database
    await initDeviceAndData();

    // 3. Avvia il polling continuo per mantenere i numeri sempre aggiornati
    startLivePolling();
  });

  function registerServiceWorker() {
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.register('./sw.js?v=1.0').catch(() => {});
    }
  }

  // ==========================================================================
  // GESTIONE PWA INSTALL BANNER IN TESTATA (Resta per 1 minuto, poi scompare)
  // ==========================================================================
  let deferredInstallPrompt = null;
  let installBannerTimer = null;

  function isAppInstalledOrStandalone() {
    return (
      window.matchMedia('(display-mode: standalone)').matches ||
      window.matchMedia('(display-mode: window-controls-overlay)').matches ||
      window.navigator.standalone === true ||
      document.referrer.includes('android-app://')
    );
  }

  function showInstallBanner() {
    if (isAppInstalledOrStandalone()) return;
    const banner = document.getElementById('pwaInstallBanner');
    if (!banner) return;

    banner.classList.add('visible');

    // Resta visibile per 1 minuto (60.000 ms), poi scompare automaticamente
    if (installBannerTimer) clearTimeout(installBannerTimer);
    installBannerTimer = setTimeout(() => {
      dismissInstallBanner();
    }, 60000);
  }

  function dismissInstallBanner() {
    if (installBannerTimer) {
      clearTimeout(installBannerTimer);
      installBannerTimer = null;
    }
    const banner = document.getElementById('pwaInstallBanner');
    if (banner) banner.classList.remove('visible');
  }

  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredInstallPrompt = e;
    showInstallBanner();
  });

  window.addEventListener('appinstalled', () => {
    deferredInstallPrompt = null;
    dismissInstallBanner();
  });

  function triggerPwaInstall() {
    if (deferredInstallPrompt) {
      deferredInstallPrompt.prompt();
      deferredInstallPrompt.userChoice.then((choice) => {
        if (choice && choice.outcome === 'accepted') {
          deferredInstallPrompt = null;
        }
        dismissInstallBanner();
      });
    } else {
      const isIos = /iphone|ipad|ipod/.test(window.navigator.userAgent.toLowerCase());
      if (isIos) {
        alert('Per installare l\'app su iPhone o iPad:\n1. Tocca l\'icona Condividi in basso nel browser\n2. Seleziona "Aggiungi alla schermata Home"');
      } else {
        alert('L\'applicazione è già installata sul dispositivo oppure puoi installarla dal menu del browser selezionando "Installa applicazione".');
      }
      dismissInstallBanner();
    }
  }

  // ==========================================================================
  // GESTIONE NAVIGAZIONE SCHERMATE E PULSANTE INDIETRO ANDROID / iOS (History API)
  // ==========================================================================
  let ignoringNextPopState = false;

  function getCurrentScreenId() {
    const screens = ['screenAuth', 'screenHome', 'screenDetail', 'screenDevices'];
    for (let i = 0; i < screens.length; i++) {
      const el = document.getElementById(screens[i]);
      if (el && el.classList.contains('active')) return screens[i];
    }
    return 'screenHome';
  }

  // Controllo e commutazione schermata attiva con sincronizzazione History API
  function showScreen(screenId, fromPopState = false) {
    const prevScreen = getCurrentScreenId();
    const screens = ['screenAuth', 'screenHome', 'screenDetail', 'screenDevices'];
    screens.forEach(id => {
      const el = document.getElementById(id);
      if (el) {
        el.classList.toggle('active', id === screenId);
      }
    });
    window.scrollTo(0, 0);

    if (!fromPopState && window.history && window.history.pushState) {
      const currentState = window.history.state;
      if (screenId === 'screenHome' || screenId === 'screenAuth') {
        window.history.replaceState({ screen: screenId }, '');
      } else if (currentState && currentState.overlay) {
        // Se stavamo aprendo una schermata partendo da una sidebar/modale aperta, sostituisce lo stato dell'overlay
        window.history.replaceState({ screen: screenId, prev: prevScreen }, '');
      } else if (screenId !== prevScreen) {
        window.history.pushState({ screen: screenId, prev: prevScreen }, '');
      }
    }
  }

  function navigateBackFromSubScreen(fallbackScreenId) {
    const target = fallbackScreenId || 'screenHome';
    if (window.history && window.history.state && (window.history.state.screen === 'screenDetail' || window.history.state.screen === 'screenDevices')) {
      window.history.back();
    } else {
      showScreen(target, false);
    }
  }

  // Intercettazione pulsante fisico / gesto Indietro di Android e browser mobile
  window.addEventListener('popstate', (e) => {
    if (ignoringNextPopState) {
      ignoringNextPopState = false;
      return;
    }

    // 1. Se la modale informazioni è aperta, il comando Indietro la chiude senza cambiare pagina
    const infoModalBackdrop = document.getElementById('infoModalBackdrop');
    if (infoModalBackdrop && infoModalBackdrop.classList.contains('open')) {
      infoModalBackdrop.classList.remove('open');
      return;
    }

    // 2. Se la sidebar impostazioni è aperta, il comando Indietro la chiude senza cambiare pagina
    const sidebar = document.getElementById('settingsSidebar');
    if (sidebar && sidebar.classList.contains('open')) {
      toggleSidebar(false, true);
      return;
    }

    // 3. Se la sezione filtro date personalizzate è espansa, la chiude
    const customDateSection = document.getElementById('customDateSection');
    const btnToggleCustomDates = document.getElementById('btnToggleCustomDates');
    if (customDateSection && customDateSection.classList.contains('open')) {
      customDateSection.classList.remove('open');
      if (btnToggleCustomDates) {
        btnToggleCustomDates.classList.remove('active');
        btnToggleCustomDates.setAttribute('aria-expanded', 'false');
      }
      if (window.history && window.history.pushState) {
        window.history.pushState({ screen: getCurrentScreenId() }, '');
      }
      return;
    }

    // 4. Ritorno dalla schermata Dettaglio o Elenco Foobot alla schermata precedente
    const currentScreen = getCurrentScreenId();
    if (e.state && e.state.screen) {
      showScreen(e.state.screen, true);
    } else if (currentScreen === 'screenDetail' || currentScreen === 'screenDevices') {
      const fallback = (currentScreen === 'screenDevices' && previousScreenBeforeDevices)
        ? previousScreenBeforeDevices
        : 'screenHome';
      showScreen(fallback, true);
    }
  });

  function populateServerUrlInput() {
    const input = document.getElementById('inputServerUrl');
    if (input) {
      const stored = localStorage.getItem('nf_custom_server_url');
      if (stored) {
        input.value = stored;
      } else if (window.location.protocol.startsWith('http')) {
        const clean = window.location.origin + window.location.pathname.replace(/\/index\.html$/i, '').replace(/\/app\/?$/i, '');
        input.value = clean;
      } else {
        input.value = 'https://foobot-sensore.barbaralerici.net';
      }
    }
  }

  function setAuthAlert(type, message) {
    const box = document.getElementById('authAlertBox');
    if (!box) return;
    if (!message) {
      box.style.display = 'none';
      box.className = 'auth-alert-box';
      box.textContent = '';
      return;
    }
    box.className = `auth-alert-box alert-${type}`;
    box.textContent = message;
    box.style.display = 'block';
  }

  function updateServerBaseUrl(rawUrl) {
    let clean = (rawUrl || '').trim().replace(/\/+$/, '');
    if (!clean.startsWith('http://') && !clean.startsWith('https://')) {
      clean = 'https://' + clean;
    }
    clean = clean.replace(/\/(api|app)\/?$/i, '');
    localStorage.setItem('nf_custom_server_url', clean);
    API_CONFIG.baseUrl = clean + '/api/';
    const labelSite = document.getElementById('labelOriginalSiteUrl');
    const linkSite  = document.getElementById('linkOriginalSite');
    if (labelSite) labelSite.textContent = clean.replace(/^https?:\/\//i, '');
    if (linkSite) linkSite.href = clean;
  }

  // Restituisce il codice BCP-47 corrispondente per la formattazione di numeri e date
  function getLocaleCode(lang) {
    const map = {
      it: 'it-IT',
      en: 'en-US',
      fr: 'fr-FR',
      de: 'de-DE',
      es: 'es-ES'
    };
    return map[lang] || (lang ? `${lang}-${lang.toUpperCase()}` : 'it-IT');
  }

  // Ripristina eventuali dizionari di lingue extra scaricati in precedenza e memorizzati in localStorage
  function restoreCachedLanguages() {
    try {
      for (let i = 0; i < localStorage.length; i++) {
        const k = localStorage.key(i);
        if (k && k.startsWith('nf_pwa_cached_lang_')) {
          const code = k.replace('nf_pwa_cached_lang_', '');
          if (code && !I18N_DICTIONARY[code]) {
            const parsed = JSON.parse(localStorage.getItem(k));
            if (parsed && typeof parsed === 'object') {
              I18N_DICTIONARY[code] = parsed;
            }
          }
        }
      }
    } catch (e) {
      console.warn('Errore nel recupero lingue in cache locale:', e);
    }
  }

  // Helper di traduzione con fallback a cascata (lingua attiva -> it -> en -> chiave grezza)
  function t(key) {
    const cur = I18N_DICTIONARY[state.lang];
    if (cur && cur[key] !== undefined && cur[key] !== '') {
      return cur[key];
    }
    if (I18N_DICTIONARY.it && I18N_DICTIONARY.it[key] !== undefined) {
      return I18N_DICTIONARY.it[key];
    }
    if (I18N_DICTIONARY.en && I18N_DICTIONARY.en[key] !== undefined) {
      return I18N_DICTIONARY.en[key];
    }
    return key;
  }

  // Applica le traduzioni a tutti gli elementi provvisti di data-i18n
  function applyTranslations(lang) {
    state.lang = I18N_DICTIONARY[lang] ? lang : 'it';
    localStorage.setItem('nf_pwa_lang', state.lang);
    document.documentElement.lang = state.lang;

    const selectLang = document.getElementById('selectLanguage');
    if (selectLang) selectLang.value = state.lang;

    document.querySelectorAll('[data-i18n]').forEach(el => {
      const key = el.getAttribute('data-i18n');
      if (key) el.textContent = t(key);
    });

    document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
      const key = el.getAttribute('data-i18n-placeholder');
      if (key) el.setAttribute('placeholder', t(key));
    });

    document.querySelectorAll('[data-i18n-title]').forEach(el => {
      const key = el.getAttribute('data-i18n-title');
      if (key) el.setAttribute('title', t(key));
    });

    const meta = SENSOR_METADATA[state.selectedSensor];
    const detailTitle = document.getElementById('detailSensorTitle');
    if (meta && detailTitle) {
      detailTitle.textContent = t(meta.titleKey);
    }
  }

  // Imposta la lingua corrente con supporto al download asincrono per nuove lingue aggiunte alla cartella languages/ della PWA
  async function setLanguage(langCode, isUserManualChoice = false) {
    if (!langCode) return;
    const code = String(langCode).toLowerCase().trim();

    if (isUserManualChoice) {
      localStorage.setItem('nf_pwa_user_lang_override', 'true');
    }

    // Se la lingua non è ancora caricata in memoria, prova prima dal localStorage
    if (!I18N_DICTIONARY[code]) {
      const cached = localStorage.getItem('nf_pwa_cached_lang_' + code);
      if (cached) {
        try {
          I18N_DICTIONARY[code] = JSON.parse(cached);
        } catch (e) {}
      }
    }

    // Se non presente, tenta prima di scaricare il file JSON statico da ./languages/{code}.json
    if (!I18N_DICTIONARY[code]) {
      try {
        const staticRes = await fetch(`./languages/${encodeURIComponent(code)}.json`);
        if (staticRes.ok) {
          const dict = await staticRes.json();
          if (dict && typeof dict === 'object' && Object.keys(dict).length > 0) {
            I18N_DICTIONARY[code] = dict;
            try {
              localStorage.setItem('nf_pwa_cached_lang_' + code, JSON.stringify(dict));
            } catch (e) {}
          }
        }
      } catch (e) {}
    }

    // Se ancora non trovato (es. cartelle remote non allineate), interroga l'endpoint API dedicato della PWA
    if (!I18N_DICTIONARY[code]) {
      try {
        const res = await fetch(`${API_CONFIG.baseUrl}i18n.php?scope=pwa&action=get&lang=${encodeURIComponent(code)}`);
        if (res.ok) {
          const data = await res.json();
          if (data && data.status === 'ok' && data.translations) {
            I18N_DICTIONARY[code] = data.translations;
            try {
              localStorage.setItem('nf_pwa_cached_lang_' + code, JSON.stringify(data.translations));
            } catch (e) {}
          }
        }
      } catch (err) {
        console.warn('Impossibile scaricare dizionario lingua PWA dal server:', code, err);
      }
    }

    applyTranslations(code);
  }

  // Sincronizza la lingua predefinita della dashboard PHP con l'app (se l'utente non ha impostato un override manuale)
  function syncDashboardLanguage(dashboardLang) {
    if (!dashboardLang) return;
    const target = String(dashboardLang).toLowerCase().trim();
    if (localStorage.getItem('nf_pwa_user_lang_override') !== 'true') {
      if (target !== state.lang) {
        setLanguage(target, false);
      }
    }
  }

  // Scansiona dinamicamente le lingue presenti nella cartella app/languages/ del server
  // e popola in tempo reale il selettore nella sidebar
  async function syncAvailableLanguagesAndDefault(dashboardLangHint = null) {
    try {
      const selectLang = document.getElementById('selectLanguage');
      const res = await fetch(`${API_CONFIG.baseUrl}i18n.php?scope=pwa&action=languages`);
      if (!res.ok) return;
      const data = await res.json();

      if (data && data.status === 'ok') {
        if (Array.isArray(data.languages) && selectLang) {
          data.languages.forEach(item => {
            const c = String(item.code || '').toLowerCase().trim();
            if (!c) return;
            const fullLabel = item.full_label || item.label || c.toUpperCase();
            let opt = selectLang.querySelector(`option[value="${c}"]`);
            if (!opt) {
              opt = document.createElement('option');
              opt.value = c;
              opt.textContent = fullLabel;
              selectLang.appendChild(opt);
            } else {
              opt.textContent = fullLabel;
            }
          });
          selectLang.value = state.lang;
        }

        const effectiveLang = data.dashboard_lang || dashboardLangHint || data.system_default;
        if (effectiveLang) {
          syncDashboardLanguage(effectiveLang);
        }
      }
    } catch (e) {
      // In modalità offline, le lingue native (it, en, fr, de, es) rimangono regolarmente fruibili
    }
  }

  // Caricamento iniziale e sincronizzazione con le API del server PHP
  async function initDeviceAndData() {
    // Ripristina l'utente memorizzato da localStorage
    const cachedUser = localStorage.getItem('nf_connected_user');
    if (cachedUser) {
      liveStore.connectedUser = cachedUser;
      updateConnectionBadge(true);
    }

    // 1. Se non c'è token locale memorizzato, tenta il pairing istantaneo se la sessione browser è attiva
    if (!liveStore.token) {
      await tryDirectSessionPair();
    }

    // 2. Se ancora non c'è token (es. scheda in incognito o primo avvio senza associazione),
    // mostra la schermata di login (#screenAuth) e nasconde completamente tutte le altre
    if (!liveStore.token) {
      showScreen('screenAuth');
      populateServerUrlInput();
      updateConnectionBadge(false);
      return;
    }

    // 3. Se c'è un token, interroga il server per verificare validità e dispositivi
    try {
      const statusRes = await apiFetch('device_status.php' + (liveStore.activeUuid ? `?uuid=${encodeURIComponent(liveStore.activeUuid)}` : ''));
      if (statusRes && statusRes.status !== 'forbidden') {
        showScreen('screenHome');
        if (statusRes.device_reg_id || statusRes.device_id || statusRes.settings_device_reg_id) {
          setDeviceRegistrationId(statusRes.device_reg_id || statusRes.device_id, statusRes);
        }
        if (statusRes.connected_user || statusRes.user || statusRes.username) {
          setConnectedUsername(statusRes.connected_user || statusRes.user || statusRes.username);
        }
        if (statusRes.dashboard_lang) {
          syncDashboardLanguage(statusRes.dashboard_lang);
        }
        updateConnectionBadge(true, statusRes.device_name || 'Online');
        if (!liveStore.connectedUser || !liveStore.deviceRegId) {
          ensureConnectedUserResolved();
        }
        if (Array.isArray(statusRes.all_devices) && statusRes.all_devices.length > 0) {
          liveStore.devices = statusRes.all_devices;
          if (!liveStore.activeUuid) {
            liveStore.activeUuid = statusRes.all_devices[0].device_uuid;
            localStorage.setItem(API_CONFIG.activeDeviceKey, liveStore.activeUuid);
          }
          populateDeviceDropdowns(statusRes.all_devices);
        }
        syncActiveDeviceStatusDots(statusRes.online);
        await fetchLiveReading();
      } else {
        // Token non più valido o revocato dal pannello: torna a schermata di login
        liveStore.token = '';
        localStorage.removeItem(API_CONFIG.deviceTokenKey);
        showScreen('screenAuth');
        populateServerUrlInput();
        updateConnectionBadge(false);
        return;
      }
    } catch (e) {
      console.warn('Connessione device_status non riuscita:', e);
      if (e.status === 401 || e.status === 403) {
        liveStore.token = '';
        localStorage.removeItem(API_CONFIG.deviceTokenKey);
        showScreen('screenAuth');
        populateServerUrlInput();
        updateConnectionBadge(false);
        return;
      }
      // Offline temporaneo: se abbiamo un token valido salvato, rimaniamo nella home
      showScreen('screenHome');
      updateConnectionBadge(false);
      syncActiveDeviceStatusDots(false);
    }

    // Se ci troviamo nella schermata di dettaglio, aggiorna anche la cronologia del grafico
    if (document.getElementById('screenDetail').classList.contains('active')) {
      await fetchChartData(state.activeTimeframe, liveStore.currentOffset);
    }
  }

  function startLivePolling() {
    if (liveStore.pollTimer) clearInterval(liveStore.pollTimer);
    liveStore.pollTimer = setInterval(async () => {
      await fetchLiveReading();
      await refreshActiveDeviceStatusSilent();
    }, API_CONFIG.pollIntervalMs);
  }

  // Aggiorna silenziosamente lo stato online/offline dei dispositivi e dei pallini durante il polling
  async function refreshActiveDeviceStatusSilent() {
    if (!liveStore.token) return;
    try {
      const q = liveStore.activeUuid ? `?uuid=${encodeURIComponent(liveStore.activeUuid)}` : '';
      const statusRes = await apiFetch('device_status.php' + q);
      if (statusRes && statusRes.status !== 'forbidden') {
        if (statusRes.device_reg_id || statusRes.device_id || statusRes.settings_device_reg_id) {
          setDeviceRegistrationId(statusRes.device_reg_id || statusRes.device_id, statusRes);
        }
        if (statusRes.connected_user || statusRes.user) {
          liveStore.connectedUser = statusRes.connected_user || statusRes.user;
          localStorage.setItem('nf_connected_user', liveStore.connectedUser);
          updateConnectionBadge(true, statusRes.device_name || liveStore.activeDeviceName);
        }
        if (Array.isArray(statusRes.all_devices) && statusRes.all_devices.length > 0) {
          liveStore.devices = statusRes.all_devices;
          populateDeviceDropdowns(statusRes.all_devices);
        }
        syncActiveDeviceStatusDots(statusRes.online);
      }
    } catch (e) {
      // Ignora errori transitori durante il polling
    }
  }

  // Aggiorna il pallino verde (online) o rosso (offline) accanto al selettore Foobot in Home e nella Sidebar
  function syncActiveDeviceStatusDots(explicitOnline) {
    let isOnline = explicitOnline;
    if (typeof isOnline !== 'boolean') {
      const dev = (liveStore.devices || []).find(d => d.device_uuid === liveStore.activeUuid);
      if (dev && typeof dev.online === 'boolean') {
        isOnline = dev.online;
      } else if (liveStore.lastReading && liveStore.lastReading.timestamp) {
        const diffSec = Math.floor(Date.now() / 1000) - Number(liveStore.lastReading.timestamp);
        isOnline = diffSec <= 900;
      } else {
        isOnline = false;
      }
    }

    const stateClass = isOnline ? 'online' : 'offline';
    ['headerDeviceStatusDot', 'sidebarDeviceStatusDot'].forEach(id => {
      const dot = document.getElementById(id);
      if (dot) {
        dot.classList.remove('online', 'offline', 'nodata');
        dot.classList.add(stateClass);
      }
    });
  }

  function setConnectedUsername(rawName) {
    const clean = String(rawName || '').trim();
    if (clean && clean !== '--' && clean !== 'null' && clean !== 'undefined') {
      liveStore.connectedUser = clean;
      localStorage.setItem('nf_connected_user', clean);
    }
    const resolved = liveStore.connectedUser || localStorage.getItem('nf_connected_user') || '';
    document.querySelectorAll('#sidebarConnectedUser, [data-db-field="connected_user"]').forEach(el => {
      el.textContent = resolved || '--';
    });
  }

  async function ensureConnectedUserResolved() {
    const current = (liveStore.connectedUser || localStorage.getItem('nf_connected_user') || '').trim();
    const currentRegId = (liveStore.deviceRegId || localStorage.getItem('nf_device_reg_id') || '').trim();
    if (current && current !== '--' && currentRegId && currentRegId !== '--') {
      setConnectedUsername(current);
      setDeviceRegistrationId(currentRegId);
      return current;
    }
    if (!liveStore.token) return '';

    // 1. Verifica tramite device_auth.php?action=check_token
    try {
      const checkRes = await apiFetch(`device_auth.php?action=check_token&device_token=${encodeURIComponent(liveStore.token)}`);
      if (checkRes) {
        if (checkRes.dashboard_lang) {
          syncDashboardLanguage(checkRes.dashboard_lang);
        }
        if (checkRes.device_reg_id || checkRes.device_id || checkRes.settings_device_reg_id) {
          setDeviceRegistrationId(checkRes.device_reg_id || checkRes.device_id, checkRes);
        }
        const u1 = checkRes.connected_user || checkRes.user || checkRes.username;
        if (u1) {
          setConnectedUsername(u1);
          if (liveStore.deviceRegId) return u1;
        }
      }
    } catch (e) {}

    // 2. Verifica tramite device_status.php
    try {
      const q = liveStore.activeUuid ? `?uuid=${encodeURIComponent(liveStore.activeUuid)}` : '';
      const statusRes = await apiFetch('device_status.php' + q);
      if (statusRes) {
        if (statusRes.device_reg_id || statusRes.device_id || statusRes.settings_device_reg_id) {
          setDeviceRegistrationId(statusRes.device_reg_id || statusRes.device_id, statusRes);
        }
        const u2 = statusRes.connected_user || statusRes.user || statusRes.username;
        if (u2) {
          setConnectedUsername(u2);
          return u2;
        }
      }
    } catch (e) {}

    return '';
  }

  function updateConnectionBadge(isOnline, labelText) {
    const badge = document.querySelector('[data-db-field="connection_status"]');
    const uuidEl = document.querySelector('[data-db-field="device_uuid"]');
    if (badge) {
      if (isOnline) {
        badge.className = 'info-val status-badge-online';
        badge.textContent = labelText || 'Collegato al DB';
      } else {
        badge.className = 'info-val status-badge-demo';
        badge.textContent = liveStore.token ? 'Autorizzato (In attesa dati)' : t('status_demo_mode');
      }
    }
    if (uuidEl && liveStore.activeUuid) {
      uuidEl.textContent = liveStore.activeUuid;
    }
    setConnectedUsername(liveStore.connectedUser);
    setDeviceRegistrationId(liveStore.deviceRegId);
  }

  // Popola dinamicamente i menu a tendina Foobot (Home e Sidebar) con i dispositivi reali dal DB e i rispettivi pallini verde/rosso
  function populateDeviceDropdowns(devices) {
    const dropdownMenu = document.getElementById('deviceDropdownMenu');
    const sidebarSelect = document.getElementById('sidebarDeviceSelect');
    const labelCurrent = document.getElementById('currentDeviceLabel');

    if (!devices || devices.length === 0) return;

    if (dropdownMenu) {
      dropdownMenu.innerHTML = devices.map(d => {
        const isSel = (d.device_uuid === liveStore.activeUuid);
        const name = d.device_name || `Foobot (${d.device_uuid.substring(0, 8)})`;
        const dotCls = d.online ? 'online' : 'offline';
        if (isSel && labelCurrent) labelCurrent.textContent = name;
        return `<li class="dropdown-item dropdown-item-with-dot ${isSel ? 'selected' : ''}" role="option" data-uuid="${d.device_uuid}" aria-selected="${isSel ? 'true' : 'false'}"><span class="device-status-dot ${dotCls}"></span><span>${name}</span></li>`;
      }).join('');

      dropdownMenu.querySelectorAll('.dropdown-item').forEach(item => {
        item.addEventListener('click', () => {
          const uuid = item.getAttribute('data-uuid');
          switchActiveDevice(uuid);
        });
      });
    }

    if (sidebarSelect) {
      sidebarSelect.innerHTML = devices.map(d => {
        const isSel = (d.device_uuid === liveStore.activeUuid);
        const name = d.device_name ? `${d.device_name} (${d.location || d.device_uuid.substring(0, 6)})` : d.device_uuid;
        return `<option value="${d.device_uuid}" ${isSel ? 'selected' : ''}>${name}</option>`;
      }).join('');
    }

    syncActiveDeviceStatusDots();
  }

  // Cambio sensore attivo
  async function switchActiveDevice(uuid) {
    liveStore.activeUuid = uuid;
    localStorage.setItem(API_CONFIG.activeDeviceKey, uuid);

    const dev = liveStore.devices.find(d => d.device_uuid === uuid);
    const name = dev ? (dev.device_name || 'Foobot') : 'Foobot';
    liveStore.activeDeviceName = name;

    const currentLabel = document.getElementById('currentDeviceLabel');
    if (currentLabel) currentLabel.textContent = name;

    const sidebarSelect = document.getElementById('sidebarDeviceSelect');
    if (sidebarSelect) sidebarSelect.value = uuid;

    const dropdownMenu = document.getElementById('deviceDropdownMenu');
    if (dropdownMenu) {
      dropdownMenu.querySelectorAll('.dropdown-item').forEach(item => {
        const isSel = item.getAttribute('data-uuid') === uuid;
        item.classList.toggle('selected', isSel);
        item.setAttribute('aria-selected', isSel ? 'true' : 'false');
      });
      const wrap = document.getElementById('customDeviceDropdown');
      if (wrap) wrap.classList.remove('open');
    }

    syncActiveDeviceStatusDots(dev ? dev.online : undefined);
    updateConnectionBadge(true, name);
    await fetchLiveReading();

    if (document.getElementById('screenDetail').classList.contains('active')) {
      await fetchChartData(state.activeTimeframe, liveStore.currentOffset);
    }
    if (document.getElementById('screenDevices').classList.contains('active')) {
      await loadAndRenderAllDevicesCards();
    }
  }

  // ==========================================================================
  // SCHERMATA DEDICATA DATI DI CONNESSIONE FOOBOT (#screenDevices)
  // Struttura dei box identica a class="device-dropdown" del sito PHP
  // ==========================================================================
  let previousScreenBeforeDevices = 'screenHome';

  function formatRelativeSecondsAgo(sec) {
    if (sec === null || sec === undefined || isNaN(sec)) return '';
    const s = Math.max(0, Number(sec));
    const isEn = state.lang === 'en';
    if (s < 60) {
      return isEn ? `(${s} sec ago)` : `(${s} sec fa)`;
    }
    if (s < 3600) {
      const m = Math.round(s / 60);
      return isEn ? `(${m} min ago)` : `(${m} min fa)`;
    }
    if (s < 86400) {
      const h = (s / 3600).toFixed(1);
      return isEn ? `(${h} hours ago)` : `(${h} ore fa)`;
    }
    const d = Math.round(s / 86400);
    return isEn ? `(${d} days ago)` : `(${d} giorni fa)`;
  }

  function formatDeviceTimestamp(ts) {
    if (!ts) return '--';
    const d = new Date(Number(ts) * 1000);
    const locale = getLocaleCode(state.lang);
    return d.toLocaleString(locale, {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit'
    });
  }

  function escapeHtml(str) {
    return String(str == null ? '' : str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  async function openDevicesScreen() {
    if (document.getElementById('screenDetail').classList.contains('active')) {
      previousScreenBeforeDevices = 'screenDetail';
    } else if (document.getElementById('screenHome').classList.contains('active')) {
      previousScreenBeforeDevices = 'screenHome';
    }
    toggleSidebar(false, true);
    showScreen('screenDevices');
    await loadAndRenderAllDevicesCards();
  }

  async function loadAndRenderAllDevicesCards() {
    const container = document.getElementById('devicesCardsContainer');
    const refreshBtn = document.getElementById('btnRefreshDevices');
    if (!container) return;

    if (refreshBtn) refreshBtn.classList.add('spinning');
    if (!container.innerHTML.trim()) {
      container.innerHTML = `<div class="devices-loading-state">${escapeHtml(t('devices_loading'))}</div>`;
    }

    try {
      // 1. Recupera l'elenco aggiornato di tutti i dispositivi Foobot registrati
      const baseRes = await apiFetch('device_status.php' + (liveStore.activeUuid ? `?uuid=${encodeURIComponent(liveStore.activeUuid)}` : ''));
      if (baseRes && (baseRes.device_reg_id || baseRes.device_id || baseRes.settings_device_reg_id)) {
        setDeviceRegistrationId(baseRes.device_reg_id || baseRes.device_id, baseRes);
      }
      if (baseRes && (baseRes.connected_user || baseRes.user)) {
        liveStore.connectedUser = baseRes.connected_user || baseRes.user;
        localStorage.setItem('nf_connected_user', liveStore.connectedUser);
        updateConnectionBadge(true, baseRes.device_name || liveStore.activeDeviceName);
      }
      let allDevices = (baseRes && Array.isArray(baseRes.all_devices) && baseRes.all_devices.length > 0)
        ? baseRes.all_devices
        : liveStore.devices;

      if (allDevices && allDevices.length > 0) {
        liveStore.devices = allDevices;
        populateDeviceDropdowns(allDevices);
      }

      // 2. Per ogni dispositivo registrato, richiede i dettagli completi di connessione da device_status.php?uuid=...
      let detailsList = [];
      if (allDevices && allDevices.length > 0) {
        detailsList = await Promise.all(
          allDevices.map(async (dev) => {
            try {
              if (baseRes && baseRes.uuid === dev.device_uuid) {
                return baseRes;
              }
              const detailRes = await apiFetch(`device_status.php?uuid=${encodeURIComponent(dev.device_uuid)}`);
              return detailRes || dev;
            } catch (err) {
              return {
                uuid: dev.device_uuid,
                device_name: dev.device_name,
                location: dev.location,
                last_seen: dev.last_seen,
                online: !!dev.online,
                readings_total: dev.total_readings || 0,
                readings_24h: '--'
              };
            }
          })
        );
      } else if (baseRes && baseRes.uuid) {
        detailsList = [baseRes];
      }

      if (detailsList.length === 0) {
        container.innerHTML = `<div class="devices-loading-state">--</div>`;
        return;
      }

      container.innerHTML = detailsList.map(data => buildSingleDeviceCardHtml(data)).join('');
    } catch (e) {
      console.warn('Errore caricamento dettagli dispositivi Foobot:', e);
    } finally {
      if (refreshBtn) refreshBtn.classList.remove('spinning');
    }
  }

  function buildSingleDeviceCardHtml(data) {
    const uuid = data.uuid || data.device_uuid || '--';
    const isOnline = !!data.online;
    const isNoData = data.status === 'no_data' || (!data.last_seen && !isOnline);
    const dotClass = isNoData ? 'nodata' : (isOnline ? 'online' : 'offline');
    const badgeClass = isOnline ? 'online' : 'offline';
    const badgeText = isOnline ? 'ONLINE' : (isNoData ? 'NO DATA' : 'OFFLINE');
    const isCurrentActive = (uuid === liveStore.activeUuid);

    const devName = data.device_name || `Foobot (${String(uuid).substring(0, 8)})`;
    const locationStr = (data.location || '').trim();
    const connStatusText = isOnline ? t('conn_active_regular') : t('conn_inactive_15m');
    const connStatusCls  = isOnline ? 'conn-active' : 'conn-inactive';

    const lastSeenStr = data.last_seen ? formatDeviceTimestamp(data.last_seen) : (state.lang === 'en' ? 'Never received' : 'Mai ricevuto');
    const agoStr      = data.last_seen ? formatRelativeSecondsAgo(data.seconds_ago) : '';

    const connTs  = data.last_connected_at || data.last_seen || null;
    const connStr = connTs ? formatDeviceTimestamp(connTs) : '--';

    const hasDisc = !!data.last_disconnected_at;
    const discStr = hasDisc ? formatDeviceTimestamp(data.last_disconnected_at) : '--';
    const discReason = data.disconnect_reason || '';

    const locale = getLocaleCode(state.lang);
    const r24h   = (data.readings_24h !== undefined && data.readings_24h !== '--')
      ? Number(data.readings_24h).toLocaleString(locale)
      : '--';
    const rTotal = (data.readings_total !== undefined)
      ? Number(data.readings_total).toLocaleString(locale)
      : '--';

    return `
      <article class="device-dropdown-card ${isCurrentActive ? 'active-device-card' : ''}">
        <div class="device-dropdown-header">
          <div class="device-dropdown-header-left">
            <span class="device-status-dot ${dotClass}"></span>
            <span class="device-dropdown-header-title">${escapeHtml(devName)}</span>
          </div>
          <span class="device-pill-badge ${badgeClass}">${badgeText}</span>
        </div>

        <div class="device-dropdown-row">
          <span class="device-dropdown-label" data-i18n="drop_device_name">${escapeHtml(t('drop_device_name'))}</span>
          <span class="device-dropdown-val">${escapeHtml(devName)}</span>
        </div>

        ${locationStr ? `
        <div class="device-dropdown-row">
          <span class="device-dropdown-label" data-i18n="drop_location">${escapeHtml(t('drop_location'))}</span>
          <span class="device-dropdown-val">${escapeHtml(locationStr)}</span>
        </div>
        ` : ''}

        <div class="device-dropdown-row">
          <span class="device-dropdown-label" data-i18n="drop_connection_status">${escapeHtml(t('drop_connection_status'))}</span>
          <span class="device-dropdown-val ${connStatusCls}">${escapeHtml(connStatusText)}</span>
        </div>

        <div class="device-dropdown-row">
          <span class="device-dropdown-label" data-i18n="drop_device_uuid">${escapeHtml(t('drop_device_uuid'))}</span>
          <span class="device-dropdown-val">${escapeHtml(uuid)}</span>
        </div>

        <div class="device-dropdown-row">
          <span class="device-dropdown-label" data-i18n="settings_device_reg_id">${escapeHtml(t('settings_device_reg_id'))}</span>
          <span class="device-dropdown-val" style="font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace; font-weight:700; color:var(--accent-blue);">${liveStore.deviceRegId ? `#${escapeHtml(liveStore.deviceRegId)}` : '--'}</span>
        </div>

        <div class="device-dropdown-row">
          <span class="device-dropdown-label" data-i18n="drop_last_seen">${escapeHtml(t('drop_last_seen'))}</span>
          <span class="device-dropdown-val">
            <span>${escapeHtml(lastSeenStr)}</span>
            ${agoStr ? `<small class="device-dropdown-sub">${escapeHtml(agoStr)}</small>` : ''}
          </span>
        </div>

        <div class="device-dropdown-row">
          <span class="device-dropdown-label" data-i18n="drop_last_connected">${escapeHtml(t('drop_last_connected'))}</span>
          <span class="device-dropdown-val">${escapeHtml(connStr)}</span>
        </div>

        ${hasDisc ? `
        <div class="device-dropdown-row">
          <span class="device-dropdown-label" data-i18n="drop_last_disconnected">${escapeHtml(t('drop_last_disconnected'))}</span>
          <span class="device-dropdown-val">
            <span>${escapeHtml(discStr)}</span>
            ${discReason ? `<small class="device-dropdown-sub reason-alert">${escapeHtml(discReason)}</small>` : ''}
          </span>
        </div>
        ` : ''}

        <div class="device-dropdown-row">
          <span class="device-dropdown-label" data-i18n="drop_count_24h">${escapeHtml(t('drop_count_24h'))}</span>
          <span class="device-dropdown-val">${escapeHtml(r24h)}</span>
        </div>

        <div class="device-dropdown-row">
          <span class="device-dropdown-label" data-i18n="drop_count_total">${escapeHtml(t('drop_count_total'))}</span>
          <span class="device-dropdown-val">${escapeHtml(rTotal)}</span>
        </div>
      </article>
    `;
  }

  // ==========================================================================
  // 4. SISTEMA UNIFICATO STATO E COLORI (OTTIMO, BUONO, MEDIO, SCARSO)
  // ==========================================================================
  const LEVEL_CLASSES = ['level-great', 'level-good', 'level-fair', 'level-poor'];

  const LEVEL_PALETTE = {
    great: { cls: 'level-great', statusKey: 'status_great', bg: '#e8f8ee', border: '#8ac1ac', text: '#0f7652' },
    good:  { cls: 'level-good',  statusKey: 'status_good',  bg: '#e8effd', border: '#9ab5f8', text: '#1d4ed8' },
    fair:  { cls: 'level-fair',  statusKey: 'status_fair',  bg: '#fef6d2', border: '#ebd27a', text: '#92400e' },
    poor:  { cls: 'level-poor',  statusKey: 'status_poor',  bg: '#fdebed', border: '#f2a7b0', text: '#b91c1c' }
  };

  // Restituisce il livello ('great' | 'good' | 'fair' | 'poor') per ciascun sensore in base alle soglie
  function getSensorQualityLevel(sensorKey, rawVal) {
    const v = Number(rawVal);
    if (isNaN(v)) return 'good';

    switch (sensorKey) {
      case 'allpollu': // GPI: 0-25 Ottimo, 25-50 Buono, 50-75 Medio, 75-100 Scarso
        if (v <= 25) return 'great';
        if (v <= 50) return 'good';
        if (v <= 75) return 'fair';
        return 'poor';

      case 'voc': // VOC (ppb): 0-150 Ottimo, 150-300 Buono, 300-450 Medio, >450 Scarso
        if (v <= 150) return 'great';
        if (v <= 300) return 'good';
        if (v <= 450) return 'fair';
        return 'poor';

      case 'pm': // PM2.5 (µg/m³): 0-12.5 Ottimo, 12.5-25 Buono, 25-37.5 Medio, >37.5 Scarso
        if (v <= 12.5) return 'great';
        if (v <= 25.0) return 'good';
        if (v <= 37.5) return 'fair';
        return 'poor';

      case 'co2': // eCO2 (ppm): <=625 Ottimo, 625-1300 Buono, 1300-1925 Medio, >1925 Scarso
        if (v <= 625)  return 'great';
        if (v <= 1300) return 'good';
        if (v <= 1925) return 'fair';
        return 'poor';

      case 'tmp': // Temperatura (°C): 18-24 Ottimo, 16-18/24-26 Buono, 14-16/26-28 Medio, <14/>28 Scarso
        if (v >= 18 && v <= 24) return 'great';
        if ((v >= 16 && v < 18) || (v > 24 && v <= 26)) return 'good';
        if ((v >= 14 && v < 16) || (v > 26 && v <= 28)) return 'fair';
        return 'poor';

      case 'hum': // Umidità (%): 40-60 Ottimo, 30-40/60-70 Buono, 20-30/70-80 Medio, <20/>80 Scarso
        if (v >= 40 && v <= 60) return 'great';
        if ((v >= 30 && v < 40) || (v > 60 && v <= 70)) return 'good';
        if ((v >= 20 && v < 30) || (v > 70 && v <= 80)) return 'fair';
        return 'poor';

      default:
        return 'good';
    }
  }

  function applyLevelClass(el, levelKey) {
    if (!el) return;
    el.classList.remove(...LEVEL_CLASSES);
    const info = LEVEL_PALETTE[levelKey] || LEVEL_PALETTE.good;
    el.classList.add(info.cls);
  }

  // ==========================================================================
  // 4.1 FETCH DATI LIVE (NUMERI DASHBOARD E VALORI CORRENTI)
  // ==========================================================================
  async function fetchLiveReading() {
    let readings = null;
    let formattedDate = '';

    try {
      const q = liveStore.activeUuid ? `&uuid=${encodeURIComponent(liveStore.activeUuid)}` : '';
      const json = await apiFetch(`get_datapoints.php?range=live${q}`);

      if (json && (json.device_reg_id || json.device_id || json.settings_device_reg_id)) {
        setDeviceRegistrationId(json.device_reg_id || json.device_id, json);
      }
      if (json && (json.connected_user || json.user)) {
        liveStore.connectedUser = json.connected_user || json.user;
        localStorage.setItem('nf_connected_user', liveStore.connectedUser);
        updateConnectionBadge(true);
      }

      if (json && json.datapoints && json.datapoints.length > 0) {
        const dp = json.datapoints[0];
        // Struttura restituzione server: [timestamp, allpollu, pm, voc, co2, tmp, hum]
        readings = {
          timestamp: dp[0],
          allpollu:  Math.round(dp[1]),
          pm:        Number(dp[2]).toFixed(1),
          voc:       Math.round(dp[3]),
          co2:       Math.round(dp[4]),
          tmp:       Number(dp[5]),
          hum:       Math.round(dp[6])
        };

        const dt = new Date(dp[0] * 1000);
        const day = String(dt.getDate()).padStart(2, '0');
        const month = String(dt.getMonth() + 1).padStart(2, '0');
        const year = dt.getFullYear();
        const hours = String(dt.getHours()).padStart(2, '0');
        const minutes = String(dt.getMinutes()).padStart(2, '0');
        const seconds = String(dt.getSeconds()).padStart(2, '0');
        formattedDate = `${day}/${month}/${year} ${hours}:${minutes}:${seconds}`;

        liveStore.lastReading = readings;
        liveStore.lastReadingFormatted = formattedDate;
        updateConnectionBadge(true);
      }
    } catch (e) {
      console.warn('Lettura live non disponibile:', e);
    }

    renderHomeValues();
    updateDetailHeroValue();
  }

  // Aggiorna tutti i cerchi, le barre e le etichette della Home con i valori e i colori di stato attuali
  function renderHomeValues() {
    const r = liveStore.lastReading;

    // Stato iniziale o in attesa lettura dal DB: mostra "--" senza numeri di esempio
    if (!r) {
      ['valGpi', 'valVoc', 'valPm', 'valCo2', 'valTmp', 'valHum'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.textContent = '--';
      });
      const labelGpi = document.getElementById('labelGpiStatus');
      if (labelGpi) {
        labelGpi.removeAttribute('data-i18n');
        labelGpi.textContent = '--';
      }
      const unitTmp = document.getElementById('unitTmp');
      if (unitTmp) unitTmp.textContent = state.tempUnit + '°';

      const gpiArc = document.getElementById('gpiProgressArc');
      if (gpiArc) gpiArc.setAttribute('stroke-dashoffset', '464.95');
      const tmpArc = document.getElementById('tmpProgressArc');
      if (tmpArc) tmpArc.setAttribute('stroke-dashoffset', '376.99');
      const humArc = document.getElementById('humProgressArc');
      if (humArc) humArc.setAttribute('stroke-dashoffset', '376.99');
      return;
    }

    // 1. GPI Centrale (Valore 0-100%, Circonferenza, Alone, Arco e Parola di Stato)
    const gpiVal = Math.min(100, Math.max(0, Number(r.allpollu) || 0));
    const gpiLevel = getSensorQualityLevel('allpollu', gpiVal);
    const gpiInfo  = LEVEL_PALETTE[gpiLevel];

    const valGpi = document.getElementById('valGpi');
    if (valGpi) valGpi.textContent = Math.round(gpiVal);

    const gpiHalo = document.getElementById('gpiHeroTrigger');
    applyLevelClass(gpiHalo, gpiLevel);

    const gpiArc = document.getElementById('gpiProgressArc');
    if (gpiArc) {
      // Calcolo circonferenza esatta dal raggio SVG (r = 74 -> 2 * PI * 74 = 464.9557)
      // 0% -> stroke-dashoffset = circ (nessuna circonferenza visibile)
      // 100% -> stroke-dashoffset = 0 (circonferenza completa al 100%)
      const radius = Number(gpiArc.getAttribute('r')) || 74;
      const circ = Number((2 * Math.PI * radius).toFixed(2));
      const pct = gpiVal / 100;
      const offset = Number((circ * (1 - pct)).toFixed(2));

      gpiArc.setAttribute('stroke-dasharray', String(circ));
      gpiArc.setAttribute('stroke-dashoffset', String(offset));
      gpiArc.setAttribute('stroke', gpiInfo.border);
      applyLevelClass(gpiArc, gpiLevel);
    }

    // Etichetta stato GPI (OTTIMO / BUONO / MEDIO / SCARSO) e relativo colore
    const labelGpi = document.getElementById('labelGpiStatus');
    if (labelGpi) {
      labelGpi.setAttribute('data-i18n', gpiInfo.statusKey);
      labelGpi.textContent = t(gpiInfo.statusKey);
      applyLevelClass(labelGpi, gpiLevel);
    }

    // 2. Sensori Orizzontali (VOC, PM2.5, eCO2): valori e colori unificati per barra e cerchio
    const valVoc = document.getElementById('valVoc');
    const valPm  = document.getElementById('valPm');
    const valCo2 = document.getElementById('valCo2');

    if (valVoc) valVoc.textContent = r.voc;
    if (valPm)  valPm.textContent  = r.pm;
    if (valCo2) valCo2.textContent = r.co2;

    const horizontalSensors = [
      { key: 'voc', val: r.voc, pillSel: '.pill-voc', circleSel: '.circle-voc' },
      { key: 'pm',  val: r.pm,  pillSel: '.pill-pm',  circleSel: '.circle-pm' },
      { key: 'co2', val: r.co2, pillSel: '.pill-co2', circleSel: '.circle-co2' }
    ];

    horizontalSensors.forEach(s => {
      const lvl = getSensorQualityLevel(s.key, s.val);
      applyLevelClass(document.querySelector(s.pillSel), lvl);
      applyLevelClass(document.querySelector(s.circleSel), lvl);
    });

    // 3. Temperatura e Umidità Inferiori
    const valTmp  = document.getElementById('valTmp');
    const unitTmp = document.getElementById('unitTmp');
    const valHum  = document.getElementById('valHum');

    let displayTmp = r.tmp;
    if (state.tempUnit === 'F') {
      displayTmp = Math.round((r.tmp * 9 / 5) + 32);
    } else {
      displayTmp = Number(r.tmp).toFixed(1);
    }

    if (valTmp)  valTmp.textContent  = displayTmp;
    if (unitTmp) unitTmp.textContent = state.tempUnit + '°';
    if (valHum)  valHum.textContent  = r.hum;

    // Archi Temperatura e Umidità con colore coerente al livello di qualità
    const tmpArc = document.getElementById('tmpProgressArc');
    const humArc = document.getElementById('humProgressArc');
    if (tmpArc) {
      const tmpLevel = getSensorQualityLevel('tmp', r.tmp);
      const tNorm = Math.min(40, Math.max(0, Number(r.tmp) || 0)) / 40;
      tmpArc.setAttribute('stroke-dashoffset', (376.99 * (1 - tNorm)).toFixed(1));
      tmpArc.setAttribute('stroke', LEVEL_PALETTE[tmpLevel].border);
    }
    if (humArc) {
      const humLevel = getSensorQualityLevel('hum', r.hum);
      const humRatio = Math.min(100, Math.max(0, Number(r.hum) || 0)) / 100;
      humArc.setAttribute('stroke-dashoffset', (376.99 * (1 - humRatio)).toFixed(1));
      humArc.setAttribute('stroke', LEVEL_PALETTE[humLevel].border);
    }
  }

  // Aggiorna la capsula valore centrale nella schermata di dettaglio (valore + colore stato dinamico)
  function updateDetailHeroValue() {
    const r = liveStore.lastReading;
    const meta = SENSOR_METADATA[state.selectedSensor];
    if (!meta) return;

    const numEl     = document.getElementById('detailValNumber');
    const unitEl    = document.getElementById('detailValUnit');
    const tsEl      = document.getElementById('detailTimestamp');
    const capsuleEl = document.getElementById('detailValueCapsule');

    let unit = meta.unit;
    if (state.selectedSensor === 'tmp') {
      unit = state.tempUnit + '°';
    }
    if (unitEl) unitEl.textContent = unit;

    // Se non è ancora arrivata la lettura dal DB, mostra "--"
    if (!r) {
      if (numEl) numEl.textContent = '--';
      if (tsEl)  tsEl.textContent  = '--';
      return;
    }

    const rawVal = r[state.selectedSensor];
    let val = rawVal;

    if (state.selectedSensor === 'tmp' && rawVal !== undefined && rawVal !== null) {
      val = (state.tempUnit === 'F') ? Math.round((r.tmp * 9 / 5) + 32) : Number(r.tmp).toFixed(1);
    }

    if (numEl) numEl.textContent = (val !== undefined && val !== null) ? val : '--';
    if (tsEl)  tsEl.textContent  = liveStore.lastReadingFormatted || '--';

    if (capsuleEl && rawVal !== undefined && rawVal !== null) {
      const level = getSensorQualityLevel(state.selectedSensor, rawVal);
      applyLevelClass(capsuleEl, level);
      capsuleEl.style.borderColor = LEVEL_PALETTE[level].border;
    }
  }

  // ==========================================================================
  // 5. APERTURA DETTAGLIO & CARICAMENTO STORICO GRAFICO DAL DB
  // ==========================================================================
  async function openSensorDetail(sensorKey) {
    if (!SENSOR_METADATA[sensorKey]) return;
    state.selectedSensor = sensorKey;

    const meta = SENSOR_METADATA[sensorKey];

    // Aggiorna titolo e voce selezionata nel dropdown
    const titleEl = document.getElementById('detailSensorTitle');
    if (titleEl) {
      titleEl.setAttribute('data-i18n', meta.titleKey);
      titleEl.textContent = t(meta.titleKey);
    }

    const sensorDropdownMenu = document.getElementById('sensorDropdownMenu');
    if (sensorDropdownMenu) {
      sensorDropdownMenu.querySelectorAll('.dropdown-item').forEach(item => {
        const isSelected = item.getAttribute('data-sensor') === sensorKey;
        item.classList.toggle('selected', isSelected);
        item.setAttribute('aria-selected', isSelected ? 'true' : 'false');
      });
    }

    // Aggiorna valore e bordo capsula in base allo stato di qualità attuale
    updateDetailHeroValue();

    // Commuta vista attiva da Home a Dettaglio con registrazione nella History API
    showScreen('screenDetail');

    // Carica serie storica reale per il sensore selezionato
    await fetchChartData(state.activeTimeframe, liveStore.currentOffset);
  }

  // Helper per formattare la barra di navigazione temporale con data e orario (o range orario)
  function formatNavTimeRange(startTs, endTs, range, isLive) {
    if (!startTs || !endTs) return isLive ? 'Tempo Reale' : '--';
    const dStart = new Date(startTs * 1000);
    const dEnd   = new Date(endTs * 1000);

    const pad = (n) => String(n).padStart(2, '0');
    const fmtDate = (d) => `${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()}`;
    const fmtTime = (d) => `${pad(d.getHours())}:${pad(d.getMinutes())}`;

    const dateStartStr = fmtDate(dStart);
    const dateEndStr   = fmtDate(dEnd);
    const timeStartStr = fmtTime(dStart);
    const timeEndStr   = fmtTime(dEnd);

    if (range === 'minuti' || range === 'ore' || range === '24h') {
      if (dateStartStr === dateEndStr) {
        return `<div class="time-nav-stacked"><span class="time-nav-main-date">${dateStartStr}</span><span class="time-nav-sub-time">${timeStartStr} — ${timeEndStr}</span></div>`;
      }
      return `
        <div class="time-nav-stacked-split">
          <span class="time-nav-split-col">
            <span class="time-nav-d">${pad(dStart.getDate())}/${pad(dStart.getMonth() + 1)}</span>
            <span class="time-nav-t">${timeStartStr}</span>
          </span>
          <span class="time-nav-arrow">→</span>
          <span class="time-nav-split-col">
            <span class="time-nav-d">${pad(dEnd.getDate())}/${pad(dEnd.getMonth() + 1)}</span>
            <span class="time-nav-t">${timeEndStr}</span>
          </span>
        </div>
      `;
    }

    // Range multi-giorno (7d, 10d, 30d, giorni, settimane)
    return `${dateStartStr} → ${dateEndStr}`;
  }

  // Helper per formattare intervalli personalizzati inseriti dall'utente (con orario sopra o sotto la data)
  function formatCustomDateRangeLabel(startIn, endIn) {
    if (!startIn) return 'Tempo Reale';
    const cleanStart = String(startIn).replace('T', ' ');
    const cleanEnd   = endIn ? String(endIn).replace('T', ' ') : cleanStart;

    const parseDatePart = (str) => {
      const parts = str.split(' ');
      const datePart = parts[0] || '';
      const timePart = parts[1] ? parts[1].substring(0, 5) : '';
      const dPieces = datePart.split('-');
      if (dPieces.length === 3) {
        return {
          dayMonth: `${dPieces[2]}/${dPieces[1]}`,
          fullDate: `${dPieces[2]}/${dPieces[1]}/${dPieces[0]}`,
          time: timePart
        };
      }
      return { dayMonth: datePart, fullDate: datePart, time: timePart };
    };

    const s = parseDatePart(cleanStart);
    const e = parseDatePart(cleanEnd);

    // Stessa data
    if (s.fullDate === e.fullDate) {
      if (s.time && e.time && s.time !== e.time) {
        return `<div class="time-nav-stacked"><span class="time-nav-main-date">${s.fullDate}</span><span class="time-nav-sub-time">${s.time} — ${e.time}</span></div>`;
      } else if (s.time) {
        return `<div class="time-nav-stacked"><span class="time-nav-main-date">${s.fullDate}</span><span class="time-nav-sub-time">${s.time}</span></div>`;
      }
      return s.fullDate;
    }

    // Date diverse: se l'utente ha inserito orari, disponi data sopra e orario sotto per ogni colonna
    if (s.time || e.time) {
      return `
        <div class="time-nav-stacked-split">
          <span class="time-nav-split-col">
            <span class="time-nav-d">${s.dayMonth}</span>
            <span class="time-nav-t">${s.time || '00:00'}</span>
          </span>
          <span class="time-nav-arrow">→</span>
          <span class="time-nav-split-col">
            <span class="time-nav-d">${e.dayMonth}</span>
            <span class="time-nav-t">${e.time || '23:59'}</span>
          </span>
        </div>
      `;
    }

    return `${s.dayMonth} → ${e.dayMonth}`;
  }

  // Interroga api/get_datapoints.php con range, offset o custom dates
  async function fetchChartData(range, offset = 0) {
    // Quando c'è un range di date personalizzato:
    // - Per 'minuti', 'ore' e '24h' richiediamo al backend '24h' con solo la data YYYY-MM-DD (compatibile anche con backend non ancora aggiornati)
    //   e poi filtriamo lato client gli orari esatti tra customStartDate e customEndDate.
    // - Per '7d', '30d', 'giorni', 'settimane' richiediamo il range giornaliero.
    const isDailyRange = ['giorni', 'settimane', '3d', '7d', '10d', '30d'].includes(range);
    let apiRange = (range === 'ore' && !liveStore.customStartDate) ? '24h' : range;
    if (liveStore.customStartDate && !isDailyRange) {
      apiRange = '24h';
    }

    let url = `get_datapoints.php?range=${encodeURIComponent(apiRange)}&offset=${parseInt(offset, 10) || 0}`;
    if (liveStore.activeUuid) {
      url += `&uuid=${encodeURIComponent(liveStore.activeUuid)}`;
    }
    if (liveStore.customStartDate) {
      const startDateOnly = String(liveStore.customStartDate).split(/[ T]/)[0];
      const endDateOnly   = liveStore.customEndDate
        ? String(liveStore.customEndDate).split(/[ T]/)[0]
        : startDateOnly;
      url += `&start_date=${encodeURIComponent(startDateOnly)}&end_date=${encodeURIComponent(endDateOnly)}`;
    }

    try {
      let json = null;

      // Se l'utente ha selezionato un intervallo di date personalizzato con risoluzione alta (minuti, ore, 24h)
      // e l'intervallo supera i 14 giorni, esegue la query in blocchi di max 14 giorni per evitare che il backend
      // applichi il fallback di aggregazione giornaliera (diffDays > 15) o il taglio a 5000 record.
      if (liveStore.customStartDate && !isDailyRange) {
        const startDateOnly = String(liveStore.customStartDate).split(/[ T]/)[0];
        const endDateOnly   = liveStore.customEndDate
          ? String(liveStore.customEndDate).split(/[ T]/)[0]
          : startDateOnly;
        const sMs = new Date(`${startDateOnly}T00:00:00`).getTime();
        const eMs = new Date(`${endDateOnly}T23:59:59`).getTime();
        const spanDays = (!isNaN(sMs) && !isNaN(eMs)) ? Math.round((eMs - sMs) / 86400000) : 0;

        if (spanDays > 14) {
          const chunkUrls = [];
          const fmtYMD = (ms) => {
            const d = new Date(ms);
            const y = d.getFullYear();
            const m = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${y}-${m}-${day}`;
          };
          let cursorMs = sMs;
          const chunkStepMs = 14 * 86400000;
          while (cursorMs <= eMs && chunkUrls.length < 10) {
            const chunkEndMs = Math.min(eMs, cursorMs + chunkStepMs - 86400000);
            const cStart = fmtYMD(cursorMs);
            const cEnd   = fmtYMD(chunkEndMs);
            let cUrl = `get_datapoints.php?range=${encodeURIComponent(apiRange)}&offset=0`;
            if (liveStore.activeUuid) {
              cUrl += `&uuid=${encodeURIComponent(liveStore.activeUuid)}`;
            }
            cUrl += `&start_date=${encodeURIComponent(cStart)}&end_date=${encodeURIComponent(cEnd)}`;
            chunkUrls.push(cUrl);
            cursorMs = chunkEndMs + 86400000;
          }

          const results = await Promise.all(chunkUrls.map(u => apiFetch(u).catch(() => null)));
          const mergedMap = new Map();
          let firstStart = null;
          let lastEnd = null;
          results.forEach(res => {
            if (!res) return;
            if (!json) json = res;
            if (res.start && (firstStart === null || res.start < firstStart)) firstStart = res.start;
            if (res.end && (lastEnd === null || res.end > lastEnd)) lastEnd = res.end;
            if (Array.isArray(res.datapoints)) {
              res.datapoints.forEach(dp => {
                if (Array.isArray(dp) && dp.length > 0) {
                  mergedMap.set(dp[0], dp);
                }
              });
            }
          });
          if (json) {
            json.datapoints = Array.from(mergedMap.values()).sort((a, b) => a[0] - b[0]);
            if (firstStart !== null) json.start = firstStart;
            if (lastEnd !== null) json.end = lastEnd;
          }
        } else {
          json = await apiFetch(url);
        }
      } else {
        json = await apiFetch(url);
      }

      if (json && (json.device_reg_id || json.device_id || json.settings_device_reg_id)) {
        setDeviceRegistrationId(json.device_reg_id || json.device_id, json);
      }
      if (json && (json.connected_user || json.user)) {
        liveStore.connectedUser = json.connected_user || json.user;
        localStorage.setItem('nf_connected_user', liveStore.connectedUser);
        updateConnectionBadge(true);
      }

      // Aggiorna etichetta data/periodo con data e orario (o range di orario caricato nel grafico)
      const labelStatus = document.getElementById('labelTimeNavStatus');
      if (labelStatus) {
        if (liveStore.customStartDate) {
          labelStatus.innerHTML = formatCustomDateRangeLabel(liveStore.customStartDate, liveStore.customEndDate);
        } else if (json && json.start && json.end) {
          labelStatus.innerHTML = formatNavTimeRange(json.start, json.end, range, offset === 0);
        } else if (offset === 0) {
          labelStatus.textContent = 'Tempo Reale';
        }
      }

      if (json && Array.isArray(json.datapoints) && json.datapoints.length > 0) {
        parseAndRenderChartData(json.datapoints, range, json.start, json.end);
        return;
      }
    } catch (e) {
      console.warn('Recupero storico get_datapoints non riuscito (utilizzo fallback locale):', e);
    }

    // Se la chiamata fallisce o non ci sono ancora punti nel DB, renderizza grafico vuoto (senza dati finti)
    renderSensorLineChart(state.selectedSensor, [], []);
  }

  // Elabora i datapoint raw restituiti dal server PHP (stessa logica del dashboard.js ufficiale)
  function parseAndRenderChartData(datapoints, range, startTs, endTs) {
    let rawPoints = datapoints.slice().sort((a, b) => a[0] - b[0]);

    // Se è attivo un filtro personalizzato con data e orario (es. 2026-10-14T18:35 -> 2026-11-04T18:35),
    // filtra i punti sull'intervallo esatto selezionato dall'utente (per viste non giornaliere)
    const isDaily = ['giorni', 'settimane', '3d', '7d', '10d', '30d'].includes(range);
    let customStartEpoch = null;
    let customEndEpoch   = null;
    if (liveStore.customStartDate) {
      const sClean = String(liveStore.customStartDate).trim();
      const sHasTime = /\d{2}:\d{2}/.test(sClean);
      const sDateObj = new Date(sHasTime ? sClean.replace(' ', 'T') : `${sClean}T00:00:00`);
      if (!isNaN(sDateObj.getTime())) {
        customStartEpoch = Math.floor(sDateObj.getTime() / 1000);
      }
      const eRaw = (liveStore.customEndDate || liveStore.customStartDate).trim();
      const eHasTime = /\d{2}:\d{2}/.test(eRaw);
      const eDateObj = new Date(eHasTime ? eRaw.replace(' ', 'T') : `${eRaw}T23:59:59`);
      if (!isNaN(eDateObj.getTime())) {
        customEndEpoch = Math.floor(eDateObj.getTime() / 1000) + 59;
      }
      if (!isDaily && customStartEpoch && customEndEpoch) {
        const withinExactTime = rawPoints.filter(p => p[0] >= customStartEpoch && p[0] <= customEndEpoch);
        if (withinExactTime.length > 0) {
          rawPoints = withinExactTime;
        }
      }
    }

    const labels = [];
    const series = [];
    const dates  = [];

    // Mappatura indice sensore nell'array del server:
    // 0: timestamp, 1: allpollu, 2: pm, 3: voc, 4: co2, 5: tmp, 6: hum
    const sensorIndexMap = {
      allpollu: 1,
      pm:       2,
      voc:      3,
      co2:      4,
      tmp:      5,
      hum:      6
    };
    const sIdx = sensorIndexMap[state.selectedSensor] || 3;

    if (range === 'ore') {
      // Gestione aggregazione per filtro 'ore': slot orari (media di ogni ora)
      // Se il backend ha restituito punti già aggregati per giorno (es. 1 punto al giorno per range > 15 gg su vecchio server),
      // mostra direttamente tutti i punti ricevuti senza creare buchi orari vuoti.
      const isAlreadyDailyAggregated = (rawPoints.length >= 2 && rawPoints.length <= 95 && ((rawPoints[1][0] - rawPoints[0][0]) >= 43200));

      if (isAlreadyDailyAggregated) {
        rawPoints.forEach(dp => {
          const dt = new Date(dp[0] * 1000);
          const pad = (n) => String(n).padStart(2, '0');
          const dd = pad(dt.getDate());
          const mm = pad(dt.getMonth() + 1);
          let val = dp[sIdx];
          if (val !== null && val !== undefined && !isNaN(val) && val > 0) {
            if (state.selectedSensor === 'tmp') {
              val = (state.tempUnit === 'F') ? Number(((val * 9 / 5) + 32).toFixed(1)) : Number(Number(val).toFixed(1));
            } else if (state.selectedSensor === 'pm') {
              val = Number(Number(val).toFixed(1));
            } else {
              val = Math.round(Number(val));
            }
          } else {
            val = null;
          }
          labels.push(`${dd}/${mm}`);
          series.push(val);
          dates.push(`${dd}/${mm}`);
        });
      } else {
        const lastPointTs = rawPoints.length > 0
          ? Math.floor(rawPoints[rawPoints.length - 1][0])
          : Math.floor(Date.now() / 1000);
        const firstPointTs = rawPoints.length > 0
          ? Math.floor(rawPoints[0][0])
          : (lastPointTs - 23 * 3600);

        let endRef = endTs ? Math.min(Math.floor(endTs), lastPointTs) : lastPointTs;
        if (liveStore.customStartDate && rawPoints.length > 0) {
          endRef = lastPointTs;
        }
        const endHourDate = new Date(endRef * 1000);
        endHourDate.setMinutes(0, 0, 0);
        const endHourTs = Math.floor(endHourDate.getTime() / 1000);

        let startHourTs = endHourTs - (23 * 3600);
        if (liveStore.customStartDate) {
          const startRef = rawPoints.length > 0 ? firstPointTs : (customStartEpoch || (endHourTs - 23 * 3600));
          const startHourDate = new Date(startRef * 1000);
          startHourDate.setMinutes(0, 0, 0);
          startHourTs = Math.floor(startHourDate.getTime() / 1000);
          if (startHourTs > endHourTs) {
            startHourTs = endHourTs;
          }
        }

        const totalHours = Math.max(0, Math.min(720, Math.round((endHourTs - startHourTs) / 3600)));

        // Aggiorna l'etichetta della barra di navigazione con il range esatto delle ore caricate (se non c'è filtro date custom)
        const labelStatus = document.getElementById('labelTimeNavStatus');
        if (labelStatus && !liveStore.customStartDate) {
          labelStatus.innerHTML = formatNavTimeRange(startHourTs, endHourTs, 'ore', liveStore.currentOffset === 0);
        }

        for (let i = totalHours; i >= 0; i--) {
          const slotTime = endHourTs - (i * 3600);
          const slotEndTime = slotTime + 3600;

          // Tutte le rilevazioni cadute all'interno dell'ora [slotTime, slotEndTime)
          const hourPoints = rawPoints.filter(p => p[0] >= slotTime && p[0] < slotEndTime);

          let chosenVal = null;
          if (hourPoints.length > 0) {
            const validNums = hourPoints
              .map(p => p[sIdx])
              .filter(v => v !== null && v !== undefined && !isNaN(v) && v > 0);

            if (validNums.length > 0) {
              const sum = validNums.reduce((a, b) => a + b, 0);
              const avg = sum / validNums.length;
              if (state.selectedSensor === 'tmp') {
                chosenVal = (state.tempUnit === 'F')
                  ? Number(((avg * 9 / 5) + 32).toFixed(1))
                  : Number(avg.toFixed(1));
              } else if (state.selectedSensor === 'pm') {
                chosenVal = Number(avg.toFixed(1));
              } else {
                chosenVal = Math.round(avg);
              }
            }
          } else {
            // Se non ci sono rilevazioni nell'ora esatta, cerca la lettura più vicina entro +/- 25 minuti
            let minDiff = Infinity;
            let nearestVal = null;
            for (let j = 0; j < rawPoints.length; j++) {
              const diff = Math.abs(rawPoints[j][0] - slotTime);
              if (diff <= 1500 && diff < minDiff) {
                const rawV = rawPoints[j][sIdx];
                if (rawV !== null && rawV !== undefined && rawV > 0) {
                  minDiff = diff;
                  nearestVal = rawV;
                }
              }
            }
            if (nearestVal !== null) {
              if (state.selectedSensor === 'tmp') {
                chosenVal = (state.tempUnit === 'F')
                  ? Number(((nearestVal * 9 / 5) + 32).toFixed(1))
                  : Number(nearestVal.toFixed(1));
              } else if (state.selectedSensor === 'pm') {
                chosenVal = Number(nearestVal.toFixed(1));
              } else {
                chosenVal = Math.round(nearestVal);
              }
            }
          }

          // Se siamo in un range multi-giorno personalizzato e in quell'ora il Foobot non aveva ancora dati nel DB,
          // non aggiungiamo centinaia di slot vuoti prima della prima rilevazione reale
          if (liveStore.customStartDate && chosenVal === null && series.length === 0) {
            continue;
          }

          const slotDt = new Date(slotTime * 1000);
          const hh = String(slotDt.getHours()).padStart(2, '0');
          const dd = String(slotDt.getDate()).padStart(2, '0');
          const mm = String(slotDt.getMonth() + 1).padStart(2, '0');
          labels.push(`${hh}:00`);
          series.push(chosenVal);
          dates.push(`${dd}/${mm}`);
        }
      }
    } else {
      // Per 'minuti' manteniamo una finestra rigorosa di 3 ore dall'ultimo punto registrato
      let pointsToProcess = rawPoints;
      if (range === 'minuti' && rawPoints.length > 0 && !liveStore.customStartDate) {
        const lastMinTs = Math.floor(rawPoints[rawPoints.length - 1][0]);
        const threeHoursStartTs = lastMinTs - (3 * 3600);
        const filtered = rawPoints.filter(p => p[0] >= threeHoursStartTs);
        if (filtered.length > 0) {
          pointsToProcess = filtered;
        }
        const labelStatus = document.getElementById('labelTimeNavStatus');
        if (labelStatus) {
          const firstMinTs = Math.floor(pointsToProcess[0][0]);
          labelStatus.innerHTML = formatNavTimeRange(firstMinTs, lastMinTs, 'minuti', liveStore.currentOffset === 0);
        }
      }

      // Per 'minuti' (rilevazioni ogni 5 minuti per finestra di 3 ore), '24h' e giorni
      pointsToProcess.forEach((dp, i) => {
        // Rilevamento interruzioni segnale / spegnimento sensore (stessa logica di dashboard.js)
        // Se tra due letture consecutive trascorrono oltre 12 minuti (> 720 sec), inserisce slot null con spanGaps: false
        if (i > 0 && !isDaily) {
          const prevDp = pointsToProcess[i - 1];
          const deltaSec = dp[0] - prevDp[0];
          if (deltaSec > 720) {
            const gapStartDt = new Date((prevDp[0] + 300) * 1000);
            const hhG1 = String(gapStartDt.getHours()).padStart(2, '0');
            const mmG1 = String(gapStartDt.getMinutes()).padStart(2, '0');
            const ddG1 = String(gapStartDt.getDate()).padStart(2, '0');
            const moG1 = String(gapStartDt.getMonth() + 1).padStart(2, '0');
            labels.push(`${hhG1}:${mmG1}`);
            series.push(null);
            dates.push(`${ddG1}/${moG1}`);

            if (deltaSec >= 1800) {
              const gapEndDt = new Date((dp[0] - 300) * 1000);
              const hhG2 = String(gapEndDt.getHours()).padStart(2, '0');
              const mmG2 = String(gapEndDt.getMinutes()).padStart(2, '0');
              const ddG2 = String(gapEndDt.getDate()).padStart(2, '0');
              const moG2 = String(gapEndDt.getMonth() + 1).padStart(2, '0');
              labels.push(`${hhG2}:${mmG2}`);
              series.push(null);
              dates.push(`${ddG2}/${moG2}`);
            }
          }
        }

        const dt = new Date(dp[0] * 1000);
        let timeLabel = '';

        if (isDaily) {
          timeLabel = dt.toLocaleDateString(getLocaleCode(state.lang), { day: 'numeric', month: 'short' });
        } else {
          const hh = String(dt.getHours()).padStart(2, '0');
          const mm = String(dt.getMinutes()).padStart(2, '0');
          timeLabel = `${hh}:${mm}`;
        }

        const pad = (n) => String(n).padStart(2, '0');
        const dateStr = `${pad(dt.getDate())}/${pad(dt.getMonth() + 1)}`;

        // Rilevamento pacchetto spurio a vuoto (tutti i sensori registrati contemporaneamente a zero)
        const isBlankZeroPacket = (
          (dp[1] === 0 || dp[1] === null) &&
          (dp[2] === 0 || dp[2] === null) &&
          (dp[3] === 0 || dp[3] === null) &&
          (dp[4] === 0 || dp[4] === null) &&
          (dp[6] === 0 || dp[6] === null)
        );

        let val = dp[sIdx];
        if (val !== null && val !== undefined && !isBlankZeroPacket) {
          val = Number(val);

          // Sanificazione dei valori fisicamente impossibili indoor:
          // L'umidità relativa indoor non è mai 0% (se <= 0 è sensore staccato/riscaldamento)
          // La CO2 in atmosfera terrestre è >= 400 ppm (sotto 250 ppm è pacchetto nullo)
          // La temperatura in Celsius indoor <= 0°C è pacchetto non valido
          if (state.selectedSensor === 'hum' && val <= 0) {
            val = null;
          } else if (state.selectedSensor === 'co2' && val < 250) {
            val = null;
          } else if (state.selectedSensor === 'tmp' && state.tempUnit === 'C' && val <= 0) {
            val = null;
          } else if (state.selectedSensor === 'tmp' && state.tempUnit === 'F') {
            val = Number(((val * 9 / 5) + 32).toFixed(1));
          } else if (state.selectedSensor === 'pm' || state.selectedSensor === 'tmp') {
            val = Number(val.toFixed(1));
          } else {
            val = Math.round(val);
          }
        } else if (isBlankZeroPacket) {
          val = null;
        }

        labels.push(timeLabel);
        series.push(val);
        dates.push(dateStr);
      });
    }

    liveStore.historySeries = series;
    liveStore.historyLabels = labels;
    liveStore.historyDates  = dates;
    liveStore.historyLabels = labels;

    renderSensorLineChart(state.selectedSensor, series, labels);
  }

  // ==========================================================================
  // 6. MOTORE GRAFICO CHART.JS STILE SAAS MODERNO CON VALORI ASSI & LINEA COLORATA
  // ==========================================================================
  function renderSensorLineChart(sensorKey, seriesData, labelsData) {
    const canvas = document.getElementById('sensorLineChart');
    if (!canvas || typeof Chart === 'undefined') return;

    if (state.chartInstance) {
      state.chartInstance.destroy();
      state.chartInstance = null;
    }

    const meta = SENSOR_METADATA[sensorKey] || SENSOR_METADATA.voc;
    const series = Array.isArray(seriesData) ? seriesData : [];
    const labels = Array.isArray(labelsData) ? labelsData : [];
    const ctx = canvas.getContext('2d');
    const pointCount = labels.length;

    // Distanziamento dinamico e calcolo margini geometrici al sub-pixel per consentire alla scanline
    // di coincidere esattamente (0 mm di scarto) con il centro del primo punto (a sinistra) e dell'ultimo (a destra)
    const scrollWrap = document.getElementById('chartScrollWrap');
    const chartInner = document.getElementById('chartInner');
    const scanlineEl = document.getElementById('chartScanline');
    const scrollAreaW = (scrollWrap && scrollWrap.clientWidth) ? scrollWrap.clientWidth : 320;

    let scanlineOffsetFromRight = 38;
    let scanlineViewportX = Math.max(80, scrollAreaW - scanlineOffsetFromRight);

    if (scrollWrap && scanlineEl) {
      const wrapRect = scrollWrap.getBoundingClientRect();
      const scanRect = scanlineEl.getBoundingClientRect();
      if (wrapRect.width > 0 && scanRect.width > 0) {
        const scanCenterInWrap = (scanRect.left + (scanRect.width / 2)) - wrapRect.left;
        scanlineViewportX = Math.max(80, Math.round(scanCenterInWrap));
        scanlineOffsetFromRight = Math.max(20, Math.round(wrapRect.width - scanCenterInWrap));
      }
    }

    // Margine a sinistra pari alla coordinata esatta della scanline: il primo punto (i=0) coincide con la barra
    const padLeft = scanlineViewportX;
    // Margine a destra pari alla distanza tra scanline e bordo destro di scrollWrap: l'ultimo punto (i=N-1) coincide con la barra
    const padRight = scanlineOffsetFromRight;

    let minPixelsPerPoint = 38; // ~1 cm standard per ciascun punto
    if (state.activeTimeframe === 'minuti') {
      minPixelsPerPoint = 38; // ~1 cm per i punti ogni 5 minuti (finestra 3 ore)
    } else if (state.activeTimeframe === 'ore') {
      minPixelsPerPoint = 38; // ~1 cm per ciascuna ora (mostra le 24 ore giornaliere)
    } else if (state.activeTimeframe === '24h') {
      minPixelsPerPoint = (pointCount > 100) ? 18 : 38;
    } else if (['7d', '10d', '30d'].includes(state.activeTimeframe)) {
      minPixelsPerPoint = 65; // ~1.8 cm per ciascun giorno
    }

    const dataWidth = Math.max(0, pointCount - 1) * minPixelsPerPoint;
    const targetW = Math.max(scrollAreaW, padLeft + dataWidth + padRight);

    if (scrollWrap && chartInner) {
      chartInner.style.width = targetW + 'px';
      chartInner.style.minWidth = targetW + 'px';
    }

    // Calcolo del colore dinamico di base
    let refValue = null;
    for (let i = series.length - 1; i >= 0; i--) {
      if (series[i] !== null && series[i] !== undefined && !isNaN(series[i])) {
        refValue = series[i];
        break;
      }
    }
    if (refValue === null && liveStore.lastReading) {
      refValue = liveStore.lastReading[sensorKey];
    }
    const levelKey = getSensorQualityLevel(sensorKey, refValue !== null ? refValue : 0);
    const pal = LEVEL_PALETTE[levelKey] || LEVEL_PALETTE.good;
    const strokeColor = pal.border;

    // Palette cromatica per singolo step qualitativo (Ottimo, Buono, Medio, Scarso)
    const LEVEL_COLORS = {
      great: { line: '#10b981', fill: 'rgba(16, 185, 129, 0.18)' },
      good:  { line: '#3b82f6', fill: 'rgba(59, 130, 246, 0.18)' },
      fair:  { line: '#f59e0b', fill: 'rgba(245, 158, 11, 0.18)' },
      poor:  { line: '#ef4444', fill: 'rgba(239, 68, 68, 0.18)' }
    };

    // Gradiente verticale fallback
    const waveGradient = ctx.createLinearGradient(0, 15, 0, 360);
    if (levelKey === 'great') {
      waveGradient.addColorStop(0, 'rgba(16, 185, 129, 0.28)');
      waveGradient.addColorStop(1, 'rgba(16, 185, 129, 0.01)');
    } else if (levelKey === 'good') {
      waveGradient.addColorStop(0, 'rgba(59, 130, 246, 0.28)');
      waveGradient.addColorStop(1, 'rgba(59, 130, 246, 0.01)');
    } else if (levelKey === 'fair') {
      waveGradient.addColorStop(0, 'rgba(245, 158, 11, 0.28)');
      waveGradient.addColorStop(1, 'rgba(245, 158, 11, 0.01)');
    } else {
      waveGradient.addColorStop(0, 'rgba(239, 68, 68, 0.28)');
      waveGradient.addColorStop(1, 'rgba(239, 68, 68, 0.01)');
    }

    state.chartInstance = new Chart(ctx, {
      type: 'line',
      data: {
        labels: labels,
        datasets: [
          {
            label: t(meta.titleKey),
            data: series,
            borderColor: strokeColor,
            borderWidth: 2.5,
            tension: 0.35,
            fill: true,
            spanGaps: false, // NON connette attraverso i punti null (nessuna caduta o linea a zero)
            backgroundColor: waveGradient,
            // Segment Styling dinamico: la riga e lo sfondo dell'area cambiano colore
            // nello step preciso in cui il valore sale/scende di livello qualitativo
            segment: {
              borderColor: (ctxSeg) => {
                const p = ctxSeg.p1;
                if (!p) return undefined;
                const v = p.parsed ? p.parsed.y : null;
                if (v === null || v === undefined) return undefined;
                const lvl = getSensorQualityLevel(sensorKey, v);
                return LEVEL_COLORS[lvl] ? LEVEL_COLORS[lvl].line : strokeColor;
              },
              backgroundColor: (ctxSeg) => {
                const p = ctxSeg.p1;
                if (!p) return undefined;
                const v = p.parsed ? p.parsed.y : null;
                if (v === null || v === undefined) return undefined;
                const lvl = getSensorQualityLevel(sensorKey, v);
                return LEVEL_COLORS[lvl] ? LEVEL_COLORS[lvl].fill : 'rgba(59, 130, 246, 0.18)';
              }
            },
            pointBackgroundColor: '#ffffff',
            pointBorderColor: (ctxPt) => {
              const v = ctxPt.raw;
              if (v === null || v === undefined) return '#cbd5e1';
              const lvl = getSensorQualityLevel(sensorKey, v);
              return LEVEL_COLORS[lvl] ? LEVEL_COLORS[lvl].line : strokeColor;
            },
            pointBorderWidth: 2,
            pointRadius: (ctxPt) => {
              const v = ctxPt.raw;
              if (v === null || v === undefined) return 0; // Mai mostrare pallini per slot null o interruzioni
              return (pointCount > 90) ? 2.5 : 3.5;
            },
            pointHoverRadius: 6,
            pointHoverBackgroundColor: (ctxPt) => {
              const v = ctxPt.raw;
              if (v === null || v === undefined) return '#cbd5e1';
              const lvl = getSensorQualityLevel(sensorKey, v);
              return LEVEL_COLORS[lvl] ? LEVEL_COLORS[lvl].line : strokeColor;
            },
            pointHoverBorderColor: '#ffffff',
            pointHoverBorderWidth: 2
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        layout: {
          padding: {
            top: 15,
            bottom: 5,
            left: padLeft,
            right: padRight
          }
        },
        interaction: {
          mode: 'index',
          intersect: false
        },
        plugins: {
          legend: {
            display: false
          },
          tooltip: {
            backgroundColor: '#1e293b',
            titleColor: '#94a3b8',
            titleFont: { size: 11, weight: '600' },
            bodyColor: '#ffffff',
            bodyFont: { size: 13, weight: 'bold' },
            padding: 10,
            cornerRadius: 8,
            boxPadding: 4,
            displayColors: false,
            callbacks: {
              label(context) {
                if (context.parsed.y === null || context.parsed.y === undefined) {
                  return t('chart_gap_interruption', 'Interruzione segnale');
                }
                return context.parsed.y + ' ' + meta.unit;
              }
            }
          }
        },
        scales: {
          x: {
            display: true,
            grid: {
              display: true,
              color: '#f1f5f9',
              drawBorder: false,
              drawTicks: false
            },
            ticks: {
              color: '#64748b',
              font: {
                size: 11,
                weight: '500'
              },
              padding: 8,
              maxRotation: 0,
              autoSkip: (state.activeTimeframe === 'ore') ? false : true,
              maxTicksLimit: (state.activeTimeframe === 'ore') ? 24 : 14
            },
            afterCalculateLabelRotation(scale) {
              scale.paddingLeft = 0;
              scale.paddingRight = 0;
            },
            afterFit(scale) {
              scale.paddingLeft = 0;
              scale.paddingRight = 0;
            },
            afterUpdate(scale) {
              scale.paddingLeft = 0;
              scale.paddingRight = 0;
            }
          },
          y: {
            display: true,
            position: 'right',
            suggestedMin: (meta.minScale !== undefined) ? meta.minScale : 0,
            suggestedMax: (meta.maxScale !== undefined) ? meta.maxScale : undefined,
            grid: {
              display: true,
              color: '#f1f5f9',
              drawBorder: false,
              drawTicks: false
            },
            ticks: {
              display: false, // Non mostrare etichette sul canvas scorrevole: renderizzate nella barra fissa esterna
              padding: 0
            },
            afterFit(scale) {
              scale.width = 0;
              scale.paddingTop = 0;
              scale.paddingBottom = 0;
            },
            afterUpdate(scale) {
              scale.width = 0;
              scale.paddingTop = 0;
              scale.paddingBottom = 0;
            }
          }
        }
      },
      plugins: [
        {
          id: 'fixedYAxisSync',
          afterRender(chart) {
            renderFixedYAxis(chart, sensorKey);
          }
        }
      ]
    });

    // Disegna subito la barra fissa dell'asse Y con l'unità di misura e i valori correnti
    renderFixedYAxis(state.chartInstance, sensorKey);

    // Registra listener per aggiornare la linea di scan durante lo scroll orizzontale
    attachScanlineScrollSync();

    // Scrolla il grafico verso destra per posizionarsi esattamente sul dato più recente allineato alla scanline
    // (scrollLeft = scrollWidth - clientWidth posiziona l'ultimo punto a destra sulla scanline e mostra tutta la curva a sinistra)
    if (scrollWrap) {
      const alignToLatestPoint = () => {
        scrollWrap.scrollLeft = Math.max(0, scrollWrap.scrollWidth - scrollWrap.clientWidth);
        updateScanlineValue();
      };
      window.requestAnimationFrame(alignToLatestPoint);
      setTimeout(alignToLatestPoint, 60);
    }
  }

  // ==========================================================================
  // 6.1 LINEA DI SCAN VALORE (1cm dal bordo destro dell'area scroll): CALCOLO
  // ==========================================================================
  let scanlineSyncBound = false;

  function updateScanlineValue() {
    const scrollWrap = document.getElementById('chartScrollWrap');
    const scanlineEl = document.getElementById('chartScanline');
    const badgeVal   = document.getElementById('scanBadgeValue');
    const badgeTime  = document.getElementById('scanBadgeTime');
    const badgeDate  = document.getElementById('scanBadgeDate');

    if (!scrollWrap || !state.chartInstance || !state.chartInstance.data) return;

    const dataset = state.chartInstance.data.datasets[0];
    const labels  = state.chartInstance.data.labels || [];
    if (!dataset || !dataset.data || dataset.data.length === 0) return;

    // Coordinata X esatta della scanline rispetto all'area visibile del chartScrollWrap
    let scanlineViewportX = scrollWrap.clientWidth - 38;
    if (scanlineEl) {
      const wrapRect = scrollWrap.getBoundingClientRect();
      const scanRect = scanlineEl.getBoundingClientRect();
      if (wrapRect.width > 0 && scanRect.width > 0) {
        scanlineViewportX = (scanRect.left + (scanRect.width / 2)) - wrapRect.left;
      }
    }

    // Coordinata X della scanline all'interno del canvas scrollato
    const scrollLeft = scrollWrap.scrollLeft;
    const scanlineCanvasX = scrollLeft + scanlineViewportX;

    // Trova l'indice del punto dati più vicino alla scanline usando le coordinate di Chart.js
    const metaChart = state.chartInstance.getDatasetMeta(0);
    if (!metaChart || !metaChart.data || metaChart.data.length === 0) return;

    let closestIdx = 0;
    let minDistance = Infinity;

    for (let i = 0; i < metaChart.data.length; i++) {
      const pt = metaChart.data[i];
      if (!pt) continue;
      const dist = Math.abs(pt.x - scanlineCanvasX);
      if (dist < minDistance) {
        minDistance = dist;
        closestIdx = i;
      }
    }

    const val = dataset.data[closestIdx];
    const timeLabel = labels[closestIdx] || '--:--';
    const dateLabel = (liveStore.historyDates && liveStore.historyDates[closestIdx]) || '--/--';

    if (val !== undefined && val !== null && !isNaN(val)) {
      if (badgeVal)  badgeVal.textContent  = val;
      if (badgeTime) badgeTime.textContent = timeLabel;
      if (badgeDate) badgeDate.textContent = dateLabel;
    } else {
      if (badgeVal)  badgeVal.textContent  = '--';
      if (badgeTime) badgeTime.textContent = timeLabel;
      if (badgeDate) badgeDate.textContent = dateLabel;
    }

    const scanBadge = document.getElementById('chartScanBadge');
    if (scanBadge) {
      scanBadge.classList.remove('scan-great', 'scan-good', 'scan-fair', 'scan-poor');
      if (val !== undefined && val !== null && !isNaN(val)) {
        const ptLevelKey = getSensorQualityLevel(state.selectedSensor, val);
        scanBadge.classList.add(`scan-${ptLevelKey}`);
      }
    }
  }

  // ==========================================================================
  // 6.2 BARRA ASSE Y FISSA ALL'ESTREMO DESTRO (Valori e Unità sempre visibili)
  // ==========================================================================
  function renderFixedYAxis(chart, sensorKey) {
    const yCanvas = document.getElementById('chartYAxisCanvas');
    if (!yCanvas || !chart || !chart.scales || !chart.scales.y) return;

    // Dimensioni fisse bloccate (60x360) per evitare qualsiasi espansione verticale a seguito di click ripetuti
    const barW = 60;
    const barH = 360;

    const dpr = window.devicePixelRatio || 1;
    const targetCanvasW = Math.round(barW * dpr);
    const targetCanvasH = Math.round(barH * dpr);
    if (yCanvas.width !== targetCanvasW)  yCanvas.width  = targetCanvasW;
    if (yCanvas.height !== targetCanvasH) yCanvas.height = targetCanvasH;

    const ctx = yCanvas.getContext('2d');
    ctx.clearRect(0, 0, yCanvas.width, yCanvas.height);
    ctx.save();
    ctx.scale(dpr, dpr);

    const meta = SENSOR_METADATA[sensorKey] || SENSOR_METADATA.voc;
    const yScale = chart.scales.y;

    // Recupera i ticks calcolati da Chart.js
    let ticks = (yScale.ticks && yScale.ticks.length > 0) ? yScale.ticks : [];
    if (ticks.length === 0 && !isNaN(yScale.min) && !isNaN(yScale.max)) {
      const step = (yScale.max - yScale.min) / 4;
      for (let i = 0; i <= 4; i++) {
        ticks.push({ value: Math.round(yScale.min + step * i) });
      }
    }

    ctx.font = '500 11px system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
    ctx.fillStyle = '#64748b';
    ctx.textAlign = 'right';
    ctx.textBaseline = 'middle';

    for (let i = 0; i < ticks.length; i++) {
      const tick = ticks[i];
      const yPx = yScale.getPixelForValue(tick.value);
      if (isNaN(yPx) || yPx < 6 || yPx > barH - 6) continue;

      const labelText = `${tick.value} ${meta.unit}`;
      ctx.fillText(labelText, barW - 6, yPx);
    }

    ctx.restore();
  }

  function attachScanlineScrollSync() {
    const scrollWrap = document.getElementById('chartScrollWrap');
    if (!scrollWrap) return;

    // Esegue subito un calcolo iniziale
    setTimeout(() => {
      // Posiziona lo scroll sul dato più recente (verso destra)
      scrollWrap.scrollLeft = scrollWrap.scrollWidth - scrollWrap.clientWidth;
      updateScanlineValue();
    }, 80);

    if (scanlineSyncBound) return;
    scanlineSyncBound = true;

    scrollWrap.addEventListener('scroll', () => {
      window.requestAnimationFrame(updateScanlineValue);
    }, { passive: true });

    window.addEventListener('resize', () => {
      window.requestAnimationFrame(updateScanlineValue);
      if (state.chartInstance) {
        renderFixedYAxis(state.chartInstance, state.selectedSensor);
      }
    });
  }

  // ==========================================================================
  // 7. GESTIONE EVENTI UI (NAVIGAZIONE, SIDEBAR, FILTRI, MODALE INFO)
  // ==========================================================================
  function bindEvents() {
    // Click sul cerchio principale GPI
    const gpiTrigger = document.getElementById('gpiHeroTrigger');
    if (gpiTrigger) {
      gpiTrigger.addEventListener('click', () => openSensorDetail('allpollu'));
    }

    // Click sulle fasce o sui cerchi valore (VOC, PM2.5, eCO2, Temp, Hum)
    document.querySelectorAll('[data-sensor]').forEach(el => {
      if (el.id === 'gpiHeroTrigger') return;
      el.addEventListener('click', (e) => {
        // Evita l'apertura del grafico se si clicca sul tasto circolare "i"
        if (e.target.closest('.sensor-info-btn')) return;
        const sensor = el.getAttribute('data-sensor');
        openSensorDetail(sensor);
      });
    });

    // Click sui pulsanti informazione "i" nelle fasce della Home
    document.querySelectorAll('.sensor-info-btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const sensor = btn.getAttribute('data-info-sensor');
        openInfoModal(sensor);
      });
    });

    // Click sul pulsante "i" nell'header della schermata Dettaglio
    const btnDetailInfo = document.getElementById('btnDetailInfo');
    if (btnDetailInfo) {
      btnDetailInfo.addEventListener('click', () => openInfoModal(state.selectedSensor));
    }

    // Chiusura Modale Info
    const btnCloseInfoModal = document.getElementById('btnCloseInfoModal');
    const infoModalBackdrop = document.getElementById('infoModalBackdrop');
    if (btnCloseInfoModal && infoModalBackdrop) {
      btnCloseInfoModal.addEventListener('click', () => closeInfoModal(false));
      infoModalBackdrop.addEventListener('click', (e) => {
        if (e.target === infoModalBackdrop) closeInfoModal(false);
      });
    }

    // Tasto Freccia Indietro (<) dalla schermata Dettaglio alla Home
    const btnBackToHome = document.getElementById('btnBackToHome');
    if (btnBackToHome) {
      btnBackToHome.addEventListener('click', () => {
        navigateBackFromSubScreen('screenHome');
      });
    }

    // Navigazione verso/da Schermata Dati di Connessione Dispositivi Foobot (#screenDevices)
    const btnOpenDevicesHome     = document.getElementById('btnOpenDevicesHome');
    const btnOpenDevicesSidebar  = document.getElementById('btnOpenDevicesSidebar');
    const btnBackFromDevices     = document.getElementById('btnBackFromDevices');
    const btnRefreshDevices      = document.getElementById('btnRefreshDevices');
    const btnOpenSettingsDevices = document.getElementById('btnOpenSettingsDevices');

    if (btnOpenDevicesHome) {
      btnOpenDevicesHome.addEventListener('click', () => openDevicesScreen());
    }
    if (btnOpenDevicesSidebar) {
      btnOpenDevicesSidebar.addEventListener('click', () => openDevicesScreen());
    }
    if (btnBackFromDevices) {
      btnBackFromDevices.addEventListener('click', () => {
        navigateBackFromSubScreen(previousScreenBeforeDevices || 'screenHome');
      });
    }
    if (btnRefreshDevices) {
      btnRefreshDevices.addEventListener('click', () => loadAndRenderAllDevicesCards());
    }

    // Apertura/Chiusura Sidebar Impostazioni
    const btnOpenSettingsHome   = document.getElementById('btnOpenSettingsHome');
    const btnOpenSettingsDetail = document.getElementById('btnOpenSettingsDetail');
    const btnCloseSidebar       = document.getElementById('btnCloseSidebar');
    const sidebarBackdrop       = document.getElementById('sidebarBackdrop');

    [btnOpenSettingsHome, btnOpenSettingsDetail, btnOpenSettingsDevices].forEach(btn => {
      if (btn) btn.addEventListener('click', () => toggleSidebar(true));
    });
    if (btnCloseSidebar) btnCloseSidebar.addEventListener('click', () => toggleSidebar(false));
    if (sidebarBackdrop) sidebarBackdrop.addEventListener('click', () => toggleSidebar(false));

    // Cambio Lingua nella Sidebar
    const selectLanguage = document.getElementById('selectLanguage');
    if (selectLanguage) {
      selectLanguage.addEventListener('change', async (e) => {
        const newLang = e.target.value;
        await setLanguage(newLang, true);
        if (document.getElementById('screenDevices').classList.contains('active')) {
          loadAndRenderAllDevicesCards();
        }
        if (document.getElementById('screenDetail').classList.contains('active')) {
          const meta = SENSOR_METADATA[state.selectedSensor];
          const detailTitle = document.getElementById('detailSensorTitle');
          if (meta && detailTitle) detailTitle.textContent = t(meta.titleKey);
          await fetchChartData(state.activeTimeframe, liveStore.currentOffset);
        }
      });
    }

    // Cambio Unità Temperatura (°C / °F)
    document.querySelectorAll('[data-temp-unit]').forEach(btn => {
      btn.addEventListener('click', () => {
        state.tempUnit = btn.getAttribute('data-temp-unit');
        localStorage.setItem('nf_pwa_temp_unit', state.tempUnit);
        syncTempUnitButtons();
        renderHomeValues();
      });
    });

    // Gestione Custom Dropdown Sensore nel Dettaglio (Navigazione Rapida)
    const customSensorDropdown = document.getElementById('customSensorDropdown');
    const btnSensorDropdown    = document.getElementById('btnSensorDropdown');
    const sensorDropdownMenu   = document.getElementById('sensorDropdownMenu');

    if (btnSensorDropdown && customSensorDropdown) {
      btnSensorDropdown.addEventListener('click', (e) => {
        e.stopPropagation();
        const isOpen = customSensorDropdown.classList.toggle('open');
        btnSensorDropdown.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      });

      document.addEventListener('click', (e) => {
        if (!customSensorDropdown.contains(e.target)) {
          customSensorDropdown.classList.remove('open');
          btnSensorDropdown.setAttribute('aria-expanded', 'false');
        }
      });
    }

    if (sensorDropdownMenu) {
      sensorDropdownMenu.querySelectorAll('.dropdown-item').forEach(item => {
        item.addEventListener('click', () => {
          const sensorKey = item.getAttribute('data-sensor');
          if (customSensorDropdown) customSensorDropdown.classList.remove('open');
          if (btnSensorDropdown) btnSensorDropdown.setAttribute('aria-expanded', 'false');
          openSensorDetail(sensorKey);
        });
      });
    }

    // Configurazione Link Dinamico al Sito Originale / Server DB
    const linkOriginalSite = document.getElementById('linkOriginalSite');
    const labelOriginalSiteUrl = document.getElementById('labelOriginalSiteUrl');
    if (linkOriginalSite) {
      // Se l'app gira dentro barbaralerici.net o localmente ricava l'origine corretta
      const originHost = window.location.hostname || 'foobot-sensore.barbaralerici.net';
      const fullOriginUrl = window.location.protocol.startsWith('http')
        ? window.location.origin.replace(/\/app\/?$/, '')
        : 'https://foobot-sensore.barbaralerici.net';

      linkOriginalSite.href = fullOriginUrl;
      if (labelOriginalSiteUrl) {
        labelOriginalSiteUrl.textContent = originHost === 'localhost' || originHost === '127.0.0.1'
          ? 'foobot-sensore.barbaralerici.net'
          : originHost;
      }
    }

    // Gestione Custom Dropdown Dispositivo Foobot (Bordi Arrotondati)
    const customDropdownWrap = document.getElementById('customDeviceDropdown');
    const btnDeviceDropdown  = document.getElementById('btnDeviceDropdown');
    const deviceDropdownMenu = document.getElementById('deviceDropdownMenu');
    const sidebarDeviceSelect = document.getElementById('sidebarDeviceSelect');

    if (btnDeviceDropdown && customDropdownWrap) {
      btnDeviceDropdown.addEventListener('click', (e) => {
        e.stopPropagation();
        const isOpen = customDropdownWrap.classList.toggle('open');
        btnDeviceDropdown.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      });

      document.addEventListener('click', (e) => {
        if (!customDropdownWrap.contains(e.target)) {
          customDropdownWrap.classList.remove('open');
          btnDeviceDropdown.setAttribute('aria-expanded', 'false');
        }
      });
    }

    if (deviceDropdownMenu) {
      deviceDropdownMenu.querySelectorAll('.dropdown-item').forEach(item => {
        item.addEventListener('click', () => {
          const val = item.getAttribute('data-uuid') || item.getAttribute('data-value');
          if (val) switchActiveDevice(val);
        });
      });
    }

    if (sidebarDeviceSelect) {
      sidebarDeviceSelect.addEventListener('change', (e) => {
        switchActiveDevice(e.target.value);
      });
    }

    // Filtri Temporali SaaS (Pills Scrollabili: Minuti, Ore, 24h, 7 gg, 30 gg)
    // Se è attivo un filtro di range tra date personalizzato, mantiene il range di date attivo
    // e ricarica il grafico con la densità/risoluzione del pulsante cliccato
    document.querySelectorAll('.saas-pill-btn').forEach(btn => {
      btn.addEventListener('click', async () => {
        document.querySelectorAll('.saas-pill-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        state.activeTimeframe = btn.getAttribute('data-range');

        if (!liveStore.customStartDate) {
          liveStore.currentOffset = 0;
          const labelStatus = document.getElementById('labelTimeNavStatus');
          if (labelStatus) labelStatus.textContent = 'Tempo Reale';
          const btnNext = document.getElementById('btnTimeNavNext');
          if (btnNext) {
            btnNext.disabled = true;
            btnNext.classList.add('disabled');
          }
        }
        await fetchChartData(state.activeTimeframe, liveStore.currentOffset);
      });
    });

    // Toggle Sezione Date Personalizzate (Calendario espandibile/collassabile)
    const btnToggleCustomDates = document.getElementById('btnToggleCustomDates');
    const customDateSection    = document.getElementById('customDateSection');
    const btnCancelDates       = document.getElementById('btnCancelDates');
    const btnFilterDates       = document.getElementById('btnFilterDates');

    function toggleCustomDatesCollapse(forceClose) {
      if (!customDateSection) return;
      const isOpen = forceClose ? false : !customDateSection.classList.contains('open');
      customDateSection.classList.toggle('open', isOpen);
      if (btnToggleCustomDates) {
        btnToggleCustomDates.classList.toggle('active', isOpen);
        btnToggleCustomDates.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      }
    }

    if (btnToggleCustomDates) {
      btnToggleCustomDates.addEventListener('click', () => toggleCustomDatesCollapse(false));
    }

    if (btnCancelDates) {
      btnCancelDates.addEventListener('click', async () => {
        toggleCustomDatesCollapse(true);
        const inputFrom = document.getElementById('inputDateFrom');
        const inputTo   = document.getElementById('inputDateTo');
        if (inputFrom) inputFrom.value = '';
        if (inputTo)   inputTo.value = '';
        liveStore.customStartDate = '';
        liveStore.customEndDate = '';
        document.querySelectorAll('.saas-pill-btn').forEach(b => {
          b.classList.toggle('active', b.getAttribute('data-range') === state.activeTimeframe);
        });
        await fetchChartData(state.activeTimeframe, liveStore.currentOffset);
      });
    }

    if (btnFilterDates) {
      btnFilterDates.addEventListener('click', async () => {
        const inputFrom = document.getElementById('inputDateFrom');
        const inputTo   = document.getElementById('inputDateTo');
        if (inputFrom && inputTo && inputFrom.value) {
          const fromVal = inputFrom.value.trim();
          const toVal   = inputTo.value ? inputTo.value.trim() : fromVal;

          liveStore.customStartDate = fromVal;
          liveStore.customEndDate = toVal;

          // Mantiene evidenziato il pulsante di risoluzione attivo (Minuti, Ore, 24h, 7 gg, 30 gg)
          document.querySelectorAll('.saas-pill-btn').forEach(b => {
            b.classList.toggle('active', b.getAttribute('data-range') === state.activeTimeframe);
          });
          const labelStatus = document.getElementById('labelTimeNavStatus');
          if (labelStatus) {
            labelStatus.innerHTML = formatCustomDateRangeLabel(fromVal, toVal);
          }
          toggleCustomDatesCollapse(true);
          await fetchChartData(state.activeTimeframe, 0);
        }
      });
    }

    // Navigazione temporale rapida (Frecce Sinistra / Destra e Reset Tempo Reale)
    const btnTimeNavPrev  = document.getElementById('btnTimeNavPrev');
    const btnTimeNavNext  = document.getElementById('btnTimeNavNext');
    const btnResetTimeNav = document.getElementById('btnResetTimeNav');

    if (btnResetTimeNav) {
      btnResetTimeNav.addEventListener('click', async () => {
        if (liveStore.currentOffset !== 0 || liveStore.customStartDate) {
          liveStore.currentOffset = 0;
          liveStore.customStartDate = '';
          liveStore.customEndDate = '';
          const inputFrom = document.getElementById('inputDateFrom');
          const inputTo   = document.getElementById('inputDateTo');
          if (inputFrom) inputFrom.value = '';
          if (inputTo)   inputTo.value = '';
          document.querySelectorAll('.saas-pill-btn').forEach(b => {
            b.classList.toggle('active', b.getAttribute('data-range') === state.activeTimeframe);
          });
          const labelStatus = document.getElementById('labelTimeNavStatus');
          if (labelStatus) labelStatus.textContent = 'Tempo Reale';
          if (btnTimeNavNext) {
            btnTimeNavNext.disabled = true;
            btnTimeNavNext.classList.add('disabled');
          }
          await fetchChartData(state.activeTimeframe, 0);
        }
      });
    }

    if (btnTimeNavPrev) {
      btnTimeNavPrev.addEventListener('click', async () => {
        liveStore.currentOffset += 1;
        if (btnTimeNavNext) {
          btnTimeNavNext.disabled = false;
          btnTimeNavNext.classList.remove('disabled');
        }
        await fetchChartData(state.activeTimeframe, liveStore.currentOffset);
      });
    }

    if (btnTimeNavNext) {
      btnTimeNavNext.addEventListener('click', async () => {
        if (liveStore.currentOffset > 0) {
          liveStore.currentOffset -= 1;
        }
        if (liveStore.currentOffset === 0) {
          btnTimeNavNext.disabled = true;
          btnTimeNavNext.classList.add('disabled');
        }
        await fetchChartData(state.activeTimeframe, liveStore.currentOffset);
      });
    }

    // ========================================================================
    // GESTIONE INSTALLAZIONE PWA (SOLO BANNER IN TESTATA TEMPORIZZATO)
    // ========================================================================
    const btnPwaInstall     = document.getElementById('btnPwaInstall');
    const btnDismissInstall = document.getElementById('btnDismissInstall');

    if (btnPwaInstall) {
      btnPwaInstall.addEventListener('click', triggerPwaInstall);
    }
    if (btnDismissInstall) {
      btnDismissInstall.addEventListener('click', dismissInstallBanner);
    }

    // ========================================================================
    // GESTIONE SCHERMATA LOGIN & CONNESSIONE SERVER (#screenAuth)
    // ========================================================================
    const tabBtnCredentials = document.getElementById('tabBtnCredentials');
    const tabBtnTicket      = document.getElementById('tabBtnTicket');
    const formAuthLogin     = document.getElementById('formAuthLogin');
    const formAuthTicket    = document.getElementById('formAuthTicket');

    if (tabBtnCredentials && tabBtnTicket) {
      tabBtnCredentials.addEventListener('click', () => {
        tabBtnCredentials.classList.add('active');
        tabBtnTicket.classList.remove('active');
        if (formAuthLogin) formAuthLogin.classList.add('active');
        if (formAuthTicket) formAuthTicket.classList.remove('active');
        setAuthAlert('', '');
      });

      tabBtnTicket.addEventListener('click', () => {
        tabBtnTicket.classList.add('active');
        tabBtnCredentials.classList.remove('active');
        if (formAuthTicket) formAuthTicket.classList.add('active');
        if (formAuthLogin) formAuthLogin.classList.remove('active');
        setAuthAlert('', '');
      });
    }

    if (formAuthLogin) {
      formAuthLogin.addEventListener('submit', handleAuthLogin);
    }
    if (formAuthTicket) {
      formAuthTicket.addEventListener('submit', handleAuthTicket);
    }

    // Pulsante Disconnetti Account / Cambia Server
    const btnSidebarLogout = document.getElementById('btnSidebarLogout');
    if (btnSidebarLogout) {
      btnSidebarLogout.addEventListener('click', handleLogout);
    }
  }

  // ==========================================================================
  // HANDLERS AUTENTICAZIONE E DISCONNESSIONE
  // ==========================================================================
  async function handleAuthLogin(e) {
    e.preventDefault();
    const serverUrlInput = document.getElementById('inputServerUrl');
    const userField      = document.getElementById('inputAuthUser');
    const passField      = document.getElementById('inputAuthPass');
    const btnSubmit      = document.getElementById('btnSubmitLogin');

    const serverRaw = serverUrlInput ? serverUrlInput.value.trim() : '';
    const user = userField ? userField.value.trim() : '';
    const pass = passField ? passField.value : '';

    if (!user || !pass) return;

    if (serverRaw) {
      updateServerBaseUrl(serverRaw);
    }

    setAuthAlert('info', 'Verifica credenziali e connessione in corso...');
    if (btnSubmit) btnSubmit.disabled = true;

    try {
      const serial = getOrCreateDeviceSerial();
      const mode = getAppExecutionMode();
      const res = await fetch(API_CONFIG.baseUrl + 'device_auth.php?action=login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'include',
        body: JSON.stringify({
          identity: user,
          password: pass,
          device_serial: serial,
          app_mode: mode,
          device_name: (mode === 'standalone' ? 'App Mobile PWA' : 'Scheda Browser') + ' (' + (navigator.platform || 'Device') + ')'
        })
      });

      const json = await res.json();
      if (res.ok && json.success && json.device_token) {
        liveStore.token = json.device_token;
        localStorage.setItem(API_CONFIG.deviceTokenKey, liveStore.token);
        if (json.device_serial) {
          localStorage.setItem('nf_device_serial', json.device_serial);
        }
        if (json.device_reg_id || json.device_id) {
          setDeviceRegistrationId(json.device_reg_id || json.device_id);
        }
        const authedUser = json.connected_user || json.user || '';
        if (authedUser) {
          liveStore.connectedUser = authedUser;
          localStorage.setItem('nf_connected_user', authedUser);
        }
        if (json.default_uuid) {
          liveStore.activeUuid = json.default_uuid;
          localStorage.setItem(API_CONFIG.activeDeviceKey, json.default_uuid);
        }
        setAuthAlert('success', t('auth_success_connected'));
        setTimeout(async () => {
          setAuthAlert('', '');
          showScreen('screenHome');
          await initDeviceAndData();
          startLivePolling();
        }, 400);
      } else {
        setAuthAlert('error', json.message || t('auth_error_creds'));
      }
    } catch (err) {
      console.warn('Errore login:', err);
      setAuthAlert('error', t('auth_error_network'));
    } finally {
      if (btnSubmit) btnSubmit.disabled = false;
    }
  }

  async function handleAuthTicket(e) {
    e.preventDefault();
    const serverUrlInput = document.getElementById('inputServerUrl');
    const ticketField    = document.getElementById('inputAuthTicket');
    const btnSubmit      = document.getElementById('btnSubmitTicket');

    const serverRaw  = serverUrlInput ? serverUrlInput.value.trim() : '';
    const ticketCode = ticketField ? ticketField.value.trim().toUpperCase() : '';

    if (!ticketCode) return;

    if (serverRaw) {
      updateServerBaseUrl(serverRaw);
    }

    setAuthAlert('info', 'Verifica codice ticket in corso...');
    if (btnSubmit) btnSubmit.disabled = true;

    try {
      const serial = getOrCreateDeviceSerial();
      const mode = getAppExecutionMode();
      const res = await fetch(API_CONFIG.baseUrl + 'device_auth.php?action=claim_ticket', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'include',
        body: JSON.stringify({
          ticket_code: ticketCode,
          device_serial: serial,
          app_mode: mode,
          device_name: (mode === 'standalone' ? 'App Mobile PWA' : 'Scheda Browser') + ' (' + (navigator.platform || 'Device') + ')'
        })
      });

      const json = await res.json();
      if (res.ok && json.success && json.device_token) {
        liveStore.token = json.device_token;
        localStorage.setItem(API_CONFIG.deviceTokenKey, liveStore.token);
        if (json.device_serial) {
          localStorage.setItem('nf_device_serial', json.device_serial);
        }
        if (json.device_reg_id || json.device_id) {
          setDeviceRegistrationId(json.device_reg_id || json.device_id);
        }
        const authedUser = json.connected_user || json.user || '';
        if (authedUser) {
          liveStore.connectedUser = authedUser;
          localStorage.setItem('nf_connected_user', authedUser);
        }
        if (json.default_uuid) {
          liveStore.activeUuid = json.default_uuid;
          localStorage.setItem(API_CONFIG.activeDeviceKey, json.default_uuid);
        }
        setAuthAlert('success', t('auth_success_connected'));
        setTimeout(async () => {
          setAuthAlert('', '');
          showScreen('screenHome');
          await initDeviceAndData();
          startLivePolling();
        }, 400);
      } else {
        setAuthAlert('error', json.message || t('auth_error_ticket'));
      }
    } catch (err) {
      console.warn('Errore claim ticket:', err);
      setAuthAlert('error', t('auth_error_network'));
    } finally {
      if (btnSubmit) btnSubmit.disabled = false;
    }
  }

  function handleLogout() {
    liveStore.token = '';
    liveStore.activeUuid = '';
    liveStore.lastReading = null;
    liveStore.connectedUser = '';
    liveStore.deviceRegId = '';
    localStorage.removeItem(API_CONFIG.deviceTokenKey);
    localStorage.removeItem(API_CONFIG.activeDeviceKey);
    localStorage.removeItem('nf_connected_user');
    localStorage.removeItem('nf_device_reg_id');
    const userBadge = document.getElementById('sidebarConnectedUser');
    if (userBadge) userBadge.textContent = '--';
    const regBadge = document.getElementById('sidebarDeviceRegId');
    if (regBadge) regBadge.textContent = '--';
    if (liveStore.pollTimer) {
      clearInterval(liveStore.pollTimer);
      liveStore.pollTimer = null;
    }
    toggleSidebar(false);
    showScreen('screenAuth');
    populateServerUrlInput();
    updateConnectionBadge(false);
  }

  function toggleSidebar(open, fromPopState = false) {
    const sidebar  = document.getElementById('settingsSidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    const wasOpen  = sidebar ? sidebar.classList.contains('open') : false;

    if (sidebar)  sidebar.classList.toggle('open', open);
    if (backdrop) backdrop.classList.toggle('open', open);

    if (open) {
      setConnectedUsername(liveStore.connectedUser);
      setDeviceRegistrationId(liveStore.deviceRegId);
      if ((!liveStore.connectedUser || liveStore.connectedUser === '--' || !liveStore.deviceRegId || liveStore.deviceRegId === '--') && liveStore.token) {
        ensureConnectedUserResolved();
      }
      if (!wasOpen && !fromPopState && window.history && window.history.pushState) {
        window.history.pushState({ screen: getCurrentScreenId(), overlay: 'sidebar' }, '');
      }
    } else if (wasOpen && !fromPopState && window.history && window.history.state && window.history.state.overlay === 'sidebar') {
      ignoringNextPopState = true;
      window.history.back();
    }
  }

  function syncTempUnitButtons() {
    document.querySelectorAll('[data-temp-unit]').forEach(btn => {
      btn.classList.toggle('active', btn.getAttribute('data-temp-unit') === state.tempUnit);
    });
  }

  // Apre la finestra modale con la descrizione e le fasce di soglia del sensore
  function openInfoModal(sensorKey) {
    const meta = SENSOR_METADATA[sensorKey];
    if (!meta) return;

    const modalBackdrop = document.getElementById('infoModalBackdrop');
    const titleEl       = document.getElementById('infoModalTitle');
    const descEl        = document.getElementById('infoModalDesc');
    const listEl        = document.getElementById('infoModalThresholds');
    const wasOpen       = modalBackdrop ? modalBackdrop.classList.contains('open') : false;

    if (titleEl) titleEl.textContent = t(meta.titleKey);
    if (descEl)  descEl.textContent  = t(meta.descKey);

    if (listEl) {
      listEl.innerHTML = meta.thresholds.map(th => `
        <div class="threshold-item-row ${th.cls}">
          <span>${t(th.statusKey)}</span>
          <span>${th.label}</span>
        </div>
      `).join('');
    }

    if (modalBackdrop) {
      modalBackdrop.classList.add('open');
      if (!wasOpen && window.history && window.history.pushState) {
        window.history.pushState({ screen: getCurrentScreenId(), overlay: 'infoModal' }, '');
      }
    }
  }

  function closeInfoModal(fromPopState = false) {
    const modalBackdrop = document.getElementById('infoModalBackdrop');
    const wasOpen = modalBackdrop ? modalBackdrop.classList.contains('open') : false;
    if (modalBackdrop) modalBackdrop.classList.remove('open');

    if (wasOpen && !fromPopState && window.history && window.history.state && window.history.state.overlay === 'infoModal') {
      ignoringNextPopState = true;
      window.history.back();
    }
  }

})();
