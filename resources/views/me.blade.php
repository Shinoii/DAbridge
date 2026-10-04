@extends('site')
@section('title', 'Личный кабинет')
@section('content')
<div class="me">
    <div class="card profile rise">
        @if($streamer->avatar)<img src="{{ $streamer->avatar }}" alt="">@endif
        <div>
            <h1>{{ $streamer->name }}</h1>
            @if(! $streamer->is_active) <span class="pill"><span class="dot"></span>отключён</span>
            @elseif($streamer->is_connected) <span class="pill ok"><span class="dot"></span>подключено</span>
            @else <span class="pill bad"><span class="dot"></span>нет соединения</span> @endif
        </div>
        <form method="post" action="{{ route('me.logout') }}">@csrf
            <button class="btn btn-sm">Выйти</button>
        </form>
    </div>

    @if($streamer->is_active && ! $streamer->is_connected)
        <div class="card rise" style="--i:1;border-color:rgba(255,93,108,.35)">
            <h2>Почему нет соединения</h2>
            @if(! $listenerAlive)
                <p>Сервис сейчас перезапускается или недоступен. Донаты начнут приходить, когда он вернётся. Донаты за время простоя могут не отобразиться в виджете.</p>
            @elseif($streamer->last_error)
                <p>{{ $streamer->last_error }}</p>
                <p class="muted" style="font-size:13px">{{ $streamer->last_error_at?->diffForHumans() }}</p>
                <a href="{{ route('da.connect') }}" class="btn btn-primary btn-sm">Войти заново</a>
            @else
                <p class="muted">Устанавливаем соединение с DonationAlerts. Обычно это занимает несколько секунд.</p>
            @endif
        </div>
    @endif

    <div class="card rise" style="--i:1">
        <h2>Токен для виджета</h2>
        <p class="muted" style="margin:0">Вставьте его в поле токена в настройках виджета StreamElements. <a href="{{ route('how') }}">Подробная инструкция</a></p>
        <div class="field">
            <input id="tok" class="mono" type="password" readonly value="{{ $streamer->widget_token }}" aria-label="Токен виджета">
            <button class="btn" type="button" id="toggle">Показать</button>
            <button class="btn btn-primary" type="button" id="copy">Копировать</button>
        </div>
        <p class="warn">Никому не показывайте токен: по нему можно читать ваши донаты.</p>
        <form method="post" action="{{ route('me.regenerate') }}" onsubmit="return confirm('Старый токен перестанет работать. Продолжить?')">@csrf
            <button class="btn btn-sm btn-danger">Создать новый токен</button>
        </form>
    </div>

    <div class="card rise" style="--i:2">
        <h2>Проверка виджета</h2>
        <p class="muted" style="margin:0">Откройте свой виджет и отправьте тестовый донат: он придёт в ваш канал, но не попадёт в историю.</p>
        <form method="post" action="{{ route('me.test') }}" class="field">@csrf
            <input type="number" name="amount" value="100" min="1" max="100000" step="1" aria-label="Сумма">
            <button class="btn btn-primary">Отправить тестовый донат</button>
        </form>
    </div>

    <div class="card rise" style="--i:2">
        <h2>Последние донаты</h2>
        @forelse($donations as $d)
            <div class="don">
                <span class="who">{{ $d->username ?: 'Аноним' }}</span>
                <span class="sum mono">{{ rtrim(rtrim(number_format($d->amount, 2, '.', ''), '0'), '.') }} {{ $d->currency }}</span>
                @if($d->message)<span class="msg">{{ $d->message }}</span>@endif
                <time>{{ $d->created_at->format('d.m.Y H:i') }}</time>
            </div>
        @empty
            <p class="muted">Пока нет донатов. Они появятся здесь после первого поступления.</p>
        @endforelse
    </div>

    <div class="card danger rise" style="--i:3">
        <h2>Удаление аккаунта</h2>
        <p>Мы удалим ваш профиль, сохранённые токены доступа и всю историю донатов. Действие необратимо. Доступ приложения также можно отозвать в настройках DonationAlerts.</p>
        <form method="post" action="{{ route('me.destroy') }}" onsubmit="return confirm('Удалить аккаунт и все данные безвозвратно?')">@csrf @method('DELETE')
            <button class="btn btn-sm btn-danger">Удалить аккаунт и данные</button>
        </form>
    </div>
</div>

<script>
    const tok = document.getElementById('tok');
    document.getElementById('toggle').onclick = function () {
        tok.type = tok.type === 'password' ? 'text' : 'password';
        this.textContent = tok.type === 'password' ? 'Показать' : 'Скрыть';
    };
    document.getElementById('copy').onclick = function () {
        navigator.clipboard.writeText(tok.value).then(() => {
            this.textContent = 'Скопировано ✓';
            setTimeout(() => this.textContent = 'Копировать', 1800);
        });
    };
</script>
@endsection
