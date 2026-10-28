<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'חיפוש מוצרים' }}</title>
    {{-- Relative URLs: the app is served under a sub-path in production. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Heebo:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="css/app.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🔍</text></svg>">
</head>
<body>
    <header class="site-header">
        <div class="site-header__inner">
            <a href="./" class="brand" aria-label="חיפוש חכם, דף הבית">
                <span class="brand__mark" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9z"/><path d="M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z"/></svg>
                </span>
                <span class="brand__name">חיפוש חכם</span>
            </a>
        </div>
    </header>
    <main class="page">
        {{ $slot }}
    </main>
    {{ $scripts ?? '' }}
</body>
</html>
