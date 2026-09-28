<?php
$configured = [];
foreach ($gateways as $gateway) {
    $configured[$gateway['gateway_name']] = $gateway;
}
?>
<section class="page-grid">
    <article class="card stack">
        <div class="card-head">
            <h2>Vincular PIX</h2>
            <span class="badge">Receba direto na sua conta</span>
        </div>
        <p class="muted">Conecte o gateway desejado para gerar cobrancas PIX no caixa e confirmar o pagamento automaticamente por webhook.</p>

        <?php foreach ($gatewayOptions as $gatewayKey => $option): ?>
            <?php $current = $configured[$gatewayKey] ?? null; ?>
            <section class="gateway-card stack">
                <div class="gateway-card-head">
                    <div>
                        <h3><?= e($option['label']); ?></h3>
                        <p class="muted">
                            Status:
                            <strong class="<?= !empty($current['is_connected']) ? 'text-success' : 'text-danger'; ?>">
                                <?= !empty($current['is_connected']) ? 'Conectado' : 'Nao conectado'; ?>
                            </strong>
                        </p>
                        <?php if (!empty($current['last_error'])): ?>
                            <p class="muted"><?= e($current['last_error']); ?></p>
                        <?php endif; ?>
                    </div>
                    <?php if ($current): ?>
                        <form method="post" action="<?= url('/payments/disconnect'); ?>">
                            <?= csrf_field(); ?>
                            <input type="hidden" name="gateway_name" value="<?= e($gatewayKey); ?>">
                            <button class="btn btn-danger btn-sm" type="submit">Desvincular</button>
                        </form>
                    <?php endif; ?>
                </div>

                <form method="post" action="<?= url('/payments/connect'); ?>" class="stack">
                    <?= csrf_field(); ?>
                    <input type="hidden" name="gateway_name" value="<?= e($gatewayKey); ?>">

                    <?php foreach ($option['fields'] as $field => $label): ?>
                        <label class="field">
                            <span><?= e($label); ?></span>
                            <?php if ($gatewayKey === 'asaas' && $field === 'environment'): ?>
                                <select name="environment">
                                    <option value="production" <?= (($current['credentials']['environment'] ?? 'production') === 'production') ? 'selected' : ''; ?>>Producao</option>
                                    <option value="sandbox" <?= (($current['credentials']['environment'] ?? '') === 'sandbox') ? 'selected' : ''; ?>>Sandbox</option>
                                </select>
                            <?php else: ?>
                                <input type="password" name="<?= e($field); ?>" value="" autocomplete="off" <?= $current ? '' : 'required'; ?>>
                            <?php endif; ?>
                        </label>
                    <?php endforeach; ?>

                    <button class="btn btn-primary" type="submit">Validar e salvar</button>
                </form>
            </section>
        <?php endforeach; ?>
    </article>

    <article class="card stack">
        <div class="card-head">
            <h2>Webhooks</h2>
        </div>
        <p class="muted">Cadastre a URL correspondente ao gateway que voce usar. O sistema consulta a transacao novamente no backend antes de marcar a venda como paga.</p>

        <div class="stack webhook-list">
            <label class="field">
                <span>Mercado Pago</span>
                <input type="text" readonly value="<?= e(public_webhook_url_or_message('/payments/webhook/mercado-pago')); ?>">
            </label>
            <label class="field">
                <span>PagSeguro</span>
                <input type="text" readonly value="<?= e(public_webhook_url_or_message('/payments/webhook/pagseguro')); ?>">
            </label>
            <label class="field">
                <span>Asaas</span>
                <input type="text" readonly value="<?= e(public_webhook_url_or_message('/payments/webhook/asaas')); ?>">
            </label>
            <label class="field">
                <span>Stripe</span>
                <input type="text" readonly value="<?= e(public_webhook_url_or_message('/payments/webhook/stripe')); ?>">
            </label>
        </div>

        <div class="banner banner-warning">
            HTTPS e obrigatorio em producao para uso de webhooks e cobrancas reais.
        </div>
    </article>
</section>
