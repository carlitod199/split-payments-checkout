<?php
declare(strict_types=1);

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\PasswordResetToken;
use App\Models\Student;
use App\Models\User;
use App\Services\Mail\MailService;
use App\Support\Csrf;

require __DIR__ . '/inc/admin.php';

if (!$isAdmin) {
    admin_login_screen($loginError, $csrf);
    exit;
}

function students_redirect(string $msg): void
{
    header('Location: students.php?ok=' . urlencode($msg));
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!Csrf::check($_POST['_csrf'] ?? null)) {
        students_redirect('Sessão expirada, tente de novo.');
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'grant_access') {
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $name  = trim((string) ($_POST['name'] ?? ''));
        $courseId = (int) ($_POST['course_id'] ?? 0);
        $sendEmail = !empty($_POST['send_email']);

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $courseId <= 0) {
            students_redirect('E-mail ou curso inválido.');
        }
        $user = User::findByEmail($email);
        if ($user === null) {
            $uid = User::create($name !== '' ? $name : $email, $email, 'student');
            $user = User::findById($uid);
        }
        $uid = (int) $user['id'];
        Student::upsert($uid, null, null);
        Enrollment::grant($uid, $courseId, null); // payment_id NULL = liberação manual

        if ($sendEmail) {
            $course = Course::find($courseId);
            $needsPassword = empty($user['password_hash']);
            $token = $needsPassword ? PasswordResetToken::issue($uid, 24) : '';
            (new MailService($config))->sendAccessGranted($email, (string) $user['name'], (string) ($course['title'] ?? 'seu curso'), $needsPassword, $token);
        }
        students_redirect('Acesso liberado manualmente.' . ($sendEmail ? ' E-mail enviado.' : ''));
    }

    if ($action === 'resend_password') {
        $uid = (int) ($_POST['user_id'] ?? 0);
        $user = $uid > 0 ? User::findById($uid) : null;
        if ($user === null) {
            students_redirect('Usuário não encontrado.');
        }
        $token = PasswordResetToken::issue($uid, 24);
        (new MailService($config))->sendPasswordSetup((string) $user['email'], (string) $user['name'], $token);
        students_redirect('Link de criação de senha enviado para ' . $user['email'] . '.');
    }

    if ($action === 'set_enrollment_status') {
        Enrollment::setStatus((int) $_POST['enrollment_id'], (string) $_POST['status']);
        students_redirect('Matrícula atualizada.');
    }

    students_redirect('Ação desconhecida.');
}

$flash = isset($_GET['ok']) ? (string) $_GET['ok'] : '';
$users = User::listAll('student');
$enrollments = Enrollment::listAll();
$courses = Course::all();
$statusColors = ['active'=>'#0E9F6E','pending'=>'#E9A21B','cancelled'=>'#C24B45','expired'=>'#5B6B78'];

admin_layout_start('Admin — Alunos', 'students');
if ($flash !== '') { echo '<div class="ok">' . e($flash) . '</div>'; }
?>
  <h1 class="page">Alunos &amp; matrículas</h1>

  <div class="card">
    <h2>Liberar acesso manualmente</h2>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <input type="hidden" name="action" value="grant_access">
      <div class="row">
        <div><label>E-mail do aluno</label><input type="email" name="email" required placeholder="aluno@email.com"></div>
        <div><label>Nome (se for novo)</label><input type="text" name="name" placeholder="Nome do aluno"></div>
        <div><label>Curso</label><select name="course_id" required>
          <?php foreach ($courses as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['title']) ?></option><?php endforeach; ?>
        </select></div>
      </div>
      <label style="display:flex;align-items:center;gap:8px;margin-top:12px;font-size:14px;color:#2A3F4F">
        <input type="checkbox" name="send_email" value="1" checked style="width:auto"> Enviar e-mail de acesso (com link de senha no 1º acesso)
      </label>
      <div style="margin-top:12px"><button class="btn" type="submit">Liberar acesso</button></div>
    </form>
  </div>

  <div class="card">
    <h2>Alunos cadastrados</h2>
    <table>
      <tr><th>Nome</th><th>E-mail</th><th>Senha</th><th>Matrículas ativas</th><th>Último login</th><th></th></tr>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><?= e($u['name']) ?></td>
          <td class="mono"><?= e($u['email']) ?></td>
          <td><?= empty($u['password_hash']) ? '<span class="pill">pendente</span>' : '✓' ?></td>
          <td class="mono"><?= (int) $u['active_enrollments'] ?></td>
          <td class="mono" style="font-size:12px"><?= e($u['last_login_at'] ?? '—') ?></td>
          <td>
            <form method="post" onsubmit="return confirm('Enviar link de criação/redefinição de senha?')">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
              <input type="hidden" name="action" value="resend_password">
              <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
              <button class="btn btn--ghost btn-sm" type="submit">Enviar senha</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$users): ?><tr><td colspan="6" class="empty">Nenhum aluno ainda.</td></tr><?php endif; ?>
    </table>
  </div>

  <div class="card">
    <h2>Matrículas</h2>
    <table>
      <tr><th>Aluno</th><th>Curso</th><th>Origem</th><th>Status</th><th>Desde</th><th></th></tr>
      <?php foreach ($enrollments as $en): ?>
        <tr>
          <td><?= e($en['user_name']) ?><br><small style="color:#5B6B78"><?= e($en['user_email']) ?></small></td>
          <td><?= e($en['course_title']) ?></td>
          <td><?= $en['payment_id'] ? '<span class="pill">compra</span>' : '<span class="pill">manual</span>' ?></td>
          <td><b class="badge" style="background:<?= $statusColors[$en['status']] ?? '#5B6B78' ?>"><?= e($en['status']) ?></b></td>
          <td class="mono" style="font-size:12px"><?= e($en['created_at']) ?></td>
          <td class="actions">
            <?php $toggle = $en['status'] === 'active' ? 'cancelled' : 'active'; ?>
            <form method="post">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
              <input type="hidden" name="action" value="set_enrollment_status">
              <input type="hidden" name="enrollment_id" value="<?= (int) $en['id'] ?>">
              <input type="hidden" name="status" value="<?= $toggle ?>">
              <button class="btn btn-sm <?= $en['status']==='active' ? 'btn--danger' : '' ?>" type="submit">
                <?= $en['status']==='active' ? 'Cancelar' : 'Reativar' ?>
              </button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$enrollments): ?><tr><td colspan="6" class="empty">Nenhuma matrícula ainda.</td></tr><?php endif; ?>
    </table>
  </div>
<?php
admin_layout_end();
