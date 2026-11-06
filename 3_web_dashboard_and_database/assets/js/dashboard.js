window.itMonths = ['gen', 'feb', 'mar', 'apr', 'mag', 'giu', 'lug', 'ago', 'set', 'ott', 'nov', 'dic'];
var itMonths = window.itMonths;
let myChart = null;
let currentRange = '24h';
let currentOffset = 0;
let customStartDate = null;
let customEndDate = null;
let activeSensorTab = 'tutti';
let hoveredDatasetIndex = -1;
let lastPointerY = null;
let lastPointerX = null;
const userHiddenDatasets = new Set();

/**
 * Funzione helper per ottenere le traduzioni da window.FOOBOT_I18N
 */
function t(key, fallback = '') {
  if (window.FOOBOT_I18N && window.FOOBOT_I18N[key]) {
    return window.FOOBOT_I18N[key];
  }
  return fallback || key;
}

/**
 * Helper per parametrizzare l'UUID del sensore Foobot attivo (Multi-Foobot)
 */
function getDeviceUuidQuery(prefix = '&') {
  if (window.CURRENT_DEVICE_UUID && window.CURRENT_DEVICE_UUID !== '') {
    return prefix + 'uuid=' + encodeURIComponent(window.CURRENT_DEVICE_UUID);
  }
  return '';
}

/**
 * Soglie Ufficiali Foobot estratte da com.airboxlab.foobot.main.SensorThresholds:
 * - GLOBAL_INDEX: Great < 25, Good < 50, Fair < 75, Poor >= 75
 * - PM: Great < 12.5, Good < 25.0, Fair < 37.5, Poor >= 37.5 (µg/m³)
 * - VOC: Great < 150, Good < 300, Fair < 450, Poor >= 450 (ppb)
 * - CO2: Great < 625, Good < 1300, Fair < 1925, Poor >= 1925 (ppm)
 * - TMP: Great 18-24°C, Good 16-18 o 24-26°C, Fair 14-16 o 26-28°C, Poor <14 o >28°C
 * - HUM: Great 40-60%, Good 30-40 o 60-70%, Fair 20-30 o 70-80%, Poor <20 o >80%
 */
function getThresholdConfigs() {
  return {
    allpollu: {
      min: 0, max: 100, unit: '%',
      title: t('title_allpollu', 'GPI — Global Pollution Index (Indice Inquinamento)'),
      desc: t('desc_allpollu'),
      levels: [
        { max: 25, label: t('level_great', 'SUPER (Ottimo)'), class: 'bg-emerald-500', badgeClass: 'bg-emerald-100 text-emerald-800', color: 'rgba(16, 185, 129, 0.12)', range: '0 — 25%' },
        { max: 50, label: t('level_good', 'BUONO (Good)'), class: 'bg-blue-500', badgeClass: 'bg-blue-100 text-blue-800', color: 'rgba(59, 130, 246, 0.12)', range: '25 — 50%' },
        { max: 75, label: t('level_fair', 'MEDIO (Fair)'), class: 'bg-amber-500', badgeClass: 'bg-amber-100 text-amber-800', color: 'rgba(245, 158, 11, 0.15)', range: '50 — 75%' },
        { max: 100, label: t('level_poor', 'CATTIVO (Poor)'), class: 'bg-rose-500', badgeClass: 'bg-rose-100 text-rose-800', color: 'rgba(239, 68, 68, 0.18)', range: '75 — 100%' }
      ]
    },
    pm: {
      min: 0, max: 50, unit: 'µg/m³',
      title: t('title_pm', 'PM2.5 — Particulate Matter (Polveri Sottili)'),
      desc: t('desc_pm'),
      levels: [
        { max: 12.5, label: t('level_great', 'SUPER (Ottimo)'), class: 'bg-emerald-500', badgeClass: 'bg-emerald-100 text-emerald-800', color: 'rgba(16, 185, 129, 0.12)', range: '0 — 12.5 µg/m³' },
        { max: 25.0, label: t('level_good', 'BUONO (Good)'), class: 'bg-blue-500', badgeClass: 'bg-blue-100 text-blue-800', color: 'rgba(59, 130, 246, 0.12)', range: '12.5 — 25.0 µg/m³' },
        { max: 37.5, label: t('level_fair', 'MEDIO (Fair)'), class: 'bg-amber-500', badgeClass: 'bg-amber-100 text-amber-800', color: 'rgba(245, 158, 11, 0.15)', range: '25.0 — 37.5 µg/m³' },
        { max: 50.0, label: t('level_poor', 'CATTIVO (Poor)'), class: 'bg-rose-500', badgeClass: 'bg-rose-100 text-rose-800', color: 'rgba(239, 68, 68, 0.18)', range: '> 37.5 µg/m³' }
      ]
    },
    voc: {
      min: 0, max: 600, unit: 'ppb',
      title: t('title_voc', 'VOC — Volatile Organic Compounds (Composti Volatili)'),
      desc: t('desc_voc'),
      levels: [
        { max: 150, label: t('level_voc_great', 'SUPER (Salubre)'), class: 'bg-emerald-500', badgeClass: 'bg-emerald-100 text-emerald-800', color: 'rgba(16, 185, 129, 0.12)', range: '0 — 150 ppb' },
        { max: 300, label: t('level_good', 'BUONO (Good)'), class: 'bg-blue-500', badgeClass: 'bg-blue-100 text-blue-800', color: 'rgba(59, 130, 246, 0.12)', range: '150 — 300 ppb' },
        { max: 450, label: t('level_fair', 'MEDIO (Fair)'), class: 'bg-amber-500', badgeClass: 'bg-amber-100 text-amber-800', color: 'rgba(245, 158, 11, 0.15)', range: '300 — 450 ppb' },
        { max: 600, label: t('level_poor', 'CATTIVO (Poor)'), class: 'bg-rose-500', badgeClass: 'bg-rose-100 text-rose-800', color: 'rgba(239, 68, 68, 0.18)', range: '> 450 ppb' }
      ]
    },
    co2: {
      min: 450, max: 2000, unit: 'ppm',
      title: t('title_co2', 'eCO2 — Carbon Dioxide Equivalent (CO2 Stimata)'),
      desc: t('desc_co2'),
      levels: [
        { max: 625, label: t('level_great', 'SUPER (Ottimo)'), class: 'bg-emerald-500', badgeClass: 'bg-emerald-100 text-emerald-800', color: 'rgba(16, 185, 129, 0.12)', range: '450 — 625 ppm' },
        { max: 1300, label: t('level_co2_good', 'BUONO (Normale)'), class: 'bg-blue-500', badgeClass: 'bg-blue-100 text-blue-800', color: 'rgba(59, 130, 246, 0.12)', range: '625 — 1300 ppm' },
        { max: 1925, label: t('level_co2_fair', 'MEDIO (Ventilare)'), class: 'bg-amber-500', badgeClass: 'bg-amber-100 text-amber-800', color: 'rgba(245, 158, 11, 0.15)', range: '1300 — 1925 ppm' },
        { max: 2500, label: t('level_co2_poor', 'CATTIVO (Soffocante)'), class: 'bg-rose-500', badgeClass: 'bg-rose-100 text-rose-800', color: 'rgba(239, 68, 68, 0.18)', range: '> 1925 ppm' }
      ]
    },
    temp: {
      min: (window.FOOBOT_TEMP_UNIT === 'F') ? 50 : 10,
      max: (window.FOOBOT_TEMP_UNIT === 'F') ? 95 : 35,
      unit: (window.FOOBOT_TEMP_UNIT === 'F') ? '°F' : '°C',
      title: t('title_temp', 'TMP — Temperatura Ambiente'),
      desc: t('desc_temp'),
      levels: (window.FOOBOT_TEMP_UNIT === 'F') ? [
        { max: 64.4, label: t('level_temp_cool', 'FRESCO'), class: 'bg-blue-500', badgeClass: 'bg-blue-100 text-blue-800', color: 'rgba(59, 130, 246, 0.12)', range: '< 64.4 °F' },
        { max: 75.2, label: t('level_temp_comfort', 'SUPER (Comfort)'), class: 'bg-emerald-500', badgeClass: 'bg-emerald-100 text-emerald-800', color: 'rgba(16, 185, 129, 0.12)', range: '64.4 — 75.2 °F' },
        { max: 82.4, label: t('level_temp_warm', 'CALDO (Moderato)'), class: 'bg-amber-500', badgeClass: 'bg-amber-100 text-amber-800', color: 'rgba(245, 158, 11, 0.15)', range: '75.2 — 82.4 °F' },
        { max: 104,  label: t('level_temp_hot', 'MOLTO CALDO'), class: 'bg-rose-500', badgeClass: 'bg-rose-100 text-rose-800', color: 'rgba(239, 68, 68, 0.18)', range: '> 82.4 °F' }
      ] : [
        { max: 18, label: t('level_temp_cool', 'FRESCO'), class: 'bg-blue-500', badgeClass: 'bg-blue-100 text-blue-800', color: 'rgba(59, 130, 246, 0.12)', range: '< 18 °C' },
        { max: 24, label: t('level_temp_comfort', 'SUPER (Comfort)'), class: 'bg-emerald-500', badgeClass: 'bg-emerald-100 text-emerald-800', color: 'rgba(16, 185, 129, 0.12)', range: '18 — 24 °C' },
        { max: 28, label: t('level_temp_warm', 'CALDO (Moderato)'), class: 'bg-amber-500', badgeClass: 'bg-amber-100 text-amber-800', color: 'rgba(245, 158, 11, 0.15)', range: '24 — 28 °C' },
        { max: 40, label: t('level_temp_hot', 'MOLTO CALDO'), class: 'bg-rose-500', badgeClass: 'bg-rose-100 text-rose-800', color: 'rgba(239, 68, 68, 0.18)', range: '> 28 °C' }
      ]
    },
    hum: {
      min: 0, max: 100, unit: '%',
      title: t('title_hum', 'HUM — Umidità Relativa'),
      desc: t('desc_hum'),
      levels: [
        { max: 40, label: t('level_hum_dry', 'SECCO'), class: 'bg-amber-400', badgeClass: 'bg-amber-100 text-amber-800', color: 'rgba(245, 158, 11, 0.15)', range: '< 40%' },
        { max: 60, label: t('level_hum_ideal', 'SUPER (Ideale)'), class: 'bg-emerald-500', badgeClass: 'bg-emerald-100 text-emerald-800', color: 'rgba(16, 185, 129, 0.12)', range: '40 — 60%' },
        { max: 80, label: t('level_hum_humid', 'UMIDO'), class: 'bg-cyan-500', badgeClass: 'bg-cyan-100 text-cyan-800', color: 'rgba(6, 182, 212, 0.12)', range: '60 — 80%' },
        { max: 100, label: t('level_hum_very_humid', 'MOLTO UMIDO'), class: 'bg-rose-500', badgeClass: 'bg-rose-100 text-rose-800', color: 'rgba(239, 68, 68, 0.18)', range: '> 80%' }
      ]
    }
  };
}

