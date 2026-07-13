@extends('layouts.admin')

@section('title', '設置店舗一覧')

@section('content')
  <div class="page-header">
    <div>
      <div class="crumbs">TOP / 設置店舗一覧</div>
      <h1 class="page-title">設置店舗一覧</h1>
    </div>
    <div class="actions">
      <a class="btn primary" href="{{ route('shops.create') }}">新規登録</a>
      <a class="btn" href="{{ route('shops.index') }}">再読み込み</a>
    </div>
  </div>

  @if(session('success'))
    <div class="toast">{{ session('success') }}</div>
  @endif

  <section class="card">
    <div class="toolbar">
      <form method="GET" action="{{ route('shops.index') }}" class="search" role="search">
        <span aria-hidden="true">⌕</span>
        <input type="search" name="keyword" value="{{ request('keyword') }}" placeholder="店舗名・住所で検索">
        <button class="btn" type="submit">検索</button>
      </form>
    </div>

    <div class="meta">
      全 <strong>{{ number_format($totalCount) }}</strong> 件を表示
      （公開中：<strong>{{ number_format($publishedCount) }}</strong> 件）
    </div>

    <div class="table-wrap">
      <table aria-label="ショップ一覧">
        <thead>
          <tr>
            <th style="width:80px">ID</th>
            <th>店舗名</th>
            <th>住所</th>
            <th class="mono">都道府県</th>
            <th class="mono">緯度</th>
            <th class="mono">経度</th>
            <th class="mono">価格</th>
            <th class="mono">台数</th>
            <th>説明</th>
            <th style="width:120px">状態</th>
            <th style="width:120px">操作</th>
          </tr>
        </thead>
        <tbody>
          @forelse($shops as $shop)
            <tr>
              <td class="mono">{{ $shop->id }}</td>
              <td><a href="{{ route('shops.edit', $shop->id) }}">{{ $shop->name }}</a></td>
              <td>{{ $shop->address }}</td>
              <td class="mono">{{ $shop->prefecture_code ?? '-' }}</td>
              <td class="mono">{{ $shop->lat }}</td>
              <td class="mono">{{ $shop->lng }}</td>
              <td class="mono">{{ $shop->price ?? '-' }}</td>
              <td class="mono">{{ $shop->number_of_machine ?? '-' }}</td>
              <td>{{ $shop->description ?? '-' }}</td>
              <td>
                @if($shop->is_deleted)
                  <span class="badge ng">削除済み</span>
                @else
                  <span class="badge ok">公開中</span>
                @endif
              </td>
              <td><a class="btn" href="{{ route('shops.edit', $shop->id) }}">編集</a></td>
            </tr>
          @empty
            <tr>
              <td colspan="11" style="text-align:center; color:var(--muted); padding:24px;">データがありません</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>
@endsection
