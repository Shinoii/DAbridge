@extends('site')
@section('description', 'Получайте донаты DonationAlerts в виджетах StreamElements в реальном времени')
@section('content')
<section class="hero">
    <div class="bridge rise" aria-hidden="true">
        <div class="nodebox"><div class="node a">DA</div>DonationAlerts</div>
        <div class="line"><i></i><i></i><i></i></div>
        <div class="nodebox"><div class="node b">SE</div>StreamElements</div>
    </div>
    <h1 class="rise" style="--i:1">Донаты в ваших виджетах - <em>мгновенно и безопасно</em></h1>
    <p class="lead rise" style="--i:2">Подключите DonationAlerts один раз и получайте новые донаты в любом своём виджете StreamElements в реальном времени.</p>
    <div class="rise" style="--i:3">
        <a href="{{ route('da.connect') }}" class="btn btn-primary btn-lg">Войти через DonationAlerts</a>
        <small>Нажимая кнопку, вы принимаете <a href="{{ route('terms') }}">Условия</a> и <a href="{{ route('privacy') }}">Политику конфиденциальности</a>.</small>
        @if(session('streamer_id'))<small><a href="{{ route('me') }}">Перейти в личный кабинет →</a></small>@endif
    </div>
</section>

<section class="grid3">
    @foreach([
        ['Подключите аккаунт', 'Авторизуйтесь через DonationAlerts и получите личный токен виджета.'],
        ['Вставьте токен', 'Добавьте токен в настройки своего виджета в StreamElements.'],
        ['Принимайте донаты', 'Каждый новый донат мгновенно приходит в виджет по защищённому сокету.'],
    ] as $i => [$title, $text])
        <div class="card lift step rise" style="--i:{{ $i + 3 }}">
            <span class="n">{{ $i + 1 }}</span>
            <h3>{{ $title }}</h3>
            <p>{{ $text }}</p>
        </div>
    @endforeach
</section>

<section class="section two">
    <div class="card yes rise" style="--i:6">
        <h3>Что сервис читает</h3>
        <ul><li>Имя, ID и аватар вашего профиля DonationAlerts</li><li>Новые донаты на вашем аккаунте</li></ul>
    </div>
    <div class="card no rise" style="--i:7">
        <h3>Чего сервис не может</h3>
        <ul><li>Выводить деньги и видеть платёжные данные</li><li>Менять настройки вашего аккаунта</li></ul>
    </div>
</section>
<p class="muted" style="text-align:center;margin-top:24px"><a href="{{ route('how') }}">Подробнее, как это работает →</a></p>
@endsection
