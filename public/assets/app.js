document.querySelectorAll('form[data-confirm]').forEach(form => form.addEventListener('submit', event => {
    if (!confirm(form.dataset.confirm)) event.preventDefault();
}));
document.querySelectorAll('[data-print]').forEach(button => button.addEventListener('click', () => window.print()));
const product = document.getElementById('product_id');
const quantity = document.getElementById('quantity');
if (product && quantity) {
    const money = cents => new Intl.NumberFormat('en-PH', {style:'currency', currency:'PHP'}).format(cents/100);
    const update = () => {
        const selected = product.selectedOptions[0];
        const cents = Number(selected?.dataset.price || 0);
        const stock = Number(selected?.dataset.stock || 0);
        const qty = Math.max(0, Math.floor(Number(quantity.value) || 0));
        document.getElementById('unit-price').textContent = money(cents);
        document.getElementById('summary-quantity').textContent = qty;
        document.getElementById('sale-total').textContent = money(cents * qty);
        document.getElementById('availability').textContent = product.value ? `${stock} units available${qty > stock ? ' — requested quantity exceeds stock.' : '.'}` : 'Select a product to see available stock.';
    };
    product.addEventListener('change', update); quantity.addEventListener('input', update); update();
}

const gmailPreset = document.getElementById('gmail-preset');
if (gmailPreset) gmailPreset.addEventListener('click', () => {
    document.getElementById('smtp_host').value = 'smtp.gmail.com';
    document.getElementById('smtp_port').value = '587';
    document.getElementById('encryption').value = 'tls';
    const sender = document.getElementById('from_address');
    const username = document.getElementById('smtp_username');
    if (!username.value && sender.value.includes('@')) username.value = sender.value;
});
