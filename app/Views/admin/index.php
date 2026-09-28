<section class="grid-cards">
    <article class="card stat-card">
        <span>Usuarios</span>
        <strong><?= (int) $summary['users_total']; ?></strong>
    </article>
    <article class="card stat-card">
        <span>Em teste</span>
        <strong><?= (int) $summary['trial_total']; ?></strong>
    </article>
    <article class="card stat-card">
        <span>Ativos</span>
        <strong><?= (int) $summary['active_total']; ?></strong>
    </article>
    <article class="card stat-card">
        <span>Expirados</span>
        <strong><?= (int) $summary['expired_total']; ?></strong>
    </article>
</section>

<section class="grid-cards">
    <article class="card stat-card">
        <span>Total em assinaturas</span>
        <strong><?= money((float) $summary['subscriptions_lifetime_total']); ?></strong>
        <small>Receita da plataforma</small>
    </article>
</section>

<section class="grid-main">
    <article class="card stack">
        <div class="card-head">
            <h2>Configuracoes da assinatura</h2>
            <span class="badge">PIX do admin</span>
        </div>

        <form method="post" action="<?= url('/admin/settings/update'); ?>" class="stack">
            <?= csrf_field(); ?>

            <div class="page-grid admin-form-grid">
                <label class="field">
                    <span>Token do Mercado Pago</span>
                    <input type="password" name="subscription_access_token" value="" placeholder="Deixe em branco para manter o token atual">
                </label>
                <label class="field">
                    <span>Valor da assinatura</span>
                    <input type="number" name="subscription_amount" min="0.01" step="0.01" value="<?= e((string) ($settings['subscription_amount'] ?? '29.90')); ?>" required>
                </label>
                <label class="field">
                    <span>Dias da assinatura</span>
                    <input type="number" name="subscription_days" min="1" step="1" value="<?= e((string) ($settings['subscription_days'] ?? '30')); ?>" required>
                </label>
                <label class="field">
                    <span>Dias de teste</span>
                    <input type="number" name="trial_days" min="0" step="1" value="<?= e((string) ($settings['trial_days'] ?? '3')); ?>" required>
                </label>
            </div>

            <div class="row-actions">
                <button class="btn btn-primary" type="submit">Salvar configuracoes</button>
            </div>
        </form>

        <div class="banner banner-warning">
            O PIX da assinatura sera sempre gerado com o token configurado aqui. Alterando valor ou dias, os proximos QR Codes de todos os usuarios passam a usar a nova configuracao. Cada QR Code expira em 15 minutos.
        </div>
    </article>

    <article class="card stack">
        <div class="card-head">
            <h2>Assinantes ativos</h2>
            <span class="badge"><?= count($activeSubscribers); ?> usando</span>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Loja</th>
                        <th>Responsavel</th>
                        <th>Expira em</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($activeSubscribers)): ?>
                        <tr>
                            <td colspan="3">Nenhum usuario com assinatura ativa no momento.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($activeSubscribers as $activeUser): ?>
                            <tr>
                                <td><?= e((string) $activeUser['store_name']); ?></td>
                                <td><?= e((string) $activeUser['name']); ?></td>
                                <td><?= !empty($activeUser['plan_expires_at']) ? date('d/m/Y H:i', strtotime((string) $activeUser['plan_expires_at'])) : '-'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>

<section class="grid-main">
    <article class="card stack">
        <div class="card-head">
            <h2>Usuarios</h2>
            <span class="badge">Controle completo</span>
        </div>

        <div class="admin-user-list">
            <?php foreach ($users as $managedUser): ?>
                <div class="gateway-card stack">
                    <form method="post" action="<?= url('/admin/users/update'); ?>" class="stack">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="id" value="<?= (int) $managedUser['id']; ?>">

                        <div class="gateway-card-head">
                            <div>
                                <h3><?= e($managedUser['store_name']); ?></h3>
                                <p class="muted"><?= e($managedUser['email']); ?></p>
                            </div>
                            <span class="badge badge-<?= ($managedUser['plan_status'] ?? '') === 'active' ? 'success' : 'danger'; ?>">
                                <?= e((string) $managedUser['plan_status']); ?>
                            </span>
                        </div>

                        <div class="page-grid admin-form-grid">
                            <label class="field">
                                <span>Loja</span>
                                <input type="text" name="store_name" value="<?= e((string) $managedUser['store_name']); ?>" required>
                            </label>
                            <label class="field">
                                <span>Responsavel</span>
                                <input type="text" name="name" value="<?= e((string) $managedUser['name']); ?>" required>
                            </label>
                            <label class="field">
                                <span>E-mail</span>
                                <input type="email" name="email" value="<?= e((string) $managedUser['email']); ?>" required>
                            </label>
                            <label class="field">
                                <span>Status do plano</span>
                                <select name="plan_status">
                                    <option value="trial" <?= ($managedUser['plan_status'] ?? '') === 'trial' ? 'selected' : ''; ?>>Trial</option>
                                    <option value="active" <?= ($managedUser['plan_status'] ?? '') === 'active' ? 'selected' : ''; ?>>Ativo</option>
                                    <option value="expired" <?= ($managedUser['plan_status'] ?? '') === 'expired' ? 'selected' : ''; ?>>Expirado</option>
                                </select>
                            </label>
                            <label class="field">
                                <span>Expira em</span>
                                <input type="datetime-local" name="plan_expires_at" value="<?= !empty($managedUser['plan_expires_at']) ? date('Y-m-d\TH:i', strtotime($managedUser['plan_expires_at'])) : ''; ?>">
                            </label>
                            <label class="field">
                                <span>Administrador</span>
                                <select name="is_admin">
                                    <option value="0" <?= empty($managedUser['is_admin']) ? 'selected' : ''; ?>>Nao</option>
                                    <option value="1" <?= !empty($managedUser['is_admin']) ? 'selected' : ''; ?>>Sim</option>
                                </select>
                            </label>
                        </div>

                        <div class="row-actions">
                            <span class="muted">Produtos: <?= (int) $managedUser['product_count']; ?> | Vendas: <?= (int) $managedUser['sales_count']; ?> | Receita: <?= money((float) $managedUser['revenue_total']); ?></span>
                        </div>

                        <div class="row-actions">
                            <button class="btn btn-primary" type="submit">Salvar usuario</button>
                        </div>
                    </form>

                    <form method="post" action="<?= url('/admin/users/delete'); ?>" onsubmit="return confirm('Deseja remover este usuario?');">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="id" value="<?= (int) $managedUser['id']; ?>">
                        <button class="btn btn-danger" type="submit">Excluir</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </article>

    <article class="card stack">
        <div class="card-head">
            <h2>Pagamentos de assinatura</h2>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Loja</th>
                        <th>Status</th>
                        <th>Valor</th>
                        <th>Periodo</th>
                        <th>Data</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($subscriptions)): ?>
                        <tr>
                            <td colspan="5">Nenhum pagamento de assinatura encontrado.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($subscriptions as $subscription): ?>
                            <tr>
                                <td><?= e((string) $subscription['store_name']); ?></td>
                                <td><?= e((string) $subscription['status']); ?></td>
                                <td><?= money((float) $subscription['amount']); ?></td>
                                <td><?= (int) $subscription['plan_days']; ?> dias</td>
                                <td><?= date('d/m/Y H:i', strtotime((string) $subscription['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>
