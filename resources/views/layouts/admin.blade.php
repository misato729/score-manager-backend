<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>@yield('title', '管理システム') | リフプラ難易度表</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
  <style>
    :root{
      --bg:#10131a; --panel:#181c24; --panel-2:#202633; --stroke:#333b4c;
      --text:#f3f5f8; --muted:#a8b0bf; --link:#9db7ff;
      --accent:#4f7cff; --accent-2:#24a37b; --danger:#e35d5d;
      --header-height:64px; --sidebar-width:232px; --radius:8px;
    }
    *{box-sizing:border-box}
    html,body{min-height:100%}
    body{
      margin:0; color:var(--text); background:var(--bg);
      font-family:"Inter",system-ui,-apple-system,Segoe UI,Roboto,"Helvetica Neue",Arial,"Noto Sans JP",Meiryo,sans-serif;
    }
    a{color:var(--link); text-decoration:none}
    a:hover{text-decoration:underline}
    .app-header{
      position:fixed; inset:0 0 auto 0; z-index:20; height:var(--header-height);
      display:flex; align-items:center; justify-content:space-between; gap:16px;
      padding:0 24px; background:#151922; border-bottom:1px solid var(--stroke);
    }
    .brand{display:flex; align-items:center; gap:12px; min-width:0; font-weight:800}
    .brand-mark{width:32px; height:32px; border-radius:8px; background:linear-gradient(135deg,var(--accent),var(--accent-2))}
    .brand-title{white-space:nowrap; overflow:hidden; text-overflow:ellipsis}
    .header-actions{display:flex; align-items:center; gap:12px}
    .user-email{max-width:260px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:var(--muted); font-size:.9rem}
    .app-sidebar{
      position:fixed; top:var(--header-height); bottom:0; left:0; z-index:10; width:var(--sidebar-width);
      padding:20px 14px; background:#131720; border-right:1px solid var(--stroke);
    }
    .nav-list{display:grid; gap:8px; margin:0; padding:0; list-style:none}
    .nav-link{
      display:flex; align-items:center; gap:10px; min-height:44px; padding:10px 12px; border-radius:var(--radius);
      color:var(--text); font-weight:700;
    }
    .nav-link:hover,.nav-link.active{background:var(--panel-2); text-decoration:none}
    .nav-icon{width:20px; text-align:center; color:var(--muted)}
    .app-main{
      min-height:100vh; padding:calc(var(--header-height) + 28px) 28px 40px calc(var(--sidebar-width) + 28px);
    }
    .page-header{display:flex; align-items:flex-end; justify-content:space-between; gap:16px; margin-bottom:16px}
    .crumbs{color:var(--muted); font-size:.92rem; margin-bottom:6px}
    .page-title{margin:0; font-size:1.6rem; line-height:1.25}
    .card{background:var(--panel); border:1px solid var(--stroke); border-radius:var(--radius)}
    .toolbar{display:flex; flex-wrap:wrap; gap:10px; align-items:center; padding:16px}
    .search{flex:1 1 320px; display:flex; align-items:center; gap:8px; padding:10px 12px; border-radius:var(--radius); border:1px solid var(--stroke); background:#11151d}
    .search input{width:100%; padding:6px 4px; background:transparent; border:0; outline:none; color:var(--text); font-size:1rem}
    .btn{
      appearance:none; border:1px solid var(--stroke); background:var(--panel-2); color:var(--text);
      min-height:40px; padding:9px 13px; border-radius:var(--radius); cursor:pointer; font-weight:700;
      display:inline-flex; align-items:center; justify-content:center; gap:8px; text-decoration:none;
    }
    .btn:hover{background:#2a3242; text-decoration:none}
    .btn.primary{border-color:transparent; background:var(--accent); color:#fff}
    .btn.danger{border-color:rgba(227,93,93,.4); color:#ffd4d4}
    .actions{display:flex; flex-wrap:wrap; gap:8px; align-items:center}
    .meta{padding:0 16px 12px; color:var(--muted); font-size:.95rem}
    .table-wrap{overflow:auto; border-top:1px solid var(--stroke)}
    table{width:100%; border-collapse:separate; border-spacing:0}
    thead th{position:sticky; top:var(--header-height); z-index:2; background:#202633; text-align:left; font-weight:700; color:var(--muted); font-size:.9rem; padding:12px 14px; border-bottom:1px solid var(--stroke)}
    tbody td{padding:12px 14px; border-bottom:1px solid var(--stroke); vertical-align:top}
    tbody tr:hover{background:#1d2330}
    .mono{font-variant-numeric:tabular-nums}
    .badge{display:inline-flex; align-items:center; padding:5px 8px; border-radius:999px; font-size:.84rem; border:1px solid var(--stroke); background:#11151d}
    .badge.ok{border-color:rgba(36,163,123,.5); color:#9ef0d2}
    .badge.ng{border-color:rgba(227,93,93,.5); color:#ffc2c2}
    .toast{margin:10px 0 16px; padding:10px 14px; border-radius:var(--radius); border:1px solid rgba(36,163,123,.45); background:rgba(36,163,123,.12); color:#b9f4df}
    .errorbox{margin:0 0 12px; padding:10px 14px; border:1px solid rgba(227,93,93,.45); background:rgba(227,93,93,.12)}
    .error{color:#ffc2c2; font-size:.92rem; margin:.2rem 0 0}
    .form-card{padding:20px; max-width:900px}
    .form-grid{display:grid; grid-template-columns:1fr 1fr; gap:16px}
    .form-row{display:flex; flex-direction:column; gap:6px}
    .full{grid-column:1 / -1}
    label{font-weight:700}
    input[type=text],input[type=number],textarea,select{
      width:100%; padding:.7rem .8rem; border-radius:var(--radius); border:1px solid var(--stroke);
      background:#11151d; color:var(--text); outline:none;
    }
    textarea{min-height:120px; resize:vertical}
    select option{background:#11151d; color:var(--text)}
    .row{display:flex; align-items:center; gap:10px}
    .muted{color:var(--muted)}
    .help{color:var(--muted); font-size:.9rem}
    .switch{--w:50px; --h:28px; position:relative; width:var(--w); height:var(--h); background:#11151d; border:1px solid var(--stroke); border-radius:999px; cursor:pointer}
    .switch input{display:none}
    .knob{position:absolute; top:3px; left:3px; width:calc(var(--h) - 6px); height:calc(var(--h) - 6px); background:#fff; border-radius:999px; transition:left .18s ease, background .18s ease}
    .switch input:checked + .knob{left:calc(var(--w) - var(--h) + 3px); background:var(--accent)}
    @media(max-width:760px){
      :root{--header-height:60px; --sidebar-width:0px}
      .app-header{padding:0 14px}
      .brand-title{font-size:.95rem}
      .user-email{display:none}
      .app-sidebar{top:var(--header-height); right:0; bottom:auto; width:auto; padding:8px 14px; border-right:0; border-bottom:1px solid var(--stroke)}
      .nav-list{grid-template-columns:1fr 1fr}
      .nav-link{min-height:40px; justify-content:center}
      .app-main{padding:calc(var(--header-height) + 78px) 14px 28px}
      .page-header{align-items:flex-start; flex-direction:column}
      .form-grid{grid-template-columns:1fr}
    }
  </style>
</head>
<body>
  <header class="app-header">
    <a class="brand" href="{{ url('/') }}">
      <span class="brand-mark" aria-hidden="true"></span>
      <span class="brand-title">リフプラ難易度表 管理システム</span>
    </a>
    <div class="header-actions">
      @auth
        <span class="user-email">{{ auth()->user()->email }}</span>
        <form method="POST" action="{{ route('logout') }}">
          @csrf
          <button class="btn danger" type="submit">ログアウト</button>
        </form>
      @else
        <a class="btn" href="{{ route('login') }}">ログイン</a>
      @endauth
    </div>
  </header>

  <aside class="app-sidebar" aria-label="サイドメニュー">
    <nav>
      <ul class="nav-list">
        <li><a class="nav-link {{ request()->routeIs('shops.*') ? 'active' : '' }}" href="{{ route('shops.index') }}"><span class="nav-icon">□</span><span>店舗一覧</span></a></li>
        <li><a class="nav-link {{ request()->routeIs('songs.*') ? 'active' : '' }}" href="{{ route('songs.index') }}"><span class="nav-icon">♪</span><span>楽曲一覧</span></a></li>
      </ul>
    </nav>
  </aside>

  <main class="app-main">
    @yield('content')
  </main>
</body>
</html>
