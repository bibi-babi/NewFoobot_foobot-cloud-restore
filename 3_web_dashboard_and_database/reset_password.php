<?php
/**
 * Imposta Nuova Password (dopo clic sul link email di reset) - NewFoobot
 * Supporta internazionalizzazione completa (IT, EN, ES, FR, DE)
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/nav.php';
require_once __DIR__ . '/i18n.php';
$pdo = require_valid_database();

$token = trim($_GET['token'] ?? ($_POST['token'] ?? ''));
$msg = '';
$error = '';
$token_valid = false;
$reset_entry = null;

if (empty($token)) {
    $error = __('reset_err_missing_token');
} else {
    // Verifica validità e scadenza del token
    $stmt = $pdo->prepare("SELECT * FROM foobot_password_resets WHERE token = ? LIMIT 1");
    $stmt->execute([$token]);
    $reset_entry = $stmt->fetch();

    if (!$reset_entry) {
        $error = __('reset_err_invalid_token');
    } elseif ((int)$reset_entry['expires_at'] < time()) {
        $error = __('reset_err_expired_token');
    } else {
        $token_valid = true;
        // Se non specificata nella richiesta GET, allinea la lingua a quella dell'utente associato al token
        if (!isset($_GET['lang']) && !empty($reset_entry['email'])) {
            try {
                $u_stmt = $pdo->prepare("SELECT language FROM foobot_users WHERE email = ? LIMIT 1");
                $u_stmt->execute([$reset_entry['email']]);
                $u_lang = $u_stmt->fetchColumn();
                if (!empty($u_lang) && in_array(strtolower($u_lang), get_available_languages(), true)) {
                    $_SESSION['foobot_lang'] = strtolower($u_lang);
                }
            } catch (Exception $e) {}
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valid) {
    $csrf = $_POST['csrf_token'] ?? '';
    $new_pwd = $_POST['new_password'] ?? '';
    $confirm_pwd = $_POST['confirm_password'] ?? '';

    if (!verify_csrf_token($csrf)) {
        $error = __('reset_err_csrf');
    } elseif (empty($new_pwd)) {
        $error = __('reset_err_empty_pwd');
    } elseif ($new_pwd !== $confirm_pwd) {
        $error = __('reset_err_pwd_mismatch');
    } elseif (strlen($new_pwd) < 6) {
        $error = __('reset_err_pwd_short');
    } else {
        $new_hash = password_hash($new_pwd, PASSWORD_DEFAULT);
        
        // Aggiorna la password dell'utente corrispondente all'email
        $up = $pdo->prepare("UPDATE foobot_users SET password_hash = ? WHERE email = ?");
        $up->execute([$new_hash, $reset_entry['email']]);

        // Cancella il token usato per sicurezza
        $del = $pdo->prepare("DELETE FROM foobot_password_resets WHERE token = ?");
        $del->execute([$token]);

        $token_valid = false;
        $msg = __('reset_success_msg');
    }
}

$csrf_token = get_csrf_token();
$current_lang = get_current_lang();
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($current_lang); ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars(__('reset_page_title')); ?> - NewFoobot</title>
  <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg?v=2">
  <link rel="apple-touch-icon" href="assets/img/favicon.png?v=2">
  <link rel="stylesheet" href="assets/css/style.css?v=25">
  <script src="assets/js/theme.js?v=15"></script>
</head>
<body>
  <?php render_auth_lang_switcher(!empty($token) ? ['token' => $token] : []); ?>
  <button type="button" class="theme-toggle theme-floating" aria-label="Cambia tema"></button>
  <div class="auth-box narrow">
    <div class="auth-header">
      <div style="display: flex; justify-content: center; margin-bottom: 12px;">
        <span class="header-brand-logo" style="width: 50px; height: 50px;">
          <?php echo get_foobot_logo_svg(50); ?>
        </span>
      </div>
      <h1><?php echo htmlspecialchars(__('reset_page_title')); ?></h1>
      <p class="card-subtext"><?php echo htmlspecialchars(__('reset_subtitle')); ?></p>
    </div>

    <?php if (!empty($msg)): ?>
      <div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div>
      <div style="margin-top: 20px; text-align: center;">
        <a href="login.php" class="btn-action"><?php echo htmlspecialchars(__('reset_btn_go_login')); ?></a>
      </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
      <?php if (!$token_valid && empty($msg)): ?>
        <div style="margin-top: 15px; text-align: center;">
          <a href="forgot_password.php" style="color: var(--accent-blue); text-decoration: none;"><?php echo htmlspecialchars(__('reset_request_new_link')); ?></a>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($token_valid): ?>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

        <div class="form-group">
          <label for="new_password"><?php echo htmlspecialchars(__('reset_label_new_pwd')); ?></label>
          <div class="password-wrapper">
            <input type="password" id="new_password" name="new_password" placeholder="<?php echo htmlspecialchars(__('reset_ph_min_chars')); ?>" required autofocus>
            <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('new_password', this)" title="<?php echo htmlspecialchars(__('pwd_toggle_title', 'Mostra/Nascondi password')); ?>">
              <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
            </button>
          </div>
        </div>

        <div class="form-group">
          <label for="confirm_password"><?php echo htmlspecialchars(__('reset_label_confirm_pwd')); ?></label>
          <div class="password-wrapper">
            <input type="password" id="confirm_password" name="confirm_password" placeholder="<?php echo htmlspecialchars(__('reset_ph_repeat')); ?>" required>
            <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('confirm_password', this)" title="<?php echo htmlspecialchars(__('pwd_toggle_title', 'Mostra/Nascondi password')); ?>">
              <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
            </button>
          </div>
        </div>

        <button type="submit" class="btn-submit"><?php echo htmlspecialchars(__('reset_btn_submit')); ?></button>
      </form>
    <?php endif; ?>

    <div style="margin-top: 20px; text-align: center;">
      <a href="login.php"><?php echo htmlspecialchars(__('reset_back_to_login')); ?></a>
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
