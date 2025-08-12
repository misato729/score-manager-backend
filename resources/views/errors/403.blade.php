<!-- resources/views/errors/403.blade.php -->
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>403 Forbidden | 管理システム</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
  <style>
    :root{
      --bg:#0b0f1a; --card:rgba(255,255,255,.06); --stroke:rgba(255,255,255,.12);
      --text:rgba(255,255,255,.92); --muted:rgba(255,255,255,.65);
      --accent1:#6C8CFF; --accent2:#9A6CFF; --danger:#ff6b6b;
      --shadow:0 12px 36px rgba(0,0,0,.45); --radius:18px;
    }
    *{box-sizing:border-box}
    html,body{height:100%}
    body{
      margin:0; color:var(--text);
      background:
        radial-gradient(1200px 800px at 10% -10%, #1b2140 0%, transparent 60%),
        radial-gradient(1200px 800px at 110% 20%, #331a4e 0%, transparent 60%),
        var(--bg);
      font-family:"Inter",system-ui,-apple-system,Segoe UI,Roboto,"Helvetica Neue",Arial,"Noto Sans JP",Meiryo,sans-serif;
      display:flex; align-items:center; justify-content:center; padding:40px 16px;
    }
    .wrap{width:min(100%, 760px)}
    .card{
      position:relative; padding:28px; border-radius:var(--radius);
      background:var(--card); border:1px solid var(--stroke); box-shadow:var(--shadow); backdrop-filter:blur(10px);
      overflow:hidden;
    }
    /* グラデのリング */
    .ring{
      position:absolute; inset:-2px; border-radius:inherit; pointer-events:none;
      background:
        radial-gradient(60% 60% at 20% 10%, rgba(108,140,255,.25), transparent 60%),
        radial-gradient(60% 60% at 90% 10%, rgba(154,108,255,.25), transparent 60%);
      mask:linear-gradient(#0000, #0000) content-box, linear-gradient(#000,#000);
      -webkit-mask:linear-gradient(#0000, #0000) content-box, linear-gradient(#000,#000);
      padding:1px; border:2px solid rgba(255,255,255,.04);
    }
    .hero{display:flex; gap:20px; align-items:center; margin-bottom:10px}
    .emblem{
      width:64px; height:64px; border-radius:16px;
      background:linear-gradient(135deg,var(--accent1),var(--accent2));
      display:grid; place-items:center; box-shadow:0 8px 22px rgba(108,140,255,.35);
    }
    .emblem svg{color:#fff}
    h1{margin:0; font-size:1.8rem; font-weight:800; letter-spacing:.3px}
    .lead{margin:6px 0 0; color:var(--muted)}
    .code{
      display:inline-flex; align-items:center; gap:8px; font-weight:800; letter-spacing:.8px;
      padding:6px 10px; border-radius:999px; background:rgba(255,255,255,.06); border:1px solid var(--stroke);
    }
    .panel{margin-top:16px; display:grid; gap:12px}
    .row{display:flex; gap:10px; flex-wrap:wrap}
    .btn{
      appearance:none; border:1px solid var(--stroke); background:rgba(255,255,255,.06);
      color:var(--text); padding:12px 16px; border-radius:12px; font-weight:700; cursor:pointer;
      display:inline-flex; align-items:center; gap:8px; text-decoration:none;
      transition:transform .15s ease, background .15s ease;
    }
    .btn:hover{transform:translateY(-1px); background:rgba(255,255,255,.1)}
    .primary{
      border-color:transparent; background:linear-gradient(135deg,var(--accent1),var(--accent2)); color:#fff;
      box-shadow:0 10px 24px rgba(108,140,255,.35);
    }
    .muted{color:var(--muted)}
    .mono{font-variant-numeric:tabular-nums}
    footer{margin-top:18px; text-align:center; color:var(--muted); font-size:.9rem}
    a.link{color:#c9d4ff; text-decoration:none; font-weight:600}
    a.link:hover{text-decoration:underline}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <div class="ring" aria-hidden="true"></div>

      <div class="hero">
        <div class="emblem" aria-hidden="true">
          <!-- shield icon -->
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none"><path d="M12 3 4 6v6c0 5 3.8 8.7 8 9 4.2-.3 8-4 8-9V6l-8-3Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <div>
          <h1>アクセスが拒否されました</h1>
          <p class="lead">このページは管理者専用です。権限がありません（<span class="code">403 Forbidden</span>）。</p>
        </div>
      </div>

      <div class="panel">
        @auth
          <div class="muted">現在のユーザー: <strong class="mono">{{ auth()->user()->email }}</strong></div>
        @else
          <div class="muted">現在は未ログインです。</div>
        @endauth

        <div class="row">
          <a class="btn" href="{{ url('/') }}">
            <!-- home -->
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M3 21h18M5 21V8l7-5 7 5v13" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M9 21v-6h6v6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Homeへ戻る
          </a>

          @guest
            <a class="btn primary" href="{{ route('login') }}">
              <!-- login -->
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
              ログイン
            </a>
          @endguest

          @auth
            <form method="POST" action="{{ route('logout') }}">
              @csrf
              <button class="btn" type="submit">
                <!-- logout -->
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M9 21H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h2M16 17l5-5-5-5M21 12H9" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                ログアウト
              </button>
            </form>
          @endauth
        </div>

        <p class="muted" style="margin-top:6px">
          この管理画面は <strong>ID=10</strong> のユーザーのみアクセス可能です。
        </p>
      </div>

      <footer>
        © {{ date('Y') }} RB+ Rank Manager · <a class="link" href="{{ url('/') }}">Home</a>
      </footer>
    </div>
  </div>
</body>
</html>
