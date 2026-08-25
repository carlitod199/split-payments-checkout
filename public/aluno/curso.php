<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
$pageTitle = $course['title'] . ' — Área de Aulas';
$active = 'inicio';
$contPct = progress_pct($continueLesson['watched_seconds'], $continueLesson['duration_seconds']);

require __DIR__ . '/inc/head.php';
?>
<body>
<?php require __DIR__ . '/inc/topbar.php'; ?>

<main class="wrap">

  <nav style="padding:18px 0 0;font-size:13px;color:var(--text-mute);" aria-label="Trilha">
    <a href="index.php" style="color:var(--text-dim);">Início</a> &nbsp;/&nbsp; <?= e($course['title']) ?>
  </nav>

  <!-- BANNER -->
  <section class="hero coursebanner" aria-label="Curso">
    <?php if (!empty($course['banner_image'])): ?>
      <div class="hero__bg" style="background-image:url('<?= e($course['banner_image']) ?>')"></div>
    <?php endif; ?>
    <div class="hero__scrim"></div>
    <div class="hero__body">
      <div class="eyebrow"><i class="ti ti-certificate" aria-hidden="true"></i> Curso</div>
      <h1 class="hero__title"><?= e($course['title']) ?></h1>
      <p style="color:var(--text-dim);font-size:14.5px;max-width:60ch;margin:0 0 16px;"><?= e($course['description']) ?></p>
      <div class="hero__meta">
        <div><b><?= $course['progress'] ?>%</b> concluído</div>
        <div><b><?= $course['lessons_done'] ?></b>/<?= $course['lessons_total'] ?> aulas</div>
        <div><?= count($modules) ?> módulos</div>
      </div>
      <div style="max-width:420px;margin-bottom:18px;">
        <div class="prog"><div class="prog__fill" style="width:<?= $course['progress'] ?>%"></div></div>
      </div>
      <div class="hero__actions">
        <a class="btn btn--primary" href="aula.php?id=<?= (int) $continueLesson['id'] ?>">
          <i class="ti ti-player-play-filled" aria-hidden="true"></i>
          <?= $contPct > 0 ? 'Continuar de onde parei' : 'Começar o curso' ?>
        </a>
      </div>
    </div>
  </section>

  <!-- MÓDULOS + AULAS -->
  <section aria-label="Conteúdo do curso" style="margin-bottom:40px;">
    <?php foreach ($modules as $m):
      $mLessons = array_values(array_filter($lessons, fn ($l) => $l['module_id'] === $m['id']));
      usort($mLessons, fn ($a, $b) => $a['n'] <=> $b['n']);
    ?>
      <div class="module" id="modulo-<?= (int) $m['id'] ?>">
        <div class="module__head">
          <div class="module__idx"><?= str_pad((string) $m['n'], 2, '0', STR_PAD_LEFT) ?></div>
          <div>
            <h2 class="module__title"><?= e($m['title']) ?></h2>
            <p class="module__desc"><?= e($m['description']) ?></p>
          </div>
          <div class="module__count"><?= count($mLessons) ?> <?= count($mLessons) === 1 ? 'aula' : 'aulas' ?></div>
        </div>

        <?php if (!$mLessons): ?>
          <div class="lessonrow"><div class="lessonrow__main"><p class="lessonrow__sub">Em breve.</p></div></div>
        <?php endif; ?>

        <?php foreach ($mLessons as $l):
          $done = $l['watched_seconds'] >= $l['duration_seconds'] && $l['duration_seconds'] > 0;
          $isCurrent = $l['id'] === $continueLesson['id'];
          $state = $done ? 'done' : ($isCurrent ? 'current' : '');
        ?>
          <a class="lessonrow" href="aula.php?id=<?= (int) $l['id'] ?>">
            <div class="lessonrow__state lessonrow__state--<?= $state ?: 'idle' ?>">
              <?php if ($done): ?><i class="ti ti-check" aria-hidden="true"></i>
              <?php elseif ($isCurrent): ?><i class="ti ti-player-play-filled" aria-hidden="true"></i>
              <?php else: ?><?= (int) $l['n'] ?><?php endif; ?>
            </div>
            <div class="lessonrow__main">
              <p class="lessonrow__title"><?= e($l['title']) ?></p>
              <div class="lessonrow__sub">
                <?= $done ? 'Concluída' : ($isCurrent ? 'Continuar assistindo' : 'Não iniciada') ?>
              </div>
            </div>
            <div class="lessonrow__dur"><?= e(fmt_duration($l['duration_seconds'])) ?></div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </section>

</main>

<footer class="site">
  <div class="wrap">Área de Aulas · acesso exclusivo para alunos matriculados.</div>
</footer>
</body>
</html>
