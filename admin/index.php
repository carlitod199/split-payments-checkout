<?php
declare(strict_types=1);

use App\Models\Payment;
use App\Models\Producer;
use App\Models\Product;
use App\Models\PaymentWebhook;
use App\Models\PaymentSplit;
use App\Support\MoneyCalculator;

require __DIR__ . '/inc/admin.php';

if (!$isAdmin) {
    admin_login_screen($loginError, $csrf);
    exit;
}

$tab = $_GET['tab'] ?? 'payments';
$statusColors = ['pago'=>'#0E9F6E','pendente'=>'#E9A21B','recusado'=>'#C24B45','cancelado'=>'#5B6B78','estornado'=>'#C24B45'];

admin_layout_start('Admin — Pagamentos', 'index');
?>
  <div class="tabs">
    <a href="?tab=payments" class="<?= $tab==='payments'?'on':'' ?>">Pagamentos</a>
    <a href="?tab=products" class="<?= $tab==='products'?'on':'' ?>">Produtos</a>
    <a href="?tab=producers" class="<?= $tab==='producers'?'on':'' ?>">Produtores</a>
    <a href="?tab=webhooks" class="<?= $tab==='webhooks'?'on':'' ?>">Webhooks</a>
  </div>

  <?php if ($tab === 'payments'):
    $rows = Payment::all(); ?>
    <table>
      <tr><th>Interno</th><th>Produto</th><th>Cliente</th><th>Forma</th><th>Bruto</th><th>Líquido</th><th>Status</th><th>Acesso</th><th>Split</th></tr>
      <?php foreach ($rows as $r):
        $splits = PaymentSplit::byPayment((int)$r['id']);
        $splitTxt = implode(' · ', array_map(fn($s)=>e($s['role']==='coprodutor'?'Copro':'Princ').' '.MoneyCalculator::format((int)$s['expected_cents']), $splits)); ?>
        <tr>
          <td class="mono"><?= e($r['internal_id']) ?></td>
          <td><?= e($r['product_name']) ?></td>
          <td><?= e($r['customer_name']) ?><br><small style="color:#5B6B78"><?= e($r['customer_email']) ?></small></td>
          <td><?= e(strtoupper($r['method'])) ?></td>
          <td class="mono"><?= MoneyCalculator::format((int)$r['gross_cents']) ?></td>
          <td class="mono"><?= MoneyCalculator::format((int)$r['net_cents']) ?></td>
          <td><b class="badge" style="background:<?= $statusColors[$r['status']] ?? '#5B6B78' ?>"><?= e($r['status']) ?></b></td>
          <td><?= !empty($r['access_granted_at']) ? '<span class="pill">liberado</span>' : '—' ?></td>
          <td style="font-size:12px;color:#5B6B78"><?= $splitTxt ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="9" class="empty">Nenhum pagamento ainda.</td></tr><?php endif; ?>
    </table>

  <?php elseif ($tab === 'products'):
    $rows = Product::all(); ?>
    <table>
      <tr><th>Produto</th><th>Slug</th><th>Preço</th><th>Principal</th><th>%</th><th>Coprodutor</th><th>%</th><th>Status</th></tr>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= e($r['name']) ?></td><td class="mono"><?= e($r['checkout_slug']) ?></td>
          <td class="mono"><?= MoneyCalculator::format((int)$r['price_cents']) ?></td>
          <td><?= e($r['principal_name']) ?></td><td class="mono"><?= e($r['principal_percent']) ?>%</td>
          <td><?= e($r['coproducer_name']) ?></td><td class="mono"><?= e($r['coproducer_percent']) ?>%</td>
          <td><?= e($r['status']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="8" class="empty">Nenhum produto cadastrado.</td></tr><?php endif; ?>
    </table>

  <?php elseif ($tab === 'producers'):
    $rows = Producer::all(); ?>
    <table>
      <tr><th>Nome</th><th>E-mail</th><th>Documento</th><th>Tipo</th><th>Wallet ID</th><th>Conta</th></tr>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= e($r['name']) ?></td><td><?= e($r['email']) ?></td><td class="mono"><?= e($r['document']) ?></td>
          <td><?= e($r['type']) ?></td><td class="mono"><?= e($r['wallet_id']) ?></td><td><?= e($r['account_status']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="6" class="empty">Nenhum produtor cadastrado.</td></tr><?php endif; ?>
    </table>

  <?php elseif ($tab === 'webhooks'):
    $rows = PaymentWebhook::all(); ?>
    <table>
      <tr><th>Recebido</th><th>Evento</th><th>Gateway</th><th>Status</th><th>Idempotency</th></tr>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="mono"><?= e($r['received_at']) ?></td><td><?= e($r['event']) ?></td>
          <td><?= e($r['gateway']) ?></td><td><?= e($r['process_status']) ?></td>
          <td class="mono" style="font-size:11px"><?= e(substr($r['idempotency_key'],0,28)) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="5" class="empty">Nenhum webhook recebido.</td></tr><?php endif; ?>
    </table>
  <?php endif; ?>
<?php
admin_layout_end();
