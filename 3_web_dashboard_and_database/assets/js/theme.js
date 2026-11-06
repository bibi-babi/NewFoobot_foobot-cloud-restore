/**
 * Gestione tema chiaro/scuro NewFoobot
 * Priorità: scelta salvata dall'utente > preferenza del sistema (telefono/PC) > chiaro.
 * Va incluso nel <head> per evitare lo sfarfallio iniziale.
 */
(function () {
  var STORAGE_KEY = 'foobot-theme';
  var media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

  var ICON_MOON = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>';
  var ICON_SUN = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>';

  function getSaved() {
    try { return localStorage.getItem(STORAGE_KEY); } catch (e) { return null; }
  }

  function resolveTheme() {
    var saved = getSaved();
    if (saved === 'light' || saved === 'dark') return saved;
    return (media && media.matches) ? 'dark' : 'light';
  }

  function updateButtons(theme) {
    var buttons = document.querySelectorAll('.theme-toggle');
    for (var i = 0; i < buttons.length; i++) {
      var next = theme === 'dark' ? 'chiaro' : 'scuro';
      buttons[i].innerHTML = theme === 'dark' ? ICON_SUN : ICON_MOON;
      buttons[i].setAttribute('aria-label', 'Passa al tema ' + next);
      buttons[i].setAttribute('title', 'Passa al tema ' + next);
    }
  }

  function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    updateButtons(theme);
    document.dispatchEvent(new CustomEvent('foobot:themechange', { detail: { theme: theme } }));
  }

  // Applicazione immediata (prima del rendering)
  document.documentElement.setAttribute('data-theme', resolveTheme());

  // Segue i cambi di tema del sistema se l'utente non ha scelto manualmente
  if (media) {
    var onChange = function () { if (!getSaved()) applyTheme(resolveTheme()); };
    if (media.addEventListener) media.addEventListener('change', onChange);
    else if (media.addListener) media.addListener(onChange);
  }

  document.addEventListener('DOMContentLoaded', function () {
    updateButtons(resolveTheme());
    document.addEventListener('click', function (ev) {
      var btn = ev.target.closest ? ev.target.closest('.theme-toggle') : null;
      if (!btn) return;
      var current = document.documentElement.getAttribute('data-theme');
      var next = current === 'dark' ? 'light' : 'dark';
      try { localStorage.setItem(STORAGE_KEY, next); } catch (e) {}
      applyTheme(next);
    });
  });
})();