// Calcola lo stato Foobot per un valore numerico
function evaluateFoobotState(key, val) {
  const configs = getThresholdConfigs();
  const conf = configs[key];
  if (!conf) return { label: '--', class: 'bg-slate-400', badgeClass: 'bg-slate-100 text-slate-700' };

  if (key === 'temp') {
    const tempC = (window.FOOBOT_TEMP_UNIT === 'F' && val > 45) ? ((val - 32) * 5 / 9) : val;
    if (tempC >= 18 && tempC <= 24) return conf.levels[1];
    if (tempC < 18) return conf.levels[0];
    if (tempC <= 28) return conf.levels[2];
    return conf.levels[3];
  }

  if (key === 'hum') {
    if (val >= 40 && val <= 60) return conf.levels[1];
    if (val < 40) return conf.levels[0];
    if (val <= 80) return conf.levels[2];
    return conf.levels[3];
  }

  for (let lvl of conf.levels) {
    if (val <= lvl.max) return lvl;
  }
  return conf.levels[conf.levels.length - 1];
}

// Genera annotazioni Chart.js
function generateAnnotations(paramKey, chartMax, chartMin) {
  const keyMap = { inquinamento: 'allpollu', pm25: 'pm', voc: 'voc', co2: 'co2', temp: 'temp', umidita: 'hum' };
  const confKey = keyMap[paramKey];
  const configs = getThresholdConfigs();
  const conf = configs[confKey];
  if (!conf) return {};

  const targetScale = (confKey === 'voc' || confKey === 'co2') ? 'y1' : 'y';
  const annotations = {};
  let prevMin = 0;
  if (confKey === 'co2') {
    prevMin = (typeof chartMin === 'number' && chartMin < 450) ? chartMin : 400;
  } else if (confKey === 'temp') {
    prevMin = (typeof chartMin === 'number' && chartMin < 10) ? chartMin : 10;
  }

  conf.levels.forEach((lvl, i) => {
    const isLast = (i === conf.levels.length - 1);
    const topLimit = (isLast && typeof chartMax === 'number' && chartMax > lvl.max) ? chartMax : lvl.max;

    annotations['zone' + i] = {
      type: 'box',
      yScaleID: targetScale,
      yMin: prevMin,
      yMax: topLimit,
      backgroundColor: lvl.color,
      borderWidth: 0,
      drawTime: 'beforeDatasetsDraw',
      label: {
        display: false
      }
    };
    prevMin = lvl.max;
  });
  return annotations;
}

// Formattazione tempo trascorso localizzata per ciascuna lingua
function formatTimeAgo(minsAgo) {
  if (minsAgo < 1) return t('time_just_now', 'pochi secondi fa');
  const loc = getAppLocale();
  if (loc === 'fr') return `il y a ${minsAgo} min`;
  if (loc === 'es') return `hace ${minsAgo} min`;
  if (loc === 'de') return `vor ${minsAgo} Min.`;
  if (loc === 'en') return `${minsAgo} ${minsAgo === 1 ? 'min ago' : 'mins ago'}`;
  return `${minsAgo} ${t('time_mins_ago', 'min fa')}`;
}

// Lingua attiva dell'applicazione per la formattazione date e numeri
function getAppLocale() {
  return (document.documentElement.lang || 'it').toLowerCase();
}

