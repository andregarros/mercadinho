<section class="page-grid">
    <article class="card stack">
        <div class="card-head">
            <h2>Venda #<?= (int) $sale['id']; ?></h2>
        </div>
        <p><strong>Data:</strong> <?= date('d/m/Y H:i', strtotime($sale['created_at'])); ?></p>
        <p><strong>Pagamento:</strong> <?= e(ucfirst($sale['payment_method'])); ?></p>
        <p><strong>Total:</strong> <?= money((float) $sale['total_amount']); ?></p>
        <a class="btn btn-light" href="/sales">Voltar</a>
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
                        <th>Código</th>
                        <th>Qtd.</th>
                        <th>Unitário</th>
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
