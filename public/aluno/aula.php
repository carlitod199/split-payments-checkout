<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';

// Curso sem aulas publicadas: mostra estado vazio (evita player vazio/erro).
if ($lessons === []) {
    $pageTitle = $course['title'] . ' — Área de Aulas';
    $active = 'inicio';
    require __DIR__ . '/inc/head.php';
    ?>
    <body>
    <?php require __DIR__ . '/inc/topbar.php'; ?>
    <main class="wrap">
      <div class="notice" style="margin-top:28px;">
        <i class="ti ti-info-circle" aria-hidden="true"></i>
        <div>Este curso ainda não tem aulas publicadas. Volte em breve.</div>
      </div>
      <p style="margin-top:16px;"><a class="btn btn--ghost" href="curso.php?slug=<?= e($course['slug']) ?>"><i class="ti ti-arrow-left" aria-hidden="true"></i> Ver curso</a></p>
    </main>
    </body></html>
    <?php
    exit;
}

// Resolve aula pedida (?id=) dentro do conjunto liberado
$reqId = isset($_GET['id']) ? (int) $_GET['id'] : (int) $continueLesson['id'];
$ordered = $lessons;
usort($ordered, fn ($a, $b) => $a['n'] <=> $b['n']);

$idx = null;
foreach ($ordered as $i => $l) {
    if ($l['id'] === $reqId) { $idx = $i; break; }
}
if ($idx === null) { $idx = 0; }

$lesson = $ordered[$idx];
$prev = $ordered[$idx - 1] ?? null;
$next = $ordered[$idx + 1] ?? null;
$done = $lesson['watched_seconds'] >= $lesson['duration_seconds'] && $lesson['duration_seconds'] > 0;

$pageTitle = $lesson['title'] . ' — Área de Aulas';
$active = 'inicio';

require __DIR__ . '/inc/head.php';
?>
<body>
<?php require __DIR__ . '/inc/topbar.php'; ?>

<main class="wrap">

  <nav style="padding:18px 0 0;font-size:13px;color:var(--text-mute);" aria-label="Trilha">
    <a href="index.php" style="color:var(--text-dim);">Início</a> &nbsp;/&nbsp;
    <a href="curso.php?slug=<?= e($course['slug']) ?>" style="color:var(--text-dim);"><?= e($course['title']) ?></a>
    &nbsp;/&nbsp; <?= e($lesson['module']) ?>
  </nav>

  <div class="player-wrap">

    <!-- COLUNA PRINCIPAL -->
    <div>
      <div class="player"><?= video_embed($lesson) ?></div>

      <div class="lesson-head">
        <h1 class="lesson-head__title"><?= e($lesson['title']) ?></h1>
        <p class="lesson-head__desc"><?= e($lesson['description']) ?></p>
      </div>

      <div class="lesson-actions">
        <?php if ($prev): ?>
          <a class="btn btn--ghost" href="aula.php?id=<?= (int) $prev['id'] ?>"><i class="ti ti-chevron-left" aria-hidden="true"></i> Anterior</a>
        <?php endif; ?>

        <button class="btn <?= $done ? 'btn--emerald' : 'btn--primary' ?>" id="btn-complete" data-lesson="<?= (int) $lesson['id'] ?>" data-done="<?= $done ? '1' : '0' ?>" type="button">
          <i class="ti ti-circle-check" aria-hidden="true"></i>
          <b id="btn-complete-label"><?= $done ? 'Aula concluída' : 'Marcar como concluída' ?></b>
        </button>

        <?php if ($next): ?>
          <a class="btn btn--ghost" href="aula.php?id=<?= (int) $next['id'] ?>">Próxima aula <i class="ti ti-chevron-right" aria-hidden="true"></i></a>
        <?php else: ?>
          <a class="btn btn--ghost" href="curso.php?slug=<?= e($course['slug']) ?>"><i class="ti ti-flag-check" aria-hidden="true"></i> Voltar ao curso</a>
        <?php endif; ?>
      </div>

    </div>

    <!-- PLAYLIST LATERAL -->
    <aside class="playlist" aria-label="Aulas do curso">
      <div class="playlist__head">
        <div class="playlist__course">Conteúdo do curso</div>
        <div class="playlist__title"><?= e($course['title']) ?></div>
      </div>
      <div class="playlist__scroll">
        <?php foreach ($ordered as $l):
          $lDone = $l['watched_seconds'] >= $l['duration_seconds'] && $l['duration_seconds'] > 0;
          $isActive = $l['id'] === $lesson['id'];
        ?>
          <a class="pl-item <?= $isActive ? 'pl-item--active' : '' ?>" href="aula.php?id=<?= (int) $l['id'] ?>">
            <div class="pl-item__idx"><?= str_pad((string) $l['n'], 2, '0', STR_PAD_LEFT) ?></div>
            <div class="pl-item__t">
              <b><?= e($l['title']) ?></b>
              <i><?= e($l['module']) ?> · <?= e(fmt_duration($l['duration_seconds'])) ?></i>
            </div>
            <div class="pl-item__dot">
              <?php if ($lDone): ?><i class="ti ti-circle-check-filled" aria-hidden="true"></i>
              <?php elseif ($isActive): ?><i class="ti ti-player-play-filled" style="color:var(--amber);" aria-hidden="true"></i>
              <?php endif; ?>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </aside>

  </div>
</main>

<footer class="site">
  <div class="wrap">Área de Aulas · acesso exclusivo para alunos matriculados.</div>
</footer>

<script>
// Marcar concluída -> salva em lesson_progress via API.
(function(){
  var btn=document.getElementById('btn-complete'),
      label=document.getElementById('btn-complete-label');
  if(!btn) return;
  var csrf = (document.querySelector('meta[name="csrf-token"]')||{}).content || '';

  function paint(done){
    btn.setAttribute('data-done',done?'1':'0');
    btn.classList.toggle('btn--emerald',done);
    btn.classList.toggle('btn--primary',!done);
    label.textContent=done?'Aula concluída':'Marcar como concluída';
  }

  btn.addEventListener('click',function(){
    var done = btn.getAttribute('data-done')!=='1'; // novo estado
    btn.disabled = true;
    fetch('api/progress.php',{
      method:'POST',
      headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},
      body:JSON.stringify({lesson_id:parseInt(btn.dataset.lesson,10),completed:done})
    }).then(function(r){return r.json();})
      .then(function(j){ if(j && j.ok){ paint(j.completed); } })
      .catch(function(){ /* mantém estado atual em caso de erro */ })
      .finally(function(){ btn.disabled = false; });
  });
})();
</script>
</body>
</html>
