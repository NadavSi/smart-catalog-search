// Fills and opens the product details <dialog> from components/product-dialog.blade.php.

import { PLACEHOLDER_IMAGE, formatPrice } from './product-card.js';

const dialog = document.querySelector('[data-product-dialog]');
const field = (name) => dialog.querySelector(`[data-field="${name}"]`);

function setText(name, value) {
    const el = field(name);
    el.textContent = value ?? '';
    el.hidden = value == null || value === '';
}

export function openProductDialog(product) {
    const image = field('image');
    const media = image.closest('.product-dialog__media');
    media.classList.toggle('is-missing', !product.image_url);
    image.alt = product.name;
    image.onerror = () => { image.src = PLACEHOLDER_IMAGE; media.classList.add('is-missing'); };
    image.src = product.image_url || PLACEHOLDER_IMAGE;

    const crumbs = [product.category, product.subcategory].filter(Boolean);
    field('crumbs').replaceChildren(...crumbs.map((text) => Object.assign(document.createElement('li'), { textContent: text })));
    field('crumbs').hidden = crumbs.length === 0;

    setText('brand', product.brand);
    setText('name', product.name);
    setText('price', product.price != null ? formatPrice(product.price) : null);
    setText('description', product.description);

    const attributes = product.attributes ?? [];
    field('attributes').replaceChildren(...attributes.flatMap(({ label, value }) => [
        Object.assign(document.createElement('dt'), { textContent: label ?? '' }),
        Object.assign(document.createElement('dd'), { textContent: value, dir: 'auto' }),
    ]));
    field('specs').hidden = attributes.length === 0;

    dialog.querySelector('.product-dialog__body').scrollTop = 0;
    dialog.showModal();
}

dialog.querySelector('[data-dialog-close]').addEventListener('click', () => dialog.close());

// A click on the backdrop lands on the <dialog> itself, outside the panel.
dialog.addEventListener('click', (event) => {
    if (event.target === dialog) dialog.close();
});
