<?php $flash = get_flash(); ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#17423c">
    <title><?= e(app_name()); ?></title>
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="<?= asset('assets/icons/icon.svg'); ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= asset('assets/css/app.css'); ?>">
</head>
<body class="auth-shell">
    <main class="auth-card">
        <section class="auth-brand">
            <span class="badge">PDV simples</span>
            <h1><?= e(app_name()); ?></h1>
            <p>Vendas, estoque e produtos em uma experiência rápida, limpa e pronta para celular.</p>
        </section>

        <section class="auth-panel">
            <?php if ($flash): ?>
                <div class="toast toast-<?= e($flash['type']); ?>"><?= e($flash['message']); ?></div>
            <?php endif; ?>
            <?php require $contentView; ?>
        </section>
    </main>
    <script src="<?= asset('assets/js/app.js'); ?>"></script>
</body>
</html>
