<?php
declare(strict_types=1);

use App\Support\Database;
use App\Support\Csrf;
use App\Support\MoneyCalculator;
use App\Models\Product;

$config = require __DIR__ . '/../config/payment.php';
Database::init($config['db']);

$slug = preg_replace('/[^a-z0-9\-]/i', '', $_GET['p'] ?? '');
$product = $slug !== '' ? Product::findBySlug($slug) : null;

$csrf = Csrf::token();
$idempotencyKey = bin2hex(random_bytes(16));

function e(?string $v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= $product ? e($product['name']) . ' — Pagamento' : 'Checkout' ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
<style>
:root{
  --ink:#10202E;        /* tinta petróleo */
  --ink-soft:#2A3F4F;
  --paper:#F6F3EC;      /* papel */
  --surface:#FFFFFF;
  --line:#E4DED1;
  --slate:#5B6B78;      /* texto secundário */
  --emerald:#0E9F6E;    /* ação / pix */
  --emerald-deep:#0B7D57;
  --amber:#E9A21B;      /* destaque / copiar */
  --danger:#C24B45;
  --radius:14px;
  --shadow:0 1px 2px rgba(16,32,46,.04),0 12px 32px -12px rgba(16,32,46,.18);
}
*{box-sizing:border-box}
html,body{margin:0}
body{
  font-family:"IBM Plex Sans",system-ui,sans-serif;
  background:
    radial-gradient(120% 80% at 100% 0%, #ECE6D8 0%, var(--paper) 55%) fixed;
  color:var(--ink);
  -webkit-font-smoothing:antialiased;
  min-height:100vh;
}
.wrap{max-width:1040px;margin:0 auto;padding:32px 20px 64px}
.brandbar{display:flex;align-items:center;gap:10px;margin-bottom:28px;color:var(--ink-soft)}
.brandbar .dot{width:10px;height:10px;border-radius:50%;background:var(--emerald);box-shadow:0 0 0 4px rgba(14,159,110,.15)}
.brandbar b{font-family:"Sora";letter-spacing:-.01em}
.brandbar .secure{margin-left:auto;font-size:13px;color:var(--slate);display:flex;align-items:center;gap:6px}

.grid{display:grid;grid-template-columns:1fr 1.15fr;gap:24px;align-items:start}
@media (max-width:840px){.grid{grid-template-columns:1fr}}

.card{background:var(--surface);border:1px solid var(--line);border-radius:var(--radius);box-shadow:var(--shadow)}

/* ---- resumo ---- */
.summary{padding:28px;position:sticky;top:24px}
@media (max-width:840px){.summary{position:static}}
.eyebrow{font-size:12px;letter-spacing:.14em;text-transform:uppercase;color:var(--slate);font-weight:600}
.summary h1{font-family:"Sora";font-weight:700;font-size:26px;line-height:1.2;margin:10px 0 8px;letter-spacing:-.02em}
.summary p.desc{color:var(--slate);font-size:15px;line-height:1.55;margin:0 0 22px}
.price{display:flex;align-items:baseline;gap:8px;padding:18px 0;border-top:1px dashed var(--line);border-bottom:1px dashed var(--line)}
.price .label{color:var(--slate);font-size:14px}
.price .value{font-family:"IBM Plex Mono";font-weight:600;font-size:32px;margin-left:auto;letter-spacing:-.02em}
.includes{list-style:none;padding:0;margin:20px 0 0}
.includes li{display:flex;gap:10px;align-items:flex-start;padding:7px 0;font-size:14px;color:var(--ink-soft)}
.includes li b{color:var(--emerald-deep)}
.trust{margin-top:22px;font-size:12.5px;color:var(--slate);line-height:1.5}

/* ---- form ---- */
.checkout{padding:28px}
.checkout h2{font-family:"Sora";font-weight:600;font-size:18px;margin:0 0 18px;letter-spacing:-.01em}
.field{margin-bottom:14px}
.field label{display:block;font-size:13px;font-weight:500;margin-bottom:6px;color:var(--ink-soft)}
.field input{
  width:100%;padding:12px 13px;font:inherit;font-size:15px;color:var(--ink);
  background:#FCFBF8;border:1px solid var(--line);border-radius:10px;transition:border-color .15s,box-shadow .15s;
}
.field input:focus{outline:none;border-color:var(--emerald);box-shadow:0 0 0 3px rgba(14,159,110,.16);background:#fff}
.field.err input{border-color:var(--danger)}
.field .msg{font-size:12px;color:var(--danger);margin-top:5px;min-height:1px}
.row2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.row3{display:grid;grid-template-columns:1.4fr 1fr 1fr;gap:12px}

.methods{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:6px 0 20px}
.method{
  border:1.5px solid var(--line);border-radius:12px;padding:14px;cursor:pointer;background:#FCFBF8;
  display:flex;flex-direction:column;gap:4px;transition:border-color .15s,background .15s;
}
.method:hover{border-color:#CFC8B8}
.method.active{border-color:var(--emerald);background:rgba(14,159,110,.06)}
.method b{font-family:"Sora";font-weight:600;font-size:15px}
.method i{font-style:normal;font-size:12.5px;color:var(--slate)}

.pay-btn{
  width:100%;margin-top:8px;padding:15px;border:0;border-radius:12px;cursor:pointer;
  font-family:"Sora";font-weight:600;font-size:16px;color:#fff;background:var(--emerald);
  transition:background .15s,transform .05s;
}
.pay-btn:hover{background:var(--emerald-deep)}
.pay-btn:active{transform:translateY(1px)}
.pay-btn:disabled{opacity:.6;cursor:progress}

.legal{margin-top:14px;font-size:12px;color:var(--slate);text-align:center;line-height:1.5}

/* ---- pix result ---- */
.hidden{display:none}
.pix{padding:28px;text-align:center}
.pix h2{font-family:"Sora";font-size:20px;margin:0 0 4px}
.pix .await{display:inline-flex;align-items:center;gap:8px;color:var(--amber);font-size:13px;font-weight:600;margin-bottom:18px}
.pix .await .pulse{width:9px;height:9px;border-radius:50%;background:var(--amber);animation:pulse 1.4s infinite}
@keyframes pulse{0%,100%{opacity:.35;transform:scale(.8)}50%{opacity:1;transform:scale(1)}}
.qr{width:220px;height:220px;border-radius:12px;border:1px solid var(--line);padding:10px;background:#fff;margin:0 auto 18px}
.qr img{width:100%;height:100%}
.copybox{display:flex;gap:8px;background:#FCFBF8;border:1px solid var(--line);border-radius:10px;padding:10px;align-items:center}
.copybox .code{font-family:"IBM Plex Mono";font-size:12px;color:var(--ink-soft);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1;text-align:left}
.copybtn{border:0;background:var(--amber);color:#3a2a00;font-weight:600;font-family:"Sora";font-size:13px;padding:9px 14px;border-radius:8px;cursor:pointer}
.copybtn:active{transform:translateY(1px)}

.formerror{background:rgba(194,75,69,.08);border:1px solid rgba(194,75,69,.3);color:#8a2f2a;padding:11px 13px;border-radius:10px;font-size:13.5px;margin-bottom:14px}

.unavailable{max-width:520px;margin:60px auto;text-align:center;color:var(--slate)}
.unavailable h1{font-family:"Sora";color:var(--ink)}
</style>
</head>
<body>
<div class="wrap">
  <div class="brandbar">
    <div class="dot"></div>
    <b>Pagamento seguro</b>
    <div class="secure">🔒 Conexão criptografada</div>
  </div>

<?php if (!$product): ?>
  <div class="unavailable">
    <h1>Produto indisponível</h1>
    <p>O link de pagamento não foi encontrado ou está inativo. Verifique o endereço e tente novamente.</p>
  </div>
<?php else:
  $price = (int) $product['price_cents'];
?>
  <div class="grid">
    <!-- RESUMO -->
    <aside class="card summary">
      <div class="eyebrow">Você está adquirindo</div>
      <h1><?= e($product['name']) ?></h1>
      <?php if (!empty($product['description'])): ?>
        <p class="desc"><?= e($product['description']) ?></p>
      <?php endif; ?>
      <div class="price">
        <div class="label">Total</div>
        <div class="value"><?= MoneyCalculator::format($price) ?></div>
      </div>
      <ul class="includes">
        <li><b>✓</b><div>Acesso liberado automaticamente após a confirmação do pagamento.</div></li>
        <li><b>✓</b><div>Pagamento processado pelo Asaas, instituição regulada.</div></li>
        <li><b>✓</b><div>Pix com confirmação em segundos ou cartão em até 2 parcelas sem complicação.</div></li>
      </ul>
      <div class="trust">Seus dados são usados apenas para processar esta compra, conforme a LGPD. Não armazenamos dados do seu cartão.</div>
    </aside>

    <!-- CHECKOUT -->
    <section class="card checkout" id="checkoutCard">
      <h2>Seus dados</h2>
      <div class="formerror hidden" id="formError"></div>

      <div class="field" id="f-name">
        <label for="name">Nome completo</label>
        <input id="name" type="text" autocomplete="name" placeholder="Como está no documento">
        <div class="msg"></div>
      </div>
      <div class="row2">
        <div class="field" id="f-email">
          <label for="email">E-mail</label>
          <input id="email" type="email" autocomplete="email" placeholder="voce@email.com">
          <div class="msg"></div>
        </div>
        <div class="field" id="f-phone">
          <label for="phone">Telefone (com DDD)</label>
          <input id="phone" type="tel" inputmode="numeric" autocomplete="tel" placeholder="(00) 00000-0000">
          <div class="msg"></div>
        </div>
      </div>
      <div class="field" id="f-document">
        <label for="document">CPF ou CNPJ</label>
        <input id="document" type="text" inputmode="numeric" placeholder="Somente números">
        <div class="msg"></div>
      </div>

      <h2 style="margin-top:8px">Forma de pagamento</h2>
      <div class="methods">
        <div class="method active" data-method="pix" id="m-pix">
          <b>Pix</b><i>Aprovação imediata</i>
        </div>
        <div class="method" data-method="cartao" id="m-cartao">
          <b>Cartão de crédito</b><i>Até 2x</i>
        </div>
      </div>

      <!-- CARTÃO -->
      <div id="cardFields" class="hidden">
        <div class="field" id="f-cardholder">
          <label for="cardholder">Nome impresso no cartão</label>
          <input id="cardholder" type="text" autocomplete="cc-name">
          <div class="msg"></div>
        </div>
        <div class="field" id="f-cardnumber">
          <label for="cardnumber">Número do cartão</label>
          <input id="cardnumber" type="text" inputmode="numeric" autocomplete="cc-number" placeholder="0000 0000 0000 0000">
          <div class="msg"></div>
        </div>
        <div class="row3">
          <div class="field" id="f-cardexp">
            <label for="cardexp">Validade</label>
            <input id="cardexp" type="text" inputmode="numeric" autocomplete="cc-exp" placeholder="MM/AAAA">
            <div class="msg"></div>
          </div>
          <div class="field" id="f-cardccv">
            <label for="cardccv">CVV</label>
            <input id="cardccv" type="text" inputmode="numeric" autocomplete="cc-csc" placeholder="000">
            <div class="msg"></div>
          </div>
          <div class="field" id="f-installments">
            <label for="installments">Parcelas</label>
            <input id="installments" type="number" min="1" max="2" value="1">
            <div class="msg"></div>
          </div>
        </div>
        <div class="row2">
          <div class="field">
            <label for="cep">CEP do titular</label>
            <input id="cep" type="text" inputmode="numeric" placeholder="00000-000">
          </div>
          <div class="field">
            <label for="addrnum">Número</label>
            <input id="addrnum" type="text" placeholder="Nº">
          </div>
        </div>
      </div>

      <button class="pay-btn" id="payBtn">Pagar <?= MoneyCalculator::format($price) ?></button>
      <div class="legal">Ao continuar você concorda com os termos da compra e com o tratamento dos seus dados conforme a LGPD.</div>
    </section>

    <!-- RESULTADO PIX (substitui o form) -->
    <section class="card pix hidden" id="pixResult">
      <h2>Quase lá! Pague com Pix</h2>
      <div class="await"><div class="pulse"></div> Aguardando pagamento</div>
      <div class="qr"><img id="pixQr" alt="QR Code Pix"></div>
      <div class="copybox">
        <div class="code" id="pixCode"></div>
        <button class="copybtn" id="copyPix">Copiar</button>
      </div>
      <p class="trust" style="margin-top:16px">Abra o app do seu banco, escolha pagar com Pix e leia o QR Code ou cole o código. A confirmação é automática.</p>
    </section>
  </div>

  <script>
    window.CHECKOUT = {
      slug: <?= json_encode($product['checkout_slug']) ?>,
      csrf: <?= json_encode($csrf) ?>,
      idempotencyKey: <?= json_encode($idempotencyKey) ?>,
      apiBase: <?= json_encode(rtrim($config['app_url'], '/') . '/api') ?>
    };
  </script>
  <script src="<?= e(rtrim($config['app_url'], '/')) ?>/assets/checkout.js"></script>
<?php endif; ?>
</div>
</body>
</html>
