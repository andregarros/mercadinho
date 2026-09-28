const barcodeInput = document.getElementById('barcode-input');
const cartItemsContainer = document.getElementById('cart-items');
const cartCount = document.getElementById('cart-count');
const cartTotal = document.getElementById('cart-total');
const feedback = document.getElementById('pos-feedback');
const checkoutButton = document.getElementById('checkout-button');
const scanToggle = document.getElementById('scan-toggle');
const scannerBox = document.getElementById('scanner');
const scannerViewport = document.getElementById('scanner-viewport');
const pixPanel = document.getElementById('pix-panel');
const pixQrImage = document.getElementById('pix-qr-image');
const pixCopyPaste = document.getElementById('pix-copy-paste');
const pixStatusBadge = document.getElementById('pix-status-badge');
const pixTransaction = document.getElementById('pix-transaction');

let cart = [];
let paymentMethod = 'dinheiro';
let scannerActive = false;
let quaggaInitialized = false;
let lastBarcode = '';
let lastScanTime = 0;
let pendingDetectedBarcode = '';
let pendingDetectedCount = 0;
let audioContext = null;
let paymentStatusInterval = null;
let scannerBooting = false;
let scannerStream = null;
let preferredDeviceId = null;
let scannerProcessing = false;

const SCAN_DEBOUNCE_MS = 700;
const REQUIRED_MATCHES = 1;
const PAYMENT_POLL_MS = 4000;
const SCANNER_AREA = {
    top: '34%',
    right: '3%',
    left: '3%',
    bottom: '34%',
};
const SCANNER_CONSTRAINT_SETS = [
    {
        width: { ideal: 2560, min: 1920 },
        height: { ideal: 1440, min: 1080 },
        aspectRatio: { ideal: 1.7777777778 },
        frameRate: { ideal: 24, min: 20 },
        facingMode: { ideal: 'environment' },
    },
    {
        width: { ideal: 1920, min: 1280 },
        height: { ideal: 1080, min: 720 },
        aspectRatio: { ideal: 1.7777777778 },
        frameRate: { ideal: 24, min: 20 },
        facingMode: { ideal: 'environment' },
    },
    {
        width: { ideal: 1280, min: 960 },
        height: { ideal: 720, min: 540 },
        frameRate: { ideal: 20, min: 15 },
        facingMode: 'environment',
    },
];

const isAndroid = () => /Android/i.test(navigator.userAgent);

const getScannerConstraintSets = () => {
    if (isAndroid()) {
        return [
            {
                width: { ideal: 1280, min: 960 },
                height: { ideal: 720, min: 540 },
                aspectRatio: { ideal: 1.7777777778 },
                frameRate: { ideal: 30, min: 24 },
                facingMode: { ideal: 'environment' },
            },
            {
                width: { ideal: 960, min: 720 },
                height: { ideal: 540, min: 480 },
                frameRate: { ideal: 24, min: 20 },
                facingMode: { ideal: 'environment' },
            },
            {
                width: { ideal: 640, min: 640 },
                height: { ideal: 480, min: 480 },
                frameRate: { ideal: 24, min: 15 },
                facingMode: 'environment',
            },
        ];
    }

    return SCANNER_CONSTRAINT_SETS;
};

const SUPPORTED_FORMATS = new Set([
    'ean_13',
    'ean_8',
    'upc_a',
    'upc_e',
    'code_128',
]);

const money = (value) => value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

const getAudioContext = async () => {
    const AudioContextClass = window.AudioContext || window.webkitAudioContext;
    if (!AudioContextClass) {
        return null;
    }

    if (!audioContext) {
        audioContext = new AudioContextClass();
    }

    if (audioContext.state === 'suspended') {
        await audioContext.resume();
    }

    return audioContext;
};

const beep = (frequency = 1000, duration = 100) => {
    getAudioContext().then((ctx) => {
        if (!ctx) {
            return;
        }

        const oscillator = ctx.createOscillator();
        const gainNode = ctx.createGain();

        oscillator.frequency.value = frequency;
        oscillator.connect(gainNode);
        gainNode.connect(ctx.destination);
        gainNode.gain.value = 0.2;

        oscillator.start();
        oscillator.stop(ctx.currentTime + duration / 1000);
    }).catch((error) => {
        console.debug('Nao foi possivel tocar o beep.', error);
    });
};

