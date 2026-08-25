<?php
declare(strict_types=1);

namespace App\Services\Mail;

use RuntimeException;

/**
 * Minimal socket-based SMTP client (no Composer dependencies).
 * Supports SMTPS (ssl), STARTTLS (tls) and AUTH LOGIN.
 * Sends multipart/alternative messages (plain text + HTML).
 */
final class SmtpMailer implements Mailer
{
    /** @var resource|null */
    private $fp = null;

    public function __construct(private array $cfg) {}

    public function send(Message $message): bool
    {
        $host   = (string) ($this->cfg['smtp_host'] ?? '');
        $port   = (int) ($this->cfg['smtp_port'] ?? 587);
        $secure = strtolower((string) ($this->cfg['smtp_secure'] ?? 'tls')); // tls|ssl|''
        $user   = (string) ($this->cfg['smtp_user'] ?? '');
        $pass   = (string) ($this->cfg['smtp_pass'] ?? '');
        $from   = (string) ($this->cfg['from'] ?? '');
        $fromNm = (string) ($this->cfg['from_name'] ?? '');

        if ($host === '' || $from === '') {
            throw new RuntimeException('SMTP is not configured (SMTP_HOST/MAIL_FROM).');
        }

        $remote = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $this->fp = @stream_socket_client($remote, $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $ctx);
        if (!$this->fp) {
            throw new RuntimeException("Could not connect to the SMTP server: $errstr ($errno)");
        }
        stream_set_timeout($this->fp, 30);

        try {
            $this->expect(220);
            $ehloHost = $this->ehloName();
            $this->cmd("EHLO $ehloHost", 250);

            if ($secure === 'tls') {
                $this->cmd('STARTTLS', 220);
                if (!stream_socket_enable_crypto($this->fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('Could not start TLS (STARTTLS).');
                }
                $this->cmd("EHLO $ehloHost", 250);
            }

            if ($user !== '') {
                $this->cmd('AUTH LOGIN', 334);
                $this->cmd(base64_encode($user), 334);
                $this->cmd(base64_encode($pass), 235);
            }

            $this->cmd('MAIL FROM:<' . $from . '>', 250);
            $this->cmd('RCPT TO:<' . $message->to . '>', 250);
            $this->cmd('DATA', 354);
            $this->raw($this->buildData($message, $from, $fromNm) . "\r\n.");
            $this->expect(250);
            $this->cmd('QUIT', 221);
        } finally {
            if (is_resource($this->fp)) {
                fclose($this->fp);
            }
        }

        return true;
    }

    private function buildData(Message $m, string $from, string $fromName): string
    {
        $boundary = 'b' . bin2hex(random_bytes(12));
        $encName  = $fromName !== '' ? '=?UTF-8?B?' . base64_encode($fromName) . '?= ' : '';
        $encSubj  = '=?UTF-8?B?' . base64_encode($m->subject) . '?=';

        $headers = [
            'Date: ' . date('r'),
            'From: ' . $encName . '<' . $from . '>',
            'To: ' . ($m->toName !== '' ? '=?UTF-8?B?' . base64_encode($m->toName) . '?= ' : '') . '<' . $m->to . '>',
            'Subject: ' . $encSubj,
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];

        $body  = '--' . $boundary . "\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($m->text)) . "\r\n";
        $body .= '--' . $boundary . "\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($m->html)) . "\r\n";
        $body .= '--' . $boundary . "--\r\n";

        // dot-stuffing: lines starting with "." get an extra "."
        $data = implode("\r\n", $headers) . "\r\n\r\n" . $body;
        return preg_replace('/^\./m', '..', $data);
    }

    private function ehloName(): string
    {
        $h = $_SERVER['SERVER_NAME'] ?? gethostname() ?: 'localhost';
        return preg_match('/^[a-z0-9.\-]+$/i', (string) $h) ? (string) $h : 'localhost';
    }

    private function cmd(string $command, int $expectedCode): void
    {
        $this->raw($command);
        $this->expect($expectedCode);
    }

    private function raw(string $line): void
    {
        fwrite($this->fp, $line . "\r\n");
    }

    private function expect(int $code): void
    {
        $response = '';
        while (($line = fgets($this->fp, 515)) !== false) {
            $response .= $line;
            // multi-line reply: "250-..." continues, "250 ..." terminates
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        $got = (int) substr($response, 0, 3);
        if ($got !== $code) {
            throw new RuntimeException("SMTP expected $code but received: " . trim($response));
        }
    }
}
