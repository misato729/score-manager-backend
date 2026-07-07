<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>楽曲編集 | 管理システム</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
  <style>
    :root{
      --bg:#0b0f1a; --card:rgba(255,255,255,.06); --stroke:rgba(255,255,255,.12);
      --text:rgba(255,255,255,.92); --muted:rgba(255,255,255,.65);
      --accent1:#6C8CFF; --accent2:#9A6CFF; --danger:#ff6b6b;
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
    .shell{max-width:760px; margin:0 auto}
    .header{display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:16px}
    .crumbs{color:var(--muted); font-size:.95rem}
    .title{font-size:1.6rem; font-weight:800; letter-spacing:.3px; margin:.25rem 0 0}
    .card{background:var(--card); border:1px solid var(--stroke); border-radius:var(--radius); box-shadow:var(--shadow); backdrop-filter:blur(8px); padding:20px}
    .errorbox{margin:0 0 12px; padding:10px 14px; border-radius:12px; border:1px solid color-mix(in srgb,var(--danger) 30%,transparent); background:color-mix(in srgb,var(--danger) 12%,transparent)}
    .error{color:color-mix(in srgb,var(--danger) 92%,white 10%); font-size:.92rem; margin:.2rem 0 0}
    .form-grid{display:grid; grid-template-columns:1fr; gap:16px}
    .form-row{display:flex; flex-direction:column; gap:6px}
    label{font-weight:700}
    input[type=text]{width:100%; padding:.7rem .8rem; border-radius:12px; border:1px solid var(--stroke); background:rgba(255,255,255,.04); color:var(--text); outline:none}
    .btn{appearance:none; border:1px solid var(--stroke); background:rgba(255,255,255,.06); color:var(--text); padding:12px 16px; border-radius:12px; cursor:pointer; font-weight:700; display:inline-flex; align-items:center; gap:8px; text-decoration:none}
    .btn:hover{background:rgba(255,255,255,.1)}
    .primary{border-color:transparent; background:linear-gradient(135deg,var(--accent1),var(--accent2)); color:#fff; box-shadow:0 10px 24px rgba(108,140,255,.35)}
    .muted{color:var(--muted)}
    .actions{display:flex; flex-wrap:wrap; gap:10px; justify-content:flex-end; margin-top:18px}
    .link{color:#c9d4ff; text-decoration:none; font-weight:600}
    .link:hover{text-decoration:underline}
    @media(max-width:720px){.header{align-items:flex-start; flex-direction:column}.title{font-size:1.4rem}}
  </style>
</head>
<body>
  <div class="shell">
    <div class="header">
      <div>
        <div class="crumbs"><a class="link" href="{{ url('/') }}">Home</a> / <a class="link" href="{{ route('songs.index') }}">楽曲一覧</a> / 編集</div>
        <h1 class="title">楽曲編集 <span class="muted">#{{ $song->id }}</span></h1>
      </div>
    </div>

    @if($errors->any())
      <div class="card errorbox">
        @foreach($errors->all() as $error)
          <div class="error">• {{ $error }}</div>
        @endforeach
      </div>
    @endif

    <form class="card" method="POST" action="{{ route('songs.update', $song->id) }}">
      @csrf
      @method('PUT')

      <div class="form-grid">
        <div class="form-row">
          <label for="title">曲名</label>
          <input type="text" id="title" name="title" value="{{ old('title', $song->title) }}" required>
          @error('title') <div class="error">{{ $message }}</div> @enderror
        </div>

        <div class="form-row">
          <label for="jiriki_rank">地力ランク</label>
          <input type="text" id="jiriki_rank" name="jiriki_rank" value="{{ old('jiriki_rank', $song->jiriki_rank) }}" required>
          @error('jiriki_rank') <div class="error">{{ $message }}</div> @enderror
        </div>
      </div>

      <div class="actions">
        <a class="btn" href="{{ route('songs.index') }}">キャンセル</a>
        <button class="btn primary" type="submit">更新する</button>
      </div>
    </form>

    <div class="actions" style="justify-content:space-between; margin-top:14px">
      <a class="link" href="{{ route('songs.index') }}">← 一覧に戻る</a>
      <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button class="btn" type="submit">ログアウト</button>
      </form>
    </div>
  </div>
</body>
</html>