const updateFeedback = (message, isError = false) => {
    feedback.textContent = message;
    feedback.style.color = isError ? '#c44536' : '#6d7b78';
};

const clearPixPanel = () => {
    if (paymentStatusInterval) {
        window.clearInterval(paymentStatusInterval);
        paymentStatusInterval = null;
    }

    pixPanel.classList.add('hidden');
    pixQrImage.removeAttribute('src');
    pixCopyPaste.value = '';
    pixTransaction.textContent = '';
    pixStatusBadge.textContent = 'Aguardando pagamento';
    pixStatusBadge.classList.remove('badge-success', 'badge-danger');
};

const renderCart = () => {
    if (!cart.length) {
        cartItemsContainer.innerHTML = '<p class="empty-state">Nenhum item adicionado.</p>';
        cartCount.textContent = '0 itens';
        cartTotal.textContent = money(0);
        checkoutButton.disabled = true;
        return;
    }

    cartItemsContainer.innerHTML = cart.map((item) => `
        <div class="cart-item">
            <div class="item-info">
                <h4>${item.name}</h4>
                <p>${money(item.price)} x ${item.quantity}</p>
            </div>
            <div class="item-controls">
                <button onclick="updateQuantity('${item.barcode}', ${item.quantity - 1})">-</button>
                <span>${item.quantity}</span>
                <button onclick="updateQuantity('${item.barcode}', ${item.quantity + 1})">+</button>
                <button onclick="removeFromCart('${item.barcode}')" class="remove-btn">x</button>
            </div>
        </div>
    `).join('');

    const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    const quantity = cart.reduce((sum, item) => sum + item.quantity, 0);

    cartCount.textContent = `${quantity} ${quantity === 1 ? 'item' : 'itens'}`;
    cartTotal.textContent = money(total);
    checkoutButton.disabled = false;
};

const addToCart = (product) => {
    const existing = cart.find((item) => item.barcode === product.barcode);

    if (existing) {
        existing.quantity += 1;
    } else {
        cart.push({ ...product, quantity: 1 });
    }

    renderCart();
};

const fetchProduct = async (barcode) => {
    try {
        const response = await fetch(`${window.APP_BASE_URL}/pos/product?barcode=${encodeURIComponent(barcode)}`);
        const data = await response.json();

        if (!response.ok || !data.success) {
            updateFeedback(data.message || 'Produto nao encontrado.', true);
            beep(400, 300);
            return false;
        }

        addToCart(data.product);
        updateFeedback(`${data.product.name} adicionado ao carrinho.`);
        beep(1200, 100);
        beep(1500, 100);
        return true;
    } catch (error) {
        console.error(error);
        updateFeedback('Erro ao buscar produto.', true);
        beep(400, 300);
        return false;
    }
};

const processBarcode = async (barcode) => {
    const normalizedBarcode = String(barcode || '').trim();

    if (!normalizedBarcode) {
        return;
    }

    const now = Date.now();
    if (normalizedBarcode === lastBarcode && now - lastScanTime < SCAN_DEBOUNCE_MS) {
        return;
    }

    lastBarcode = normalizedBarcode;
    lastScanTime = now;
    barcodeInput.value = normalizedBarcode;

    const added = await fetchProduct(normalizedBarcode);
    if (added) {
        barcodeInput.value = '';
    } else {
        barcodeInput.select();
    }
};

const isLikelyBarcode = (code, format) => {
    const normalizedCode = String(code || '').trim();

    if (!normalizedCode || !SUPPORTED_FORMATS.has(format)) {
        return false;
    }

    return /^\d{8,14}$/.test(normalizedCode);
};

const isIOS = () => /iPad|iPhone|iPod/.test(navigator.userAgent)
    || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

const buildConstraints = (baseConstraints) => {
    if (preferredDeviceId) {
        return {
            ...baseConstraints,
            deviceId: { exact: preferredDeviceId },
        };
    }

    return baseConstraints;
};

