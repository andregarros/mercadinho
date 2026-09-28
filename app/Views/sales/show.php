<section class="page-grid">
    <article class="card stack">
        <div class="card-head">
            <h2>Venda #<?= (int) $sale['id']; ?></h2>
        </div>
        <p><strong>Data:</strong> <?= date('d/m/Y H:i', strtotime($sale['created_at'])); ?></p>
        <p><strong>Pagamento:</strong> <?= e(ucfirst($sale['payment_method'])); ?></p>
        <p><strong>Status:</strong> <?= e(ucfirst($sale['payment_status'] ?? 'pending')); ?></p>
        <p><strong>Total:</strong> <?= money((float) $sale['total_amount']); ?></p>
        <?php if (!empty($sale['paid_at'])): ?>
            <p><strong>Pago em:</strong> <?= date('d/m/Y H:i', strtotime($sale['paid_at'])); ?></p>
        <?php endif; ?>
        <?php if (!empty($payment)): ?>
            <p><strong>Gateway:</strong> <?= e(gateway_label((string) $payment['gateway'])); ?></p>
            <p><strong>Transacao:</strong> <?= e((string) $payment['transaction_id']); ?></p>
            <?php if (!empty($payment['pix_copy_paste'])): ?>
                <label class="field">
                    <span>Copia e cola PIX</span>
                    <input type="text" readonly value="<?= e((string) $payment['pix_copy_paste']); ?>">
                </label>
            <?php endif; ?>
        <?php endif; ?>
        <a class="btn btn-light" href="<?= url('/sales') ?>">Voltar</a>
    </article>

    <article class="card">
        <div class="card-head">
            <h2>Itens da venda</h2>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th>Codigo</th>
                        <th>Qtd.</th>
                        <th>Unitario</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sale['items'] as $item): ?>
                        <tr>
                            <td><?= e($item['product_name']); ?></td>
                            <td><?= e($item['barcode']); ?></td>
                            <td><?= (int) $item['quantity']; ?></td>
                            <td><?= money((float) $item['unit_price']); ?></td>
                            <td><?= money((float) $item['subtotal']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>
