// Renders products from the <template> elements in components/product-card.blade.php.

const priceFormat = new Intl.NumberFormat('he-IL', { style: 'currency', currency: 'ILS', maximumFractionDigits: 2, minimumFractionDigits: 0 });

export const PLACEHOLDER_IMAGE = 'data:image/svg+xml,' + encodeURIComponent(
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 4 3"><rect width="4" height="3" fill="#eef0f3"/></svg>'
);

export function formatPrice(price) {
    return priceFormat.format(price);
}

export function renderProductCard(product, template = document.getElementById('product-card-template')) {
    const card = template.content.firstElementChild.cloneNode(true);
    const field = (name) => card.querySelector(`[data-field="${name}"]`);

    const image = field('image');
    image.alt = product.name;
    image.src = product.image_url || PLACEHOLDER_IMAGE;
    image.addEventListener('error', () => {
        image.src = PLACEHOLDER_IMAGE;
        image.closest('.product-card__media').classList.add('is-missing');
    }, { once: true });
    if (!product.image_url) image.closest('.product-card__media').classList.add('is-missing');

    field('name').textContent = product.name;
    // Some catalogs have no prices: drop the line rather than show "₪0".
    product.price != null ? (field('price').textContent = formatPrice(product.price)) : field('price').remove();
    product.brand ? (field('meta').textContent = product.brand) : field('meta').remove();

    return card;
}

export function renderSkeletons(count, template = document.getElementById('product-card-skeleton')) {
    return Array.from({ length: count }, () => template.content.firstElementChild.cloneNode(true));
}
