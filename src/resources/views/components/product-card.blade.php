{{-- Client-side template: filled by public/js/product-card.js for each result. --}}
<template id="product-card-template">
    <article class="product-card">
        <div class="product-card__media">
            <img class="product-card__image" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer" data-field="image">
        </div>
        <div class="product-card__body">
            <p class="product-card__meta" data-field="meta"></p>
            <h3 class="product-card__name" dir="auto" data-field="name"></h3>
            <p class="product-card__price" data-field="price"></p>
        </div>
    </article>
</template>

<template id="product-card-skeleton">
    <div class="product-card product-card--skeleton" aria-hidden="true">
        <div class="product-card__media"></div>
        <div class="product-card__body">
            <span class="skeleton-line"></span>
            <span class="skeleton-line skeleton-line--short"></span>
        </div>
    </div>
</template>
