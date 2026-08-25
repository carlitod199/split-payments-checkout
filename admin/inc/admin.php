<?php
declare(strict_types=1);

/**
 * Bootstrap + guard compartilhado do painel admin.
 * Inclua no topo de cada página: require __DIR__ . '/inc/admin.php';
 * Depois use: if (!$isAdmin) { admin_login_screen($loginError, $csrf); exit; }
 * E envolva o conteúdo com admin_layout_start()/admin_layout_end().
 */

use App\Models\User;
use App\Services\Auth\AuthService;
use App\Support\Csrf;
use App\Support\Database;
use App\Support\Http;
use App\Support\RateLimiter;

$config = require dirname(__DIR__, 2) . '/config/payment.php';
Database::init($config['db']);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!function_exists('e')) {
    function e(?string $v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
}

// ---------------- LOGIN ----------------
$loginError = '';
if (($_POST['action'] ?? '') === 'admin_login') {
    if (!Csrf::check($_POST['_csrf'] ?? null)) {
        $loginError = 'Sessão expirada. Recarregue e tente novamente.';
    } elseif (RateLimiter::tooMany('admin_login:' . Http::clientIp(), $config['rate_limit']['max'], $config['rate_limit']['window'])) {
        $loginError = 'Muitas tentativas. Aguarde alguns instantes.';
    } else {
        $u = (string) ($_POST['user'] ?? '');
        $p = (string) ($_POST['pass'] ?? '');

        // 1) Credenciais do .env (ADMIN_USER/ADMIN_PASS_HASH).
        //    ADMIN_PASS_HASH guarda um hash bcrypt; vazio desativa este caminho.
        $envHash = (string) $config['admin']['pass_hash'];
        $envOk = $envHash !== ''
            && hash_equals($config['admin']['user'], $u)
            && password_verify($p, $envHash);

        // 2) Usuário role=admin no banco (login por e-mail + senha).
        $adminUser = null;
        if (!$envOk) {
            $cand = (new AuthService($config))->attempt($u, $p);
            if ($cand !== null && $cand['role'] === 'admin') {
                $adminUser = $cand;
            }
        }

        if ($envOk || $adminUser !== null) {
            $_SESSION['admin'] = true;
            $_SESSION['admin_name'] = $adminUser['name'] ?? $config['admin']['user'];
            if ($adminUser !== null) {
                (new AuthService($config))->completeLogin($adminUser);
            }
        } else {
            $loginError = 'Usuário ou senha inválidos.';
        }
    }
}
if (($_GET['logout'] ?? '') === '1') {
    unset($_SESSION['admin'], $_SESSION['admin_name']);
    header('Location: index.php');
    exit;
}

$isAdmin = !empty($_SESSION['admin']);
$csrf = Csrf::token();

// ---------------- LAYOUT ----------------
function admin_styles(): string
{
    return '<style>
*{box-sizing:border-box}body{margin:0;font-family:"IBM Plex Sans",system-ui,sans-serif;background:#F6F3EC;color:#10202E}
.top{background:#10202E;color:#fff;padding:14px 24px;display:flex;align-items:center;gap:18px}
.top b{font-family:"Sora"}.top .sp{margin-left:auto}.top a.out{color:#9fb4c0;text-decoration:none;font-size:14px}
.nav{display:flex;gap:6px}.nav a{color:#cdd9e1;text-decoration:none;font-size:14px;padding:6px 12px;border-radius:8px}
.nav a.on{background:#1d3547;color:#fff}
.wrap{max-width:1100px;margin:24px auto;padding:0 20px}
.tabs{display:flex;gap:8px;margin-bottom:18px;flex-wrap:wrap}
.tabs a{text-decoration:none;font-size:14px;padding:8px 14px;border-radius:9px;color:#2A3F4F;background:#fff;border:1px solid #E4DED1}
.tabs a.on{background:#10202E;color:#fff;border-color:#10202E}
table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #E4DED1;border-radius:12px;overflow:hidden;font-size:14px}
th,td{text-align:left;padding:11px 13px;border-bottom:1px solid #EFEADF;vertical-align:top}
th{font-size:12px;text-transform:uppercase;letter-spacing:.06em;color:#5B6B78;background:#FCFBF8}
td.mono{font-family:"IBM Plex Mono";font-size:13px}
.badge{display:inline-block;padding:3px 9px;border-radius:20px;font-size:12px;font-weight:600;color:#fff}
.login{max-width:340px;margin:80px auto;background:#fff;padding:30px;border-radius:14px;border:1px solid #E4DED1}
.login h1{font-family:"Sora";font-size:20px;margin:0 0 18px}
.login input{width:100%;padding:11px;margin-bottom:12px;border:1px solid #E4DED1;border-radius:9px;font:inherit}
.btn{display:inline-block;padding:9px 14px;border:0;border-radius:9px;background:#0E9F6E;color:#fff;font-family:"Sora";font-weight:600;cursor:pointer;text-decoration:none;font-size:14px}
.btn--dark{background:#10202E}.btn--ghost{background:#fff;color:#2A3F4F;border:1px solid #E4DED1}
.btn--danger{background:#C24B45}.btn-sm{font-size:12px;padding:6px 10px}
.err{color:#C24B45;font-size:13px;margin-bottom:10px}
.ok{background:#E7F6EF;border:1px solid #BCE3D0;color:#0E7A53;font-size:13.5px;padding:10px 12px;border-radius:9px;margin-bottom:16px}
.card{background:#fff;border:1px solid #E4DED1;border-radius:12px;padding:18px;margin-bottom:18px}
.card h2{font-family:"Sora";font-size:16px;margin:0 0 14px}
label{display:block;font-size:13px;color:#5B6B78;margin:10px 0 5px}
input[type=text],input[type=email],input[type=number],input[type=url],select,textarea{
  width:100%;padding:10px;border:1px solid #E4DED1;border-radius:9px;font:inherit;background:#fff}
textarea{min-height:70px;resize:vertical}
.row{display:flex;gap:14px;flex-wrap:wrap}.row>div{flex:1;min-width:160px}
.actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.empty{padding:30px;text-align:center;color:#5B6B78}
.pill{font-size:11px;padding:2px 8px;border-radius:20px;background:#EEF2F4;color:#3A5161}
h1.page{font-family:"Sora";font-size:22px;margin:0 0 18px}
</style>';
}

function admin_layout_start(string $title, string $active): void
{
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1"><title>' . e($title) . '</title>'
        . '<link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@500&display=swap" rel="stylesheet">'
        . admin_styles() . '</head><body>';
    $nav = ['index' => 'Pagamentos', 'courses' => 'Cursos', 'students' => 'Alunos'];
    echo '<div class="top"><b>Painel</b><div class="nav">';
    foreach ($nav as $file => $label) {
        $on = $active === $file ? ' on' : '';
        echo '<a class="' . trim($on) . '" href="' . $file . '.php">' . e($label) . '</a>';
    }
    echo '</div><span class="sp"></span><a class="out" href="index.php?logout=1">Sair</a></div><div class="wrap">';
}

function admin_layout_end(): void
{
    echo '</div></body></html>';
}

function admin_login_screen(string $error, string $csrf): void
{
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1"><title>Admin</title>'
        . '<link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700&family=IBM+Plex+Sans:wght@400;500&display=swap" rel="stylesheet">'
        . admin_styles() . '</head><body><div class="login"><h1>Área administrativa</h1>';
    if ($error !== '') {
        echo '<div class="err">' . e($error) . '</div>';
    }
    echo '<form method="post"><input type="hidden" name="action" value="admin_login">'
        . '<input type="hidden" name="_csrf" value="' . e($csrf) . '">'
        . '<input name="user" placeholder="Usuário ou e-mail" autocomplete="username">'
        . '<input name="pass" type="password" placeholder="Senha" autocomplete="current-password">'
        . '<button class="btn" style="width:100%" type="submit">Entrar</button></form></div></body></html>';
}
