<?php
/**
 * Template: password creation (first access).
 * Available variables: $name, $link, $e (HTML escaper) and $subject (set here).
 *
 * The body copy is intentionally left in Portuguese: it is customer-facing
 * text for a Brazilian audience.
 */
declare(strict_types=1);

$subject = 'Crie sua senha de acesso';
$first = $name !== '' ? ' ' . $e(explode(' ', trim($name))[0]) : '';
?>
<h1 style="margin:0 0 12px;font-size:20px;color:#111827">Bem-vindo<?= $first ?>! 👋</h1>
<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#374151">
  Seu acesso à área de aulas está quase pronto. Clique no botão abaixo para criar sua senha
  e entrar na plataforma.
</p>
<p style="margin:0 0 24px">
  <a href="<?= $e($link) ?>" style="display:inline-block;background:#1f6feb;color:#fff;text-decoration:none;
     padding:13px 26px;border-radius:10px;font-size:15px;font-weight:bold">Criar minha senha</a>
</p>
<p style="margin:0 0 6px;font-size:13px;color:#6b7280">Se o botão não funcionar, copie e cole este link no navegador:</p>
<p style="margin:0 0 16px;font-size:13px;word-break:break-all"><a href="<?= $e($link) ?>" style="color:#1f6feb"><?= $e($link) ?></a></p>
<p style="margin:0;font-size:13px;color:#9ca3af">Este link expira em 24 horas e só pode ser usado uma vez.</p>
