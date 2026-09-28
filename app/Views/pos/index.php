<section class="pos-layout" data-pos-app>
    <article class="card pos-panel stack">
        <div class="card-head">
            <h2>Caixa</h2>
            <span class="badge">Leitura por código de barras</span>
        </div>

        <label class="field">
            <span>Digite ou escaneie o código</span>
            <div class="barcode-row">
                <input type="text" id="barcode-input" placeholder="789456123..." autocomplete="off" autofocus>
                <button class="btn btn-light" type="button" id="scan-toggle">📷 Câmera</button>
            </div>
        </label>

        <div id="scanner" class="scanner-box hidden">
            <div id="scanner-viewport" class="scanner-viewport"></div>
            <div class="scanner-line"></div>
        </div>
        <div id="pos-feedback" class="feedback-text">Pronto para vender.</div>

        <div class="payment-grid">
            <button class="payment-chip active" data-payment="dinheiro" type="button">Dinheiro</button>
            <button class="payment-chip" data-payment="pix" type="button">Pix</button>
            <button class="payment-chip" data-payment="cartao" type="button">Cartão</button>
        </div>
    </article>

    <article class="card pos-cart">
        <div class="card-head">
            <h2>Carrinho</h2>
            <span id="cart-count" class="badge">0 itens</span>
        </div>

        <div id="cart-items" class="cart-items">
            <p class="empty-state">Nenhum item adicionado.</p>
        </div>

        <div class="total-box">
            <span>Total</span>
            <strong id="cart-total">R$ 0,00</strong>
        </div>

        <button class="btn btn-primary btn-lg" type="button" id="checkout-button">Finalizar venda</button>

        <div id="pix-panel" class="pix-panel hidden">
            <div class="card-head">
                <h3>Pagamento PIX</h3>
                <span id="pix-status-badge" class="badge">Aguardando pagamento</span>
            </div>
            <div class="pix-panel-body">
                <img id="pix-qr-image" alt="QR Code PIX">
                <label class="field">
                    <span>Copia e cola</span>
                    <textarea id="pix-copy-paste" rows="4" readonly></textarea>
                </label>
                <p id="pix-transaction" class="muted"></p>
            </div>
        </div>
    </article>
</section>

<script src="https://cdn.jsdelivr.net/npm/quagga@0.12.1/dist/quagga.min.js"></script>
<script src="<?= asset('assets/js/pos.js'); ?>"></script>
