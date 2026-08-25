<?php
declare(strict_types=1);

namespace App\Services\Mail;

/** An e-mail message (a plain DTO). */
final class Message
{
    public string $text;

    public function __construct(
        public string $to,
        public string $subject,
        public string $html,
        public string $toName = '',
        string $text = ''
    ) {
        // Plain-text fallback derived from the HTML (for clients without HTML support).
        $this->text = $text !== ''
            ? $text
            : trim(html_entity_decode(strip_tags(preg_replace('/<(br|\/p|\/div|\/h[1-6])\s*>/i', "\n", $html)), ENT_QUOTES, 'UTF-8'));
    }
}
