@extends('app.layout')

@section('title', 'Токены')

@section('content')
    <div class="kicker">Доступ</div>
    <h1>Токены</h1>
    <p class="lead">
        У каждого клиента — свой токен: ноутбук, домашний компьютер, агент.
        Отзыв одного не ломает остальные. Способность write включает чтение,
        поэтому для агента, который пишет знания, достаточно одной галочки.
    </p>

    @include('app.partials.tokens')
@endsection
