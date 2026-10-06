<!doctype html>
<html lang="en">
<head>
    @php($platform = app(\App\Services\System\SystemSettingsService::class)->all())
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="application-name" content="{{ $platform['platform_name'] }}">
    <meta name="theme-color" content="{{ $platform['theme_primary'] }}">
    <link rel="manifest" href="{{ route('manifest', [], false) }}">
    @if(!empty($platform['assets']['favicon']))<link rel="icon" href="{{ $platform['assets']['favicon'] }}">@else<link rel="icon" href="/icons/semizzy-one.svg" type="image/svg+xml">@endif
    <title inertia>{{ $platform['platform_name'] }}</title>
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    @inertiaHead
</head>
<body class="bg-slate-50 font-sans antialiased">
    @inertia
</body>
</html>
