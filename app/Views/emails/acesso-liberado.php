<?php
/**
 * Template: access granted after a confirmed payment.
 * Available variables: $name, $courseTitle, $actionUrl, $needsPassword,
 * $e (HTML escaper) and $subject (set here).
 *
 * The body copy is intentionally left in Portuguese: it is customer-facing
 * text for a Brazilian audience.
 */
declare(strict_types=1);

$subject = 'Seu acesso foi liberado 🎉';
$first = $name !== '' ? ' ' . $e(explode(' ', trim($name))[0]) : '';
?>
<h1 style="margin:0 0 12px;font-size:20px;color:#111827">Pagamento confirmado<?= $first ?>! 🎉</h1>
<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#374151">
  Seu acesso ao curso <b><?= $e($courseTitle) ?></b> foi liberado. Tudo pronto para você começar a estudar.
</p>
<p style="margin:0 0 24px">
  <a href="<?= $e($actionUrl) ?>" style="display:inline-block;background:#238636;color:#fff;text-decoration:none;
     padding:13px 26px;border-radius:10px;font-size:15px;font-weight:bold">
     <?= $needsPassword ? 'Criar senha e acessar' : 'Acessar minhas aulas' ?>
  </a>
</p>
<?php if ($needsPassword): ?>
<p style="margin:0;font-size:13px;color:#9ca3af">Como é seu primeiro acesso, você definirá uma senha. O link expira em 24 horas.</p>
<?php else: ?>
<p style="margin:0;font-size:13px;color:#9ca3af">Use o e-mail e a senha que você já cadastrou para entrar.</p>
<?php endif; ?>