// Caricamento ultima lettura live e aggiornamento dinamico KPI card
async function loadLatestData() {
  try {
    const res = await fetch('api/get_datapoints.php?range=live' + getDeviceUuidQuery('&'));
    const json = await res.json();
    if (!json.datapoints || json.datapoints.length === 0) return;

    const d = json.datapoints[0];
    const time = new Date(d[0] * 1000);
    const allpollu = Number(d[1]);
    const pm = Number(d[2]);
    const voc = Number(d[3]);
    const co2 = Number(d[4]);
    const tmp = Number(d[5]);
    const hum = Number(d[6]);

    const isF = (window.FOOBOT_TEMP_UNIT === 'F');
    const displayTmp = isF ? ((tmp * 9 / 5) + 32) : tmp;
    const tempUnitSym = isF ? '°F' : '°C';

    const setTxt = (id, txt) => { const el = document.getElementById(id); if (el) el.innerText = txt; };
    setTxt('val-allpollu', allpollu);
    setTxt('val-voc', voc);
    setTxt('val-pm', pm.toFixed(1));
    setTxt('val-co2', co2);
    setTxt('val-tmp', displayTmp.toFixed(1));
    setTxt('unit-tmp', tempUnitSym);
    setTxt('val-hum', hum);
    const loc = getAppLocale();
    const formattedTimeWithSec = time.toLocaleDateString(loc, { day: '2-digit', month: '2-digit', year: 'numeric' }) + ' ' + time.toLocaleTimeString(loc, { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    // Orario senza secondi per la barra in alto (es. 24/10/2026 01:58 o 10/24/2026 01:58)
    const formattedTimeShort = time.toLocaleDateString(loc, { day: '2-digit', month: '2-digit', year: 'numeric' }) + ' ' + time.toLocaleTimeString(loc, { hour: '2-digit', minute: '2-digit' });
    setTxt('val-lastupdate', formattedTimeWithSec);
    setTxt('banner-lastupdate', formattedTimeShort);

    // Aggiornamento Barre e Indicatori/Freccina con punto preciso
    const updateBarAndIndicator = (key, val, rawPct, displayTxt) => {
      const state = evaluateFoobotState(key, val);
      const pct = Math.max(0, Math.min(100, rawPct));

      // Mappatura esatta del colore solido esadecimale corrispondente alla classe
      const colorMap = {
        'bg-emerald-500': '#10b981',
        'bg-blue-500': '#3b82f6',
        'bg-amber-400': '#fbbf24',
        'bg-amber-500': '#f59e0b',
        'bg-cyan-500': '#06b6d4',
        'bg-rose-500': '#f43f5e',
        'bg-slate-400': '#94a3b8'
      };
      const solidColor = colorMap[state.class] || '#10b981';

      // Barra
      const bar = document.getElementById('bar-' + key);
      if (bar) {
        bar.style.width = pct + '%';
        bar.className = 'h-2 rounded-full transition-all duration-500 ' + state.class;
        bar.style.backgroundColor = solidColor;
      }

      // Freccina indicatore con numero esatto e triangolino con punta verso il basso
      const ind = document.getElementById('indicator-' + key);
      const indBadge = document.getElementById('indicator-badge-' + key);
      if (ind && indBadge) {
        ind.style.left = pct + '%';
        indBadge.innerText = displayTxt;
        indBadge.className = 'bar-indicator-badge ' + state.class;
        // Assegna direttamente il colore esatto senza attendere ricalcoli CSS asincroni
        indBadge.style.backgroundColor = solidColor;
        indBadge.style.borderTopColor = solidColor;
      }

      // Badge livello in alto a destra (nessun bordo né ombra)
      const badge = document.getElementById('badge-' + key);
      if (badge) {
        badge.innerText = state.label;
        badge.className = 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold ' + state.badgeClass;
        badge.style.border = 'none';
        badge.style.boxShadow = 'none';
      }
    };

    updateBarAndIndicator('allpollu', allpollu, allpollu, allpollu + '%');
    updateBarAndIndicator('voc', voc, (voc / 600) * 100, voc + ' ppb');
    updateBarAndIndicator('pm', pm, (pm / 50) * 100, pm.toFixed(1) + ' µg/m³');
    updateBarAndIndicator('co2', co2, Math.max(0, ((co2 - 450) / 1550) * 100), co2 + ' ppm');
    updateBarAndIndicator('temp', tmp, ((tmp - 10) / 25) * 100, displayTmp.toFixed(1) + ' ' + tempUnitSym);
    updateBarAndIndicator('hum', hum, hum, hum + '%');

    // Colore LED sensore (secondo le specifiche autentiche Foobot)
    const led = document.getElementById('led-color');
    if (led) {
      if (allpollu <= 25) led.style.backgroundColor = '#1f6feb'; // Blu scuro
      else if (allpollu <= 50) led.style.backgroundColor = '#58a6ff'; // Azzurro chiaro
      else if (allpollu <= 75) led.style.backgroundColor = '#d29922'; // Arancio
      else led.style.backgroundColor = '#f85149'; // Rosso
    }

  } catch (err) {
    console.error('Errore caricamento dati live:', err);
  }
}

// Tooltip al click sulla barra per mostrare il valore preciso
function showBarTooltip(e, key) {
  const ind = document.getElementById('indicator-' + key);
  if (!ind) return;
  ind.style.transform = 'translateX(-50%) scale(1.15)';
  setTimeout(() => {
    ind.style.transform = 'translateX(-50%) scale(1)';
  }, 400);
}

// Modal informativo con spiegazione e soglie ufficiali Foobot
function openInfoModal(key) {
  const configs = getThresholdConfigs();
  const conf = configs[key];
  if (!conf) return;

  const modal = document.getElementById('info-modal');
  const title = document.getElementById('modal-title');
  const subtitle = document.getElementById('modal-subtitle');
  const desc = document.getElementById('modal-description');
  const thresholds = document.getElementById('modal-thresholds');
  const icon = document.getElementById('modal-icon');

  if (title) title.innerText = conf.title;
  if (subtitle) subtitle.innerText = t('modal_total_scale', 'Scala totale barra:') + ' ' + conf.min + ' — ' + conf.max + ' ' + conf.unit;
  if (desc) desc.innerText = conf.desc;

  if (icon) {
    icon.className = 'p-2 rounded-xl bg-blue-50 text-blue-600 flex-shrink-0';
    icon.innerHTML = '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:block;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>';
  }

  if (thresholds) {
    thresholds.innerHTML = '';
    conf.levels.forEach(lvl => {
      const row = document.createElement('div');
      row.className = 'flex items-center justify-between p-2.5 rounded-xl bg-slate-50 border border-slate-100 text-xs';
      row.innerHTML = `
        <div class="flex items-center gap-2.5">
          <span class="w-3 h-3 rounded-full ${lvl.class}"></span>
          <span class="font-semibold text-slate-800">${lvl.label}</span>
        </div>
        <span class="font-mono font-medium text-slate-600 bg-white px-2 py-0.5 rounded border border-slate-200">${lvl.range}</span>
      `;
      thresholds.appendChild(row);
    });
  }

  if (modal) {
    modal.classList.remove('hidden');
    modal.onclick = (e) => { if (e.target === modal) closeInfoModal(); };
  }
}

function closeInfoModal() {
  const modal = document.getElementById('info-modal');
  if (modal) modal.classList.add('hidden');
}

// Caricamento storico e generazione grafico Chart.js
async function loadHistory(range = '24h', offset = currentOffset) {
  currentRange = range;
  currentOffset = Math.max(0, parseInt(offset, 10) || 0);
  try {
    let url = 'api/get_datapoints.php?range=' + encodeURIComponent(range) + '&offset=' + currentOffset + getDeviceUuidQuery('&');
    if (customStartDate) {
      url += '&start_date=' + encodeURIComponent(customStartDate);
      if (customEndDate) {
        url += '&end_date=' + encodeURIComponent(customEndDate);
      }
    }
    const res = await fetch(url);
    const json = await res.json();
    if (!json.datapoints) return;

    // Formattatore di date e orari localizzato per date asse X e tooltip
    const langCode = (document.documentElement.lang || 'it').toLowerCase();
    const formatItDate = (d) => {
      try {
        return d.toLocaleDateString(langCode, { day: 'numeric', month: 'short', year: 'numeric' });
      } catch (e) {
        return `${d.getDate()}/${d.getMonth() + 1}/${d.getFullYear()}`;
      }
    };
    const formatDayMonth = (d) => {
      try {
        return d.toLocaleDateString(langCode, { day: 'numeric', month: 'short' });
      } catch (e) {
        const m = (itMonths && itMonths[d.getMonth()]) ? itMonths[d.getMonth()] : (d.getMonth() + 1);
        return `${d.getDate()} ${m}`;
      }
    };
    const formatItTime = (d) => `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;

    // Aggiorna barra di navigazione temporale (Frecce Indietro / Avanti / Oggi)
    const periodLabel = document.getElementById('chart-period-label');
    const btnNext = document.getElementById('btn-chart-next');
    const btnLive = document.getElementById('btn-chart-live');
    if (btnNext) btnNext.disabled = (currentOffset === 0 && !customStartDate);
    if (btnLive) btnLive.style.display = (currentOffset > 0 || customStartDate) ? 'inline-flex' : 'none';
    if (periodLabel) {
      if (customStartDate) {
        periodLabel.innerText = customEndDate ? `${t('date_range_label', 'Intervallo')}: ${customStartDate} → ${customEndDate}` : `${t('date_label', 'Data')}: ${customStartDate}`;
      } else if (currentOffset === 0) {
        periodLabel.innerText = t('nav_realtime', 'Tempo Reale');
      } else if (json.start && json.end) {
        const dStart = new Date(json.start * 1000);
        const dEnd = new Date(json.end * 1000);
        periodLabel.innerText = `${formatItDate(dStart)} ${formatItTime(dStart)} — ${formatItDate(dEnd)} ${formatItTime(dEnd)}`;
      }
    }

    const labels = [];
    const tooltipLabels = [];
    const dPollu = [];
    const dVOC = [];
    const dPM = [];
    const dCO2 = [];
    const dTemp = [];
    const dHum = [];
    const gapBoundaryIndices = new Set();

    // Rileva se il range corrente visualizza aggregati giornalieri
    const isDailyRange = ['giorni', 'settimane', '3d', '7d', '10d', '30d'].includes(range) || /^\d+d$/.test(range);

    // Gestione aggregazione per filtro 'ore': slot orari esatti di 1 ora (24 per 1 giorno, o tutti gli slot se scelto range multi-giorno)
    if (range === 'ore') {
      const dayStart = json.start ? Math.floor(json.start) : (Math.floor(Date.now() / 1000) - 86400);
      const dayEnd = json.end ? Math.floor(json.end) : (dayStart + 86400);
      const totalHours = Math.max(24, Math.min(720, Math.round((dayEnd - dayStart) / 3600)));

      // Ordina i datapoint raw per timestamp crescente
      const rawPoints = (json.datapoints || []).slice().sort((a, b) => a[0] - b[0]);

      // Genera slot orari con step di 1 ora
      for (let h = 0; h < totalHours; h++) {
        const slotTime = dayStart + (h * 3600);
        const slotEndTime = slotTime + 3600;

        // Cerca tutte le rilevazioni cadute all'interno dell'ora [slotTime, slotEndTime)
        const hourPoints = rawPoints.filter(p => p[0] >= slotTime && p[0] < slotEndTime);

        let chosenPoint = null;
        let ptDate = null;
        if (hourPoints.length > 0) {
          // Media delle rilevazioni presenti nell'ora
          const avgPollu = Math.round(hourPoints.reduce((acc, p) => acc + (p[1] || 0), 0) / hourPoints.length);
          const avgPM = +(hourPoints.reduce((acc, p) => acc + (p[2] || 0), 0) / hourPoints.length).toFixed(1);
          const avgVOC = Math.round(hourPoints.reduce((acc, p) => acc + (p[3] || 0), 0) / hourPoints.length);
          const avgCO2 = Math.round(hourPoints.reduce((acc, p) => acc + (p[4] || 0), 0) / hourPoints.length);
          const avgTemp = +(hourPoints.reduce((acc, p) => acc + (p[5] || 0), 0) / hourPoints.length).toFixed(1);
          const avgHum = Math.round(hourPoints.reduce((acc, p) => acc + (p[6] || 0), 0) / hourPoints.length);
          ptDate = new Date(hourPoints[Math.floor(hourPoints.length / 2)][0] * 1000);
          chosenPoint = [0, avgPollu, avgPM, avgVOC, avgCO2, avgTemp, avgHum];
        } else {
          // Tolleranza: se nessuna rilevazione nell'ora, cerca la rilevazione più vicina entro +/- 30 minuti dallo scoccare dell'ora
          let minDiff = Infinity;
          for (let i = 0; i < rawPoints.length; i++) {
            const diff = Math.abs(rawPoints[i][0] - slotTime);
            if (diff <= 1800 && diff < minDiff) {
              minDiff = diff;
              chosenPoint = rawPoints[i];
              ptDate = new Date(rawPoints[i][0] * 1000);
            }
          }
        }

        const slotDate = new Date(slotTime * 1000);
        const hourLabel = `${String(slotDate.getHours()).padStart(2, '0')}:00`;
        const dateText = formatItDate(slotDate);

        // Se più di 24 ore, mostra orario e data su due righe; se 24 ore mostra solo l'ora 00:00 .. 23:00
        labels.push(totalHours > 24 ? [hourLabel, dateText] : hourLabel);
        // Nel tooltip popup: data italiana e orario preciso della rilevazione
        if (chosenPoint) {
          tooltipLabels.push(`${hourLabel} (${formatItTime(ptDate || slotDate)}) — ${dateText}`);
          dPollu.push(chosenPoint[1]);
          dPM.push(chosenPoint[2]);
          dVOC.push(chosenPoint[3]);
          dCO2.push(chosenPoint[4]);
          const isF = (window.FOOBOT_TEMP_UNIT === 'F');
          const rawT = chosenPoint[5];
          dTemp.push(rawT !== null && rawT !== undefined ? (isF ? Number(((Number(rawT) * 9 / 5) + 32).toFixed(1)) : Number(Number(rawT).toFixed(1))) : null);
          dHum.push(chosenPoint[6]);
        } else {
          tooltipLabels.push(`${hourLabel} — ${dateText}||${t('chart_gap_interruption', 'Interruzione segnale / Blackout')}`);
          dPollu.push(null);
          dPM.push(null);
          dVOC.push(null);
          dCO2.push(null);
          dTemp.push(null);
          dHum.push(null);
        }
      }

      // Individua i punti a confine con gli intervalli vuoti per applicare la righetta verticale
      for (let h = 0; h < totalHours; h++) {
        if (dPollu[h] !== null) {
          const prevNull = (h > 0 && dPollu[h - 1] === null);
          const nextNull = (h < totalHours - 1 && dPollu[h + 1] === null);
          if (prevNull || nextNull) {
            gapBoundaryIndices.add(h);
          }
        }
      }
    } else if (isDailyRange) {
      // Gestione raggruppamento per Giorni / Settimane: 1 punto per ciascun giorno con media giornaliera
      json.datapoints.forEach(dp => {
        const dt = new Date(dp[0] * 1000);

        // Barra asse X sotto: giorno e mese (riga 1) e anno (riga 2)
        labels.push([formatDayMonth(dt), `${dt.getFullYear()}`]);

        // Tooltip popup: etichetta formattata per lingua (es. "25 ott 2026 (Media Giornaliera)")
        // Separatore '||': a sinistra la data, a destra la dicitura "Media giornaliera"
        tooltipLabels.push(`${formatItDate(dt)}||${t('chart_daily_avg', 'Media giornaliera')}`);

        dPollu.push(dp[1]);
        dPM.push(dp[2]);
        dVOC.push(dp[3]);
        dCO2.push(dp[4]);
        const isF = (window.FOOBOT_TEMP_UNIT === 'F');
        const rawT = dp[5];
        dTemp.push(rawT !== null && rawT !== undefined ? (isF ? Number(((Number(rawT) * 9 / 5) + 32).toFixed(1)) : Number(Number(rawT).toFixed(1))) : null);
        dHum.push(dp[6]);
      });
    } else {
      // Per minuti, 24h e gli altri range ad alta risoluzione:
      const rawPoints = (json.datapoints || []).slice().sort((a, b) => a[0] - b[0]);
      rawPoints.forEach((dp, i) => {
        // Se tra due letture consecutive trascorrono più di 12 minuti (> 720 sec, intervallo standard Foobot = 5 min)
        if (i > 0) {
          const prevDp = rawPoints[i - 1];
          const deltaSec = dp[0] - prevDp[0];
          if (deltaSec > 720) {
            // Contrassegna l'ultimo punto prima dello stacco con righetta verticale
            gapBoundaryIndices.add(labels.length - 1);

            const gapStartDt = new Date((prevDp[0] + 300) * 1000);
            const gapEndDt = new Date((dp[0] - 300) * 1000);
            const gapMin = Math.round(deltaSec / 60);
            const gapDuration = gapMin >= 60 ? `${Math.floor(gapMin / 60)}h ${gapMin % 60}m` : `${gapMin} min`;
            const gapLabel = `${t('chart_gap_interruption', 'Interruzione segnale / Blackout')} (${gapDuration})`;

            // Slot null inizio interruzione/blackout (linea spezzata)
            labels.push([formatItTime(gapStartDt), '✕']);
            tooltipLabels.push(`${gapLabel}||${formatItDate(gapStartDt)} ${formatItTime(new Date(prevDp[0] * 1000))} → ${formatItTime(new Date(dp[0] * 1000))}`);
            dPollu.push(null);
            dPM.push(null);
            dVOC.push(null);
            dCO2.push(null);
            dTemp.push(null);
            dHum.push(null);

            // Se l'interruzione supera i 30 minuti, inserisce un secondo slot null prima della ripresa
            if (deltaSec >= 1800) {
              labels.push([formatItTime(gapEndDt), '✕']);
              tooltipLabels.push(`${gapLabel}||${formatItDate(gapEndDt)} ${formatItTime(new Date(prevDp[0] * 1000))} → ${formatItTime(new Date(dp[0] * 1000))}`);
              dPollu.push(null);
              dPM.push(null);
              dVOC.push(null);
              dCO2.push(null);
              dTemp.push(null);
              dHum.push(null);
            }

            // Contrassegna il primo punto al ripristino del segnale con righetta verticale
            gapBoundaryIndices.add(labels.length);
          }
        }

        const dt = new Date(dp[0] * 1000);
        const timeStr = formatItTime(dt);
        const dateStr = formatItDate(dt);

        // Asse X a due righe: riga 1 = orario, riga 2 = data formattata per lingua
        labels.push([timeStr, dateStr]);

        // Tooltip popup: prima orario poi data localizzata
        tooltipLabels.push(`${timeStr} • ${dateStr}`);

        dPollu.push(dp[1]);
        dPM.push(dp[2]);
        dVOC.push(dp[3]);
        dCO2.push(dp[4]);
        const isF = (window.FOOBOT_TEMP_UNIT === 'F');
        const rawT = dp[5];
        dTemp.push(rawT !== null && rawT !== undefined ? (isF ? Number(((Number(rawT) * 9 / 5) + 32).toFixed(1)) : Number(Number(rawT).toFixed(1))) : null);
        dHum.push(dp[6]);
      });
    }

    const ctx = document.getElementById('trendChart');
    if (!ctx) return;
    const ctx2d = ctx.getContext('2d');

    const createGradient = (colorStart) => {
      const g = ctx2d.createLinearGradient(0, 0, 0, 350);
      g.addColorStop(0, colorStart);
      g.addColorStop(1, 'rgba(255, 255, 255, 0)');
      return g;
    };

    const makeDatasetConfig = (id, label, data, color, rgbaStart, width, yAxisID) => ({
      id: id,
      label: label,
      data: data,
      borderColor: color,
      backgroundColor: createGradient(rgbaStart),
      borderWidth: width,
      hidden: userHiddenDatasets.has(id),
      pointRadius: (ctx) => {
        const idx = ctx.dataIndex;
        if (gapBoundaryIndices.has(idx)) return 5.5; // Righetta visibile a stacco
        return (range === 'minuti') ? 3 : (range === 'ore' ? 3 : (isDailyRange ? 3 : (data.length > 60 ? 0 : 2.5)));
      },
      pointStyle: (ctx) => {
        const idx = ctx.dataIndex;
        if (gapBoundaryIndices.has(idx)) return 'line'; // Righetta verticale per stacco
        return 'circle';
      },
      pointRotation: (ctx) => {
        const idx = ctx.dataIndex;
        if (gapBoundaryIndices.has(idx)) return 90; // Ruotata a 90° = orientamento verticale
        return 0;
      },
      pointBorderWidth: (ctx) => {
        const idx = ctx.dataIndex;
        if (gapBoundaryIndices.has(idx)) return 2.8; // Spessore righetta verticale
        return 1.5;
      },
      pointBorderColor: (ctx) => {
        const idx = ctx.dataIndex;
        if (gapBoundaryIndices.has(idx)) return '#f43f5e'; // Accento corallo per stacco
        return color;
      },
      pointBackgroundColor: (ctx) => {
        const idx = ctx.dataIndex;
        if (gapBoundaryIndices.has(idx)) return '#f43f5e';
        return (ctx.datasetIndex === hoveredDatasetIndex || activeSensorTab !== 'tutti') ? color : '#ffffff';
      },
      pointHoverRadius: (ctx) => {
        const idx = ctx.dataIndex;
        if (gapBoundaryIndices.has(idx)) return 7.5;
        return (ctx.datasetIndex === hoveredDatasetIndex || activeSensorTab !== 'tutti') ? 4.5 : 3;
      },
      pointHoverBackgroundColor: (ctx) => {
        const idx = ctx.dataIndex;
        if (gapBoundaryIndices.has(idx)) return '#f43f5e';
        return (ctx.datasetIndex === hoveredDatasetIndex || activeSensorTab !== 'tutti') ? color : '#ffffff';
      },
      pointHoverBorderColor: (ctx) => {
        const idx = ctx.dataIndex;
        if (gapBoundaryIndices.has(idx)) return '#ffffff';
        return (ctx.datasetIndex === hoveredDatasetIndex || activeSensorTab !== 'tutti') ? '#ffffff' : color;
      },
      pointHoverBorderWidth: (ctx) => {
        const idx = ctx.dataIndex;
        if (gapBoundaryIndices.has(idx)) return 2.5;
        return (ctx.datasetIndex === hoveredDatasetIndex || activeSensorTab !== 'tutti') ? 2 : 1.5;
      },
      fill: true,
      tension: 0.35,
      spanGaps: false,
      yAxisID: yAxisID
    });

    const datasets = [
      makeDatasetConfig('inquinamento', t('tab_gpi', 'GPI — Indice Inquinamento') + ' (%)', dPollu, '#10b981', 'rgba(16, 185, 129, 0.22)', 2, 'y'),
      makeDatasetConfig('voc', t('tab_voc', 'VOC — Composti Volatili') + ' (ppb)', dVOC, '#9333ea', 'rgba(147, 51, 234, 0.22)', 2.2, 'y1'),
      makeDatasetConfig('pm25', t('tab_pm25', 'PM2.5 — Particolato Fine') + ' (µg/m³)', dPM, '#2563eb', 'rgba(37, 99, 235, 0.22)', 2.2, 'y'),
      makeDatasetConfig('co2', t('tab_eco2', 'eCO2 — CO2 Equivalente') + ' (ppm)', dCO2, '#0284c7', 'rgba(2, 132, 199, 0.22)', 2.2, 'y1'),
      makeDatasetConfig('temp', t('tab_temp', 'TMP — Temperatura') + ' (' + ((window.FOOBOT_TEMP_UNIT === 'F') ? '°F' : '°C') + ')', dTemp, '#991b1b', 'rgba(153, 27, 27, 0.22)', 2.2, 'y'),
      makeDatasetConfig('umidita', t('tab_hum', 'HUM — Umidità') + ' (%)', dHum, '#f97316', 'rgba(249, 115, 22, 0.22)', 2, 'y')
    ];

    // Regolazione dinamica della larghezza interna del canvas in base alla quantità di punti (dilatazione punti stile Foobot)
    const wrapper = document.getElementById('chart-canvas-wrapper');
    const minPixelsPerPoint = updateChartScrollDimensions(range, labels.length);

    if (myChart && myChart.canvas) {
      // Aggiornamento fluido in tempo reale senza distruggere o far sfarfallare il canvas
      myChart.data.labels = labels;
      myChart._foobotTooltipLabels = tooltipLabels;
      myChart.data.datasets.forEach((ds, idx) => {
        if (datasets[idx]) {
          ds.data = datasets[idx].data;
          ds.pointRadius = datasets[idx].pointRadius;
          ds.pointHoverRadius = datasets[idx].pointHoverRadius;
          ds.pointBorderWidth = datasets[idx].pointBorderWidth;
          ds.pointBorderColor = datasets[idx].pointBorderColor;
          ds.pointBackgroundColor = datasets[idx].pointBackgroundColor;
          ds.pointHoverBackgroundColor = datasets[idx].pointHoverBackgroundColor;
          ds.pointHoverBorderColor = datasets[idx].pointHoverBorderColor;
          ds.pointHoverBorderWidth = datasets[idx].pointHoverBorderWidth;
          ds.pointStyle = datasets[idx].pointStyle;
          ds.pointRotation = datasets[idx].pointRotation;
          ds.spanGaps = datasets[idx].spanGaps;
        }
      });
      if (myChart.options && myChart.options.scales && myChart.options.scales.x && myChart.options.scales.x.ticks) {
        myChart.options.scales.x.ticks.autoSkip = (range === 'ore') ? false : true;
        myChart.options.scales.x.ticks.autoSkipPadding = (range === 'minuti') ? 10 : 14;
      }
      applyTabFilter(activeSensorTab);
      renderCustomLegend();
      myChart.resize();
      syncStickyAxesAndZones();
      if (wrapper) {
        setTimeout(() => {
          updateChartScrollDimensions(currentRange, myChart.data.labels.length);
          myChart.resize();
          if (minPixelsPerPoint > 0) {
            let lastValidIdx = -1;
            const ds = myChart.data.datasets[0];
            if (ds && ds.data) {
              for (let i = ds.data.length - 1; i >= 0; i--) {
                if (ds.data[i] !== null && ds.data[i] !== undefined) {
                  lastValidIdx = i;
                  break;
                }
              }
            }
            if (lastValidIdx >= 0) {
              const pointPx = lastValidIdx * minPixelsPerPoint;
              const halfViewport = (wrapper.clientWidth || 800) / 2;
              wrapper.scrollLeft = Math.max(0, pointPx - halfViewport);
            } else {
              wrapper.scrollLeft = wrapper.scrollWidth;
            }
          } else {
            wrapper.scrollLeft = 0;
          }
          syncStickyAxesAndZones();
        }, 60);
      }
      return;
    }

    if (window['chartjs-plugin-annotation']) {
      try { Chart.register(window['chartjs-plugin-annotation']); } catch (e) {}
    }

    myChart = new Chart(ctx2d, {
      type: 'line',
      data: {
        labels: labels,
        datasets: datasets
      },
      plugins: [{
        id: 'foobotTooltipMeta',
        // Imposta le etichette solo alla creazione: gli aggiornamenti successivi
        // (cambio filtro) assegnano myChart._foobotTooltipLabels direttamente,
        // evitando che la closure ripristini le etichette del primo caricamento.
        beforeInit: (chart) => {
          if (!chart._foobotTooltipLabels) chart._foobotTooltipLabels = tooltipLabels;
        }
      }],
      options: {
        responsive: true,
        maintainAspectRatio: false,
        events: ['mousemove', 'mouseout', 'click', 'touchstart', 'touchmove'],
        interaction: { mode: 'index', intersect: false },
        onHover: (event, activeElements, chart) => {
          if (event) {
            if (typeof event.x === 'number') lastPointerX = event.x;
            if (typeof event.y === 'number') lastPointerY = event.y;
            if (event.type === 'mouseout') {
              lastPointerX = null;
              lastPointerY = null;
              hoveredDatasetIndex = -1;
              chart.draw();
              return;
            }
          }
          const nearest = chart.getElementsAtEventForMode(event, 'nearest', { intersect: false }, false);
          let nextIdx = -1;
          if (nearest && nearest.length > 0 && event && typeof event.y === 'number') {
            const el = nearest[0].element;
            if (el && typeof el.y === 'number' && Math.abs(el.y - event.y) <= 32) {
              nextIdx = nearest[0].datasetIndex;
            }
          }
          if (nextIdx !== hoveredDatasetIndex) {
            hoveredDatasetIndex = nextIdx;
            chart.draw();
          }
        },
        plugins: {
          legend: {
            display: false // Disattivata legenda grezza nativa dentro il canvas
          },
          tooltip: {
            enabled: false,
            external: function(context) {
              const { chart, tooltip } = context;
              const isMobile = window.innerWidth < 768;
              const mobileInspector = document.getElementById('mobile-chart-inspector');
              let tooltipEl = chart.canvas.parentNode.querySelector('.foobot-chart-tooltip');
              if (!tooltipEl && !isMobile) {
                tooltipEl = document.createElement('div');
                tooltipEl.className = 'foobot-chart-tooltip';
                tooltipEl.style.cssText = 'position:absolute;background:rgba(15,23,42,0.94);color:#ffffff;border-radius:10px;padding:11px 14px;pointer-events:none;transition:opacity 0.12s ease, transform 0.12s ease;font-family:\'Inter\',sans-serif;box-shadow:0 10px 25px -5px rgba(0,0,0,0.4);z-index:50;min-width:175px;max-width:calc(100% - 16px);border:1px solid rgba(255,255,255,0.1);';
                chart.canvas.parentNode.appendChild(tooltipEl);
              }

              // Su mobile il tooltip galleggiante interno al canvas NON deve MAI comparire per evitare tagli
              if (isMobile && tooltipEl) {
                tooltipEl.style.opacity = '0';
                tooltipEl.style.display = 'none';
              }

              if (tooltip.opacity === 0) {
                if (tooltipEl) {
                  tooltipEl.style.opacity = '0';
                  tooltipEl.style.display = 'none';
                }
                return;
              }

              const dataPoints = tooltip.dataPoints || [];
              const validPoints = dataPoints.filter(dp => dp.raw !== null && dp.raw !== undefined && !isNaN(dp.raw));

              // VERIFICA VICINANZA ALLA LINEA O PALLINO:
              // Il popup/inspector deve comparire SOLO se il cursore/tocco è effettivamente vicino a una delle linee o punti
              let isNearLine = false;
              let activeDsIndex = hoveredDatasetIndex;

              if (validPoints.length > 0 && lastPointerY !== null) {
                let minDist = Infinity;
                validPoints.forEach(dp => {
                  if (dp.element && typeof dp.element.y === 'number') {
                    const d = Math.abs(dp.element.y - lastPointerY);
                    if (d < minDist) {
                      minDist = d;
                      activeDsIndex = dp.datasetIndex;
                    }
                  }
                });

                // Tolleranza verticale (30px su desktop, 38px per touch mobile)
                const proximityThreshold = isMobile ? 38 : 30;
                if (minDist <= proximityThreshold) {
                  isNearLine = true;
                }
              }

              // Se il cursore o tocco NON è vicino a nessuna linea o pallino, nascondi il popup e termina
              if (!isNearLine) {
                if (tooltipEl) {
                  tooltipEl.style.opacity = '0';
                  tooltipEl.style.display = 'none';
                }
                return;
              }

              const titleLines = tooltip.title || [];
              const dataIndex = (dataPoints.length > 0 && typeof dataPoints[0].dataIndex === 'number') ? dataPoints[0].dataIndex : -1;
              let timeTitle = '';
              if (chart._foobotTooltipLabels && dataIndex >= 0 && chart._foobotTooltipLabels[dataIndex]) {
                timeTitle = chart._foobotTooltipLabels[dataIndex];
              } else if (titleLines.length > 0) {
                timeTitle = Array.isArray(titleLines[0]) ? titleLines[0].join(' — ') : titleLines[0];
              }

              // Titolo a due colonne (es. data a sinistra, "Media giornaliera" a destra)
              let timeTitleDisplay = timeTitle;
              if (typeof timeTitle === 'string' && timeTitle.indexOf('||') !== -1) {
                const parts = timeTitle.split('||');
                timeTitleDisplay = `<span style="display:flex;justify-content:space-between;gap:14px;width:100%;"><span>${parts[0]}</span><span style="font-weight:500;color:#94a3b8;font-style:italic;">${parts[1]}</span></span>`;
              }

              // GESTIONE SPECIFICA PER MOBILE: Mostra i dettagli fuori dal grafico nel box sottostante
              if (isMobile) {
                if (tooltipEl) {
                  tooltipEl.style.opacity = '0';
                  tooltipEl.style.display = 'none';
                }
                if (mobileInspector) {
                  let cleanTimeTitle = typeof timeTitle === 'string' ? timeTitle.replace('||', ' — ') : timeTitle;
                  let mobHtml = `
                    <div class="mobile-chart-inspector-header">
                      <div class="mobile-chart-inspector-title">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--accent-blue,#2563eb);flex-shrink:0;">
                          <circle cx="12" cy="12" r="10"></circle>
                          <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                        <span>${cleanTimeTitle}</span>
                      </div>
                      <button type="button" class="mobile-chart-inspector-close" onclick="dismissMobileChartInspector()" title="Chiudi" aria-label="Chiudi">&times;</button>
                    </div>
                  `;

                  if (validPoints.length === 0) {
                    mobHtml += `
                      <div style="font-size:0.82rem;font-weight:700;color:#ef4444;display:flex;align-items:center;gap:6px;padding:6px 0;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                        <span>${t('chart_gap_interruption', 'Interruzione segnale / Blackout')}</span>
                      </div>
                    `;
                  } else {
                    mobHtml += '<div class="mobile-chart-inspector-grid">';
                    validPoints.forEach(dp => {
                      const rawVal = dp.formattedValue || dp.raw;
                      const dsLabel = dp.dataset.label || '';
                      const color = dp.dataset.borderColor || '#38bdf8';
                      const isHighlighted = (dp.datasetIndex === activeDsIndex);
                      const highlightClass = isHighlighted ? ' highlighted' : '';

                      mobHtml += `
                        <div class="mobile-chart-inspector-item${highlightClass}">
                          <div style="display:flex;align-items:center;gap:7px;min-width:0;">
                            <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:${color};border:1.5px solid #ffffff;box-shadow:0 0 4px ${color};flex-shrink:0;"></span>
                            <span style="font-size:0.75rem;font-weight:600;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${dsLabel}</span>
                          </div>
                          <strong style="font-size:0.95rem;font-weight:800;color:var(--text-color);white-space:nowrap;">${rawVal}</strong>
                        </div>
                      `;
                    });
                    mobHtml += '</div>';
                  }

                  mobileInspector.innerHTML = mobHtml;
                  mobileInspector.style.display = 'block';
                }
                return;
              }

              // GESTIONE DESKTOP: Mostra il tooltip nero all'interno del grafico
              if (mobileInspector) {
                mobileInspector.style.display = 'none';
              }
              if (!tooltipEl) return;

              let innerHtml = '';
              if (validPoints.length === 0) {
                innerHtml = `
                  <div style="font-size:12.5px;font-weight:700;color:#f87171;display:flex;align-items:center;gap:6px;margin-bottom:5px;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    <span>${t('chart_gap_interruption', 'Interruzione segnale / Blackout')}</span>
                  </div>
                  <div style="font-size:11.5px;color:#cbd5e1;line-height:1.4;">${timeTitleDisplay}</div>
                `;
              } else if (validPoints.length === 1) {
                const dp = validPoints[0];
                const rawVal = dp.formattedValue || dp.raw;
                const dsLabel = dp.dataset.label || '';
                const color = dp.dataset.borderColor || '#38bdf8';

                innerHtml += `
                  <div style="display:flex;align-items:baseline;justify-content:space-between;gap:18px;margin-bottom:7px;">
                    <span style="font-size:12.5px;font-weight:600;color:#cbd5e1;">${timeTitleDisplay}</span>
                    <span style="font-size:21px;font-weight:800;color:#ffffff;line-height:1;letter-spacing:-0.02em;">${rawVal}</span>
                  </div>
                  <div style="display:flex;align-items:center;gap:7px;padding-top:5px;border-top:1px solid rgba(255,255,255,0.12);">
                    <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:${color};border:1.5px solid #ffffff;flex-shrink:0;"></span>
                    <span style="font-size:11.5px;font-weight:600;color:#ffffff;white-space:nowrap;">${dsLabel}</span>
                  </div>
                `;
              } else {
                innerHtml += `<div style="font-size:12.5px;font-weight:600;color:#cbd5e1;margin-bottom:6px;padding-bottom:4px;border-bottom:1px solid rgba(255,255,255,0.12);">${timeTitleDisplay}</div>`;
                validPoints.forEach(dp => {
                  const rawVal = dp.formattedValue || dp.raw;
                  const dsLabel = dp.dataset.label || '';
                  const color = dp.dataset.borderColor || '#38bdf8';
                  const isHighlighted = (dp.datasetIndex === activeDsIndex);

                  const rowStyle = isHighlighted
                    ? `display:flex;align-items:center;justify-content:space-between;gap:16px;padding:5px 8px;margin:3px -4px;border:1.5px solid ${color};border-radius:7px;background:rgba(255,255,255,0.12);`
                    : `display:flex;align-items:center;justify-content:space-between;gap:16px;padding:3px 4px;opacity:0.85;`;

                  const dotStyle = isHighlighted
                    ? `display:inline-block;width:11px;height:11px;border-radius:50%;background:${color};border:2px solid #ffffff;box-shadow:0 0 6px ${color};flex-shrink:0;`
                    : `display:inline-block;width:9px;height:9px;border-radius:50%;background:#ffffff;border:2px solid ${color};flex-shrink:0;`;

                  const labelStyle = isHighlighted
                    ? `font-size:12px;font-weight:800;color:#ffffff;white-space:nowrap;`
                    : `font-size:11.5px;font-weight:400;color:#cbd5e1;white-space:nowrap;`;

                  const valStyle = isHighlighted
                    ? `font-size:17px;font-weight:900;color:#ffffff;line-height:1.1;`
                    : `font-size:14.5px;font-weight:600;color:#e2e8f0;line-height:1.1;`;

                  innerHtml += `
                    <div style="${rowStyle}">
                      <div style="display:flex;align-items:center;gap:7px;">
                        <span style="${dotStyle}"></span>
                        <span style="${labelStyle}">${dsLabel}</span>
                      </div>
                      <span style="${valStyle}">${rawVal}</span>
                    </div>
                  `;
                });
              }

              tooltipEl.innerHTML = innerHtml;
              tooltipEl.style.display = 'block';
              tooltipEl.style.opacity = '1';

              const canvasRect = chart.canvas.getBoundingClientRect();
              const tipWidth = tooltipEl.offsetWidth || 200;
              const tipHeight = tooltipEl.offsetHeight || 70;

              let left = tooltip.caretX + 14;
              if (left + tipWidth > canvasRect.width - 8) {
                left = tooltip.caretX - tipWidth - 14;
              }
              if (left < 8) left = 8;

              let top = tooltip.caretY - (tipHeight / 2);
              if (top < 8) top = 8;
              if (top + tipHeight > canvasRect.height - 8) {
                top = canvasRect.height - tipHeight - 8;
              }

              tooltipEl.style.left = left + 'px';
              tooltipEl.style.top = top + 'px';
            }
          },
          annotation: { annotations: {} }
        },
        layout: {
          padding: { left: 40, right: 44 }
        },
        scales: {
          x: {
            grid: { display: false, drawBorder: false },
            ticks: {
              color: '#94a3b8',
              font: (context) => {
                // Per le etichette su due righe (orario e data), la seconda riga è resa leggermente più piccola
                return { family: "'Inter', sans-serif", size: 10.5 };
              },
              maxRotation: 0,
              autoSkip: (range === 'ore') ? false : true,
              autoSkipPadding: (range === 'minuti') ? 10 : 14
            }
          },
          y: { 
            type: 'linear', 
            display: true, 
            position: 'left', 
            beginAtZero: true,
            min: 0,
            grid: { color: () => document.documentElement.getAttribute('data-theme') === 'dark' ? '#2d333b' : '#f1f5f9', drawBorder: false }, 
            ticks: { display: false } 
          },
          y1: { 
            type: 'linear', 
            display: true, 
            position: 'right', 
            beginAtZero: true,
            min: 0,
            grid: { drawOnChartArea: false, drawBorder: false }, 
            ticks: { display: false } 
          }
        }
      }
    });

    applyTabFilter(activeSensorTab);
    renderCustomLegend();
    setTimeout(() => { 
      if (myChart) myChart.resize(); 
      if (wrapper) {
        if (minPixelsPerPoint > 0) {
          let lastValidIdx = -1;
          const ds = myChart?.data?.datasets?.[0];
          if (ds && ds.data) {
            for (let i = ds.data.length - 1; i >= 0; i--) {
              if (ds.data[i] !== null && ds.data[i] !== undefined) {
                lastValidIdx = i;
                break;
              }
            }
          }
          if (lastValidIdx >= 0) {
            const pointPx = lastValidIdx * minPixelsPerPoint;
            const halfViewport = (wrapper.clientWidth || 800) / 2;
            wrapper.scrollLeft = Math.max(0, pointPx - halfViewport);
          } else {
            wrapper.scrollLeft = wrapper.scrollWidth;
          }
        } else {
          wrapper.scrollLeft = 0;
        }
      }
      syncStickyAxesAndZones();
    }, 200);

  } catch (err) {
    console.error('Errore storico:', err);
  }
}

