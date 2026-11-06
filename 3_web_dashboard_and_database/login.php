<?php
/**
 * Pagina di Accesso (Login) NewFoobot
 * Con protezione Anti-Brute-Force (10 tentativi), Token CSRF, Filtro Paese (Geo-IP) e Whitelist IP
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/nav.php';
$pdo = require_valid_database();
if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$client_ip = get_client_ip();
$settings  = get_all_settings($pdo);

$is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
         || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
         || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
         || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on');

$error = '';

// Verifica blocco Brute-Force (10 tentativi errati = 15 minuti di blocco)
$lock_remaining = check_brute_force_lock($pdo, $client_ip);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    $identity = trim($_POST['identity'] ?? ($_POST['username'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($lock_remaining > 0) {
        $mins = ceil($lock_remaining / 60);
        $error = "Troppi tentativi falliti dal tuo IP ({$client_ip}). Accesso bloccato per {$mins} minuti.";
    } elseif (!verify_csrf_token($csrf)) {
        $error = "Sessione di sicurezza scaduta (CSRF). Ricarica la pagina e riprova.";
    } elseif (empty($identity) || empty($password)) {
        $error = "Inserisci nome utente o email e la password.";
    } else {
        // Cerca l'utente sia per username sia per email
        $stmt = $pdo->prepare("SELECT * FROM foobot_users WHERE username = ? OR (email != '' AND email = ?) LIMIT 1");
        $stmt->execute([$identity, $identity]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            clear_login_attempts($pdo, $client_ip);
            session_regenerate_id(true);
            $_SESSION['foobot_user'] = $user['username'];
            $_SESSION['foobot_role'] = !empty($user['role']) ? $user['role'] : 'admin';
            
            // Imposta cookie di login persistente per 1 anno (365 giorni)
            set_remember_cookie($pdo, $user['id']);

            // Imposta la lingua preferita dell'utente se presente nel suo profilo
            if (!empty($user['language']) && in_array(strtolower($user['language']), get_available_languages(), true)) {
                $u_lang = strtolower($user['language']);
                $_SESSION['foobot_lang'] = $u_lang;
                setcookie('foobot_lang', $u_lang, [
                    'expires'  => time() + (365 * 86400),
                    'path'     => '/',
                    'domain'   => '',
                    'secure'   => $is_https,
                    'httponly' => false,
                    'samesite' => 'Lax'
                ]);
            }

            // Salva esplicitamente e sblocca la sessione su disco prima del redirect
            // per evitare race-condition o lock contention al caricamento di index.php
            session_write_close();

            // Reindirizza alla Dashboard principale dopo il login
            header('Location: index.php');
            exit;
        } else {
            $left = record_failed_login($pdo, $client_ip, 10, 900);
            if ($left <= 0) {
                $error = "Hai raggiunto il limite massimo di 10 tentativi errati. Il tuo IP è stato bloccato per 15 minuti.";
            } else {
                $error = "Credenziali non valide. Tentativi rimasti prima del blocco: {$left}.";
            }
        }
    }
}

$csrf_token = get_csrf_token();
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Accedi - NewFoobot</title>
  <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg?v=2">
  <link rel="apple-touch-icon" href="assets/img/favicon.png?v=2">
  <link rel="stylesheet" href="assets/css/style.css?v=25">
  <script src="assets/js/theme.js?v=15"></script>
</head>
<body>
  <?php render_auth_lang_switcher(); ?>
  <button type="button" class="theme-toggle theme-floating" aria-label="Cambia tema"></button>
  <div class="auth-box narrow">
    <div class="auth-header">
      <div style="display: flex; justify-content: center; margin-bottom: 12px;">
        <span class="header-brand-logo" style="width: 50px; height: 50px;">
          <?php echo get_foobot_logo_svg(50); ?>
        </span>
      </div>
      <h1>Area Riservata</h1>
      <p class="card-subtext">Accedi per gestire le impostazioni e la sicurezza</p>
    </div>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

      <div class="form-group">
        <label for="identity">Nome Utente o Email</label>
        <input type="text" id="identity" name="identity" placeholder="es. admin oppure tua@email.it" required autofocus <?php echo $lock_remaining > 0 ? 'disabled' : ''; ?>>
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <div class="password-wrapper">
          <input type="password" id="password" name="password" required <?php echo $lock_remaining > 0 ? 'disabled' : ''; ?>>
          <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('password', this)" title="Mostra/Nascondi password">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
          </button>
        </div>
      </div>

      <button type="submit" class="btn-submit" <?php echo $lock_remaining > 0 ? 'disabled' : ''; ?>>Accedi &rarr;</button>
    </form>

    <div style="margin-top: 16px; text-align: center; font-size: 0.88rem;">
      <a href="forgot_password.php" style="color: var(--accent-blue); text-decoration: none;">Hai dimenticato la password? Recuperala via email</a>
    </div>
  </div>

  <script>
    function togglePasswordVisibility(fieldId, btn) {
      var field = document.getElementById(fieldId);
      if (!field) return;
      if (field.type === 'password') {
        field.type = 'text';
        btn.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></line><line x1="1" y1="1" x2="23" y2="23"></line></svg>';
      } else {
        field.type = 'password';
        btn.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
      }
    }
  </script>
</body>
</html>
