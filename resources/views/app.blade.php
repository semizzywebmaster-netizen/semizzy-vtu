<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#4338ca">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/icons/semizzy-one.svg" type="image/svg+xml">
    <title inertia>{{ config('app.name', 'SEMIZZY ONE') }}</title>
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    @inertiaHead
</head>
<body class="bg-slate-50 font-sans antialiased">
    @inertia
</body>
</html>
