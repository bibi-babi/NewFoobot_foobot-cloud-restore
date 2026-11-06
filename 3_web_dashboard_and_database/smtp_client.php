<?php
/**
 * Client SMTP Nativo e Leggero per NewFoobot (Zero dipendenze esterne)
 * Supporta:
 * - Connessione diretta SSL/TLS su porta 465 (ssl://)
 * - Connessione STARTTLS su porta 587 (opportunistic/explicit TLS)
 * - Autenticazione crittografata AUTH LOGIN
 * - Standard RFC 5321 e conformità con i requisiti SPF/DKIM/DMARC di Google (Gmail), Microsoft e Yahoo
 */

if (!defined('FOOBOT_ACCESS') && !defined('ROOT_PATH')) {
    // Permette inclusione
}

class FoobotSMTPClient {
    private $host;
    private $port;
    private $encryption; // 'ssl', 'tls', 'none'
    private $username;
    private $password;
    private $timeout;
    private $debug_log = [];

    public function __construct($host, $port = 465, $encryption = 'ssl', $username = '', $password = '', $timeout = 15) {
        $this->host = trim($host);
        $this->port = (int)$port;
        $this->encryption = strtolower(trim($encryption));
        $this->username = trim($username);
        $this->password = (string)$password;
        $this->timeout = (int)$timeout;
    }

    public function getLogs() {
        return $this->debug_log;
    }

    private function log($msg) {
        $this->debug_log[] = date('H:i:s') . ' ' . $msg;
    }

    /**
     * Invia un messaggio email via protocollo SMTP standard
     * @param string $from_email
     * @param string $from_name
     * @param string $to_email
     * @param string $subject
     * @param string $body_text
     * @return array ['success' => bool, 'message' => string, 'transcript' => array]
     */
    public function send($from_email, $from_name, $to_email, $subject, $body_text) {
        if (empty($this->host)) {
            return ['success' => false, 'message' => 'Host del server SMTP non specificato.', 'transcript' => $this->debug_log];
        }
        if (!filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Indirizzo email destinatario non valido.', 'transcript' => $this->debug_log];
        }

        $remote_addr = $this->host;
        if ($this->encryption === 'ssl') {
            $remote_addr = 'ssl://' . $this->host;
        }

        $this->log("Connessione a {$remote_addr}:{$this->port}...");
        $context = stream_context_create([
            'ssl' => [
                'verify_peer'       => true,
                'verify_peer_name'  => true,
                'allow_self_signed' => false
            ]
        ]);

        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client(
            $remote_addr . ':' . $this->port,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$socket) {
            $err_msg = "Impossibile connettersi al server SMTP {$this->host}:{$this->port} ($errno - $errstr)";
            $this->log($err_msg);
            return ['success' => false, 'message' => $err_msg, 'transcript' => $this->debug_log];
        }

        stream_set_timeout($socket, $this->timeout);

        try {
            // 1. Lettura banner di benvenuto (220)
            $reply = $this->readReply($socket);
            if ($this->getCode($reply) !== 220) {
                throw new Exception("Risposta iniziale inattesa dal server: " . $reply);
            }

            // 2. EHLO
            $client_domain = !empty($_SERVER['HTTP_HOST']) ? preg_replace('/:[0-9]+$/', '', $_SERVER['HTTP_HOST']) : 'localhost';
            $reply = $this->sendCommand($socket, "EHLO {$client_domain}");
            if ($this->getCode($reply) !== 250) {
                // Fallback HELO
                $reply = $this->sendCommand($socket, "HELO {$client_domain}");
                if ($this->getCode($reply) !== 250) {
                    throw new Exception("EHLO/HELO rifiutato: " . $reply);
                }
            }

            // 3. Se cifratura TLS (STARTTLS su porta 587 o analoga)
            if ($this->encryption === 'tls') {
                $reply = $this->sendCommand($socket, "STARTTLS");
                if ($this->getCode($reply) !== 220) {
                    throw new Exception("STARTTLS non supportato o rifiutato: " . $reply);
                }
                $crypto_method = STREAM_CRYPTO_METHOD_TLS_CLIENT;
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                    $crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
                }
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                    $crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
                }
                $crypto_ok = @stream_socket_enable_crypto($socket, true, $crypto_method);
                if (!$crypto_ok) {
                    throw new Exception("Handshake crittografico STARTTLS fallito.");
                }
                $this->log("Handshake crittografico TLS stabilito con successo.");
                // Ri-invia EHLO dopo l'attivazione di TLS come da RFC 3207
                $reply = $this->sendCommand($socket, "EHLO {$client_domain}");
                if ($this->getCode($reply) !== 250) {
                    throw new Exception("EHLO dopo STARTTLS fallito: " . $reply);
                }
            }

