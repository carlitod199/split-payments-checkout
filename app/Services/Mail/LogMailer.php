<?php
declare(strict_types=1);

namespace App\Services\Mail;

/**
 * Development driver: nothing is actually sent. The rendered e-mail is
 * appended to storage/logs/mail.log for inspection, which makes it easy to
 * exercise the flow (for example the create-password link) without SMTP.
 */
final class LogMailer implements Mailer
{
    public function __construct(private array $cfg) {}

    public function send(Message $message): bool
    {
        $dir = dirname(__DIR__, 3) . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $from = ($this->cfg['from_name'] ?? '') . ' <' . ($this->cfg['from'] ?? '') . '>';
        $block = sprintf(
            "=== MAIL %s ===\nDe:      %s\nPara:    %s <%s>\nAssunto: %s\n--- TEXTO ---\n%s\n--- HTML ---\n%s\n=== FIM ===\n\n",
            date('Y-m-d H:i:s'),
            $from,
            $message->toName,
            $message->to,
            $message->subject,
            $message->text,
            $message->html
        );

        return @file_put_contents($dir . '/mail.log', $block, FILE_APPEND | LOCK_EX) !== false;
    }
}