// Calcola e applica la larghezza scrollabile del grafico in base al filtro temporale e al numero di punti
function updateChartScrollDimensions(range = currentRange, pointCount = 0) {
  const wrapper = document.getElementById('chart-canvas-wrapper');
  const innerContainer = document.getElementById('chart-inner-container');
  const isMobileOrTablet = window.innerWidth <= 1024;
  const isDailyRange = ['giorni', 'settimane', '3d', '7d', '10d', '30d'].includes(range) || /^\d+d$/.test(range);
  let minPixelsPerPoint = 0;

  if (range === 'minuti') {
    minPixelsPerPoint = 38; // ~1 cm per ciascun punto rilevazione minuti
  } else if (range === 'ore') {
    minPixelsPerPoint = 76; // ~2 cm per ciascuno slot orario (12:00, 13:00, ecc.)
  } else if (range === '24h') {
    minPixelsPerPoint = isMobileOrTablet ? 14 : (pointCount > 300 ? 12 : 0);
  } else if (isDailyRange) {
    if (isMobileOrTablet) {
      minPixelsPerPoint = 76; // ~2 cm di distanza su mobile/tablet per scorrere 7gg, 10gg, 30gg, Settimane
    } else {
      minPixelsPerPoint = pointCount > 35 ? 38 : 0; // Su desktop 7gg, 10gg e 30gg stanno ravvicinati nella finestra
    }
  }

  if (wrapper && innerContainer) {
    const wrapperWidth = wrapper.clientWidth || 800;
    const targetWidth = minPixelsPerPoint > 0 ? Math.max(wrapperWidth, pointCount * minPixelsPerPoint) : wrapperWidth;
    innerContainer.style.width = targetWidth + 'px';
    innerContainer.style.minWidth = targetWidth + 'px';
  }
  return minPixelsPerPoint;
}

