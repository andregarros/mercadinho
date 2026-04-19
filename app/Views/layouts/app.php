<?php
$flash = get_flash();
$user = $currentUser;
$isExpired = ($user['plan_status'] ?? '') === 'expired';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#17423c">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title><?= e(app_name()); ?></title>
    <link rel="manifest" href="<?= url('/manifest.webmanifest') ?>">
    <link rel="icon" href="<?= asset('assets/icons/icon.svg'); ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= asset('assets/css/app.css'); ?>">
    <script>
        window.APP_CSRF = '<?= e(csrf_token()); ?>';
        window.APP_BASE_URL = '<?= e(rtrim(config('app.base_url', '/'), '/')); ?>';
    </script>
</head>
<body>
    <div class="app-shell">
        <aside class="sidebar">
            <div>
                <div class="brand">
                    <div class="brand-mark">M</div>
                    <div>
                        <strong><?= e($user['store_name'] ?? 'Mercadinho'); ?></strong>
                        <small><?= e($user['plan_status'] ?? 'trial'); ?></small>
                    </div>
                </div>

                <nav class="nav-list">
                    <a class="<?= is_active_path('/dashboard') || is_active_path('/') ? 'active' : ''; ?>" href="<?= url('/dashboard') ?>">Dashboard</a>
                    <a class="<?= is_active_path('/pos') ? 'active' : ''; ?>" href="<?= url('/pos') ?>">Caixa</a>
                    <a class="<?= is_active_path('/products') ? 'active' : ''; ?>" href="<?= url('/products') ?>">Produtos</a>
                    <a class="<?= is_active_path('/stock') ? 'active' : ''; ?>" href="<?= url('/stock') ?>">Estoque</a>
                    <a class="<?= is_active_path('/sales') ? 'active' : ''; ?>" href="<?= url('/sales') ?>">Vendas</a>
                </nav>
            </div>

            <form method="post" action="<?= url('/logout') ?>">
                <?= csrf_field(); ?>
                <button class="btn btn-light w-full" type="submit">Sair</button>
            </form>
        </aside>

        <main class="main-content">
            <header class="topbar">
                <div>
                    <h1><?= e(app_name()); ?></h1>
                    <p>Operação rápida para pequenos mercadinhos.</p>
                </div>
                <div class="topbar-actions">
                    <span class="badge badge-<?= $isExpired ? 'danger' : 'success'; ?>">
                        Plano: <?= e($user['plan_status'] ?? 'trial'); ?>
                    </span>
                    <?php if (!empty($user['plan_expires_at'])): ?>
                        <span class="muted">Até <?= date('d/m/Y', strtotime($user['plan_expires_at'])); ?></span>
                    <?php endif; ?>
                </div>
            </header>

            <?php if ($isExpired): ?>
                <div class="banner banner-warning">
                    Seu plano expirou. Consultas continuam liberadas, mas vendas, cadastro e estoque estão bloqueados.
                </div>
            <?php endif; ?>

            <?php if ($flash): ?>
                <div class="toast toast-<?= e($flash['type']); ?>"><?= e($flash['message']); ?></div>
            <?php endif; ?>

            <?php require $contentView; ?>
        </main>
    </div>

    <script src="<?= asset('assets/js/app.js'); ?>"></script>
</body>
</html>
