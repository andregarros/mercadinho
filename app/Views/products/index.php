<section class="page-grid">
    <article class="card">
        <div class="card-head">
            <h2>Produtos</h2>
            <form method="get" action="/products" class="inline-form">
                <input type="text" name="search" placeholder="Buscar por nome ou código" value="<?= e($_GET['search'] ?? ''); ?>">
                <button class="btn btn-light" type="submit">Buscar</button>
            </form>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Nome</th>
                        <th>Preço</th>
                        <th>Estoque</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td><?= e($product['barcode']); ?></td>
                            <td><?= e($product['name']); ?></td>
                            <td><?= money((float) $product['price']); ?></td>
                            <td><?= (int) $product['stock']; ?></td>
                            <td>
                                <div class="row-actions">
                                    <button
                                        class="btn btn-light btn-sm js-fill-form"
                                        type="button"
                                        data-target="#update-form"
                                        data-id="<?= (int) $product['id']; ?>"
                                        data-barcode="<?= e($product['barcode']); ?>"
                                        data-name="<?= e($product['name']); ?>"
                                        data-price="<?= e((string) $product['price']); ?>"
                                        data-stock="<?= (int) $product['stock']; ?>"
                                    >
                                        Editar
                                    </button>
                                    <form method="post" action="/products/delete">
                                        <?= csrf_field(); ?>
                                        <input type="hidden" name="id" value="<?= (int) $product['id']; ?>">
                                        <button class="btn btn-danger btn-sm" type="submit">Excluir</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="5" class="empty-state">Nenhum produto cadastrado ainda.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>

    <article class="stack">
        <form method="post" action="/products/store" class="card stack">
            <div class="card-head">
                <h2>Novo produto</h2>
            </div>
            <?= csrf_field(); ?>
            <label class="field">
                <span>Código de barras</span>
                <input type="text" name="barcode" required>
            </label>
            <label class="field">
                <span>Nome</span>
                <input type="text" name="name" required>
            </label>
            <label class="field">
                <span>Preço</span>
                <input type="number" step="0.01" min="0" name="price" required>
            </label>
            <label class="field">
                <span>Estoque inicial</span>
                <input type="number" min="0" name="stock" value="0" required>
            </label>
            <button class="btn btn-primary" type="submit">Cadastrar produto</button>
        </form>

        <form method="post" action="/products/update" class="card stack" id="update-form">
            <div class="card-head">
                <h2>Editar produto</h2>
            </div>
            <?= csrf_field(); ?>
            <input type="hidden" name="id">
            <label class="field">
                <span>Código de barras</span>
                <input type="text" name="barcode" required>
            </label>
            <label class="field">
                <span>Nome</span>
                <input type="text" name="name" required>
            </label>
            <label class="field">
                <span>Preço</span>
                <input type="number" step="0.01" min="0" name="price" required>
            </label>
            <label class="field">
                <span>Estoque atual</span>
                <input type="number" min="0" name="stock" required>
            </label>
            <button class="btn btn-secondary" type="submit">Salvar edição</button>
        </form>
    </article>
</section>
