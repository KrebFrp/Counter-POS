document.querySelectorAll('[data-dialog]').forEach(button => {
    button.addEventListener('click', () => document.getElementById(button.dataset.dialog).showModal());
});
document.querySelectorAll('[data-close-dialog]').forEach(button => {
    button.addEventListener('click', () => button.closest('dialog').close());
});
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', () => {
        form.querySelectorAll('button:not([type="button"])').forEach(button => {
            button.disabled = true;
            button.classList.add('submitting');
        });
    });
});
window.addEventListener('pageshow', () => {
    document.querySelectorAll('.submitting').forEach(button => {
        button.disabled = false;
        button.classList.remove('submitting');
    });
});
document.querySelectorAll('[data-print-receipt]').forEach(button => {
    button.addEventListener('click', () => window.print());
});
const saleForm = document.getElementById('sale-form');
if (saleForm) {
    const rows = document.getElementById('cart-items');
    const addButton = document.getElementById('add-cart-item');
    const payment = document.getElementById('payment_method');
    const cash = document.getElementById('amount_tendered');
    let nextIndex = Math.max(...Array.from(rows.querySelectorAll('[data-product]'), select => Number(select.name.match(/items\[(\d+)\]/)[1]))) + 1;
    const format = cents => `${saleForm.dataset.currency} ${(cents / 100n).toLocaleString('en-US')}.${(cents % 100n).toString().padStart(2, '0')}`;
    const cents = value => {
        if (! /^\d{1,8}(?:\.\d{1,2})?$/.test(value)) { return null; }
        const [whole, fraction = ''] = value.split('.');
        return BigInt(whole) * 100n + BigInt(fraction.padEnd(2, '0'));
    };
    const update = () => {
        let total = 0n;
        let units = 0n;
        let count = 0;
        const selectedProducts = new Set();
        const cartRows = Array.from(rows.querySelectorAll('[data-cart-row]'));
        cartRows.forEach(row => {
            const select = row.querySelector('[data-product]');
            const quantity = row.querySelector('[data-quantity]');
            const option = select.selectedOptions[0];
            select.setCustomValidity(select.value && selectedProducts.has(select.value) ? 'This product is already in your cart. Update its quantity instead.' : '');
            if (select.value) { selectedProducts.add(select.value); }
            quantity.max = option?.dataset.stock || '2147483647';
            const unit = cents(option?.dataset.price || '');
            const amount = /^\d{1,10}$/.test(quantity.value) ? BigInt(quantity.value) : 0n;
            const lineTotal = unit !== null ? unit * amount : 0n;
            row.querySelector('[data-line-total]').textContent = unit !== null ? format(lineTotal) : '—';
            row.querySelector('[data-remove-item]').disabled = cartRows.length === 1;
            if (unit !== null) { total += lineTotal; units += amount; count++; }
        });
        const isCash = payment.value === 'cash';
        cash.disabled = !isCash;
        cash.required = isCash;
        cash.closest('.field').hidden = !isCash;
        const received = cents(cash.value);
        cash.setCustomValidity(isCash && received !== null && received < total ? 'Cash received must cover the full checkout total.' : '');
        document.getElementById('summary-lines').textContent = count.toLocaleString('en-US');
        document.getElementById('summary-quantity').textContent = units.toLocaleString('en-US');
        document.getElementById('summary-total').textContent = format(total);
        document.getElementById('summary-change').textContent = isCash && received !== null && received >= total ? format(received - total) : '—';
        addButton.disabled = cartRows.length >= 100;
    };
    addButton.addEventListener('click', () => {
        if (rows.querySelectorAll('[data-cart-row]').length >= 100) { return; }
        const row = rows.querySelector('[data-cart-row]').cloneNode(true);
        row.querySelectorAll('.field-error').forEach(error => error.remove());
        const select = row.querySelector('[data-product]');
        const quantity = row.querySelector('[data-quantity]');
        select.name = `items[${nextIndex}][product_id]`;
        select.id = `product-${nextIndex}`;
        select.value = '';
        quantity.name = `items[${nextIndex}][quantity]`;
        quantity.id = `quantity-${nextIndex}`;
        quantity.value = '1';
        row.querySelectorAll('label')[0].htmlFor = select.id;
        row.querySelectorAll('label')[1].htmlFor = quantity.id;
        nextIndex++;
        rows.append(row);
        update();
        select.focus();
    });
    rows.addEventListener('click', event => {
        const button = event.target.closest('[data-remove-item]');
        if (button && rows.querySelectorAll('[data-cart-row]').length > 1) {
            button.closest('[data-cart-row]').remove();
            update();
        }
    });
    saleForm.addEventListener('input', update);
    saleForm.addEventListener('change', update);
    window.addEventListener('pageshow', update);
    update();
}

const singleSaleForm = document.getElementById('single-sale-form');
if (singleSaleForm) {
    const product = document.getElementById('product_id');
    const quantity = document.getElementById('quantity');
    const format = cents => `${singleSaleForm.dataset.currency} ${(cents / 100n).toLocaleString('en-US')}.${(cents % 100n).toString().padStart(2, '0')}`;
    const updateSingleSale = () => {
        const option = product.selectedOptions[0];
        const price = option?.dataset.price;
        const [whole, fraction = ''] = (price || '0').split('.');
        const unit = BigInt(whole) * 100n + BigInt(fraction.padEnd(2, '0'));
        const count = /^\d{1,10}$/.test(quantity.value) ? BigInt(quantity.value) : 0n;
        quantity.max = option?.dataset.stock || '2147483647';
        document.getElementById('summary-product').textContent = price ? option.textContent : 'Choose a product';
        document.getElementById('summary-price').textContent = price ? format(unit) : '—';
        document.getElementById('summary-quantity').textContent = count > 0n ? count.toLocaleString('en-US') : '—';
        document.getElementById('summary-total').textContent = format(unit * count);
        document.getElementById('stock-hint').textContent = price ? `${option.dataset.stock} units available. Stock and price are checked again when you save.` : 'Stock and price are checked again when the sale is recorded.';
    };
    product.addEventListener('change', updateSingleSale);
    quantity.addEventListener('input', updateSingleSale);
    window.addEventListener('pageshow', updateSingleSale);
    updateSingleSale();
}
