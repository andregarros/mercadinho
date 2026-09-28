<section class="card">
    <div class="card-head">
        <h2>Historico de vendas</h2>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Data</th>
                    <th>Pagamento</th>
                    <th>Status</th>
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
                        <td>
                            <span class="badge badge-<?= ($sale['payment_status'] ?? 'pending') === 'paid' ? 'success' : 'danger'; ?>">
                                <?= e(ucfirst($sale['payment_status'] ?? 'pending')); ?>
                            </span>
                        </td>
                        <td><?= money((float) $sale['total_amount']); ?></td>
                        <td><a class="btn btn-light btn-sm" href="<?= url('/sales/show?id=' . (int) $sale['id']); ?>">Ver detalhes</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($sales)): ?>
                    <tr>
                        <td colspan="6" class="empty-state">Nenhuma venda registrada ate agora.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
