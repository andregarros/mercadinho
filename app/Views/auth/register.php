<div class="section-heading">
    <h2>Criar conta</h2>
    <p>Comece com trial de 14 dias para testar o sistema.</p>
</div>

<form method="post" action="/register" class="stack">
    <?= csrf_field(); ?>
    <label class="field">
        <span>Nome do mercadinho</span>
        <input type="text" name="store_name" placeholder="Mercadinho da Esquina" required>
    </label>
    <label class="field">
        <span>Responsável</span>
        <input type="text" name="name" placeholder="Seu nome" required>
    </label>
    <label class="field">
        <span>E-mail</span>
        <input type="email" name="email" placeholder="contato@mercadinho.com" required>
    </label>
    <label class="field">
        <span>Senha</span>
        <input type="password" name="password" minlength="6" placeholder="Mínimo 6 caracteres" required>
    </label>
    <button class="btn btn-primary" type="submit">Criar conta</button>
</form>

<p class="auth-link">Já tem conta? <a href="/login">Fazer login</a></p>
