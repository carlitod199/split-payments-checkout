<?php
declare(strict_types=1);

namespace App\Services\Mail;

use App\Support\Logger;
use Throwable;

/**
 * E-mail facade: picks the driver (log|smtp), renders the templates in
 * app/Views/emails and exposes high-level send methods. A delivery failure
 * NEVER propagates an exception to the caller (the webhook, for instance);
 * it is logged and false is returned instead.
 */
final class MailService
{
    private Mailer $driver;
    private array $mail;

    public function __construct(private array $config)
    {
        $this->mail = $config['mail'] ?? [];
        $driver = strtolower((string) ($this->mail['driver'] ?? 'log'));
        $this->driver = $driver === 'smtp' ? new SmtpMailer($this->mail) : new LogMailer($this->mail);
    }

    public function send(Message $message): bool
    {
        try {
            return $this->driver->send($message);
        } catch (Throwable $e) {
            Logger::log('mail', 'Failed to send e-mail', [
                'to'      => $message->to,
                'subject' => $message->subject,
                'error'   => $e->getMessage(),
            ]);
            return false;
        }
    }

    /** First-access e-mail (create your password). */
    public function sendPasswordSetup(string $toEmail, string $toName, string $token): bool
    {
        $link = $this->url('definir-senha.php?token=' . urlencode($token));
        [$subject, $html] = $this->render('definir-senha', [
            'name' => $toName,
            'link' => $link,
        ]);
        return $this->send(new Message($toEmail, $subject, $html, $toName));
    }

    /** Access-granted e-mail, sent once a payment is confirmed. */
    public function sendAccessGranted(string $toEmail, string $toName, string $courseTitle, bool $needsPassword, string $token = ''): bool
    {
        $action = $needsPassword
            ? $this->url('definir-senha.php?token=' . urlencode($token))
            : $this->url('login.php');
        [$subject, $html] = $this->render('acesso-liberado', [
            'name'          => $toName,
            'courseTitle'   => $courseTitle,
            'actionUrl'     => $action,
            'needsPassword' => $needsPassword,
        ]);
        return $this->send(new Message($toEmail, $subject, $html, $toName));
    }

    /**
     * Renders an e-mail template. The template file sets $subject and echoes
     * the body.
     * @return array{0:string,1:string} [subject, html]
     */
    private function render(string $template, array $vars): array
    {
        $file = dirname(__DIR__, 2) . '/Views/emails/' . $template . '.php';
        $subject = '';
        $e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        extract($vars, EXTR_SKIP);
        ob_start();
        include $file;
        $body = (string) ob_get_clean();

        return [$subject, $this->layout($subject, $body)];
    }

    private function layout(string $title, string $body): string
    {
        $brand = htmlspecialchars((string) ($this->mail['from_name'] ?? 'Área de Aulas'), ENT_QUOTES, 'UTF-8');
        return '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1"></head>'
            . '<body style="margin:0;background:#f4f5f7;font-family:Arial,Helvetica,sans-serif;color:#1f2937">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f7;padding:28px 0">'
            . '<tr><td align="center">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e5e7eb">'
            . '<tr><td style="background:#0d1117;padding:18px 28px;color:#fff;font-size:16px;font-weight:bold">▶ ' . $brand . '</td></tr>'
            . '<tr><td style="padding:28px">' . $body . '</td></tr>'
            . '<tr><td style="padding:16px 28px;background:#fafafa;border-top:1px solid #eee;color:#9ca3af;font-size:12px">'
            . 'Você recebeu este e-mail porque há uma conta vinculada a ele. Se não reconhece, ignore.</td></tr>'
            . '</table></td></tr></table></body></html>';
    }

    private function url(string $path): string
    {
        $base = rtrim((string) ($this->mail['app_url'] ?? $this->config['app_url'] ?? ''), '/');
        return $base . '/' . ltrim($path, '/');
    }
}
