<section class="page-grid">
    <article class="card stack">
        <div class="card-head">
            <h2>Movimentar estoque</h2>
        </div>
        <form method="post" action="/stock/move" class="stack">
            <?= csrf_field(); ?>
            <label class="field">
                <span>Produto</span>
                <select name="product_id" required>
                    <option value="">Selecione</option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?= (int) $product['id']; ?>"><?= e($product['name']); ?> (<?= (int) $product['stock']; ?>)</option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">
                <span>Tipo</span>
                <select name="type" required>
                    <option value="in">Entrada</option>
                    <option value="out">Saída</option>
                </select>
            </label>
            <label class="field">
                <span>Quantidade</span>
                <input type="number" name="quantity" min="1" value="1" required>
            </label>
            <label class="field">
                <span>Observação</span>
                <input type="text" name="note" placeholder="Ex: reposição do fornecedor">
            </label>
            <button class="btn btn-primary" type="submit">Registrar movimentação</button>
        </form>

        <?php if (!empty($lowStock)): ?>
            <div class="banner banner-warning">
                Atenção: <?= count($lowStock); ?> produto(s) com estoque baixo.
            </div>
        <?php endif; ?>
    </article>

    <article class="card">
        <div class="card-head">
            <h2>Histórico</h2>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Produto</th>
                        <th>Tipo</th>
                        <th>Qtd.</th>
                        <th>Obs.</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($movements as $movement): ?>
                        <tr>
                            <td><?= date('d/m H:i', strtotime($movement['created_at'])); ?></td>
                            <td><?= e($movement['product_name']); ?></td>
                            <td><span class="badge badge-<?= $movement['type'] === 'in' ? 'success' : 'danger'; ?>"><?= $movement['type'] === 'in' ? 'Entrada' : 'Saída'; ?></span></td>
                            <td><?= (int) $movement['quantity']; ?></td>
                            <td><?= e($movement['note']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($movements)): ?>
                        <tr>
                            <td colspan="5" class="empty-state">Sem movimentações registradas.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>
