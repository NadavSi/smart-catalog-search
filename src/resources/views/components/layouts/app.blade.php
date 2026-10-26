<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'חיפוש מוצרים' }}</title>
    {{-- Relative URLs: the app is served under a sub-path in production. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Assistant:wght@400;600;700;800&display=swap">
    <link rel="stylesheet" href="css/app.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🔍</text></svg>">
</head>
<body>
    <header class="site-header">
        <div class="site-header__inner">
            <a href="./" class="logo" dir="ltr" aria-label="htzone, דף הבית">htzone<span class="logo__dot" aria-hidden="true"></span></a>
            <a href="#search-input" class="site-header__search" data-focus-search>
                <span>חיפוש מוצרים</span>
                <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
            </a>
        </div>
    </header>
    <main class="page">
        {{ $slot }}
    </main>
    {{ $scripts ?? '' }}
</body>
</html>
