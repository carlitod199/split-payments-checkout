<?php
/** Topbar compartilhada. Requer $student. */
declare(strict_types=1);
$active = $active ?? 'inicio';
?>
<header class="topbar">
  <div class="wrap topbar__in">
    <a class="brand" href="index.php">
      <div class="brand__mark"><i class="ti ti-player-play-filled" aria-hidden="true"></i></div>
      <div>Área de Aulas</div>
    </a>

    <nav class="topbar__nav" aria-label="Navegação principal">
      <a class="navlink <?= $active === 'inicio' ? 'navlink--active' : '' ?>" href="index.php">Início</a>
      <a class="navlink navlink--account <?= $active === 'conta' ? 'navlink--active' : '' ?>" href="minha-conta.php">Minha conta</a>
      <a class="navlink" href="../logout.php">Sair</a>
      <div class="avatar" title="<?= e($student['name']) ?>"><?= e(initials($student['name'])) ?></div>
    </nav>
  </div>
</header>
