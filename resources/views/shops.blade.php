@extends('layouts.app')

@section('content')
    <h1>店舗一覧</h1>
    <ul>
        @foreach ($shops as $shop)
            <li>{{ $shop->name }}</li>
        @endforeach
    </ul>
@endsection
