<?php
declare(strict_types=1);

/** Página exibida ao aluno logado que ainda não tem matrícula ativa. */
function render_no_access(array $currentUser): void
{
    $first = e(explode(' ', trim($currentUser['name'] ?? ''))[0] ?? '');
    ?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Sem cursos — Área de Aulas</title>
<link rel="stylesheet" href="assets/student.css">
<style>
  body{display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
  .na{max-width:460px;text-align:center;padding:30px}
  .na__icon{font-size:42px;margin-bottom:10px}
  .na h1{font-family:"Sora",system-ui,sans-serif;font-size:22px;margin:0 0 10px}
  .na p{color:var(--text-dim,#9aa7b2);font-size:15px;line-height:1.6;margin:0 0 22px}
  .na a{display:inline-block;background:#1f6feb;color:#fff;text-decoration:none;padding:12px 22px;border-radius:10px;font-weight:600}
  .na a.ghost{background:transparent;border:1px solid #30363d;color:#c9d1d9;margin-left:8px}
</style>
</head>
<body>
  <div class="na">
    <div class="na__icon">🎓</div>
    <h1>Olá<?= $first !== '' ? ', ' . $first : '' ?>!</h1>
    <p>Você ainda não tem nenhum curso liberado nesta conta. Assim que uma compra for confirmada
       (ou um acesso for liberado), seus cursos aparecem aqui.</p>
    <a href="../checkout.php?p=curso-demo">Ver oferta</a>
    <a class="ghost" href="../logout.php">Sair</a>
  </div>
</body>
</html>
    <?php
}
