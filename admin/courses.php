<?php
declare(strict_types=1);

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductCourse;
use App\Support\Csrf;

require __DIR__ . '/inc/admin.php';

if (!$isAdmin) {
    admin_login_screen($loginError, $csrf);
    exit;
}

// ---------------- POST (com CSRF + PRG) ----------------
function redirect_back(string $msg, ?int $courseId = null): void
{
    $q = $courseId ? "courses.php?id={$courseId}&ok=" : 'courses.php?ok=';
    header('Location: ' . $q . urlencode($msg));
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!Csrf::check($_POST['_csrf'] ?? null)) {
        redirect_back('Sessão expirada, tente de novo.');
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'save_course') {
        $data = [
            'title'        => trim((string) ($_POST['title'] ?? '')),
            'slug'         => trim((string) ($_POST['slug'] ?? '')),
            'description'  => trim((string) ($_POST['description'] ?? '')),
            'cover_image'  => trim((string) ($_POST['cover_image'] ?? '')),
            'banner_image' => trim((string) ($_POST['banner_image'] ?? '')),
            'producer_id'  => (int) ($_POST['producer_id'] ?? 0),
            'status'       => (string) ($_POST['status'] ?? 'draft'),
        ];
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            Course::update($id, $data);
            redirect_back('Curso atualizado.', $id);
        }
        $newId = Course::create($data);
        redirect_back('Curso criado.', $newId);
    }

    if ($action === 'save_module') {
        $cid = (int) $_POST['course_id'];
        $mid = (int) ($_POST['module_id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        $desc = trim((string) ($_POST['description'] ?? ''));
        $ord = (int) ($_POST['order_index'] ?? 0);
        if ($mid > 0) { CourseModule::update($mid, $title, $desc, $ord); }
        else { CourseModule::create($cid, $title, $desc, $ord); }
        redirect_back('Módulo salvo.', $cid);
    }

    if ($action === 'delete_module') {
        CourseModule::delete((int) $_POST['module_id']);
        redirect_back('Módulo removido (e suas aulas).', (int) $_POST['course_id']);
    }

    if ($action === 'save_lesson') {
        $cid = (int) $_POST['course_id'];
        $lid = (int) ($_POST['lesson_id'] ?? 0);
        $data = [
            'course_id'        => $cid,
            'module_id'        => (int) ($_POST['module_id'] ?? 0),
            'title'            => trim((string) ($_POST['title'] ?? '')),
            'description'      => trim((string) ($_POST['description'] ?? '')),
            'video_url'        => trim((string) ($_POST['video_url'] ?? '')),
            'video_provider'   => (string) ($_POST['video_provider'] ?? 'other'),
            'duration_seconds' => (int) ($_POST['duration_minutes'] ?? 0) * 60 + (int) ($_POST['duration_secs'] ?? 0),
            'order_index'      => (int) ($_POST['order_index'] ?? 0),
            'status'           => (string) ($_POST['status'] ?? 'published'),
        ];
        if ($lid > 0) { Lesson::update($lid, $data); }
        else { Lesson::create($data); }
        redirect_back('Aula salva.', $cid);
    }

    if ($action === 'delete_lesson') {
        Lesson::delete((int) $_POST['lesson_id']);
        redirect_back('Aula removida.', (int) $_POST['course_id']);
    }

    if ($action === 'link_product') {
        ProductCourse::link((int) $_POST['product_id'], (int) $_POST['course_id']);
        redirect_back('Produto vinculado.', (int) $_POST['course_id']);
    }

    if ($action === 'unlink_product') {
        ProductCourse::unlink((int) $_POST['product_id'], (int) $_POST['course_id']);
        redirect_back('Produto desvinculado.', (int) $_POST['course_id']);
    }

    redirect_back('Ação desconhecida.');
}

// ---------------- VIEW ----------------
$flash = isset($_GET['ok']) ? (string) $_GET['ok'] : '';
$editId = (int) ($_GET['id'] ?? 0);
$course = $editId > 0 ? Course::find($editId) : null;

admin_layout_start('Admin — Cursos', 'courses');
if ($flash !== '') { echo '<div class="ok">' . e($flash) . '</div>'; }

if ($course === null):
    // ===== LISTA + NOVO CURSO =====
    $courses = Course::all();
    $producers = Producer::all();
?>
  <h1 class="page">Cursos</h1>
  <table>
    <tr><th>Curso</th><th>Slug</th><th>Produtor</th><th>Aulas</th><th>Status</th><th></th></tr>
    <?php foreach ($courses as $c): ?>
      <tr>
        <td><?= e($c['title']) ?></td>
        <td class="mono"><?= e($c['slug']) ?></td>
        <td><?= e($c['producer_name']) ?></td>
        <td class="mono"><?= (int) $c['lessons_count'] ?></td>
        <td><span class="pill"><?= e($c['status']) ?></span></td>
        <td><a class="btn btn--ghost btn-sm" href="courses.php?id=<?= (int) $c['id'] ?>">Editar</a></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$courses): ?><tr><td colspan="6" class="empty">Nenhum curso. Crie o primeiro abaixo.</td></tr><?php endif; ?>
  </table>

  <div class="card" style="margin-top:20px">
    <h2>Novo curso</h2>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <input type="hidden" name="action" value="save_course">
      <div class="row">
        <div><label>Título</label><input type="text" name="title" required></div>
        <div><label>Slug (URL)</label><input type="text" name="slug" required placeholder="meu-curso"></div>
      </div>
      <div class="row">
        <div><label>Produtor</label><select name="producer_id" required>
          <?php foreach ($producers as $p): ?><option value="<?= (int) $p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?>
        </select></div>
        <div><label>Status</label><select name="status">
          <option value="draft">Rascunho</option><option value="published">Publicado</option><option value="archived">Arquivado</option>
        </select></div>
      </div>
      <label>Descrição</label><textarea name="description"></textarea>
      <div class="row">
        <div><label>Imagem capa (URL)</label><input type="text" name="cover_image"></div>
        <div><label>Banner (URL)</label><input type="text" name="banner_image"></div>
      </div>
      <div style="margin-top:14px"><button class="btn" type="submit">Criar curso</button></div>
    </form>
  </div>

