<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">\n    <meta name="theme-color" content="#4338ca">\n    <link rel="manifest" href="/manifest.webmanifest">\n    <link rel="icon" href="/icons/semizzy-one.svg" type="image/svg+xml">
    <title inertia>{{ config('app.name', 'SEMIZZY ONE') }}</title>
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    @inertiaHead
</head>
<body class="bg-slate-50 font-sans antialiased">
    @inertia
</body>
</html>
