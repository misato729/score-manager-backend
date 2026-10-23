@extends('layouts.admin')

@section('title', 'ユーザー一覧')

@section('content')
  <style>
    .users-table{min-width:640px; table-layout:fixed}
    .users-table th,.users-table td{overflow-wrap:anywhere}
    .users-table .user-id{width:80px}
    .users-table .registered-at{width:200px}
    .users-search{min-width:0}
    .users-search input{min-width:0}
    .users-search .btn{flex-shrink:0}
    .users-pagination{display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:12px; padding:16px}
    .users-pagination .btn[aria-disabled=true]{color:var(--muted)}
  </style>

  <div class="page-header">
    <div>
      <div class="crumbs">TOP / ユーザー一覧</div>
      <h1 class="page-title">ユーザー一覧</h1>
    </div>
  </div>

  @if($errors->any())
    <div class="errorbox" role="alert">
      @foreach($errors->all() as $error)
        <div class="error">{{ $error }}</div>
      @endforeach
    </div>
  @endif

  <section class="card" aria-label="ユーザー一覧">
    <div class="toolbar">
      <form method="GET" action="{{ route('users.index') }}" class="search users-search" role="search">
        <span aria-hidden="true">⌕</span>
        <input type="search" name="keyword" value="{{ $keyword }}" placeholder="ユーザー名・メールアドレスで検索" aria-label="ユーザー名・メールアドレスで検索" maxlength="255">
        <button class="btn" type="submit">検索</button>
      </form>
      @if($keyword !== '')
        <a class="btn" href="{{ route('users.index') }}">検索を解除</a>
      @endif
    </div>

    <div class="meta">
      {{ $keyword !== '' ? '検索結果' : '全' }} <strong>{{ number_format($users->total()) }}</strong> 件
      @if($users->count())
        <span>（{{ number_format($users->firstItem()) }}〜{{ number_format($users->lastItem()) }}件を表示）</span>
      @endif
    </div>

    <div class="table-wrap" tabindex="0" role="region" aria-label="ユーザー一覧の表（横にスクロールできます）">
      <table class="users-table" aria-label="ユーザー情報">
        <thead>
          <tr>
            <th scope="col" class="user-id">ID</th>
            <th scope="col">ユーザー名</th>
            <th scope="col">メールアドレス</th>
            <th scope="col" class="registered-at">登録日時（日本時間）</th>
          </tr>
        </thead>
        <tbody>
          @forelse($users as $user)
            <tr>
              <td class="mono">{{ $user->id }}</td>
              <td>{{ $user->name }}</td>
              <td>{{ $user->email }}</td>
              <td class="mono">{{ $user->created_at?->timezone('Asia/Tokyo')->format('Y-m-d H:i') ?? '—' }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="4" style="text-align:center; color:var(--muted); padding:24px;">{{ $keyword !== '' ? '条件に一致するユーザーがいません' : 'ユーザーがいません' }}</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if($users->hasPages())
      <nav class="users-pagination" aria-label="ページ切り替え">
        @if($users->onFirstPage())
          <span class="btn" aria-disabled="true">前へ</span>
        @else
          <a class="btn" href="{{ $users->previousPageUrl() }}" rel="prev">前へ</a>
        @endif
        <span class="muted">{{ $users->currentPage() }} / {{ $users->lastPage() }} ページ</span>
        @if($users->hasMorePages())
          <a class="btn" href="{{ $users->nextPageUrl() }}" rel="next">次へ</a>
        @else
          <span class="btn" aria-disabled="true">次へ</span>
        @endif
      </nav>
    @endif
  </section>
@endsection
