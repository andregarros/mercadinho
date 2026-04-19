document.querySelectorAll('.js-fill-form').forEach((button) => {
    button.addEventListener('click', () => {
        const target = document.querySelector(button.dataset.target);
        if (!target) return;

        ['id', 'barcode', 'name', 'price', 'stock'].forEach((field) => {
            const input = target.querySelector(`[name="${field}"]`);
            if (input) {
                input.value = button.dataset[field] || '';
            }
        });

        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
});

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/service-worker.js').catch(() => null);
    });
}
