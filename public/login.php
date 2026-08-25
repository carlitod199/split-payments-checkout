<?php
declare(strict_types=1);

use App\Controllers\AuthController;
use App\Support\Csrf;
use App\Support\Database;

$config = require __DIR__ . '/../config/payment.php';
Database::init($config['db']);

$view = (new AuthController($config))->handleLogin();
$csrf = Csrf::token();
function e(?string $v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Entrar — Área de Aulas</title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box}
body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;
  font-family:"IBM Plex Sans",system-ui,sans-serif;background:#0d1117;color:#e6edf3;padding:20px}
.card{width:100%;max-width:380px;background:#161b22;border:1px solid #21262d;border-radius:16px;padding:34px}
.brand{display:flex;align-items:center;gap:10px;margin-bottom:24px}
.brand__mark{width:38px;height:38px;border-radius:10px;background:#1f6feb;display:flex;align-items:center;justify-content:center;font-size:20px}
h1{font-family:"Sora";font-size:20px;margin:0 0 4px}
.sub{color:#8b949e;font-size:13.5px;margin:0 0 22px}
label{display:block;font-size:13px;color:#c9d1d9;margin:14px 0 6px}
input{width:100%;padding:12px;border:1px solid #30363d;border-radius:10px;background:#0d1117;color:#e6edf3;font:inherit}
input:focus{outline:none;border-color:#1f6feb}
button{width:100%;margin-top:22px;padding:13px;border:0;border-radius:10px;background:#1f6feb;color:#fff;
  font-family:"Sora";font-weight:600;font-size:15px;cursor:pointer}
button:hover{background:#388bfd}
.err{background:#3d1418;border:1px solid #5c2126;color:#ff9da3;font-size:13.5px;padding:10px 12px;border-radius:9px;margin-bottom:6px}
.foot{margin-top:18px;text-align:center;font-size:13px}
.foot a{color:#58a6ff;text-decoration:none}
</style>
</head>
<body>
  <form class="card" method="post" action="login.php<?= $view['next'] !== '' ? '?next=' . urlencode($view['next']) : '' ?>">
    <div class="brand">
      <div class="brand__mark">▶</div>
      <div><b style="font-family:'Sora'">Área de Aulas</b></div>
    </div>
    <h1>Entrar</h1>
    <p class="sub">Acesse com o e-mail usado na compra.</p>

    <?php if ($view['error'] !== ''): ?>
      <div class="err"><?= e($view['error']) ?></div>
    <?php endif; ?>

    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

    <label for="email">E-mail</label>
    <input id="email" name="email" type="email" required autocomplete="username" autofocus
           value="<?= e($_POST['email'] ?? '') ?>">

    <label for="password">Senha</label>
    <input id="password" name="password" type="password" required autocomplete="current-password">

    <button type="submit">Entrar</button>

    <div class="foot">Primeiro acesso? Use o link de criação de senha enviado por e-mail.</div>
  </form>
</body>
</html>
