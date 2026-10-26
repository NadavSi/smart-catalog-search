<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'חיפוש מוצרים' }}</title>
    {{-- Relative URLs: the app is served under a sub-path in production. --}}
    <link rel="stylesheet" href="css/app.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🔍</text></svg>">
</head>
<body>
    <main class="page">
        {{ $slot }}
    </main>
    {{ $scripts ?? '' }}
</body>
</html>
