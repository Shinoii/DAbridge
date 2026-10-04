@extends('layout')
@section('title', 'Стримеры')
@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="m-0">Подключённые стримеры</h3>
        <form method="post" action="{{ route('admin.logout') }}">@csrf
            <button class="btn btn-outline-secondary btn-sm">Выйти</button>
        </form>
    </div>

    @php($age = \App\Support\ListenerStatus::age())
    @if(\App\Support\ListenerStatus::alive())
        <div class="alert alert-success py-2">da:listen работает (сигнал {{ $age }} с назад)</div>
    @else
        <div class="alert alert-danger">da:listen не отвечает{{ $age ? " уже {$age} с" : '' }}. Донаты не принимаются, проверьте процесс (<code>supervisorctl status</code>).</div>
    @endif

    @if(session('ok'))<div class="alert alert-success">{{ session('ok') }}</div>@endif

    <div class="row g-3 mb-4">
        @foreach(['Всего стримеров' => $stats['total'], 'Онлайн (сокет)' => $stats['online']] as $label => $val)
            <div class="col-6">
                <div class="card"><div class="card-body">
                        <div class="text-secondary small">{{ $label }}</div>
                        <div class="fs-3">{{ $val }}</div>
                    </div></div>
            </div>
        @endforeach
    </div>

    <form class="mb-3"><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Поиск по имени"></form>

    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr>
                <th>Стример</th><th>DA ID</th><th>Статус</th><th>Донатов</th>
                <th>Последний донат</th><th>Токен виджета</th><th></th>
            </tr></thead>
            <tbody>
            @foreach($streamers as $s)
                <tr>
                    <td>
                        @if($s->avatar)<img src="{{ $s->avatar }}" width="28" height="28" class="rounded-circle me-2">@endif
                        {{ $s->name }}
                    </td>
                    <td>{{ $s->da_user_id }}</td>
                    <td>
                        @if($s->is_active && ! $s->is_connected && $s->last_error)
                            <div class="small text-secondary mt-1">{{ $s->last_error }}</div>
                        @endif
                        @if(! $s->is_active) <span class="badge text-bg-secondary">отключён</span>
                        @elseif($s->is_connected) <span class="badge text-bg-success">online</span>
                        @else <span class="badge text-bg-danger">нет соединения</span> @endif
                    </td>
                    <td>{{ $s->donations_count }}</td>
                    <td>{{ $s->last_donation_at?->diffForHumans() ?? '—' }}</td>
                    <td><code>{{ Str::limit($s->widget_token, 10, '…') }}</code></td>
                    <td class="text-end text-nowrap">
                        <form method="post" action="{{ route('admin.toggle', $s) }}" class="d-inline">@csrf @method('PATCH')
                            <button class="btn btn-sm btn-outline-warning">{{ $s->is_active ? 'Отключить' : 'Включить' }}</button>
                        </form>
                        <form method="post" action="{{ route('admin.regenerate', $s) }}" class="d-inline" onsubmit="return confirm('Старый токен перестанет работать. Продолжить?')">@csrf
                            <button class="btn btn-sm btn-outline-info">Новый токен</button>
                        </form>
                        <form method="post" action="{{ route('admin.destroy', $s) }}" class="d-inline" onsubmit="return confirm('Удалить стримера и его историю?')">@csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">Удалить</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    {{ $streamers->links() }}
@endsection
