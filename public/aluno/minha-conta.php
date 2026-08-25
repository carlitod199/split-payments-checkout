<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
$pageTitle = 'Minha conta — Área de Aulas';
$active = 'conta';

require __DIR__ . '/inc/head.php';
?>
<body>
<?php require __DIR__ . '/inc/topbar.php'; ?>

<main class="wrap" style="max-width:720px;">

  <div class="greet">
    <h1 class="greet__hi">Minha conta</h1>
    <p class="greet__sub">Seus dados de acesso à plataforma.</p>
  </div>

  <div class="panelcard" style="margin-top:8px;">
    <div class="field">
      <label for="f-nome">Nome</label>
      <input id="f-nome" type="text" value="<?= e($student['name']) ?>" disabled>
    </div>
    <div class="field">
      <label for="f-email">E-mail de acesso</label>
      <input id="f-email" type="email" value="<?= e($student['email']) ?>" disabled>
    </div>
    <div class="field">
      <label for="f-fone">Telefone</label>
      <input id="f-fone" type="text" value="<?= e($student['phone']) ?>" disabled>
    </div>

    <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:8px;">
      <a class="btn btn--ghost" href="../recuperar-senha.php"><i class="ti ti-key" aria-hidden="true"></i> Trocar senha</a>
      <a class="btn btn--ghost" href="../logout.php"><i class="ti ti-logout" aria-hidden="true"></i> Sair</a>
    </div>
  </div>


</main>

<footer class="site">
  <div class="wrap">Área de Aulas · acesso exclusivo para alunos matriculados.</div>
</footer>
</body>
</html>