const chooseRearCamera = async () => {
    if (!navigator.mediaDevices?.enumerateDevices) {
        return null;
    }

    const devices = await navigator.mediaDevices.enumerateDevices();
    const videoInputs = devices.filter((device) => device.kind === 'videoinput');

    if (!videoInputs.length) {
        return null;
    }

    const rankedRear = videoInputs.find((device) => /back|rear|traseira|environment/i.test(device.label))
        ?? videoInputs.find((device) => /wide|ultra|1x/i.test(device.label))
        ?? videoInputs[videoInputs.length - 1];

    preferredDeviceId = rankedRear?.deviceId || null;
    return preferredDeviceId;
};

const getActiveVideoTrack = () => scannerStream?.getVideoTracks?.()[0] ?? null;

const pushAdvancedConstraint = (advanced, capabilities, key, value) => {
    if (!(key in capabilities)) {
        return;
    }

    const capability = capabilities[key];

    if (Array.isArray(capability)) {
        if (capability.includes(value)) {
            advanced.push({ [key]: value });
        }
        return;
    }

    if (typeof capability?.min === 'number' && typeof capability?.max === 'number') {
        const numericValue = Math.min(capability.max, Math.max(capability.min, Number(value)));
        advanced.push({ [key]: numericValue });
    }
};

const enhanceActiveCamera = async () => {
    const videoTrack = getActiveVideoTrack();
    if (!videoTrack?.getCapabilities) {
        return;
    }

    const capabilities = videoTrack.getCapabilities();
    const advanced = [];

    pushAdvancedConstraint(advanced, capabilities, 'focusMode', 'continuous');
    pushAdvancedConstraint(advanced, capabilities, 'exposureMode', 'continuous');
    pushAdvancedConstraint(advanced, capabilities, 'whiteBalanceMode', 'continuous');

    if (typeof capabilities.zoom?.max === 'number' && capabilities.zoom.max > 1) {
        const preferredZoom = 1;
        pushAdvancedConstraint(advanced, capabilities, 'zoom', preferredZoom);
    }

    pushAdvancedConstraint(advanced, capabilities, 'sharpness', 1);
    pushAdvancedConstraint(advanced, capabilities, 'contrast', 1);
    pushAdvancedConstraint(advanced, capabilities, 'saturation', 1);

    if (capabilities.torch && !isIOS()) {
        advanced.push({ torch: false });
    }

    if (!advanced.length) {
        return;
    }

    try {
        await videoTrack.applyConstraints({ advanced });
    } catch (error) {
        console.debug('Nao foi possivel otimizar a camera do scanner.', error);
    }
};

const prepareScannerVideo = async () => {
    const video = scannerViewport.querySelector('video');
    if (!video) {
        return;
    }

    video.setAttribute('autoplay', 'true');
    video.setAttribute('muted', 'true');
    video.setAttribute('playsinline', 'true');
    video.setAttribute('webkit-playsinline', 'true');

    try {
        await video.play();
    } catch (error) {
        console.debug('O navegador bloqueou o play imediato da camera.', error);
    }
};

const waitForScannerVideo = () => new Promise((resolve) => {
    const existingVideo = scannerViewport.querySelector('video');
    if (existingVideo) {
        resolve(existingVideo);
        return;
    }

    const observer = new MutationObserver(() => {
        const video = scannerViewport.querySelector('video');
        if (!video) {
            return;
        }

        observer.disconnect();
        resolve(video);
    });

    observer.observe(scannerViewport, { childList: true, subtree: true });

    window.setTimeout(() => {
        observer.disconnect();
        resolve(scannerViewport.querySelector('video'));
    }, 2000);
});

const updateQuantity = (barcode, quantity) => {
    if (quantity <= 0) {
        removeFromCart(barcode);
        return;
    }

    const item = cart.find((cartItem) => cartItem.barcode === barcode);
    if (!item) {
        return;
    }

    item.quantity = quantity;
    renderCart();
};

const removeFromCart = (barcode) => {
    cart = cart.filter((item) => item.barcode !== barcode);
    renderCart();
};

