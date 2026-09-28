<section class="grid-cards">
    <article class="card stat-card">
        <span>Vendas do dia</span>
        <strong><?= money((float) $totals['daily_total']); ?></strong>
    </article>
    <article class="card stat-card">
        <span>Vendas do mes</span>
        <strong><?= money((float) $totals['monthly_total']); ?></strong>
    </article>
    <article class="card stat-card">
        <span>Produtos cadastrados</span>
        <strong><?= (int) $productCount; ?></strong>
    </article>
    <article class="card stat-card">
        <span>Assinatura</span>
        <strong><?= e((string) ($subscription['user']['plan_status'] ?? 'trial')); ?></strong>
        <small>
            Ate
            <?= !empty($subscription['user']['plan_expires_at']) ? date('d/m/Y', strtotime($subscription['user']['plan_expires_at'])) : 'sem data'; ?>
        </small>
    </article>
</section>

<section class="grid-main">
    <article class="card">
        <div class="card-head">
            <h2>Estoque baixo</h2>
            <span class="badge">Limite: <?= (int) $threshold; ?></span>
        </div>
        <?php if (empty($lowStock)): ?>
            <p class="empty-state">Tudo sob controle. Nenhum produto abaixo do limite.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th>Codigo</th>
                            <th>Estoque</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lowStock as $item): ?>
                            <tr>
                                <td><?= e($item['name']); ?></td>
                                <td><?= e($item['barcode']); ?></td>
                                <td><span class="badge badge-danger"><?= (int) $item['stock']; ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </article>

    <article class="card card-cta">
        <h2>Atalhos rapidos</h2>
        <p>Abra o caixa, acompanhe a assinatura e mantenha o mercadinho rodando sem interrupcao.</p>
        <div class="quick-actions">
            <a class="btn btn-primary" href="<?= url('/subscription') ?>">Ver assinatura</a>
            <a class="btn btn-secondary" href="<?= url('/products') ?>">Produtos</a>
            <a class="btn btn-light" href="<?= url('/sales') ?>">Vendas</a>
        </div>
    </article>
</section>