// Configura scale Y/Y1 e fasce colore di sfondo per una specifica sostanza
function applySubstanceBackgroundAndScales(substanceId) {
  if (!myChart) return;
  const activeDataset = myChart.data.datasets.find(ds => ds.id === substanceId);
  const dataVals = (activeDataset && activeDataset.data && activeDataset.data.length > 0) ? activeDataset.data.filter(v => v !== null && !isNaN(v)) : [];
  const maxVal = dataVals.length > 0 ? Math.max(...dataVals) : 0;
  const minVal = dataVals.length > 0 ? Math.min(...dataVals) : 0;

  let chartMin = 0;
  let chartMax = 100;

  if (activeDataset && activeDataset.yAxisID === 'y1') {
    myChart.options.scales.y.display = false;
    myChart.options.scales.y1.display = true;
    if (substanceId === 'voc') {
      chartMin = 0;
      chartMax = maxVal > 600 ? Math.ceil((maxVal * 1.1) / 100) * 100 : 600;
      myChart.options.scales.y1.min = chartMin;
      myChart.options.scales.y1.max = chartMax;
    } else if (substanceId === 'co2') {
      chartMin = minVal < 400 ? Math.max(0, Math.floor(minVal / 50) * 50) : 400;
      chartMax = maxVal > 2200 ? Math.ceil((maxVal * 1.1) / 100) * 100 : 2200;
      myChart.options.scales.y1.min = chartMin;
      myChart.options.scales.y1.max = chartMax;
    }
  } else {
    myChart.options.scales.y.display = true;
    myChart.options.scales.y1.display = false;
    if (substanceId === 'inquinamento') {
      chartMin = 0;
      chartMax = maxVal > 100 ? Math.ceil(maxVal) : 100;
      myChart.options.scales.y.min = chartMin;
      myChart.options.scales.y.max = chartMax;
    } else if (substanceId === 'pm25') {
      chartMin = 0;
      chartMax = maxVal > 50 ? Math.ceil((maxVal * 1.15) / 10) * 10 : 50;
      myChart.options.scales.y.min = chartMin;
      myChart.options.scales.y.max = chartMax;
    } else if (substanceId === 'temp') {
      chartMin = minVal < 10 ? Math.floor(minVal - 2) : 10;
      chartMax = maxVal > 35 ? Math.ceil(maxVal + 2) : 35;
      myChart.options.scales.y.min = chartMin;
      myChart.options.scales.y.max = chartMax;
    } else if (substanceId === 'umidita') {
      chartMin = 0;
      chartMax = 100;
      myChart.options.scales.y.min = chartMin;
      myChart.options.scales.y.max = chartMax;
    }
  }

  myChart.options.plugins.annotation.annotations = generateAnnotations(substanceId, chartMax, chartMin);
  myChart._activeZoneSubstance = substanceId;
}

