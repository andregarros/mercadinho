const barcodeInput = document.getElementById('barcode-input');
const cartItemsContainer = document.getElementById('cart-items');
const cartCount = document.getElementById('cart-count');
const cartTotal = document.getElementById('cart-total');
const feedback = document.getElementById('pos-feedback');
const checkoutButton = document.getElementById('checkout-button');
const scanToggle = document.getElementById('scan-toggle');
const scannerBox = document.getElementById('scanner');

if (barcodeInput && cartItemsContainer) {
    let cart = [];
    let paymentMethod = 'dinheiro';
    let scannerActive = false;
    let lastScannedCode = '';
    let lastScanTime = 0;

    const money = (value) => value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

    const beep = () => {
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioContext.createOscillator();
        const gain = audioContext.createGain();

        oscillator.type = 'sine';
        oscillator.frequency.value = 880;
        oscillator.connect(gain);
        gain.connect(audioContext.destination);
        gain.gain.value = 0.05;
        oscillator.start();
        oscillator.stop(audioContext.currentTime + 0.12);
    };

    const updateFeedback = (message, type = 'info') => {
        feedback.textContent = message;
        feedback.style.color = type === 'error' ? '#c44536' : '#6d7b78';
    };

    const renderCart = () => {
        if (cart.length === 0) {
            cartItemsContainer.innerHTML = '<p class="empty-state">Nenhum item adicionado.</p>';
            cartCount.textContent = '0 itens';
            cartTotal.textContent = money(0);
            return;
        }

        cartItemsContainer.innerHTML = cart.map((item) => `
            <div class="cart-item">
                <div>
                    <strong>${item.name}</strong>
                    <small>${item.barcode} • ${money(Number(item.price))}</small>
                </div>
                <div class="qty-controls">
                    <button class="qty-button" type="button" data-action="decrease" data-id="${item.product_id}">-</button>
                    <span>${item.quantity}</span>
                    <button class="qty-button" type="button" data-action="increase" data-id="${item.product_id}">+</button>
                    <button class="qty-button" type="button" data-action="remove" data-id="${item.product_id}">x</button>
                </div>
            </div>
        `).join('');

        cartItemsContainer.querySelectorAll('[data-action]').forEach((button) => {
            button.addEventListener('click', () => {
                const id = Number(button.dataset.id);
                const item = cart.find((entry) => entry.product_id === id);
                if (!item) return;

                if (button.dataset.action === 'increase') item.quantity += 1;
                if (button.dataset.action === 'decrease') item.quantity = Math.max(1, item.quantity - 1);
                if (button.dataset.action === 'remove') cart = cart.filter((entry) => entry.product_id !== id);
                renderCart();
            });
        });

        const totalQuantity = cart.reduce((sum, item) => sum + item.quantity, 0);
        const totalAmount = cart.reduce((sum, item) => sum + (Number(item.price) * item.quantity), 0);
        cartCount.textContent = `${totalQuantity} item(ns)`;
        cartTotal.textContent = money(totalAmount);
    };

    const addToCart = (product) => {
        const existing = cart.find((item) => item.product_id === Number(product.id));
        if (existing) {
            existing.quantity += 1;
        } else {
            cart.push({
                product_id: Number(product.id),
                barcode: product.barcode,
                name: product.name,
                price: Number(product.price),
                quantity: 1,
            });
        }

        beep();
        updateFeedback(`✓ ${product.name} adicionado!`);
        renderCart();
    };

    const fetchProduct = async (barcode) => {
        if (!barcode) return;

        try {
            const response = await fetch(`${window.APP_BASE_URL}/pos/product?barcode=${encodeURIComponent(barcode)}`);
            const data = await response.json();

            if (!response.ok || !data.success) {
                updateFeedback(data.message || 'Produto não encontrado.', 'error');
                return;
            }

            addToCart(data.product);
            barcodeInput.value = '';
        } catch (error) {
            updateFeedback('Erro ao buscar o produto.', 'error');
        }
    };

    barcodeInput.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            fetchProduct(barcodeInput.value.trim());
        }
    });

    document.querySelectorAll('.payment-chip').forEach((chip) => {
        chip.addEventListener('click', () => {
            paymentMethod = chip.dataset.payment;
            document.querySelectorAll('.payment-chip').forEach((button) => button.classList.remove('active'));
            chip.classList.add('active');
        });
    });

    checkoutButton.addEventListener('click', async () => {
        if (cart.length === 0) {
            updateFeedback('Adicione produtos antes de finalizar.', 'error');
            return;
        }

        checkoutButton.disabled = true;
        checkoutButton.textContent = 'Finalizando...';

        try {
            const response = await fetch(`${window.APP_BASE_URL}/pos/checkout`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    csrf_token: window.APP_CSRF,
                    payment_method: paymentMethod,
                    items: cart,
                }),
            });

            const data = await response.json();
            if (!response.ok || !data.success) {
                updateFeedback(data.message || 'Não foi possível concluir a venda.', 'error');
                return;
            }

            cart = [];
            renderCart();
            updateFeedback(`${data.message} Venda #${data.sale_id}.`);
        } catch (error) {
            updateFeedback('Falha ao concluir a venda.', 'error');
        } finally {
            checkoutButton.disabled = false;
            checkoutButton.textContent = 'Finalizar venda';
        }
    });

    const stopScanner = () => {
        if (window.scannerStream) {
            window.scannerStream.getTracks().forEach(track => track.stop());
            window.scannerStream = null;
        }
        window.scannerContinue = false;
        scannerActive = false;
        scannerBox.innerHTML = '';
        scannerBox.classList.add('hidden');
        scanToggle.textContent = '📷 Câmera';
        barcodeInput.focus();
    };

    const startScanner = async () => {
        try {
            if (typeof ZXing === 'undefined') {
                console.error('ZXing não carregou');
                updateFeedback('⚠️ Scanner indisponível. Digite o código manualmente.', 'error');
                barcodeInput.focus();
                return;
            }

            console.log('Iniciando scanner com ZXing...');
            scannerBox.classList.remove('hidden');
            scannerBox.innerHTML = '<video id="qr-video" style="width:100%;height:100%;border-radius:14px;"></video>';

            const video = document.getElementById('qr-video');
            const codeReader = new ZXing.BrowserMultiFormatReader();

            window.scannerContinue = true;
            
            const constraints = {
                video: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 960 } },
                audio: false,
            };

            console.log('Solicitando acesso à câmera...');
            const stream = await navigator.mediaDevices.getUserMedia(constraints);
            window.scannerStream = stream;
            video.srcObject = stream;

            await new Promise(resolve => {
                video.onloadedmetadata = resolve;
            });

            scannerActive = true;
            scanToggle.textContent = '❌ Fechar';
            updateFeedback('📷 Câmera aberta. Aponte o código para ler.');
            console.log('Scanner iniciado com sucesso');

            const detectBarcode = async () => {
                if (!window.scannerContinue || !scannerActive) return;

                try {
                    const canvas = document.createElement('canvas');
                    const { videoWidth, videoHeight } = video;
                    
                    if (videoWidth === 0 || videoHeight === 0) {
                        requestAnimationFrame(detectBarcode);
                        return;
                    }

                    canvas.width = videoWidth;
                    canvas.height = videoHeight;

                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(video, 0, 0, videoWidth, videoHeight);

                    const luminanceSource = new ZXing.HTMLCanvasElementLuminanceSource(canvas);
                    const binaryBitmap = new ZXing.BinaryBitmap(new ZXing.HybridBinarizer(luminanceSource));

                    try {
                        const result = codeReader.decodeFromBitmap(binaryBitmap);
                        if (result) {
                            const code = result.getText();

                            if (code) {
                                const now = Date.now();
                                if (code === lastScannedCode && now - lastScanTime < 800) {
                                    requestAnimationFrame(detectBarcode);
                                    return;
                                }

                                lastScannedCode = code;
                                lastScanTime = now;

                                console.log('✓ Código detectado:', code);
                                updateFeedback(`✓ Lido: ${code}`);
                                fetchProduct(code);
                                window.scannerContinue = false;
                                return;
                            }
                        }
                    } catch (e) {
                        // Sem código detectado, continuar
                    }
                } catch (err) {
                    console.error('Erro na detecção:', err);
                    window.scannerContinue = false;
                }

                if (window.scannerContinue) {
                    requestAnimationFrame(detectBarcode);
                }
            };

            detectBarcode();
        } catch (error) {
            console.error('Erro ao iniciar câmera:', error);
            
            let msg = 'Erro ao acessar câmera.';
            if (error.name === 'NotAllowedError') {
                msg = '⚠️ Permissão negada. Ative câmera nas configurações do navegador.';
            } else if (error.name === 'NotFoundError') {
                msg = '⚠️ Câmera não encontrada no dispositivo.';
            }
            
            updateFeedback(msg, 'error');
            stopScanner();
            barcodeInput.focus();
        }
    };

    scanToggle.addEventListener('click', () => {
        if (scannerActive) {
            stopScanner();
        } else {
            startScanner();
        }
    });

    renderCart();
    startScanner();
}
