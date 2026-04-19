# Mercadinho PDV

Sistema web simples para pequenos mercadinhos com:

- autenticacao com login, cadastro e controle de plano
- dashboard com indicadores de vendas e estoque
- CRUD de produtos
- movimentacao de estoque com historico
- caixa estilo PDV com leitura de codigo de barras e fechamento de venda
- historico e detalhes de vendas
- base preparada para PWA

## Estrutura

- `app/`: controllers, models, core e views
- `config/`: configuracao da aplicacao e rotas
- `database/schema.sql`: estrutura completa do MySQL
- `public/assets/`: CSS, JS e icone do app

## Como rodar

1. Crie o banco usando `database/schema.sql`.
2. Ajuste credenciais em `config/app.php`.
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
