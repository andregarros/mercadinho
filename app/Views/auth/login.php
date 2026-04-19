<div class="section-heading">
    <h2>Entrar</h2>
    <p>Acesse seu caixa e estoque em segundos.</p>
</div>

<form method="post" action="/login" class="stack">
    <?= csrf_field(); ?>
    <label class="field">
        <span>E-mail</span>
        <input type="email" name="email" placeholder="voce@mercadinho.com" required>
    </label>
    <label class="field">
        <span>Senha</span>
        <input type="password" name="password" placeholder="Digite sua senha" required>
    </label>
    <button class="btn btn-primary" type="submit">Entrar</button>
</form>

<p class="auth-link">Ainda não tem conta? <a href="/register">Criar mercadinho</a></p>