const setPixStatus = (status) => {
    const normalizedStatus = String(status || 'pending').toLowerCase();
    pixStatusBadge.classList.remove('badge-success', 'badge-danger');

    if (normalizedStatus === 'paid') {
        pixStatusBadge.textContent = 'Pago';
        pixStatusBadge.classList.add('badge-success');
        return;
    }

    if (normalizedStatus === 'expired') {
        pixStatusBadge.textContent = 'Expirado';
        pixStatusBadge.classList.add('badge-danger');
        return;
    }

    if (normalizedStatus === 'cancelled') {
        pixStatusBadge.textContent = 'Cancelado';
        pixStatusBadge.classList.add('badge-danger');
        return;
    }

    if (normalizedStatus === 'failed') {
        pixStatusBadge.textContent = 'Falhou';
        pixStatusBadge.classList.add('badge-danger');
        return;
    }

    pixStatusBadge.textContent = 'Aguardando pagamento';
};

const renderPixPanel = (payment, saleId) => {
    if (!payment) {
        return;
    }

    pixPanel.classList.remove('hidden');
    pixQrImage.src = payment.qr_code_image || '';
    pixCopyPaste.value = payment.pix_copy_paste || '';
    pixTransaction.textContent = `Venda #${saleId} | Transacao ${payment.transaction_id}`;
    setPixStatus(payment.status);
};

const pollPaymentStatus = (saleId) => {
    if (paymentStatusInterval) {
        window.clearInterval(paymentStatusInterval);
    }

    paymentStatusInterval = window.setInterval(async () => {
        try {
            const response = await fetch(`${window.APP_BASE_URL}/payments/status?sale_id=${encodeURIComponent(saleId)}`);
            const data = await response.json();

            if (!response.ok || !data.success) {
                return;
            }

            setPixStatus(data.sale.payment_status || data.payment.status);

            if ((data.sale.payment_status || data.payment.status) === 'paid') {
                window.clearInterval(paymentStatusInterval);
                paymentStatusInterval = null;
                updateFeedback(`PIX confirmado para a venda #${saleId}.`);
                beep(1300, 100);
                beep(1600, 120);
            }
        } catch (error) {
            console.debug('Falha ao consultar status do pagamento PIX.', error);
        }
    }, PAYMENT_POLL_MS);
};

const checkout = async () => {
    if (!cart.length) {
        updateFeedback('Carrinho vazio.', true);
        return;
    }

    const items = cart.map((item) => ({
        product_id: item.id,
        quantity: item.quantity,
        price: item.price,
    }));

    try {
        const response = await fetch(`${window.APP_BASE_URL}/pos/checkout`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                items,
                payment_method: paymentMethod,
                csrf_token: window.APP_CSRF,
            }),
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            updateFeedback(data.message || 'Erro ao finalizar venda.', true);
            beep(400, 300);
            return;
        }

        if (paymentMethod === 'pix') {
            renderPixPanel(data.payment, data.sale_id);
            pollPaymentStatus(data.sale_id);
            updateFeedback(data.message || 'PIX gerado com sucesso.');
        } else {
            clearPixPanel();
            updateFeedback(`Venda finalizada com sucesso. ID: ${data.sale_id}`);
        }

        cart = [];
        renderCart();
        beep(1200, 100);
        beep(1500, 100);
        barcodeInput.focus();
    } catch (error) {
        console.error(error);
        updateFeedback('Erro de conexao.', true);
        beep(400, 300);
    }
};

const handleDetectedBarcode = async (result) => {
    const code = result?.codeResult?.code;
    const format = result?.codeResult?.format;

    if (!code || !scannerActive || scannerProcessing) {
        return;
    }

    if (!isLikelyBarcode(code, format)) {
        return;
    }

    if (code !== pendingDetectedBarcode) {
        pendingDetectedBarcode = code;
        pendingDetectedCount = 1;
        if (REQUIRED_MATCHES <= 1) {
            pendingDetectedBarcode = '';
            pendingDetectedCount = 0;
            scannerProcessing = true;

            try {
                await processBarcode(code);
            } finally {
                window.setTimeout(() => {
                    scannerProcessing = false;
                }, SCAN_DEBOUNCE_MS);
            }
        }
        return;
    }

    pendingDetectedCount += 1;
    if (pendingDetectedCount < REQUIRED_MATCHES) {
        return;
    }

    pendingDetectedBarcode = '';
    pendingDetectedCount = 0;
    scannerProcessing = true;

    try {
        await processBarcode(code);
    } finally {
        window.setTimeout(() => {
            scannerProcessing = false;
        }, SCAN_DEBOUNCE_MS);
    }
};

const resetScannerViewport = () => {
    scannerViewport.innerHTML = '';
};

