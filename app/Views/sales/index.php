<section class="card">
    <div class="card-head">
        <h2>Histórico de vendas</h2>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Data</th>
                    <th>Pagamento</th>
                    <th>Total</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sales as $sale): ?>
                    <tr>
                        <td>#<?= (int) $sale['id']; ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($sale['created_at'])); ?></td>
                        <td><?= e(ucfirst($sale['payment_method'])); ?></td>
                        <td><?= money((float) $sale['total_amount']); ?></td>
                        <td><a class="btn btn-light btn-sm" href="<?= url('/sales/show?id=' . (int) $sale['id']); ?>">Ver detalhes</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($sales)): ?>
                    <tr>
                        <td colspan="5" class="empty-state">Nenhuma venda registrada até agora.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
