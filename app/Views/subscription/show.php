<?php
$currentUser = $user;
$payment = $pending_payment ?? $latest_payment ?? null;
$isLocked = ($currentUser['plan_status'] ?? 'trial') === 'expired';
$paymentStatus = (string) ($payment['status'] ?? '');
$providerPayload = [];

if (!empty($payment['provider_payload']) && is_string($payment['provider_payload'])) {
    $decodedPayload = json_decode($payment['provider_payload'], true);
    if (is_array($decodedPayload)) {
        $providerPayload = $decodedPayload;
    }
}

$statusDetail = (string) ($providerPayload['status_detail'] ?? '');
$statusMessage = (string) ($providerPayload['status_message'] ?? $providerPayload['message'] ?? '');
?>
<section class="grid-main">
    <article class="card stack">
        <div class="card-head">
            <h2>Assinatura</h2>
            <span class="badge badge-<?= $isLocked ? 'danger' : 'success'; ?>">
                <?= e($currentUser['plan_status'] ?? 'trial'); ?>
            </span>
        </div>

        <p class="muted">
            Teste gratis: <?= (int) ($trial_days ?? 3); ?> dias.
            Depois disso, o scanner, o caixa e o cadastro de produtos ficam liberados somente com a assinatura ativa.
        </p>

        <div class="grid-cards subscription-summary">
            <article class="card stat-card">
                <span>Valor</span>
                <strong><?= money((float) $amount); ?></strong>
            </article>
            <article class="card stat-card">
                <span>Periodo</span>
                <strong><?= (int) $plan_days; ?> dias</strong>
            </article>
            <article class="card stat-card">
                <span>Validade atual</span>
                <strong><?= !empty($currentUser['plan_expires_at']) ? date('d/m/Y', strtotime($currentUser['plan_expires_at'])) : 'Nao definida'; ?></strong>
            </article>
        </div>

        <form method="post" action="<?= url('/subscription/pix'); ?>">
            <?= csrf_field(); ?>
            <button class="btn btn-primary" type="submit">
                <?= $paymentStatus === 'expired' ? 'Gerar outro QR Code' : 'Gerar PIX da assinatura'; ?>
            </button>
        </form>

        <p class="muted">O QR Code da assinatura expira em <?= (int) ($pix_expiration_minutes ?? 3); ?> minutos. Se vencer, gere outro codigo para pagar.</p>

        <?php if ($payment): ?>
            <div class="pix-panel" id="subscription-panel" data-subscription-status="<?= e((string) ($payment['status'] ?? 'pending')); ?>">
                <div class="card-head">
                    <h3>Pagamento da assinatura</h3>
                    <span id="subscription-status-badge" class="badge">Aguardando pagamento</span>
                </div>
                <div class="pix-panel-body subscription-pix-panel-body">
                    <?php if (!empty($payment['qr_code_image'])): ?>
                        <img
                            id="subscription-qr-image"
                            class="pix-qr-image"
                            src="<?= e((string) $payment['qr_code_image']); ?>"
                            alt="QR Code da assinatura"
                            style="display:block;width:100%;max-width:220px;height:auto;object-fit:contain;margin:0 auto;"
                        >
                    <?php endif; ?>
                    <?php if (!empty($payment['pix_copy_paste'])): ?>
                        <label class="field">
                            <span>Copia e cola PIX</span>
                            <textarea id="subscription-copy-paste" rows="4" readonly><?= e((string) $payment['pix_copy_paste']); ?></textarea>
                        </label>
                    <?php endif; ?>
                    <p class="muted">
                        Transacao: <?= e((string) $payment['transaction_id']); ?>
                    </p>
                    <?php if (!empty($payment['expires_at'])): ?>
                        <p class="muted">
                            Expira em: <?= date('d/m/Y H:i:s', strtotime((string) $payment['expires_at'])); ?>
                        </p>
                    <?php endif; ?>
                    <?php if ($statusDetail !== ''): ?>
                        <p class="muted">
                            Detalhe do gateway: <?= e($statusDetail); ?>
                        </p>
                    <?php endif; ?>
                    <?php if ($statusMessage !== ''): ?>
                        <div class="banner banner-warning">
                            Mensagem do gateway: <?= e($statusMessage); ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($paymentStatus === 'expired'): ?>
                        <div class="banner banner-warning">
                            Este QR Code expirou. Gere outro QR Code para concluir o pagamento da assinatura.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </article>

    <article class="card stack">
        <div class="card-head">
            <h2>Como funciona</h2>
        </div>
        <p class="muted">1. Gere o PIX da assinatura.</p>
        <p class="muted">2. Pague no banco ou app do cliente.</p>
        <p class="muted">3. O sistema confirma automaticamente e libera o acesso.</p>

        <div class="banner banner-warning">
            O pagamento desta assinatura sera enviado para o PIX configurado pelo admin no painel administrativo.
        </div>
    </article>
</section>

<?php if ($payment): ?>
    <script>
        (() => {
            const badge = document.getElementById('subscription-status-badge');
            const panel = document.getElementById('subscription-panel');

            if (!badge || !panel) {
                return;
            }

            const setStatus = (status) => {
                const normalized = String(status || 'pending').toLowerCase();
                badge.classList.remove('badge-success', 'badge-danger');

                if (normalized === 'paid' || normalized === 'active') {
                    badge.textContent = 'Pago';
                    badge.classList.add('badge-success');
                    return;
                }

                if (normalized === 'expired') {
                    badge.textContent = 'Expirado';
                    badge.classList.add('badge-danger');
                    return;
                }

                if (normalized === 'cancelled') {
                    badge.textContent = 'Cancelado';
                    badge.classList.add('badge-danger');
                    return;
                }

                if (normalized === 'failed') {
                    badge.textContent = 'Falhou';
                    badge.classList.add('badge-danger');
                    return;
                }

                badge.textContent = 'Aguardando pagamento';
            };

            setStatus(panel.dataset.subscriptionStatus);

            if (panel.dataset.subscriptionStatus !== 'pending') {
                return;
            }

            const timer = window.setInterval(async () => {
                try {
                    const response = await fetch(`${window.APP_BASE_URL}/subscription/status`);
                    const data = await response.json();

                    if (!response.ok || !data.success) {
                        return;
                    }

                    const status = data.payment?.status || data.user?.plan_status;
                    setStatus(status);

                    if (status === 'paid' || data.user?.plan_status === 'active') {
                        window.clearInterval(timer);
                        window.location.reload();
                    }
                } catch (error) {
                    console.debug('Falha ao atualizar a assinatura.', error);
                }
            }, 4000);
        })();
    </script>
<?php endif; ?>
