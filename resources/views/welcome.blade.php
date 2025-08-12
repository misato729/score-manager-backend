<!-- resources/views/welcome.blade.php -->
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>リフプラ難易度表 管理システム</title>
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
      --danger: #ff6b6b;
      --shadow: 0 10px 30px rgba(0,0,0,.35);
      --radius: 16px;
    }
    *{box-sizing:border-box}
    html,body{height:100%}
    body{
      margin:0;
      color:var(--text);
      background: radial-gradient(1200px 800px at 10% -10%, #1b2140 0%, transparent 60%),
                  radial-gradient(1200px 800px at 110% 20%, #331a4e 0%, transparent 60%),
                  var(--bg);
      font-family: "Inter", system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial, "Noto Sans JP", "Hiragino Kaku Gothic ProN", Meiryo, sans-serif;
      display:flex;
      align-items:center;
      justify-content:center;
      padding: 32px 16px;
    }
    .shell{
      width:min(100%, 1000px);
    }
    .header{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:16px;
      margin-bottom:24px;
    }
    .brand{
      display:flex; align-items:center; gap:12px;
    }
    .logo{
      width:44px; height:44px;
      border-radius:12px;
      background:linear-gradient(135deg, var(--accent1), var(--accent2));
      box-shadow: 0 6px 18px rgba(108,140,255,.35);
    }
    .title{
      font-weight:800; letter-spacing:.3px; line-height:1.1;
    }
    .title small{
      display:block; font-weight:600; color:var(--muted); font-size:.9rem; margin-top:2px;
    }
    .pill{
      display:inline-flex; align-items:center; gap:8px;
      padding:8px 12px; border-radius:999px; font-size:.85rem; color:var(--muted);
      background: var(--card); border:1px solid var(--stroke); backdrop-filter: blur(8px);
    }
    .card{
      background: var(--card);
      border: 1px solid var(--stroke);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      backdrop-filter: blur(8px);
      padding: 24px;
    }
    .grid{
      display:grid;
      grid-template-columns: 1.2fr .8fr;
      gap:20px;
    }
    @media (max-width: 820px){
      .grid{grid-template-columns: 1fr;}
    }

    .cta{
      display:flex; flex-wrap:wrap; gap:12px; margin-top:18px;
    }
    .btn{
      appearance: none;
      display:inline-flex; align-items:center; justify-content:center; gap:10px;
      padding:14px 18px; border-radius:12px; border:1px solid transparent;
      font-weight:600; text-decoration:none; cursor:pointer; transition: all .2s ease;
      background:linear-gradient(135deg, var(--accent1), var(--accent2));
      color:#fff; box-shadow: 0 10px 24px rgba(108,140,255,.35);
    }
    .btn:hover{ transform: translateY(-1px); box-shadow: 0 14px 28px rgba(108,140,255,.45); }
    .btn.secondary{
      background: transparent; color: var(--text);
      border-color: var(--stroke);
    }
    .btn.secondary:hover{ background: rgba(255,255,255,.06); }

    .section-title{
      font-size:1.1rem; font-weight:700; margin-bottom:10px; color:var(--muted);
    }

    .toast{
      margin: 16px 0 -8px;
      padding: 10px 14px; border-radius: 12px; font-size:.95rem;
      border:1px solid color-mix(in srgb, var(--success) 30%, transparent);
      background: color-mix(in srgb, var(--success) 16%, transparent);
      color: color-mix(in srgb, var(--success) 92%, white 10%);
    }
    .error{
      border-color: color-mix(in srgb, var(--danger) 35%, transparent);
      background: color-mix(in srgb, var(--danger) 12%, transparent);
      color: color-mix(in srgb, var(--danger) 92%, white 10%);
    }

    .list{
      display:grid; gap:10px; margin:0; padding:0; list-style:none;
    }
    .list li{
      display:flex; align-items:center; justify-content:space-between; gap:12px;
      padding:12px 14px; background:rgba(255,255,255,.04); border:1px solid var(--stroke);
      border-radius:12px;
    }
    .mono{font-variant-numeric: tabular-nums;}
    .muted{color:var(--muted)}
    .spacer{height:8px}
    footer{margin-top:18px; color:var(--muted); font-size:.85rem; text-align:center}
    .link{color:#c9d4ff; text-decoration:none}
    .link:hover{text-decoration:underline}
  </style>
</head>
<body>
  <div class="shell">
    <div class="header">
      <div class="brand">
        <div class="logo" aria-hidden="true"></div>
        <div class="title">
          リフプラ難易度表 管理システム
          <small>REFLEC BEAT plus Admin</small>
        </div>
      </div>

      {{-- 状態ピル（ログイン／ゲスト） --}}
      @auth
        <span class="pill">Signed in as <strong class="mono">{{ auth()->user()->email }}</strong></span>
      @else
        <span class="pill">Not signed in</span>
      @endauth
    </div>

    <div class="grid">
      <!-- 左：メインカード -->
      <section class="card">
        <h2 style="margin:0 0 6px 0; font-size:1.6rem; font-weight:800;">ようこそ</h2>
        <p class="muted" style="margin:0 0 6px 0;">
          管理対象：<strong>店舗データ / 楽曲データ</strong>（ID=10のみアクセス可）
        </p>

        @if (session('status'))
          <div class="toast">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
          <div class="toast error">
            @foreach ($errors->all() as $error)
              <div>{{ $error }}</div>
            @endforeach
          </div>
        @endif

        <div class="spacer"></div>

        <div class="section-title">クイックアクション</div>
        <div class="cta">
          <a class="btn" href="{{ url('/admin/shops') }}">
            {{-- 小さなアイコン（SVG） --}}
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 21h18M5 21V8l7-5 7 5v13" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M9 21v-6h6v6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
            店舗一覧画面へ
          </a>

          @guest
            <a class="btn secondary" href="{{ route('login') }}">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
              ログイン
            </a>
          @endguest

          @auth
            <form method="POST" action="{{ route('logout') }}">
              @csrf
              <button class="btn secondary" type="submit">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M9 21H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h2M16 17l5-5-5-5M21 12H9" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                ログアウト
              </button>
            </form>
          @endauth
        </div>
      </section>

      <!-- 右：インフォカード -->
      <aside class="card">
        <div class="section-title">インフォ</div>
        <ul class="list">
          <li><span>権限制御</span><span class="muted">ID=10のみ / <code class="mono">only10</code></span></li>
          <li><span>セッション</span><span class="muted">webガード / CSRF保護</span></li>
          <li><span>UI</span><span class="muted">グラス×ダーク / レスポンシブ</span></li>
        </ul>
        <div style="margin-top:14px; font-size:.92rem" class="muted">
          まずは「店舗一覧画面へ」から編集を始めてください。
        </div>
      </aside>
    </div>

    <footer>
      © {{ date('Y') }} RB+ Rank Manager
      · <a class="link" href="{{ url('/') }}">Home</a>
    </footer>
  </div>
</body>
</html>
