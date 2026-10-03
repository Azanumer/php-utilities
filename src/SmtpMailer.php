<?php
/**
 * SmtpMailer — send mail over SMTP without any dependency (no PHPMailer needed).
 *
 * Supports STARTTLS on port 587 and implicit TLS on port 465.
 *
 * Usage:
 *   $mail = new SmtpMailer('smtp.example.com', 587, 'user@example.com', 'secret');
 *   $mail->send('to@example.com', 'Subject', '<h1>Hi</h1>', 'noreply@example.com');
 */

class SmtpMailer
{
    private string $host;
    private int $port;
    private string $username;
    private string $password;
    private bool $implicitTls;
    private int $timeout;

    /** @var resource|null */
    private $socket = null;

    /**
     * @param string $host        SMTP hostname, e.g. smtp.gmail.com
     * @param int    $port        587 (STARTTLS) or 465 (implicit TLS)
     * @param string $username    SMTP username (usually the full email)
     * @param string $password    SMTP password / app password
     * @param bool   $implicitTls true for port 465, false for STARTTLS on 587
     * @param int    $timeout     Connection timeout in seconds
     */
    public function __construct(
        string $host,
        int $port,
        string $username,
        string $password,
        bool $implicitTls = false,
        int $timeout = 10
    ) {
        $this->host        = $host;
        $this->port        = $port;
        $this->username    = $username;
        $this->password    = $password;
        $this->implicitTls = $implicitTls;
        $this->timeout     = $timeout;
    }

    /**
     * Send one email.
     *
     * @param string $to      Recipient address
     * @param string $subject Subject line
     * @param string $body    HTML or plain-text body
     * @param string $from    Envelope sender / From address
     * @param bool   $isHtml  Wrap body as HTML email when true
     * @return bool true on acceptance by the server
     * @throws RuntimeException on connection or SMTP errors
     */
    public function send(string $to, string $subject, string $body, string $from, bool $isHtml = true): bool
    {
        $this->connect();

        try {
            $this->command("EHLO localhost", 250);

            // Upgrade to TLS on port 587 style connections.
            if (!$this->implicitTls) {
                $this->command("STARTTLS", 220);
                if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('STARTTLS negotiation failed');
                }
                $this->command("EHLO localhost", 250);
            }

            // AUTH LOGIN (base64 username/password in two steps).
            $this->command("AUTH LOGIN", 334);
            $this->command(base64_encode($this->username), 334);
            $this->command(base64_encode($this->password), 235);

            $this->command("MAIL FROM:<{$from}>", 250);
            $this->command("RCPT TO:<{$to}>", [250, 251]);
            $this->command("DATA", 354);

            $headers = [
                "From: {$from}",
                "To: {$to}",
                "Subject: " . $this->encodeSubject($subject),
                "MIME-Version: 1.0",
                "Content-Type: " . ($isHtml ? 'text/html' : 'text/plain') . '; charset=UTF-8',
                "Content-Transfer-Encoding: 8bit",
                "Date: " . date('r'),
            ];
            // End DATA with \r\n.\r\n; dot-stuff any leading dots in the body.
            $data = implode("\r\n", $headers) . "\r\n\r\n"
                  . preg_replace('/^\./m', '..', $body) . "\r\n.\r\n";
            fwrite($this->socket, $data);
            $this->expect([250]);

            $this->command("QUIT", 221);
        } finally {
            if (is_resource($this->socket)) {
                fclose($this->socket);
            }
            $this->socket = null;
        }

        return true;
    }

    /** Open the TCP (or TLS) connection and read the 220 greeting. */
    private function connect(): void
    {
        $scheme = $this->implicitTls ? 'tls://' : 'tcp://';
        $this->socket = @stream_socket_client(
            $scheme . $this->host . ':' . $this->port,
            $errno,
            $errstr,
            $this->timeout
        );
        if (!$this->socket) {
            throw new RuntimeException("SMTP connect failed: {$errstr} ({$errno})");
        }
        stream_set_timeout($this->socket, $this->timeout);
        $this->expect([220]);
    }

    /**
     * Send one SMTP command and assert the reply code.
     *
     * @param string     $cmd  Command line (without CRLF)
     * @param int|int[]  $want Expected reply code(s)
     */
    private function command(string $cmd, $want): void
    {
        fwrite($this->socket, $cmd . "\r\n");
        $this->expect((array) $want, $cmd);
    }

    /**
     * Read a full (possibly multi-line) SMTP reply and check its code.
     *
     * @param int[]  $want Expected codes
     * @param string $cmd  Command that triggered it (for error messages)
     * @throws RuntimeException on unexpected reply
     */
    private function expect(array $want, string $cmd = ''): void
    {
        $reply = '';
        // Multi-line replies end when the 4th char is a space instead of '-'.
        do {
            $line = fgets($this->socket, 1024);
            if ($line === false) {
                throw new RuntimeException('SMTP connection lost while reading reply');
            }
            $reply .= $line;
        } while (strlen($line) > 3 && $line[3] === '-');

        $code = (int) substr($reply, 0, 3);
        if (!in_array($code, $want, true)) {
            throw new RuntimeException(
                "SMTP error" . ($cmd !== '' ? " after '{$cmd}'" : '') . ": " . trim($reply)
            );
        }
    }

    /** RFC 2047-encode a non-ASCII subject line. */
    private function encodeSubject(string $subject): string
    {
        if (preg_match('/[^\x20-\x7E]/', $subject)) {
            return '=?UTF-8?B?' . base64_encode($subject) . '?=';
        }
        return $subject;
    }
}
