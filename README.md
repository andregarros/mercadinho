# Mercadinho PDV

Sistema web simples para pequenos mercadinhos com:

- autenticacao com login, cadastro e controle de plano
- dashboard com indicadores de vendas e estoque
- CRUD de produtos
- movimentacao de estoque com historico
- caixa estilo PDV com leitura de codigo de barras e fechamento de venda
- integracao PIX multiusuario com Mercado Pago, PagSeguro, Asaas e base preparada para Stripe
- historico e detalhes de vendas
- base preparada para PWA

## Estrutura

- `app/`: controllers, models, core e views
- `config/`: configuracao da aplicacao e rotas
- `database/schema.sql`: estrutura completa do MySQL
- `public/assets/`: CSS, JS e icone do app

## Como rodar

1. Crie o banco usando `database/schema.sql`.
2. Configure as variaveis de ambiente do app e do banco. Em hospedagem compartilhada, copie `.env.example` para `.env` e preencha os valores reais no servidor:

```powershell
$env:APP_ENV="local"
$env:APP_DEBUG="true"
$env:APP_BASE_URL="/"
$env:APP_ENCRYPTION_KEY="troque-por-uma-chave-forte"
$env:APP_FORCE_HTTPS="false"
$env:GEMINI_API_KEY="sua-chave-gratis-do-google-ai-studio"
$env:DB_HOST="127.0.0.1"
$env:DB_PORT="3306"
$env:DB_DATABASE="mercadinhopdv"
$env:DB_USERNAME="garros"
$env:DB_PASSWORD=""
```

Exemplo de `.env` em producao:

```env
APP_ENV=production
APP_DEBUG=false
APP_BASE_URL=/
APP_PUBLIC_URL=https://seudominio.com
APP_FORCE_HTTPS=true
APP_ADMIN_EMAIL=admin@seudominio.com
APP_ENCRYPTION_KEY=gere-uma-chave-aleatoria-forte
GEMINI_API_KEY=sua-chave-gemini
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=nome_do_banco
DB_USERNAME=usuario_do_banco
DB_PASSWORD=senha_do_banco
```
3. Rode com PHP local apontando para a raiz do projeto:

```powershell
php -S localhost:8000 router.php
```

4. Abra `http://localhost:8000`.

## Observacoes

- O front controller fica em `index.php`.
- O `router.php` faz as rotas amigaveis funcionarem no servidor embutido do PHP.
- O scanner usa Quagga2 via CDN na tela do caixa.
- Quando o plano expira, o sistema bloqueia acoes de escrita, mas mantem consulta de dados.
- Cada usuario pode vincular seu proprio gateway em `Vincular PIX`.
- O assistente usa Gemini com free tier via `GEMINI_API_KEY`; sem essa chave, ele responde em modo local com orientacoes basicas do sistema.
- As credenciais ficam criptografadas no banco antes de serem salvas.
- Para usar confirmacao automatica, cadastre exatamente a URL de webhook exibida na tela `Vincular PIX` ou em `Admin`, incluindo o `token` secreto da query string.
- Em producao, mantenha `APP_FORCE_HTTPS=true` para forcar redirecionamento HTTPS e cookie seguro.
- O sistema nao deve iniciar sem `APP_ENCRYPTION_KEY`, e o projeto nao deve manter senhas de banco ou tokens fixos em `config/app.php`.
- As paginas dinamicas agora enviam headers de seguranca como CSP, HSTS (quando em HTTPS), `X-Frame-Options` e `X-Content-Type-Options`.
- O cadastro exige senha forte: minimo de 12 caracteres, com maiuscula, minuscula, numero e simbolo.
- O backend recalcula o valor real da venda a partir do banco e ignora preco enviado pelo navegador.
- Eventos criticos ficam registrados em `storage/logs/app.log`, sem gravar senha ou token em texto puro.
- Segredos de API e banco devem ficar no `.env` ou nas variaveis da hospedagem, nunca no JavaScript, HTML ou arquivos versionados.
- Nao remova o `.htaccess`: ele bloqueia acesso web a `app`, `config`, `database`, `storage`, `.env`, backups e arquivos sensiveis.
- Se o provedor permitir, configure backup automatico fora de `public_html` e proteja o arquivo `storage/app.key`, pois ele e necessario para descriptografar credenciais salvas.

## Backup e restauracao

- Para gerar um backup local do banco e da pasta `storage`, use:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\backup.ps1
```

- O script grava em `storage/backups/<data-hora>/`.
- Para restaurar, importe o `database.sql` no MySQL e recoloque `storage/app.key` e `storage/reports`.

## Site

- Produção: https://garrostech.com
