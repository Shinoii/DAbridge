<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@hasSection('title')@yield('title') · @endif{{ config('site.name') }}</title>
    <meta name="description" content="@yield('description', 'Донаты DonationAlerts в виджеты StreamElements в реальном времени')">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E🌉%3C/text%3E%3C/svg%3E">
    <link rel="stylesheet" href="{{ asset('css/site.css') }}?v={{ filemtime(public_path('css/site.css')) }}">
</head>
<body>
<header class="top">
    <div class="wrap">
        <a href="{{ route('home') }}" class="logo">
            <svg width="30" height="30" viewBox="0 0 30 30" fill="none" aria-hidden="true">
                <defs><linearGradient id="lg" x1="0" x2="1"><stop offset="0" stop-color="#ffb524"/><stop offset="1" stop-color="#8b6cff"/></linearGradient></defs>
                <circle cx="6" cy="15" r="4.5" fill="#ffb524"/><circle cx="24" cy="15" r="4.5" fill="#8b6cff"/>
                <path d="M10.5 15h9" stroke="url(#lg)" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
            {{ config('site.name') }}
        </a>
        <nav class="nav">
            <a href="{{ route('how') }}" class="hide-sm {{ request()->routeIs('how') ? 'on' : '' }}">Как это работает</a>
            <a href="{{ route('privacy') }}" class="hide-sm {{ request()->routeIs('privacy') ? 'on' : '' }}">Конфиденциальность</a>
            @if(session('streamer_id'))
                <a href="{{ route('me') }}" class="btn btn-sm">Кабинет</a>
            @else
                <a href="{{ route('da.connect') }}" class="btn btn-sm btn-primary">Войти</a>
            @endif
        </nav>
    </div>
</header>

<main>
    <div class="wrap">
        @if(session('ok'))<div class="flash">{{ session('ok') }}</div>@endif
        @if(session('err'))<div class="flash bad">{{ session('err') }}</div>@endif
        @yield('content')
    </div>
</main>

<footer class="foot">
    <div class="wrap">
        <span>© {{ date('Y') }} {{ config('site.name') }}</span>
        <nav>
            <a href="{{ route('how') }}">Как это работает</a>
            <a href="{{ route('privacy') }}">Политика конфиденциальности</a>
            <a href="{{ route('terms') }}">Условия использования</a>
            <a href="mailto:{{ config('site.contact_email') }}">{{ config('site.contact_email') }}</a>
        </nav>
        <small>Сервис не связан с DonationAlerts и StreamElements. Все названия принадлежат их владельцам.</small>
    </div>
</footer>
@include('partials.cookie-banner')
</body>
</html>
