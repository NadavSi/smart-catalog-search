<x-layouts.app>
    <section class="hero">
        <span class="hero__badge">חיפוש סמנטי · מבין מה התכוונתם</span>
        <h1 class="hero__title">מה בא לכם למצוא?</h1>
        <p class="hero__subtitle">תארו מה אתם מחפשים, אנחנו נמצא מוצרים מתאימים</p>

        <x-search-box :examples="[
            'מתנה למישהו שאוהב קפה',
            'שואב אבק רובוטי',
            'Dyson',
            'אוזניות עם ביטול רעשים',
            'משהו שיעזור לנקות שערות של כלב מהספה',
        ]" />
    </section>

    <section class="results" aria-labelledby="results-title">
        <div class="results__header" data-results-header hidden>
            <h2 id="results-title" class="results__title">מצאנו מוצרים בשבילכם</h2>
            <p class="results__summary" aria-live="polite" data-summary></p>
        </div>

        <div class="results__grid" data-results aria-busy="false"></div>

        <x-state-message state="idle" icon="🛍️" title="מה מחפשים היום?">
            הקלידו שם של מוצר, מותג או תיאור של צורך.
        </x-state-message>

        <x-state-message state="empty" icon="🔎" title="לא נמצאו תוצאות">
            נסו לנסח אחרת, להשתמש במילים כלליות יותר או לתאר למה המוצר משמש.
        </x-state-message>

        <x-state-message state="error" icon="⚠️" title="משהו השתבש" action="נסו שוב">
            לא הצלחנו להשלים את החיפוש.
        </x-state-message>
    </section>

    <x-product-card />

    <x-slot:scripts>
        <script type="module" src="js/search-page.js"></script>
    </x-slot:scripts>
</x-layouts.app>
