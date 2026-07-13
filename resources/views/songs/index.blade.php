@extends('layouts.admin')

@section('title', '楽曲一覧')

@section('content')
  <div class="page-header">
    <div>
      <div class="crumbs">TOP / 楽曲一覧</div>
      <h1 class="page-title">楽曲一覧</h1>
    </div>
    <div class="actions">
      <a class="btn" href="{{ route('songs.index') }}">再読み込み</a>
    </div>
  </div>

  @if(session('success'))
    <div class="toast">{{ session('success') }}</div>
  @endif

  <section class="card">
    <div class="toolbar">
      <form method="GET" action="{{ route('songs.index') }}" class="search" role="search">
        <span aria-hidden="true">⌕</span>
        <input type="search" name="keyword" value="{{ request('keyword') }}" placeholder="曲名・地力ランクで検索">
        <button class="btn" type="submit">検索</button>
      </form>
    </div>

    <div class="meta">全 <strong>{{ number_format($totalCount) }}</strong> 件を表示</div>

    <div class="table-wrap">
      <table aria-label="楽曲一覧">
        <thead>
          <tr>
            <th style="width:80px">ID</th>
            <th>曲名</th>
            <th style="width:180px">地力ランク</th>
            <th style="width:180px">更新日時</th>
            <th style="width:120px">操作</th>
          </tr>
        </thead>
        <tbody>
          @forelse($songs as $song)
            <tr>
              <td class="mono">{{ $song->id }}</td>
              <td><a href="{{ route('songs.edit', $song->id) }}">{{ $song->title }}</a></td>
              <td>{{ $song->jiriki_rank }}</td>
              <td class="mono">{{ optional($song->updated_at)->format('Y-m-d H:i') }}</td>
              <td><a class="btn" href="{{ route('songs.edit', $song->id) }}">編集</a></td>
            </tr>
          @empty
            <tr>
              <td colspan="5" style="text-align:center; color:var(--muted); padding:24px;">データがありません</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>
@endsection
