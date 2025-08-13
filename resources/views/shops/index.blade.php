<!-- resources/views/shops/index.blade.php -->
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>ショップ一覧 | 管理システム</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
  <style>
    :root{
      --bg: #0b0f1a;
      --card: rgba(255,255,255,.06);
      --stroke: rgba(255,255,255,.12);
      --text: rgba(255,255,255,.92);
      --muted: rgba(255,255,255,.65);
      --accent1: #6C8CFF;
      --accent2: #9A6CFF;
      --success: #35d399;
      --warn: #e7b416;
      --danger: #ff6b6b;
      --shadow: 0 10px 30px rgba(0,0,0,.35);
      --radius: 16px;
    }
    *{box-sizing:border-box}
    html,body{height:100%}
    body{
      margin:0; padding:24px 16px;
      color:var(--text);
      background: radial-gradient(1200px 800px at 10% -10%, #1b2140 0%, transparent 60%),
                  radial-gradient(1200px 800px at 110% 20%, #331a4e 0%, transparent 60%),
                  var(--bg);
      font-family:"Inter", system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial, "Noto Sans JP", Meiryo, sans-serif;
    }
    .shell{max-width:1200px; margin:0 auto;}
    .header{
      display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:16px;
    }
    .title{font-size:1.6rem; font-weight:800; letter-spacing:.3px}
    .crumbs{color:var(--muted); font-size:.95rem}
    .card{
      background:var(--card); border:1px solid var(--stroke); border-radius:var(--radius);
      box-shadow:var(--shadow); backdrop-filter:blur(8px);
    }

    /* Toolbar */
    .toolbar{display:flex; flex-wrap:wrap; gap:10px; align-items:center; padding:16px}
    .toolbar .search{
      flex:1 1 320px; display:flex; align-items:center; gap:8px; padding:10px 12px; border-radius:12px;
      border:1px solid var(--stroke); background:rgba(255,255,255,.04);
    }
    .search input{
      width:100%; padding:6px 4px; background:transparent; border:0; outline:none; color:var(--text);
      font-size:1rem;
    }
    .btn{
      appearance:none; border:1px solid var(--stroke); background:rgba(255,255,255,.06); color:var(--text);
      padding:10px 14px; border-radius:12px; cursor:pointer; font-weight:600; display:inline-flex; align-items:center; gap:8px;
      transition:transform .15s ease, background .15s ease;
    }
    .btn:hover{transform:translateY(-1px); background:rgba(255,255,255,.1)}
    .btn.primary{
      border-color:transparent; background:linear-gradient(135deg, var(--accent1), var(--accent2)); color:#fff;
      box-shadow:0 10px 24px rgba(108,140,255,.35);
    }
    .meta{padding:0 16px 12px; color:var(--muted); font-size:.95rem}

    /* Table */
    .table-wrap{overflow:auto; border-top:1px solid var(--stroke)}
    table{width:100%; border-collapse:separate; border-spacing:0}
    thead th{
      position:sticky; top:0; z-index:2;
      background:rgba(15,20,35,.9); backdrop-filter:blur(6px);
      text-align:left; font-weight:700; color:var(--muted); font-size:.9rem;
      padding:12px 14px; border-bottom:1px solid var(--stroke);
    }
    tbody td{
      padding:12px 14px; border-bottom:1px solid var(--stroke); vertical-align:top;
    }
    tbody tr:hover{background:rgba(255,255,255,.03)}
    .mono{font-variant-numeric:tabular-nums}
    .badge{
      display:inline-flex; align-items:center; gap:6px; padding:6px 8px; border-radius:999px; font-size:.85rem;
      border:1px solid var(--stroke); background:rgba(255,255,255,.05);
    }
    .badge.ok{border-color: color-mix(in srgb, var(--success) 30%, transparent); color: color-mix(in srgb, var(--success) 92%, white 10%);}
    .badge.ng{border-color: color-mix(in srgb, var(--danger) 30%, transparent); color: color-mix(in srgb, var(--danger) 92%, white 10%);}
    .actions{display:flex; gap:8px; align-items:center}
    .link{
      color:#c9d4ff; text-decoration:none; font-weight:600;
    }
    .link:hover{text-decoration:underline}
    .toast{margin:10px 0 16px; padding:10px 14px; border-radius:12px;
      border:1px solid color-mix(in srgb, var(--success) 30%, transparent);
      background: color-mix(in srgb, var(--success) 10%, transparent);
      color: color-mix(in srgb, var(--success) 92%, white 10%);
    }

    /* Simple pager (if you use Laravel paginator links(),そのままでもOK) */
    .pager{display:flex; align-items:center; justify-content:flex-end; gap:8px; padding:12px 16px}
    .pager a, .pager span{
      padding:8px 12px; border-radius:10px; border:1px solid var(--stroke);
      color:var(--text); text-decoration:none; background:rgba(255,255,255,.05);
    }
    .pager .disabled{opacity:.5; pointer-events:none}

    @media (max-width: 720px){
      .title{font-size:1.4rem}
      thead th, tbody td{padding:10px 12px}
    }
  </style>
</head>
<body>
  <div class="shell">
    <div class="header">
      <div>
        <div class="crumbs"><a class="link" href="{{ url('/') }}">Home</a> / ショップ一覧</div>
        <h1 class="title" style="margin:.25rem 0 0">ショップ一覧</h1>
      </div>

      <div class="actions">
        <a class="btn" href="{{ url('/admin/shops') }}">再読み込み</a>
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
      <!-- Toolbar -->
      <div class="toolbar">
        <form method="GET" action="{{ route('shops.index') }}" class="search" role="search">
          <!-- 検索キーワード（Controller側で keyword を when 条件に） -->
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m21 21-4.3-4.3M10.8 18.5a7.7 7.7 0 1 1 0-15.4 7.7 7.7 0 0 1 0 15.4Z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
          <input type="search" name="keyword" value="{{ request('keyword') }}" placeholder="店舗名・住所で検索…" />
          <button class="btn" type="submit">検索</button>
        </form>
        {{-- ここに将来フィルタを追加（例：未削除のみ 等） --}}
      </div>

      <!-- 件数などのメタ -->
      <div class="meta">
        @php
          $isPaginator = method_exists($shops, 'total');
        @endphp
        @if($isPaginator)
          全 <strong>{{ number_format($shops->total()) }}</strong> 件中
          <strong>{{ number_format($shops->firstItem()) }}–{{ number_format($shops->lastItem()) }}</strong> を表示
        @else
          全 <strong>{{ number_format($shops->count()) }}</strong> 件を表示
        @endif
      </div>

      <!-- Table -->
      <div class="table-wrap">
        <table aria-label="ショップ一覧">
          <thead>
            <tr>
              <th style="width:80px">ID</th>
              <th>店舗名</th>
              <th>住所</th>
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
                <td>
                  <a class="link" href="{{ route('shops.edit', $shop->id) }}">
                    {{ $shop->name }}
                  </a>
                </td>
                <td>{{ $shop->address }}</td>
                <td class="mono">{{ $shop->lat }}</td>
                <td class="mono">{{ $shop->lng }}</td>
                <td class="mono">{{ $shop->price ?? '—' }}</td>
                <td class="mono">{{ $shop->number_of_machine ?? '—' }}</td>
                <td>{{ $shop->description ?? '—' }}</td>
                <td>
                  @if($shop->is_deleted)
                    <span class="badge ng">削除済み</span>
                  @else
                    <span class="badge ok">公開中</span>
                  @endif
                </td>
                <td>
                  <div class="actions">
                    <a class="btn" href="{{ route('shops.edit', $shop->id) }}">編集</a>
                  </div>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="10" style="text-align:center; color:var(--muted); padding:24px;">データがありません</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <!-- Pager（paginate 使用時のみ表示） -->
      @if(method_exists($shops, 'links'))
        <div class="pager">
          {{-- Laravel標準のページネーションUIを使うなら： --}}
          {{-- {!! $shops->onEachSide(1)->links() !!} --}}

          {{-- シンプルPrev/Next（コントローラが paginate の場合のみ有効） --}}
          @if($shops->previousPageUrl())
            <a href="{{ $shops->previousPageUrl() }}">← 前へ</a>
          @else
            <span class="disabled">← 前へ</span>
          @endif

          <span>
            {{ $shops->currentPage() }} / {{ $shops->lastPage() }}
          </span>

          @if($shops->nextPageUrl())
            <a href="{{ $shops->nextPageUrl() }}">次へ →</a>
          @else
            <span class="disabled">次へ →</span>
          @endif
        </div>
      @endif
    </section>
  </div>
</body>
</html>
