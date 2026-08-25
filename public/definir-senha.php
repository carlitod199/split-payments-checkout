<?php
declare(strict_types=1);

use App\Controllers\AuthController;
use App\Support\Csrf;
use App\Support\Database;

$config = require __DIR__ . '/../config/payment.php';
Database::init($config['db']);

$view = (new AuthController($config))->handleSetPassword();
$csrf = Csrf::token();
function e(?string $v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Criar senha — Área de Aulas</title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box}
body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;
  font-family:"IBM Plex Sans",system-ui,sans-serif;background:#0d1117;color:#e6edf3;padding:20px}
.card{width:100%;max-width:380px;background:#161b22;border:1px solid #21262d;border-radius:16px;padding:34px}
h1{font-family:"Sora";font-size:20px;margin:0 0 4px}
.sub{color:#8b949e;font-size:13.5px;margin:0 0 22px}
label{display:block;font-size:13px;color:#c9d1d9;margin:14px 0 6px}
input{width:100%;padding:12px;border:1px solid #30363d;border-radius:10px;background:#0d1117;color:#e6edf3;font:inherit}
input:focus{outline:none;border-color:#1f6feb}
button{width:100%;margin-top:22px;padding:13px;border:0;border-radius:10px;background:#238636;color:#fff;
  font-family:"Sora";font-weight:600;font-size:15px;cursor:pointer}
button:hover{background:#2ea043}
.err{background:#3d1418;border:1px solid #5c2126;color:#ff9da3;font-size:13.5px;padding:10px 12px;border-radius:9px;margin-bottom:6px}
.note{background:#172554;border:1px solid #1e3a8a;color:#9db5ff;font-size:13px;padding:12px;border-radius:9px}
.foot{margin-top:18px;text-align:center;font-size:13px}
.foot a{color:#58a6ff;text-decoration:none}
</style>
</head>
<body>
  <div class="card">
    <?php if (!$view['valid']): ?>
      <h1>Link inválido</h1>
      <p class="sub"><?= e($view['error']) ?></p>
      <div class="foot"><a href="login.php">Voltar para o login</a></div>
    <?php else: ?>
      <h1>Criar sua senha</h1>
      <p class="sub">Olá<?= $view['name'] !== '' ? ', ' . e(explode(' ', $view['name'])[0]) : '' ?>! Defina uma senha para acessar suas aulas.</p>

      <?php if ($view['error'] !== ''): ?>
        <div class="err"><?= e($view['error']) ?></div>
      <?php endif; ?>

      <form method="post" action="definir-senha.php">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="token" value="<?= e($view['token']) ?>">

        <label for="password">Nova senha</label>
        <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password" autofocus>

        <label for="password_confirm">Confirme a senha</label>
        <input id="password_confirm" name="password_confirm" type="password" required minlength="8" autocomplete="new-password">

        <button type="submit">Salvar e entrar</button>
      </form>
    <?php endif; ?>
  </div>
</body>
</html>
