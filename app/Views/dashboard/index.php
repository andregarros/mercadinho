<section class="grid-cards">
    <article class="card stat-card">
        <span>Vendas do dia</span>
        <strong><?= money((float) $totals['daily_total']); ?></strong>
    </article>
    <article class="card stat-card">
        <span>Vendas do mês</span>
        <strong><?= money((float) $totals['monthly_total']); ?></strong>
    </article>
    <article class="card stat-card">
        <span>Produtos cadastrados</span>
        <strong><?= (int) $productCount; ?></strong>
    </article>
    <article class="card stat-card">
        <span>Produto mais vendido</span>
        <strong><?= e($topSeller['name'] ?? 'Sem dados'); ?></strong>
        <small><?= isset($topSeller['total_quantity']) ? ((int) $topSeller['total_quantity']) . ' unidades' : 'Ainda não há vendas'; ?></small>
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
                            <th>Código</th>
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
        <h2>Atalhos rápidos</h2>
        <p>Abra o caixa, cadastre produtos ou atualize estoque sem sair do celular.</p>
        <div class="quick-actions">
            <a class="btn btn-primary" href="<?= url('/pos') ?>">Abrir caixa</a>
            <a class="btn btn-secondary" href="<?= url('/products') ?>">Cadastrar produto</a>
            <a class="btn btn-light" href="<?= url('/stock') ?>">Movimentar estoque</a>
        </div>
    </article>
</section>
