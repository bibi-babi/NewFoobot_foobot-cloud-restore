<?php
/**
 * Recupero Password via Email - NewFoobot
 * Invia un link temporaneo monouso valido 30 minuti all'indirizzo email dell'amministratore/utente
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/nav.php';
require_once __DIR__ . '/i18n.php';
$pdo = require_valid_database();

$current_lang = get_current_lang();
$msg = '';
$error = '';
$client_ip = get_client_ip();

// Rate limiting su richieste di reset
$lock_remaining = check_brute_force_lock($pdo, $client_ip);

/**
 * Restituisce oggetto e messaggio email tradotti per la lingua dell'utente
 */
function get_password_reset_email_content($lang, $username, $reset_link) {
    $templates = [
        'it' => [
            'subject' => "Recupero Password - NewFoobot",
            'body'    => "Ciao " . $username . ",\n\n"
                       . "Abbiamo ricevuto una richiesta di recupero password per il tuo account NewFoobot Air Monitor.\n"
                       . "Per impostare una nuova password, fai clic sul link seguente (valido per 30 minuti):\n\n"
                       . $reset_link . "\n\n"
                       . "Se non hai richiesto tu il ripristino, puoi ignorare questo messaggio: la tua password rimarrà invariata.\n\n"
                       . "--\nNewFoobot Air Monitor"
        ],
        'en' => [
            'subject' => "Password Reset - NewFoobot",
            'body'    => "Hello " . $username . ",\n\n"
                       . "We received a request to reset the password for your NewFoobot Air Monitor account.\n"
                       . "To set a new password, click the link below (valid for 30 minutes):\n\n"
                       . $reset_link . "\n\n"
                       . "If you did not request a password reset, you can ignore this message: your password will remain unchanged.\n\n"
                       . "--\nNewFoobot Air Monitor"
        ],
        'es' => [
            'subject' => "Recuperación de Contraseña - NewFoobot",
            'body'    => "Hola " . $username . ",\n\n"
                       . "Hemos recibido una solicitud para restablecer la contraseña de tu cuenta NewFoobot Air Monitor.\n"
                       . "Para definir una nueva contraseña, haz clic en el siguiente enlace (válido por 30 minutos):\n\n"
                       . $reset_link . "\n\n"
                       . "Si no solicitaste este restablecimiento, puedes ignorar este mensaje: tu contraseña no cambiará.\n\n"
                       . "--\nNewFoobot Air Monitor"
        ],
        'fr' => [
            'subject' => "Récupération de mot de passe - NewFoobot",
            'body'    => "Bonjour " . $username . ",\n\n"
                       . "Nous avons reçu une demande de réinitialisation de mot de passe pour votre compte NewFoobot Air Monitor.\n"
                       . "Pour définir un nouveau mot de passe, veuillez cliquer sur le lien suivant (valide pendant 30 minutes) :\n\n"
                       . $reset_link . "\n\n"
                       . "Si vous n'avez pas demandé cette réinitialisation, vous pouvez ignorer ce message : votre mot de passe restera inchangé.\n\n"
                       . "--\nNewFoobot Air Monitor"
        ],
        'de' => [
            'subject' => "Passwort zurücksetzen - NewFoobot",
            'body'    => "Hallo " . $username . ",\n\n"
                       . "wir haben eine Anfrage zum Zurücksetzen des Passworts für Ihr NewFoobot Air Monitor-Konto erhalten.\n"
                       . "Um ein neues Passwort festzulegen, klicken Sie bitte auf den folgenden Link (gültig für 30 Minuten):\n\n"
                       . $reset_link . "\n\n"
                       . "Wenn Sie diese Anfrage nicht gestellt haben, können Sie diese Nachricht ignorieren: Ihr Passwort bleibt unverändert.\n\n"
                       . "--\nNewFoobot Air Monitor"
        ],
    ];

    $l = isset($templates[$lang]) ? $lang : 'en';
    return $templates[$l];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    $email = trim($_POST['email'] ?? '');

    if ($lock_remaining > 0) {
        $mins = ceil($lock_remaining / 60);
        $error = sprintf(__('forgot_err_too_many'), $mins);
    } elseif (!verify_csrf_token($csrf)) {
        $error = __('forgot_err_csrf');
    } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = __('forgot_err_invalid_email');
    } else {
        // Cerca l'utente associato a questa email, includendo la preferenza di lingua
        $stmt = $pdo->prepare("SELECT id, username, email, language FROM foobot_users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Messaggio neutro per prevenire user-enumeration
        $msg = __('forgot_msg_sent');

        if ($user) {
            // Lingua di destinazione dell'email: lingua impostata sul profilo utente, altrimenti lingua corrente del sito
            $target_lang = !empty($user['language']) ? strtolower(trim($user['language'])) : $current_lang;
            if (!in_array($target_lang, get_available_languages(), true)) {
                $target_lang = $current_lang;
            }

            // Genera token crittografico sicuro
            $token = bin2hex(random_bytes(32));
            $expires_at = time() + (30 * 60); // 30 minuti di validità

            // Invalida eventuali vecchi token per questa email
            $del = $pdo->prepare("DELETE FROM foobot_password_resets WHERE email = ?");
            $del->execute([$email]);

            // Salva il nuovo token
            $ins = $pdo->prepare("INSERT INTO foobot_password_resets (email, token, expires_at) VALUES (?, ?, ?)");
            $ins->execute([$email, $token, $expires_at]);

            // Costruisci link assoluto di reset con preservazione della lingua dell'utente
            $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
            $protocol = $is_https ? 'https://' : 'http://';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $reset_link = $protocol . $host . rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . '/reset_password.php?token=' . urlencode($token) . '&lang=' . urlencode($target_lang);

            // Invia email con il metodo configurato (SMTP autenticato o funzione mail)
            $email_data = get_password_reset_email_content($target_lang, $user['username'], $reset_link);
            $subject = $email_data['subject'];
            $message = $email_data['body'];

            $email_driver = get_setting($pdo, 'email_driver', 'php_mail');
            $sent_success = false;
            $send_details = '';

            if ($email_driver === 'smtp') {
                require_once __DIR__ . '/smtp_client.php';
                $smtp_host   = get_setting($pdo, 'smtp_host', '');
                $smtp_port   = (int)get_setting($pdo, 'smtp_port', '465');
                $smtp_enc    = get_setting($pdo, 'smtp_encryption', 'ssl');
                $smtp_user   = get_setting($pdo, 'smtp_user', '');
                $smtp_pass   = get_setting($pdo, 'smtp_pass', '');
                $from_email  = get_setting($pdo, 'smtp_from_email', '');
                $from_name   = get_setting($pdo, 'smtp_from_name', 'NewFoobot Air Monitor');

                if (empty($from_email)) {
                    $clean_h = preg_replace('/:[0-9]+$/', '', $host);
                    $from_email = !empty($smtp_user) && filter_var($smtp_user, FILTER_VALIDATE_EMAIL) ? $smtp_user : ('noreply@' . $clean_h);
                }

                $client = new FoobotSMTPClient($smtp_host, $smtp_port, $smtp_enc, $smtp_user, $smtp_pass, 15);
                $res = $client->send($from_email, $from_name, $email, $subject, $message);
                $sent_success = !empty($res['status']);
                $send_details = $res['message'] ?? 'SMTP sent';
            } else {
                $headers = "From: noreply@" . $host . "\r\n" .
                           "Reply-To: noreply@" . $host . "\r\n" .
                           "Content-Type: text/plain; charset=UTF-8\r\n" .
                           "X-Mailer: PHP/" . phpversion();
                $sent_success = @mail($email, $subject, $message, $headers);
                $send_details = $sent_success ? 'php_mail sent' : 'php_mail failed';
            }

            // Registra nel log storico email
            try {
                $ins_log = $pdo->prepare("INSERT INTO foobot_email_logs (recipient, subject, email_type, status, details, timestamp) VALUES (?, ?, 'password_reset', ?, ?, ?)");
                $ins_log->execute([$email, $subject, $sent_success ? 'sent' : 'failed', "Password reset ({$target_lang}) - {$send_details}", time()]);
            } catch (Exception $e) {}
        }
    }
}

$csrf_token = get_csrf_token();
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($current_lang); ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars(__('forgot_page_title')); ?> - NewFoobot</title>
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
      <h1><?php echo htmlspecialchars(__('forgot_page_title')); ?></h1>
      <p class="card-subtext"><?php echo htmlspecialchars(__('forgot_subtitle')); ?></p>
    </div>

    <?php if (!empty($msg)): ?>
      <div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

      <div class="form-group">
        <label for="email"><?php echo htmlspecialchars(__('forgot_email_label')); ?></label>
        <input type="email" id="email" name="email" placeholder="<?php echo htmlspecialchars(__('forgot_email_ph')); ?>" required autofocus>
        <div class="form-help"><?php echo htmlspecialchars(__('forgot_email_help')); ?></div>
      </div>

      <button type="submit" class="btn-submit"><?php echo htmlspecialchars(__('forgot_btn_submit')); ?></button>
    </form>

    <div style="margin-top: 20px; text-align: center;">
      <a href="login.php"><?php echo htmlspecialchars(__('forgot_back_to_login')); ?></a>
    </div>
  </div>
</body>
</html>