// Filtro tab sensori
function applyTabFilter(target) {
  activeSensorTab = target;
  window.dismissMobileChartInspector?.();
  if (!myChart) return;

  const cards = document.querySelectorAll('.sensor-card');
  const cardsContainer = document.getElementById('cards-container');

  if (target === 'tutti') {
    if (cardsContainer) cardsContainer.className = "grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 md:gap-6 mb-8";
    cards.forEach(c => c.style.display = 'block');

    // Vista Completa: rispetta le curve disattivate dall'utente nella legenda
    myChart.data.datasets.forEach(ds => {
      ds.hidden = userHiddenDatasets.has(ds.id);
    });

    // Se in Vista Completa l'utente ha lasciato attiva una sola sostanza, carica lo sfondo dedicato di quella sostanza;
    // altrimenti mostra lo sfondo fasce dell'Indice Generale Inquinamento (GPI) sull'asse Y principale
    const visibleDatasets = myChart.data.datasets.filter(ds => !ds.hidden);
    if (visibleDatasets.length === 1) {
      applySubstanceBackgroundAndScales(visibleDatasets[0].id);
    } else {
      const gpiDs = myChart.data.datasets.find(ds => ds.id === 'inquinamento');
      const gpiVals = (gpiDs && gpiDs.data) ? gpiDs.data.filter(v => v !== null && !isNaN(v)) : [];
      const gpiMax = gpiVals.length > 0 ? Math.max(100, Math.ceil(Math.max(...gpiVals))) : 100;
      myChart.options.scales.y.display = true;
      myChart.options.scales.y1.display = true;
      myChart.options.scales.y.min = 0;
      myChart.options.scales.y.max = gpiMax;
      myChart.options.scales.y1.min = 0;
      delete myChart.options.scales.y1.max;
      myChart.options.plugins.annotation.annotations = generateAnnotations('inquinamento', gpiMax, 0);
      myChart._activeZoneSubstance = 'inquinamento';
    }
  } else {
    if (cardsContainer) cardsContainer.className = "grid grid-cols-1 max-w-2xl mx-auto gap-5 md:gap-6 mb-8";
    cards.forEach(c => {
      c.style.display = (c.id === `card-${target}`) ? 'block' : 'none';
    });

    // Quando si seleziona la tab di una singola sostanza, la relativa curva deve essere sempre visibile
    userHiddenDatasets.delete(target);
    myChart.data.datasets.forEach(ds => {
      ds.hidden = (ds.id !== target);
    });

    applySubstanceBackgroundAndScales(target);
  }

  const pointCount = (myChart.data && myChart.data.labels) ? myChart.data.labels.length : 0;
  const minPx = updateChartScrollDimensions(currentRange, pointCount);
  myChart.resize();
  myChart.update();
  syncStickyAxesAndZones();
  renderCustomLegend();
  setTimeout(() => {
    if (!myChart) return;
    myChart.resize();
    syncStickyAxesAndZones();
    const wrapper = document.getElementById('chart-canvas-wrapper');
    if (wrapper && minPx > 0 && wrapper.scrollLeft === 0) {
      wrapper.scrollLeft = wrapper.scrollWidth;
    }
  }, 40);
}

// Sincronizza le barre verticali Y (sinistra e destra) e le etichette delle fasce colore affinché rimangano fisse durante lo scroll orizzontale
function syncStickyAxesAndZones() {
  if (!myChart) return;
  const leftCol = document.getElementById('chart-sticky-y-left');
  const rightCol = document.getElementById('chart-sticky-y-right');
  const zonesBox = document.getElementById('chart-sticky-zones-labels');
  const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
  const panelBg = isDark ? 'rgba(22, 27, 34, 0.92)' : 'rgba(255, 255, 255, 0.92)';

  const yScale = myChart.scales.y;
  const y1Scale = myChart.scales.y1;

  // 1. Barra Verticale Sinistra (Asse Y)
  if (leftCol) {
    if (yScale && myChart.options.scales.y.display) {
      leftCol.style.display = 'block';
      leftCol.style.background = panelBg;
      leftCol.style.backdropFilter = 'blur(2px)';
      let html = '';
      const ticks = yScale.ticks || [];
      ticks.forEach((tObj, idx) => {
        const yPx = yScale.getPixelForTick(idx);
        if (yPx >= yScale.top - 6 && yPx <= yScale.bottom + 6) {
          html += `<div style="position:absolute;right:6px;top:${yPx}px;transform:translateY(-50%);font-size:11px;font-family:'Inter',sans-serif;color:#94a3b8;line-height:1;white-space:nowrap;">${tObj.label ?? tObj.value}</div>`;
        }
      });
      leftCol.innerHTML = html;
    } else {
      leftCol.style.display = 'none';
      leftCol.innerHTML = '';
    }
  }

  // 2. Barra Verticale Destra (Asse Y1)
  if (rightCol) {
    if (y1Scale && myChart.options.scales.y1.display) {
      rightCol.style.display = 'block';
      rightCol.style.background = panelBg;
      rightCol.style.backdropFilter = 'blur(2px)';
      let html = '';
      const ticks = y1Scale.ticks || [];
      ticks.forEach((tObj, idx) => {
        const yPx = y1Scale.getPixelForTick(idx);
        if (yPx >= y1Scale.top - 6 && yPx <= y1Scale.bottom + 6) {
          html += `<div style="position:absolute;left:6px;top:${yPx}px;transform:translateY(-50%);font-size:11px;font-family:'Inter',sans-serif;color:#94a3b8;line-height:1;white-space:nowrap;">${tObj.label ?? tObj.value}</div>`;
        }
      });
      rightCol.innerHTML = html;
    } else {
      rightCol.style.display = 'none';
      rightCol.innerHTML = '';
    }
  }

  // 3. Etichette Fisse delle Zone di Soglia (SUPER, BUONO, MEDIO, CATTIVO)
  if (zonesBox) {
    const zoneTarget = myChart._activeZoneSubstance || (activeSensorTab !== 'tutti' ? activeSensorTab : 'inquinamento');
    const keyMap = { inquinamento: 'allpollu', pm25: 'pm', voc: 'voc', co2: 'co2', temp: 'temp', umidita: 'hum' };
    const confKey = keyMap[zoneTarget];
    const configs = getThresholdConfigs();
    const conf = configs[confKey];
    const activeScale = (confKey === 'voc' || confKey === 'co2') ? y1Scale : yScale;

    if (conf && activeScale) {
      let html = '';
      let prevMin = 0;
      if (confKey === 'co2') {
        prevMin = (typeof activeScale.min === 'number' && activeScale.min < 450) ? activeScale.min : 400;
      } else if (confKey === 'temp') {
        prevMin = (typeof activeScale.min === 'number' && activeScale.min < 10) ? activeScale.min : 10;
      }

      conf.levels.forEach((lvl, i) => {
        const isLast = (i === conf.levels.length - 1);
        const topVal = (isLast && typeof activeScale.max === 'number' && activeScale.max > lvl.max) ? activeScale.max : lvl.max;
        const yTopPx = activeScale.getPixelForValue(topVal);
        const yBotPx = activeScale.getPixelForValue(prevMin);
        const clampedTop = Math.max(activeScale.top, Math.min(activeScale.bottom, yTopPx));
        const clampedBot = Math.max(activeScale.top, Math.min(activeScale.bottom, yBotPx));
        const boxHeight = clampedBot - clampedTop;

        if (boxHeight >= 14) {
          const labelTop = clampedTop + 4;
          html += `<div style="position:absolute;left:6px;top:${labelTop}px;font-size:10px;font-weight:500;font-family:'Inter',sans-serif;color:#64748b;white-space:nowrap;pointer-events:none;">${lvl.label} (${lvl.range})</div>`;
        }
        prevMin = lvl.max;
      });
      zonesBox.innerHTML = html;
    } else {
      zonesBox.innerHTML = '';
    }
  }
}

