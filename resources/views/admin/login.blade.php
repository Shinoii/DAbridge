@extends('layout')
@section('content')
    <form method="post" class="mx-auto" style="max-width:340px">
        @csrf
        <h3 class="mb-3">Админка</h3>
        @error('login')<div class="alert alert-danger">{{ $message }}</div>@enderror
        <input name="login" class="form-control mb-2" placeholder="Логин" required>
        <input name="password" type="password" class="form-control mb-3" placeholder="Пароль" required>
        <button class="btn btn-primary w-100">Войти</button>
    </form>
@endsection
