'use strict';

(function () {
  const cfg = window.CHECKOUT;
  let method = 'pix';
  let polling = null;

  const $ = (id) => document.getElementById(id);
  const onlyDigits = (s) => (s || '').replace(/\D+/g, '');

  // ---- troca de método ----
  document.querySelectorAll('.method').forEach((el) => {
    el.addEventListener('click', () => {
      document.querySelectorAll('.method').forEach((m) => m.classList.remove('active'));
      el.classList.add('active');
      method = el.dataset.method;
      $('cardFields').classList.toggle('hidden', method !== 'cartao');
      updatePayLabel();
    });
  });

  function updatePayLabel() {
    $('payBtn').textContent = method === 'pix' ? 'Gerar Pix' : 'Pagar com cartão';
  }
  updatePayLabel();

  // ---- máscaras simples ----
  $('document').addEventListener('input', (e) => { e.target.value = onlyDigits(e.target.value).slice(0, 14); });
  $('phone').addEventListener('input', (e) => { e.target.value = onlyDigits(e.target.value).slice(0, 11); });
  $('cardnumber') && $('cardnumber').addEventListener('input', (e) => {
    e.target.value = onlyDigits(e.target.value).slice(0, 19).replace(/(\d{4})(?=\d)/g, '$1 ');
  });
  $('cardexp') && $('cardexp').addEventListener('input', (e) => {
    let v = onlyDigits(e.target.value).slice(0, 6);
    if (v.length > 2) v = v.slice(0, 2) + '/' + v.slice(2);
    e.target.value = v;
  });

  function clearErrors() {
    document.querySelectorAll('.field').forEach((f) => {
      f.classList.remove('err');
      const m = f.querySelector('.msg');
      if (m) m.textContent = '';
    });
    $('formError').classList.add('hidden');
  }

  function setError(fieldId, msg) {
    const f = $(fieldId);
    if (!f) return;
    f.classList.add('err');
    const m = f.querySelector('.msg');
    if (m) m.textContent = msg;
  }

  function topError(msg) {
    const box = $('formError');
    box.textContent = msg;
    box.classList.remove('hidden');
    box.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  function buildPayload() {
    const payload = {
      slug: cfg.slug,
      csrf: cfg.csrf,
      idempotency_key: cfg.idempotencyKey,
      method: method,
      name: $('name').value.trim(),
      email: $('email').value.trim(),
      phone: onlyDigits($('phone').value),
      document: onlyDigits($('document').value),
    };
    if (method === 'cartao') {
      const exp = ($('cardexp').value || '').split('/');
      payload.installments = parseInt($('installments').value, 10) || 1;
      payload.card = {
        holder_name: $('cardholder').value.trim(),
        number: onlyDigits($('cardnumber').value),
        expiry_month: (exp[0] || '').padStart(2, '0'),
        expiry_year: exp[1] || '',
        ccv: onlyDigits($('cardccv').value),
        postal_code: onlyDigits($('cep').value),
        address_number: $('addrnum').value.trim(),
      };
    }
    return payload;
  }

  $('payBtn').addEventListener('click', async () => {
    clearErrors();
    const btn = $('payBtn');
    const original = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Processando…';

    try {
      const res = await fetch(cfg.apiBase + '/create_payment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(buildPayload()),
      });
      const data = await res.json();

      if (!data.ok) {
        if (data.errors) {
          const map = {
            name: 'f-name', email: 'f-email', phone: 'f-phone',
            document: 'f-document', card: 'f-cardnumber',
          };
          Object.entries(data.errors).forEach(([k, v]) => setError(map[k] || 'f-name', v));
          topError('Revise os campos destacados.');
        } else {
          topError(data.error || 'Não foi possível concluir o pagamento.');
        }
        return;
      }

      const d = data.data;
      if (d.method === 'pix') {
        showPix(d);
      } else {
        handleCardResult(d.status, d.internal_id);
      }
    } catch (err) {
      topError('Falha de conexão. Verifique sua internet e tente novamente.');
    } finally {
      btn.disabled = false;
      btn.textContent = original;
    }
  });

  function showPix(d) {
    $('checkoutCard').classList.add('hidden');
    const box = $('pixResult');
    box.classList.remove('hidden');
    if (d.pix_qr_code) $('pixQr').src = 'data:image/png;base64,' + d.pix_qr_code;
    $('pixCode').textContent = d.pix_copy_paste || '';
    $('copyPix').addEventListener('click', () => {
      navigator.clipboard.writeText(d.pix_copy_paste || '').then(() => {
        $('copyPix').textContent = 'Copiado!';
        setTimeout(() => ($('copyPix').textContent = 'Copiar'), 2000);
      });
    });
    startPolling(d.external_id);
  }

  function startPolling(externalId) {
    polling = setInterval(async () => {
      try {
        const res = await fetch(cfg.apiBase + '/pix_status.php?external_id=' + encodeURIComponent(externalId));
        const data = await res.json();
        if (data.ok && data.status === 'pago') {
          clearInterval(polling);
          window.location.href = 'payment_success.php';
        }
      } catch (e) { /* tenta de novo no próximo tick */ }
    }, 4000);
  }

  function handleCardResult(status, internalId) {
    if (status === 'pago') {
      window.location.href = 'payment_success.php';
    } else if (status === 'pendente') {
      window.location.href = 'payment_pending.php';
    } else {
      window.location.href = 'payment_failed.php';
    }
  }
})();