// Stato dispositivo e tendina
async function loadDeviceStatus() {
  try {
    const res = await fetch('api/device_status.php' + getDeviceUuidQuery('?'), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const data = await res.json();

    const dot = document.getElementById('device-status-dot');
    const statusText = document.getElementById('device-status-text');
    const badgeBtn = document.getElementById('device-badge-btn');
    const pill = document.getElementById('device-pill-status');
    const dropStatus = document.getElementById('drop-status');
    const dropUuid = document.getElementById('drop-uuid');
    const dropLastSeen = document.getElementById('drop-last-seen');
    const dropCount24h = document.getElementById('drop-count-24h');
    const dropCountTotal = document.getElementById('drop-count-total');

    if (badgeBtn) {
      badgeBtn.classList.remove('offline', 'online', 'nodata');
      if (data.status === 'offline') {
        badgeBtn.classList.add('offline');
      }
    }

    if (dot && statusText) {
      dot.className = 'status-dot';
      const devLabel = (data.device_name && data.device_name.trim() !== '') ? data.device_name : 'Foobot';
      if (data.status === 'online') {
        dot.classList.add('online');
        statusText.innerText = devLabel + ': ' + t('status_online', 'Connesso');
        if (pill) { pill.className = 'badge badge-ok'; pill.innerText = 'ONLINE'; }
        if (dropStatus) dropStatus.innerHTML = '<span style="color:var(--accent-good);">' + t('status_active_regular', 'Attivo (invio regolare)') + '</span>';
      } else if (data.status === 'offline') {
        dot.classList.add('offline');
        statusText.innerText = devLabel + ': ' + t('status_offline', 'Non raggiungibile');
        if (pill) { pill.className = 'badge badge-fail'; pill.innerText = 'OFFLINE'; }
        if (dropStatus) dropStatus.innerHTML = '<span style="color:var(--accent-danger);">' + t('status_no_data_15m', 'Nessun dato da >15 min') + '</span>';
      } else {
        dot.classList.add('nodata');
        statusText.innerText = devLabel + ': ' + t('status_waiting', 'In attesa');
        if (pill) { pill.className = 'badge'; pill.innerText = 'WAITING'; }
        if (dropStatus) dropStatus.innerText = t('status_no_readings', 'Nessuna lettura registrata');
      }
    }

    if (dropUuid) dropUuid.innerText = data.uuid || 'N/D';
    if (dropCount24h) dropCount24h.innerText = data.readings_24h || 0;
    if (dropCountTotal) dropCountTotal.innerText = data.readings_total || 0;

    const loc = getAppLocale();
    if (dropLastSeen) {
      if (data.last_seen) {
        const dt = new Date(data.last_seen * 1000);
        const minsAgo = Math.floor((data.seconds_ago || 0) / 60);
        const agoText = formatTimeAgo(minsAgo);
        dropLastSeen.innerText = dt.toLocaleDateString(loc, { day: '2-digit', month: '2-digit' }) + ' ' + dt.toLocaleTimeString(loc, { hour: '2-digit', minute: '2-digit', second: '2-digit' }) + ' (' + agoText + ')';
      } else {
        dropLastSeen.innerText = t('time_never', 'Mai');
      }
    }

    const dropLastConn = document.getElementById('drop-last-connected');
    if (dropLastConn) {
      if (data.last_connected_at) {
        const dtConn = new Date(data.last_connected_at * 1000);
        dropLastConn.innerText = dtConn.toLocaleDateString(loc, { day: '2-digit', month: '2-digit' }) + ' ' + dtConn.toLocaleTimeString(loc, { hour: '2-digit', minute: '2-digit', second: '2-digit' });
      } else if (data.last_seen) {
        const dtConn = new Date(data.last_seen * 1000);
        dropLastConn.innerText = dtConn.toLocaleDateString(loc, { day: '2-digit', month: '2-digit' }) + ' ' + dtConn.toLocaleTimeString(loc, { hour: '2-digit', minute: '2-digit', second: '2-digit' });
      } else {
        dropLastConn.innerText = t('time_never', 'Mai');
      }
    }

    const rowDisc = document.getElementById('drop-row-disconnected');
    const dropDisc = document.getElementById('drop-last-disconnected');
    if (rowDisc && dropDisc) {
      if (data.last_disconnected_at) {
        const dtDisc = new Date(data.last_disconnected_at * 1000);
        const dateTimeStr = dtDisc.toLocaleDateString(loc, { day: '2-digit', month: '2-digit', year: 'numeric' }) + ' ' + dtDisc.toLocaleTimeString(loc, { hour: '2-digit', minute: '2-digit' });
        let shortReason = t('reason_power_cut', 'Assenza segnale o corrente (>12 min)');
        if (data.disconnect_reason) {
          const rLow = data.disconnect_reason.toLowerCase();
          if (rLow.includes('socket') || rLow.includes('tcp') || rLow.includes('connessione chiusa') || rLow.includes('wifi') || rLow.includes('wi-fi')) {
            shortReason = t('reason_wifi_drop', 'Caduta connessione Wi-Fi / Rete');
          } else if (rLow.includes('alimentazione') || rLow.includes('assenza segnale') || rLow.includes('corrente') || rLow.includes('segnale') || rLow.includes('12 min')) {
            shortReason = t('reason_power_cut', 'Assenza segnale o corrente (>12 min)');
          } else {
            shortReason = data.disconnect_reason;
          }
        }
        dropDisc.innerHTML = '<div>' + dateTimeStr + '</div><div style="font-size:0.75rem; font-weight:500; opacity:0.9; margin-top:2px;">' + shortReason + '</div>';
        rowDisc.style.display = 'flex';
      } else {
        rowDisc.style.display = 'none';
      }
    }

    const dropName = document.getElementById('drop-device-name');
    if (dropName && data.device_name) {
      dropName.innerText = data.device_name;
    }

    const dropLoc = document.getElementById('drop-device-location');
    const rowLoc = document.getElementById('drop-row-location');
    if (dropLoc && rowLoc) {
      if (data.location && data.location.trim() !== '') {
        dropLoc.innerText = data.location;
        rowLoc.style.display = 'flex';
      } else {
        dropLoc.innerText = '';
        rowLoc.style.display = 'none';
      }
    }

  } catch (err) {
    console.error('Errore stato dispositivo:', err);
  }
}

// Inizializzazione al caricamento
document.addEventListener('DOMContentLoaded', () => {
  loadLatestData();
  loadDeviceStatus();
  loadHistory('24h');

  // Aggiornamento sincronizzato ogni 5 minuti (300 secondi = ciclo naturale del Foobot)
  // Ricarica sia le schede live sia il grafico storico in modo visivo e fluido (se in Tempo Reale)
  const refreshFoobotCycle = () => {
    loadLatestData();
    loadDeviceStatus();
    if (currentOffset === 0) {
      loadHistory(currentRange, 0);
    }
  };
  setInterval(refreshFoobotCycle, 300000);

  // Controllo leggero dello stato di connessione ogni 60 secondi
  setInterval(loadDeviceStatus, 60000);

  // Switch tabs sensori
  const tabButtons = document.querySelectorAll('.tab-btn');
  tabButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      const target = btn.dataset.tab;
      tabButtons.forEach(b => {
        b.className = 'tab-btn inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium bg-white text-slate-600 hover:bg-slate-100 border border-slate-200 transition-all';
      });
      btn.className = 'tab-btn active inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold bg-blue-600 text-white transition-all';
      applyTabFilter(target);
    });
  });

  // Toggle tendina dispositivo
  const badgeBtn = document.getElementById('device-badge-btn');
  const dropdown = document.getElementById('device-dropdown');
  if (badgeBtn && dropdown) {
    badgeBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      dropdown.classList.toggle('show');
      const expanded = dropdown.classList.contains('show');
      badgeBtn.setAttribute('aria-expanded', expanded);
    });
    document.addEventListener('click', (e) => {
      if (!dropdown.contains(e.target) && !badgeBtn.contains(e.target)) {
        dropdown.classList.remove('show');
        badgeBtn.setAttribute('aria-expanded', 'false');
      }
    });
  }

  // Chiusura modal con tasto ESC
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeInfoModal();
  });

  // Aggiorna colori griglia grafico al cambio tema chiaro/scuro
  document.addEventListener('foobot:themechange', () => {
    if (myChart) {
      myChart.update('none');
      syncStickyAxesAndZones();
    }
  });

  // Funzione globale per chiudere il box ispezione mobile
  window.dismissMobileChartInspector = function() {
    const inspector = document.getElementById('mobile-chart-inspector');
    if (inspector) inspector.style.display = 'none';
  };

  // Click fuori dall'area grafico e dal box ispezione per chiudere l'inspector su mobile
  document.addEventListener('click', (e) => {
    const shell = document.getElementById('chart-viewport-shell');
    const inspector = document.getElementById('mobile-chart-inspector');
    if (inspector && inspector.style.display !== 'none') {
      if (shell && !shell.contains(e.target) && !inspector.contains(e.target)) {
        inspector.style.display = 'none';
      }
    }
  });

  // Reset cursore e tooltip all'uscita dal canvas del grafico
  const chartCanvas = document.getElementById('trendChart');
  if (chartCanvas) {
    chartCanvas.addEventListener('mouseleave', () => {
      lastPointerY = null;
      lastPointerX = null;
      hoveredDatasetIndex = -1;
      const tooltipEl = chartCanvas.parentNode?.querySelector('.foobot-chart-tooltip');
      if (tooltipEl) {
        tooltipEl.style.opacity = '0';
        tooltipEl.style.display = 'none';
      }
      if (myChart) myChart.draw();
    });
  }

  // Ricalcola larghezza scrollabile e colonne assi fissi al resize finestra
  window.addEventListener('resize', () => {
    if (window.innerWidth >= 768) {
      window.dismissMobileChartInspector();
    }
    if (myChart) {
      const pointCount = (myChart.data && myChart.data.labels) ? myChart.data.labels.length : 0;
      updateChartScrollDimensions(currentRange, pointCount);
      myChart.resize();
      setTimeout(syncStickyAxesAndZones, 80);
    }
  });

  // Inizializza trascinamento touch con le dita (mobile) e drag (desktop) sul grafico
  initChartTouchAndDrag();
});

