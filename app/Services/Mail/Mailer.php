<?php
declare(strict_types=1);

namespace App\Services\Mail;

/** Contract for an e-mail driver. */
interface Mailer
{
    public function send(Message $message): bool;
}