const initQuagga = async () => {
    if (typeof Quagga === 'undefined') {
        throw new Error('Biblioteca Quagga nao foi carregada.');
    }

    resetScannerViewport();

    let lastError = null;

    for (const constraintSet of getScannerConstraintSets()) {
        try {
            await new Promise((resolve, reject) => {
                Quagga.init({
                    inputStream: {
                        name: 'Live',
                        type: 'LiveStream',
                        target: scannerViewport,
                        constraints: buildConstraints(constraintSet),
                        area: SCANNER_AREA,
                    },
                    locator: {
                        patchSize: isAndroid() ? 'large' : (window.innerWidth < 768 ? 'medium' : 'small'),
                        halfSample: false,
                    },
                    numOfWorkers: Math.max(2, Math.min(navigator.hardwareConcurrency || 4, 4)),
                    frequency: isAndroid() ? 24 : 18,
                    decoder: {
                        multiple: false,
                        readers: [
                            'ean_reader',
                            'ean_8_reader',
                            'code_128_reader',
                            'upc_reader',
                            'upc_e_reader',
                        ],
                    },
                    locate: true,
                }, (error) => {
                    if (error) {
                        reject(error);
                        return;
                    }

                    if (!quaggaInitialized) {
                        Quagga.onDetected(handleDetectedBarcode);
                    }

                    quaggaInitialized = true;
                    resolve();
                });
            });

            return;
        } catch (error) {
            lastError = error;
            resetScannerViewport();
        }
    }

    throw lastError ?? new Error('Nao foi possivel iniciar a camera.');
};

const startScanner = async () => {
    if (scannerActive || scannerBooting) {
        return;
    }

    scannerBooting = true;

    try {
        scannerBox.classList.remove('hidden');
        updateFeedback('Preparando camera traseira para leitura rapida do codigo.');

        await chooseRearCamera();

        await initQuagga();
        Quagga.start();
        await waitForScannerVideo();
        scannerStream = Quagga.CameraAccess?.getActiveStream?.() ?? null;
        await prepareScannerVideo();
        await enhanceActiveCamera();

        scannerActive = true;
        scannerBox.dataset.ready = 'true';
        scanToggle.textContent = 'Fechar camera';
        updateFeedback('Camera ativa. Centralize o codigo na faixa para adicionar ao carrinho.');
    } catch (error) {
        console.error('Erro ao iniciar leitura de codigo de barras:', error);
        scannerActive = false;
        scannerBox.dataset.ready = 'false';
        scannerBox.classList.add('hidden');
        updateFeedback(`Camera nao disponivel: ${error.message}`, true);
        beep(400, 300);
    } finally {
        scannerBooting = false;
    }
};

const stopScanner = () => {
    if (typeof Quagga !== 'undefined' && quaggaInitialized) {
        Quagga.stop();
    }

    pendingDetectedBarcode = '';
    pendingDetectedCount = 0;
    scannerProcessing = false;
    scannerStream = null;
    resetScannerViewport();
    scannerActive = false;
    scannerBox.dataset.ready = 'false';
    scannerBox.classList.add('hidden');
    scanToggle.textContent = 'Camera';
    barcodeInput.focus();
};

barcodeInput.addEventListener('keydown', async (event) => {
    if (event.key !== 'Enter') {
        return;
    }

    event.preventDefault();
    await processBarcode(barcodeInput.value);
});

scanToggle.addEventListener('click', () => {
    if (scannerActive) {
        stopScanner();
        return;
    }

    beep(900, 80);
    startScanner();
});

checkoutButton.addEventListener('click', checkout);

document.querySelectorAll('.payment-chip').forEach((chip) => {
    chip.addEventListener('click', () => {
        document.querySelectorAll('.payment-chip').forEach((button) => button.classList.remove('active'));
        chip.classList.add('active');
        paymentMethod = chip.dataset.payment;

        if (paymentMethod !== 'pix') {
            clearPixPanel();
        }
    });
});

window.updateQuantity = updateQuantity;
window.removeFromCart = removeFromCart;

document.addEventListener('DOMContentLoaded', () => {
    clearPixPanel();
    renderCart();
    barcodeInput.focus();
});

document.addEventListener('click', () => {
    getAudioContext().catch(() => {});
}, { once: true });
