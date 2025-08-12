<!-- resources/views/auth/login.blade.php -->
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>ログイン | 管理システム</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
  <style>
    :root{
      --bg:#0b0f1a; --card:rgba(255,255,255,.06); --stroke:rgba(255,255,255,.12);
      --text:rgba(255,255,255,.92); --muted:rgba(255,255,255,.65);
      --accent1:#6C8CFF; --accent2:#9A6CFF; --danger:#ff6b6b; --success:#35d399;
      --shadow:0 10px 30px rgba(0,0,0,.35); --radius:16px;
    }
    *{box-sizing:border-box}
    html,body{height:100%}
    body{
      margin:0; color:var(--text);
      background: radial-gradient(1200px 800px at 10% -10%, #1b2140 0%, transparent 60%),
                  radial-gradient(1200px 800px at 110% 20%, #331a4e 0%, transparent 60%),
                  var(--bg);
      font-family:"Inter",system-ui,-apple-system,Segoe UI,Roboto,"Helvetica Neue",Arial,"Noto Sans JP",Meiryo,sans-serif;
      display:flex; align-items:center; justify-content:center; padding:32px 16px;
    }
    .shell{width:min(100%, 460px)}
    .brand{display:flex; gap:12px; align-items:center; justify-content:center; margin-bottom:16px}
    .logo{width:44px; height:44px; border-radius:12px; background:linear-gradient(135deg,var(--accent1),var(--accent2)); box-shadow:0 6px 18px rgba(108,140,255,.35)}
    h1{margin:0; font-size:1.6rem; font-weight:800; letter-spacing:.3px; text-align:center}
    .card{background:var(--card); border:1px solid var(--stroke); border-radius:var(--radius); box-shadow:var(--shadow); backdrop-filter:blur(8px); padding:20px}
    .muted{color:var(--muted)}
    .toast{margin:0 0 12px; padding:10px 14px; border-radius:12px; font-size:.95rem;
      border:1px solid color-mix(in srgb, var(--success) 30%, transparent);
      background: color-mix(in srgb, var(--success) 10%, transparent);
      color: color-mix(in srgb, var(--success) 92%, white 10%);
    }
    .errorbox{margin:0 0 12px; padding:10px 14px; border-radius:12px; border:1px solid color-mix(in srgb, var(--danger) 30%, transparent); background:color-mix(in srgb, var(--danger) 12%, transparent)}
    .error{color: color-mix(in srgb, var(--danger) 92%, white 10%); font-size:.92rem; margin:.2rem 0 0}
    form{display:grid; gap:14px}
    label{display:grid; gap:6px; font-weight:700}
    input[type=email], input[type=password]{
      width:100%; padding:.8rem .9rem; border-radius:12px; border:1px solid var(--stroke);
      background:rgba(255,255,255,.04); color:var(--text); outline:none;
    }
    .row{display:flex; align-items:center; justify-content:space-between; gap:12px}
    .check{display:flex; align-items:center; gap:8px; color:var(--muted); font-weight:600}
    .btn{
      appearance:none; border:1px solid transparent; border-radius:12px; padding:.9rem 1rem;
      font-weight:800; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; gap:10px;
      background:linear-gradient(135deg,var(--accent1),var(--accent2)); color:#fff; box-shadow:0 10px 24px rgba(108,140,255,.35);
      transition:transform .15s ease, box-shadow .15s ease;
    }
    .btn:hover{transform:translateY(-1px); box-shadow:0 14px 28px rgba(108,140,255,.45)}
    .btn.outline{background:transparent; color:var(--text); border-color:var(--stroke)}
    .btn.outline:hover{background:rgba(255,255,255,.06)}
    .hint{margin-top:6px; font-size:.88rem; text-align:center; color:var(--muted)}
    .foot{display:flex; justify-content:space-between; align-items:center; margin-top:14px}
    a.link{color:#c9d4ff; text-decoration:none; font-weight:600}
    a.link:hover{text-decoration:underline}
    .pw{position:relative}
    .toggle{
      position:absolute; right:10px; top:50%; translate:0 -50%;
      background:transparent; border:0; color:var(--muted); cursor:pointer; padding:6px; border-radius:8px;
    }
    .toggle:hover{background:rgba(255,255,255,.06); color:var(--text)}
  </style>
</head>
<body>
  <div class="shell">
    <div class="brand">
      <div class="logo" aria-hidden="true"></div>
      <h1>ログイン</h1>
    </div>

    @if (session('status'))
      <div class="toast">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
      <div class="card errorbox" style="padding:12px 14px">
        @foreach ($errors->all() as $error)
          <div class="error">• {{ $error }}</div>
        @endforeach
      </div>
    @endif

    <form class="card" method="POST" action="{{ route('login.store') }}" novalidate>
      @csrf

      <label>
        メールアドレス
        <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
      </label>

      <label class="pw">
        パスワード
        <input id="password" type="password" name="password" required autocomplete="current-password">
        <button type="button" class="toggle" aria-label="パスワードの表示切替" onclick="const p=document.getElementById('password'); p.type = p.type==='password' ? 'text' : 'password'; this.setAttribute('aria-pressed', p.type==='text');">
          <!-- eye icon -->
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" stroke="currentColor" stroke-width="1.6"/></svg>
        </button>
      </label>

      <div class="row">
        <label class="check">
          <input type="checkbox" name="remember" value="1">
          ログイン状態を保持する
        </label>
        <a class="link" href="{{ url('/') }}">Home</a>
      </div>

      <button class="btn" type="submit">
        <!-- login icon -->
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        ログイン
      </button>
    </form>

    <p class="hint">この管理画面は <strong>ID=10</strong> のユーザーのみアクセス可能です。</p>
  </div>
</body>
</html>
