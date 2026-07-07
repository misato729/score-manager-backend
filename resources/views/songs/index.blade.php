<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>楽曲一覧 | 管理システム</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
  <style>
    :root{
      --bg:#0b0f1a; --card:rgba(255,255,255,.06); --stroke:rgba(255,255,255,.12);
      --text:rgba(255,255,255,.92); --muted:rgba(255,255,255,.65);
      --accent1:#6C8CFF; --accent2:#9A6CFF; --success:#35d399;
      --shadow:0 10px 30px rgba(0,0,0,.35); --radius:16px;
    }
    *{box-sizing:border-box}
    html,body{height:100%}
    body{
      margin:0; padding:24px 16px; color:var(--text);
      background:radial-gradient(1200px 800px at 10% -10%, #1b2140 0%, transparent 60%),
                 radial-gradient(1200px 800px at 110% 20%, #331a4e 0%, transparent 60%),
                 var(--bg);
      font-family:"Inter",system-ui,-apple-system,Segoe UI,Roboto,"Helvetica Neue",Arial,"Noto Sans JP",Meiryo,sans-serif;
    }
    .shell{max-width:1000px; margin:0 auto}
    .header{display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:16px}
    .title{font-size:1.6rem; font-weight:800; letter-spacing:.3px}
    .crumbs{color:var(--muted); font-size:.95rem}
    .card{background:var(--card); border:1px solid var(--stroke); border-radius:var(--radius); box-shadow:var(--shadow); backdrop-filter:blur(8px)}
    .toolbar{display:flex; flex-wrap:wrap; gap:10px; align-items:center; padding:16px}
    .search{flex:1 1 320px; display:flex; align-items:center; gap:8px; padding:10px 12px; border-radius:12px; border:1px solid var(--stroke); background:rgba(255,255,255,.04)}
    .search input{width:100%; padding:6px 4px; background:transparent; border:0; outline:none; color:var(--text); font-size:1rem}
    .btn{appearance:none; border:1px solid var(--stroke); background:rgba(255,255,255,.06); color:var(--text); padding:10px 14px; border-radius:12px; cursor:pointer; font-weight:600; display:inline-flex; align-items:center; gap:8px; text-decoration:none}
    .btn:hover{background:rgba(255,255,255,.1)}
    .primary{border-color:transparent; background:linear-gradient(135deg,var(--accent1),var(--accent2)); color:#fff; box-shadow:0 10px 24px rgba(108,140,255,.35)}
    .meta{padding:0 16px 12px; color:var(--muted); font-size:.95rem}
    .table-wrap{overflow:auto; border-top:1px solid var(--stroke)}
    table{width:100%; border-collapse:separate; border-spacing:0}
    thead th{position:sticky; top:0; z-index:2; background:rgba(15,20,35,.9); text-align:left; font-weight:700; color:var(--muted); font-size:.9rem; padding:12px 14px; border-bottom:1px solid var(--stroke)}
    tbody td{padding:12px 14px; border-bottom:1px solid var(--stroke); vertical-align:top}
    tbody tr:hover{background:rgba(255,255,255,.03)}
    .mono{font-variant-numeric:tabular-nums}
    .actions{display:flex; gap:8px; align-items:center}
    .link{color:#c9d4ff; text-decoration:none; font-weight:600}
    .link:hover{text-decoration:underline}
    .toast{margin:10px 0 16px; padding:10px 14px; border-radius:12px; border:1px solid color-mix(in srgb,var(--success) 30%,transparent); background:color-mix(in srgb,var(--success) 10%,transparent); color:color-mix(in srgb,var(--success) 92%,white 10%)}
    @media(max-width:720px){.header{align-items:flex-start; flex-direction:column}.title{font-size:1.4rem}thead th,tbody td{padding:10px 12px}}
  </style>
</head>
<body>
  <div class="shell">
    <div class="header">
      <div>
        <div class="crumbs"><a class="link" href="{{ url('/') }}">Home</a> / 楽曲一覧</div>
        <h1 class="title" style="margin:.25rem 0 0">楽曲一覧</h1>
      </div>
      <div class="actions">
        <a class="btn" href="{{ route('shops.index') }}">設置店舗一覧</a>
        <a class="btn" href="{{ route('songs.index') }}">再読み込み</a>
        <form method="POST" action="{{ route('logout') }}">
          @csrf
          <button class="btn" type="submit">ログアウト</button>
        </form>
      </div>
    </div>

    @if(session('success'))
      <div class="toast">{{ session('success') }}</div>
    @endif

    <section class="card">
      <div class="toolbar">
        <form method="GET" action="{{ route('songs.index') }}" class="search" role="search">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m21 21-4.3-4.3M10.8 18.5a7.7 7.7 0 1 1 0-15.4 7.7 7.7 0 0 1 0 15.4Z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
          <input type="search" name="keyword" value="{{ request('keyword') }}" placeholder="曲名・地力ランクで検索…" />
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
                <td><a class="link" href="{{ route('songs.edit', $song->id) }}">{{ $song->title }}</a></td>
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
  </div>
</body>
</html>