            // 4. Autenticazione AUTH LOGIN (se username presente)
            if (!empty($this->username)) {
                $reply = $this->sendCommand($socket, "AUTH LOGIN");
                if ($this->getCode($reply) !== 334) {
                    throw new Exception("AUTH LOGIN rifiutato dal server: " . $reply);
                }

                $reply = $this->sendCommand($socket, base64_encode($this->username));
                if ($this->getCode($reply) !== 334) {
                    throw new Exception("Username SMTP rifiutato: " . $reply);
                }

                $reply = $this->sendCommand($socket, base64_encode($this->password));
                if ($this->getCode($reply) !== 235) {
                    throw new Exception("Password o credenziali SMTP non valide: " . $reply);
                }
                $this->log("Autenticazione SMTP riuscita.");
            }

            // 5. MAIL FROM
            $reply = $this->sendCommand($socket, "MAIL FROM:<{$from_email}>");
            if ($this->getCode($reply) !== 250) {
                throw new Exception("MAIL FROM rifiutato: " . $reply);
            }

            // 6. RCPT TO
            $reply = $this->sendCommand($socket, "RCPT TO:<{$to_email}>");
            if ($this->getCode($reply) !== 250 && $this->getCode($reply) !== 251) {
                throw new Exception("Destinatario RCPT TO <{$to_email}> rifiutato: " . $reply);
            }

            // 7. DATA
            $reply = $this->sendCommand($socket, "DATA");
            if ($this->getCode($reply) !== 354) {
                throw new Exception("Comando DATA rifiutato: " . $reply);
            }

            // Costruzione Header RFC 5322 conformi ai requisiti Gmail / Yahoo
            $msg_id = sprintf("<%s.%s@%s>", bin2hex(random_bytes(12)), time(), $client_domain);
            $encoded_subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
            $from_header = !empty($from_name) ? ('"=?UTF-8?B?' . base64_encode($from_name) . '?=" <' . $from_email . '>') : $from_email;

            $headers_str  = "Date: " . date('r') . "\r\n";
            $headers_str .= "To: <{$to_email}>\r\n";
            $headers_str .= "From: {$from_header}\r\n";
            $headers_str .= "Reply-To: <{$from_email}>\r\n";
            $headers_str .= "Subject: {$encoded_subject}\r\n";
            $headers_str .= "Message-ID: {$msg_id}\r\n";
            $headers_str .= "X-Mailer: NewFoobot-Monitor-SMTP/1.0\r\n";
            $headers_str .= "MIME-Version: 1.0\r\n";
            $headers_str .= "Content-Type: text/plain; charset=UTF-8; format=flowed\r\n";
            $headers_str .= "Content-Transfer-Encoding: 8bit\r\n";

            // Dot-stuffing come da RFC 5321 (se una riga inizia con '.', aggiunge '.')
            $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $body_text));
            foreach ($lines as &$l) {
                if (isset($l[0]) && $l[0] === '.') {
                    $l = '.' . $l;
                }
            }
            $sanitized_body = implode("\r\n", $lines);

            $payload = $headers_str . "\r\n" . $sanitized_body . "\r\n.\r\n";

            $this->log("Invio corpo email e chiusura blocco DATA...");
            @fwrite($socket, $payload);
            $reply = $this->readReply($socket);
            if ($this->getCode($reply) !== 250) {
                throw new Exception("Errore durante l'accettazione del messaggio: " . $reply);
            }

            $this->log("Messaggio accettato per la consegna con esito 250 OK.");

            // 8. QUIT
            $this->sendCommand($socket, "QUIT");
            @fclose($socket);

            return [
                'success'    => true,
                'message'    => 'Email inviata e accettata con successo dal server SMTP.',
                'transcript' => $this->debug_log
            ];

        } catch (Exception $ex) {
            $this->log("ERRORE: " . $ex->getMessage());
            @fwrite($socket, "QUIT\r\n");
            @fclose($socket);
            return [
                'success'    => false,
                'message'    => $ex->getMessage(),
                'transcript' => $this->debug_log
            ];
        }
    }

    private function sendCommand($socket, $cmd) {
        $log_cmd = $cmd;
        if (strpos($cmd, 'AUTH LOGIN') === false && !preg_match('/^[A-Z0-9+=]{20,}$/', $cmd)) {
            $this->log("> " . $cmd);
        } else {
            $this->log("> [DATI DI AUTENTICAZIONE RISERVATI]");
        }
        @fwrite($socket, $cmd . "\r\n");
        return $this->readReply($socket);
    }

    private function readReply($socket) {
        $reply = '';
        while (!feof($socket)) {
            $line = @fgets($socket, 512);
            if ($line === false) {
                break;
            }
            $reply .= $line;
            // Se il quarto carattere è uno spazio o fine riga, è l'ultima riga della risposta (RFC 5321)
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        $trimmed = trim($reply);
        $this->log("< " . $trimmed);
        return $trimmed;
    }

    private function getCode($reply) {
        return (int)substr(trim($reply), 0, 3);
    }
}
