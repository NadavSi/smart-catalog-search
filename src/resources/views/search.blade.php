<x-layouts.app>
    <header class="hero">
        <h1 class="hero__title">מוצאים בדיוק מה שצריך</h1>
        <p class="hero__subtitle">חפשו לפי שם מוצר, או פשוט תארו מה אתם צריכים, בעברית או באנגלית.</p>

        <x-search-box :examples="[
            'שואב אבק',
            'iPhone 16',
            'אייפון',
            'מתנה למישהו שאוהב לבשל',
            'משהו שיעזור לנקות שערות של כלב מהספה',
        ]" />
    </header>

    <section class="results" aria-labelledby="results-summary">
        <p id="results-summary" class="results__summary" aria-live="polite" data-summary></p>

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
