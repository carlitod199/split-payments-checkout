<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
$pageTitle = 'Início — Área de Aulas';
$active = 'inicio';

$firstName = explode(' ', trim($student['name']))[0];
$contPct = progress_pct($continueLesson['watched_seconds'], $continueLesson['duration_seconds']);

// "Continuar assistindo": aulas com progresso parcial (>0 e <100)
$watching = array_values(array_filter($lessons, function ($l) {
    return $l['watched_seconds'] > 0 && $l['watched_seconds'] < $l['duration_seconds'];
}));

require __DIR__ . '/inc/head.php';
?>
<body>
<?php require __DIR__ . '/inc/topbar.php'; ?>

<main class="wrap">

  <div class="greet">
    <h1 class="greet__hi">Olá, <?= e($firstName) ?> 👋</h1>
    <p class="greet__sub">Bom te ver de volta. Você está a <b><?= 100 - $course['progress'] ?>%</b> de concluir seu curso.</p>
  </div>

  <!-- HERO: continuar de onde parou -->
  <section class="hero" aria-label="Continuar curso">
    <?php if (!empty($course['banner_image'])): ?>
      <div class="hero__bg" style="background-image:url('<?= e($course['banner_image']) ?>')"></div>
    <?php endif; ?>
    <div class="hero__scrim"></div>
    <div class="hero__body">
      <div class="eyebrow"><i class="ti ti-bookmark-filled" aria-hidden="true"></i> Continue de onde parou</div>
      <h2 class="hero__title"><?= e($course['title']) ?></h2>
      <div class="hero__meta">
        <div><b><?= $course['progress'] ?>%</b> concluído</div>
        <div><b><?= $course['lessons_done'] ?></b>/<?= $course['lessons_total'] ?> aulas</div>
        <div>Por <?= e($course['producer']) ?></div>
      </div>
      <div class="hero__actions">
        <a class="btn btn--primary" href="aula.php?id=<?= (int) $continueLesson['id'] ?>">
          <i class="ti ti-player-play-filled" aria-hidden="true"></i>
          <?= $contPct > 0 ? 'Retomar' : 'Começar' ?>: <?= e($continueLesson['title']) ?>
        </a>
        <a class="btn btn--ghost" href="curso.php?slug=<?= e($course['slug']) ?>">
          <i class="ti ti-list" aria-hidden="true"></i> Ver conteúdo
        </a>
      </div>
    </div>
  </section>

  <!-- CONTINUAR ASSISTINDO -->
  <?php if ($watching): ?>
  <section class="section" aria-label="Continuar assistindo">
    <div class="section__head">
      <h3 class="section__title">Continuar assistindo</h3>
    </div>
    <div class="rail">
      <?php foreach ($watching as $l): $p = progress_pct($l['watched_seconds'], $l['duration_seconds']); ?>
        <a class="card" href="aula.php?id=<?= (int) $l['id'] ?>">
          <div class="thumb">
            <div class="thumb__fallback"><i class="ti ti-photo" aria-hidden="true"></i></div>
            <div class="thumb__play"><i class="ti ti-player-play-filled" aria-hidden="true"></i></div>
            <div class="thumb__dur"><?= e(fmt_duration($l['duration_seconds'])) ?></div>
          </div>
          <div class="card__body">
            <div class="card__kicker"><?= e($l['module']) ?></div>
            <p class="card__title"><?= e($l['title']) ?></p>
            <div class="prog"><div class="prog__fill" style="width:<?= $p ?>%"></div></div>
            <div class="prog__label"><?= $p ?>% assistido</div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- MEUS CURSOS -->
  <section class="section" aria-label="Meus cursos">
    <div class="section__head">
      <h3 class="section__title">Meus cursos</h3>
    </div>
    <div class="rail">
      <a class="card card--course" href="curso.php?slug=<?= e($course['slug']) ?>">
        <div class="thumb thumb--course">
          <div class="thumb__fallback"><i class="ti ti-school" aria-hidden="true"></i></div>
          <div class="thumb__play"><i class="ti ti-player-play-filled" aria-hidden="true"></i></div>
        </div>
        <div class="card__body">
          <p class="card__title"><?= e($course['title']) ?></p>
          <div class="prog"><div class="prog__fill" style="width:<?= $course['progress'] ?>%"></div></div>
          <div class="prog__label"><?= $course['progress'] ?>% concluído</div>
        </div>
      </a>
    </div>
  </section>

  <!-- MÓDULOS -->
  <section class="section" aria-label="Módulos do curso">
    <div class="section__head">
      <h3 class="section__title">Módulos</h3>
      <a class="section__link" href="curso.php?slug=<?= e($course['slug']) ?>">Ver todos</a>
    </div>
    <div class="rail">
      <?php foreach ($modules as $m):
        $mLessons = array_values(array_filter($lessons, fn ($l) => $l['module_id'] === $m['id']));
        $count = count($mLessons);
      ?>
        <a class="card" href="curso.php?slug=<?= e($course['slug']) ?>#modulo-<?= (int) $m['id'] ?>">
          <div class="thumb">
            <div class="thumb__fallback"><i class="ti ti-stack-2" aria-hidden="true"></i></div>
          </div>
          <div class="card__body">
            <div class="card__kicker">Módulo <?= (int) $m['n'] ?></div>
            <p class="card__title"><?= e($m['title']) ?></p>
            <div class="prog__label" style="margin-top:8px;"><?= $count ?> <?= $count === 1 ? 'aula' : 'aulas' ?></div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

</main>

<footer class="site">
  <div class="wrap">Área de Aulas · acesso exclusivo para alunos matriculados.</div>
</footer>
</body>
</html>