<?php else:
    // ===== EDITAR CURSO =====
    $producers = Producer::all();
    $modules = CourseModule::forCourse($editId);
    $lessons = Lesson::forCourse($editId);
    $linkedProducts = ProductCourse::productsForCourse($editId);
    $linkedIds = array_column($linkedProducts, 'id');
    $allProducts = Product::all();
    $lessonsByModule = [];
    foreach ($lessons as $l) { $lessonsByModule[(int) $l['module_id']][] = $l; }
?>
  <p style="margin:0 0 14px"><a href="courses.php" style="color:#5B6B78;text-decoration:none">&larr; Voltar aos cursos</a></p>
  <h1 class="page">Editar: <?= e($course['title']) ?></h1>

  <div class="card">
    <h2>Dados do curso</h2>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <input type="hidden" name="action" value="save_course">
      <input type="hidden" name="id" value="<?= (int) $course['id'] ?>">
      <div class="row">
        <div><label>Título</label><input type="text" name="title" value="<?= e($course['title']) ?>" required></div>
        <div><label>Slug</label><input type="text" name="slug" value="<?= e($course['slug']) ?>" required></div>
      </div>
      <div class="row">
        <div><label>Produtor</label><select name="producer_id">
          <?php foreach ($producers as $p): ?>
            <option value="<?= (int) $p['id'] ?>" <?= (int)$p['id']===(int)$course['producer_id']?'selected':'' ?>><?= e($p['name']) ?></option>
          <?php endforeach; ?>
        </select></div>
        <div><label>Status</label><select name="status">
          <?php foreach (['draft'=>'Rascunho','published'=>'Publicado','archived'=>'Arquivado'] as $k=>$v): ?>
            <option value="<?= $k ?>" <?= $course['status']===$k?'selected':'' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select></div>
      </div>
      <label>Descrição</label><textarea name="description"><?= e($course['description']) ?></textarea>
      <div class="row">
        <div><label>Imagem capa (URL)</label><input type="text" name="cover_image" value="<?= e($course['cover_image']) ?>"></div>
        <div><label>Banner (URL)</label><input type="text" name="banner_image" value="<?= e($course['banner_image']) ?>"></div>
      </div>
      <div style="margin-top:14px"><button class="btn" type="submit">Salvar dados</button></div>
    </form>
  </div>

  <div class="card">
    <h2>Produtos que liberam este curso</h2>
    <div class="actions" style="margin-bottom:12px">
      <?php if ($linkedProducts): foreach ($linkedProducts as $lp): ?>
        <form method="post" style="display:inline">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <input type="hidden" name="action" value="unlink_product">
          <input type="hidden" name="course_id" value="<?= $editId ?>">
          <input type="hidden" name="product_id" value="<?= (int) $lp['id'] ?>">
          <span class="pill"><?= e($lp['name']) ?> <button class="btn-sm" style="background:none;border:0;color:#C24B45;cursor:pointer" title="Desvincular">✕</button></span>
        </form>
      <?php endforeach; else: ?>
        <span style="color:#5B6B78;font-size:13px">Nenhum produto vinculado — compras não liberam este curso ainda.</span>
      <?php endif; ?>
    </div>
    <form method="post" class="actions">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <input type="hidden" name="action" value="link_product">
      <input type="hidden" name="course_id" value="<?= $editId ?>">
      <select name="product_id" style="max-width:320px">
        <?php foreach ($allProducts as $p): if (in_array((int)$p['id'],$linkedIds,true)) continue; ?>
          <option value="<?= (int) $p['id'] ?>"><?= e($p['name']) ?> (<?= e($p['checkout_slug']) ?>)</option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn--dark" type="submit">Vincular produto</button>
    </form>
  </div>

  <div class="card">
    <h2>Módulos &amp; aulas</h2>
    <?php foreach ($modules as $m): ?>
      <div style="border:1px solid #EFEADF;border-radius:10px;padding:14px;margin-bottom:14px">
        <div class="actions" style="justify-content:space-between">
          <b style="font-family:Sora"><?= e($m['title']) ?> <span class="pill">ordem <?= (int)$m['order_index'] ?></span></b>
          <div class="actions">
            <details><summary class="btn btn--ghost btn-sm" style="list-style:none">Editar módulo</summary>
              <form method="post" style="margin-top:10px">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="action" value="save_module">
                <input type="hidden" name="course_id" value="<?= $editId ?>">
                <input type="hidden" name="module_id" value="<?= (int)$m['id'] ?>">
                <div class="row"><div><label>Título</label><input type="text" name="title" value="<?= e($m['title']) ?>"></div>
                <div style="max-width:110px"><label>Ordem</label><input type="number" name="order_index" value="<?= (int)$m['order_index'] ?>"></div></div>
                <label>Descrição</label><textarea name="description"><?= e($m['description']) ?></textarea>
                <div style="margin-top:10px"><button class="btn btn-sm" type="submit">Salvar módulo</button></div>
              </form>
            </details>
            <form method="post" onsubmit="return confirm('Remover o módulo e TODAS as suas aulas?')">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
              <input type="hidden" name="action" value="delete_module">
              <input type="hidden" name="course_id" value="<?= $editId ?>">
              <input type="hidden" name="module_id" value="<?= (int)$m['id'] ?>">
              <button class="btn btn--danger btn-sm" type="submit">Excluir</button>
            </form>
          </div>
        </div>

        <table style="margin-top:12px">
          <tr><th>#</th><th>Aula</th><th>Provedor</th><th>Duração</th><th>Status</th><th></th></tr>
          <?php foreach (($lessonsByModule[(int)$m['id']] ?? []) as $l): ?>
            <tr>
              <td class="mono"><?= (int)$l['order_index'] ?></td>
              <td><?= e($l['title']) ?></td>
              <td><span class="pill"><?= e($l['video_provider']) ?></span></td>
              <td class="mono"><?= intdiv((int)$l['duration_seconds'],60) ?>m<?= (int)$l['duration_seconds']%60 ?>s</td>
              <td><span class="pill"><?= e($l['status']) ?></span></td>
              <td class="actions">
                <details><summary class="btn btn--ghost btn-sm" style="list-style:none">Editar</summary>
                  <?php require __DIR__ . '/inc/_lesson_form.php'; lesson_form($csrf, $editId, $modules, $l); ?>
                </details>
                <form method="post" onsubmit="return confirm('Remover esta aula?')">
                  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                  <input type="hidden" name="action" value="delete_lesson">
                  <input type="hidden" name="course_id" value="<?= $editId ?>">
                  <input type="hidden" name="lesson_id" value="<?= (int)$l['id'] ?>">
                  <button class="btn btn--danger btn-sm" type="submit">Excluir</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($lessonsByModule[(int)$m['id']])): ?><tr><td colspan="6" class="empty">Sem aulas neste módulo.</td></tr><?php endif; ?>
        </table>

        <details style="margin-top:10px"><summary class="btn btn-sm" style="list-style:none">+ Nova aula neste módulo</summary>
          <?php require_once __DIR__ . '/inc/_lesson_form.php'; lesson_form($csrf, $editId, $modules, null, (int)$m['id']); ?>
        </details>
      </div>
    <?php endforeach; ?>

    <details style="margin-top:8px"><summary class="btn btn--dark btn-sm" style="list-style:none">+ Novo módulo</summary>
      <form method="post" style="margin-top:12px">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="save_module">
        <input type="hidden" name="course_id" value="<?= $editId ?>">
        <div class="row"><div><label>Título</label><input type="text" name="title" required></div>
        <div style="max-width:110px"><label>Ordem</label><input type="number" name="order_index" value="<?= count($modules)+1 ?>"></div></div>
        <label>Descrição</label><textarea name="description"></textarea>
        <div style="margin-top:10px"><button class="btn" type="submit">Criar módulo</button></div>
      </form>
    </details>
  </div>
<?php
endif;
admin_layout_end();
