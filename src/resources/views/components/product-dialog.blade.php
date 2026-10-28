{{-- Product details popup: filled by public/js/product-dialog.js when a result card is clicked. --}}
<dialog class="product-dialog" aria-labelledby="product-dialog-title" data-product-dialog>
    <div class="product-dialog__panel">
        <button type="button" class="product-dialog__close" aria-label="סגירה" data-dialog-close>
            <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
        </button>

        <div class="product-dialog__media">
            <img class="product-dialog__image" alt="" decoding="async" referrerpolicy="no-referrer" data-field="image">
        </div>

        <div class="product-dialog__body">
            <ol class="product-dialog__crumbs" aria-label="קטגוריה" data-field="crumbs"></ol>
            <p class="product-dialog__brand" data-field="brand"></p>
            <h2 id="product-dialog-title" class="product-dialog__title" dir="auto" data-field="name"></h2>
            <p class="product-dialog__price" data-field="price"></p>
            <p class="product-dialog__description" dir="auto" data-field="description"></p>

            <section class="product-dialog__specs" data-field="specs">
                <h3 class="product-dialog__section-title">מפרט</h3>
                <dl class="product-dialog__attributes" data-field="attributes"></dl>
            </section>
        </div>
    </div>
</dialog>