function initChartTouchAndDrag() {
  const wrapper = document.getElementById('chart-canvas-wrapper');
  const inner = document.getElementById('chart-inner-container');
  if (!wrapper) return;

  // Abilita drag con il mouse per desktop su tutto il wrapper
  let isMouseDown = false;
  let startX = 0;
  let scrollLeft = 0;

  wrapper.addEventListener('mousedown', (e) => {
    // Solo se c'è overflow orizzontale
    if (wrapper.scrollWidth <= wrapper.clientWidth) return;
    isMouseDown = true;
    wrapper.style.cursor = 'grabbing';
    wrapper.style.userSelect = 'none';
    startX = e.pageX - wrapper.offsetLeft;
    scrollLeft = wrapper.scrollLeft;
  });

  window.addEventListener('mouseup', () => {
    if (isMouseDown) {
      isMouseDown = false;
      wrapper.style.cursor = 'default';
      wrapper.style.removeProperty('user-select');
    }
  });

  wrapper.addEventListener('mousemove', (e) => {
    if (!isMouseDown) return;
    e.preventDefault();
    const x = e.pageX - wrapper.offsetLeft;
    const walk = (x - startX) * 1.5; // Velocità di scorrimento naturale
    wrapper.scrollLeft = scrollLeft - walk;
  });
}

// Range temporale
function switchRange(range, elem) {
  window.dismissMobileChartInspector?.();
  document.querySelectorAll('.range-btn').forEach(b => {
    b.className = 'range-btn px-3 py-1.5 text-xs sm:text-sm font-medium text-slate-600 hover:text-slate-900 rounded-lg hover:bg-slate-200/50 transition-colors';
    b.classList.remove('active');
  });
  if (elem) {
    elem.className = 'range-btn active px-3 py-1.5 text-xs sm:text-sm font-medium bg-white text-slate-900 rounded-lg shadow-sm border border-slate-200';
  }
  // Ripristina l'intervallo date personalizzato quando si attiva un filtro rapido
  customStartDate = null;
  customEndDate = null;
  const btnReset = document.getElementById('btn-reset-daterange');
  if (btnReset) btnReset.style.display = 'none';
  const inputFrom = document.getElementById('chart-date-from');
  if (inputFrom) inputFrom.value = '';
  const inputTo = document.getElementById('chart-date-to');
  if (inputTo) inputTo.value = '';

  currentOffset = 0;
  loadHistory(range, 0);
}

// Navigazione indietro / avanti nel tempo per il grafico (frecce)
function shiftChartTime(step) {
  const nextOffset = Math.max(0, currentOffset + step);
  if (nextOffset === currentOffset && currentOffset === 0) return;
  loadHistory(currentRange, nextOffset);
}

function resetChartTime() {
  customStartDate = null;
  customEndDate = null;
  const btnReset = document.getElementById('btn-reset-daterange');
  if (btnReset) btnReset.style.display = 'none';
  const inputFrom = document.getElementById('chart-date-from');
  const inputTo = document.getElementById('chart-date-to');
  if (inputFrom) inputFrom.value = '';
  if (inputTo) inputTo.value = '';
  loadHistory(currentRange, 0);
}

// Filtro personalizzato per intervallo di date (es. dal 20 al 25 ottobre)
function applyCustomDateRange() {
  const fromVal = document.getElementById('chart-date-from')?.value;
  const toVal = document.getElementById('chart-date-to')?.value;

  if (!fromVal) {
    alert('Seleziona almeno una data di inizio per il filtro.');
    return;
  }

  customStartDate = fromVal;
  customEndDate = toVal || fromVal;

  if (customEndDate < customStartDate) {
    // Inverti se l'utente ha inserito una data 'A' precedente a 'Da'
    const tmp = customStartDate;
    customStartDate = customEndDate;
    customEndDate = tmp;
    if (document.getElementById('chart-date-from')) document.getElementById('chart-date-from').value = customStartDate;
    if (document.getElementById('chart-date-to')) document.getElementById('chart-date-to').value = customEndDate;
  }

  const btnReset = document.getElementById('btn-reset-daterange');
  if (btnReset) btnReset.style.display = 'inline-flex';

  currentOffset = 0;
  loadHistory(currentRange, 0);
}

function resetCustomDateRange() {
  resetChartTime();
}

function submitCustomDays() {
  const input = document.getElementById('custom-days-input');
  if (!input) return;
  const days = parseInt(input.value, 10);
  if (!days || days < 1) return;
  document.querySelectorAll('.range-btn').forEach(b => {
    b.className = 'range-btn px-3 py-1.5 text-xs sm:text-sm font-medium text-slate-600 hover:text-slate-900 rounded-lg hover:bg-slate-200/50 transition-colors';
    b.classList.remove('active');
  });
  currentOffset = 0;
  loadHistory(days + 'd', 0);
}


// Helper per etichette semplificate dei grafici attivi
function getSimplifiedSensorMeta(sensorId) {
  switch (sensorId) {
    case 'inquinamento':
      return { code: 'GPI', desc: t('legend_pollution', 'Inquinamento'), activeBg: 'bg-green-50', activeBorder: 'border-green-200', hoverBg: 'hover:bg-green-100', dotBg: 'bg-green-500', hexBg: '#ecfdf5', hexBorder: '#a7f3d0', hexDot: '#10b981' };
    case 'voc':
      return { code: 'VOC', desc: t('legend_voc', 'Volatili'), activeBg: 'bg-purple-50', activeBorder: 'border-purple-200', hoverBg: 'hover:bg-purple-100', dotBg: 'bg-purple-500', hexBg: '#faf5ff', hexBorder: '#e9d5ff', hexDot: '#9333ea' };
    case 'pm25':
      return { code: 'PM2.5', desc: t('legend_pm25', 'Particelle'), activeBg: 'bg-blue-50', activeBorder: 'border-blue-200', hoverBg: 'hover:bg-blue-100', dotBg: 'bg-blue-500', hexBg: '#eff6ff', hexBorder: '#bfdbfe', hexDot: '#2563eb' };
    case 'co2':
      return { code: 'eCO2', desc: t('legend_co2', 'Anidride Carbonica'), activeBg: 'bg-cyan-50', activeBorder: 'border-cyan-200', hoverBg: 'hover:bg-cyan-100', dotBg: 'bg-cyan-500', hexBg: '#ecfeff', hexBorder: '#a5f3fc', hexDot: '#06b6d4' };
    case 'temp':
      return { code: 'TMP', desc: t('legend_temp', 'Temperatura'), activeBg: 'bg-red-50', activeBorder: 'border-red-200', hoverBg: 'hover:bg-red-100', dotBg: 'bg-red-500', hexBg: '#fef2f2', hexBorder: '#fecdd3', hexDot: '#ef4444' };
    case 'umidita':
      return { code: 'HUM', desc: t('legend_humidity', 'Umidità'), activeBg: 'bg-orange-50', activeBorder: 'border-orange-200', hoverBg: 'hover:bg-orange-100', dotBg: 'bg-orange-500', hexBg: '#fff7ed', hexBorder: '#fed7aa', hexDot: '#f97316' };
    default:
      return { code: sensorId.toUpperCase(), desc: '', activeBg: 'bg-gray-50', activeBorder: 'border-gray-200', hoverBg: 'hover:bg-gray-100', dotBg: 'bg-gray-500', hexBg: '#f8fafc', hexBorder: '#e2e8f0', hexDot: '#64748b' };
  }
}

// Generatore Legenda HTML Interattiva Stile Pill con bordino, dot colorato e testo semplificato
function renderCustomLegend() {
  const container = document.getElementById('chart-legend-container');
  if (!container || !myChart) return;
  container.innerHTML = '';

  myChart.data.datasets.forEach((ds, idx) => {
    // Se siamo su una scheda specifica e il dataset non appartiene alla scheda, non mostrarlo
    if (activeSensorTab !== 'tutti' && ds.id !== activeSensorTab) {
      return;
    }

    const isVisible = !ds.hidden;
    const meta = getSimplifiedSensorMeta(ds.id);

    const pill = document.createElement('button');
    pill.type = 'button';
    pill.className = 'inline-flex items-center px-3 py-1.5 rounded-full border transition-colors shadow-sm cursor-pointer whitespace-nowrap ' +
      (isVisible
        ? `${meta.activeBorder} ${meta.activeBg} ${meta.hoverBg}`
        : 'border-gray-200 bg-white hover:bg-gray-50 opacity-60');

    if (isVisible) {
      pill.style.backgroundColor = meta.hexBg;
      pill.style.borderColor = meta.hexBorder;
    } else {
      pill.style.backgroundColor = '#ffffff';
      pill.style.borderColor = '#e2e8f0';
    }

    // Pallino / Dot del colore esatto della curva
    const dot = document.createElement('span');
    dot.className = `w-2.5 h-2.5 rounded-full shrink-0 ${meta.dotBg}`;
    dot.style.width = '10px';
    dot.style.height = '10px';
    dot.style.marginRight = '8px';
    dot.style.backgroundColor = meta.hexDot;

    // Codice Sensore in grassetto
    const codeSpan = document.createElement('span');
    codeSpan.className = 'text-sm font-bold ' + (isVisible ? 'text-gray-800' : 'text-gray-500');
    codeSpan.innerText = meta.code;

    // Descrizione semplificata con spazio prima del trattino (" — Inquinamento", " — Volatili", ecc.)
    const descSpan = document.createElement('span');
    descSpan.className = 'text-sm ' + (isVisible ? 'text-gray-600' : 'text-gray-400') + ' whitespace-nowrap';
    descSpan.style.marginLeft = '4px';
    descSpan.innerText = meta.desc ? `— ${meta.desc}` : '';

    pill.appendChild(dot);
    pill.appendChild(codeSpan);
    if (meta.desc) pill.appendChild(descSpan);

    // Click per attivare/disattivare la curva sul grafico (memorizza lo stato per la sessione)
    pill.onclick = () => {
      ds.hidden = !ds.hidden;
      if (ds.hidden) {
        userHiddenDatasets.add(ds.id);
      } else {
        userHiddenDatasets.delete(ds.id);
      }
      applyTabFilter(activeSensorTab);
    };

    container.appendChild(pill);
  });
}