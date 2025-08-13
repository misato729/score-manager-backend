<!-- resources/views/shops/edit.blade.php -->
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>設置店舗編集 | 管理システム</title>
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
      margin:0; padding:24px 16px; color:var(--text);
      background: radial-gradient(1200px 800px at 10% -10%, #1b2140 0%, transparent 60%),
                  radial-gradient(1200px 800px at 110% 20%, #331a4e 0%, transparent 60%),
                  var(--bg);
      font-family:"Inter",system-ui,-apple-system,Segoe UI,Roboto,"Helvetica Neue",Arial,"Noto Sans JP",Meiryo,sans-serif;
    }
    .shell{max-width:900px; margin:0 auto;}
    .header{display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:16px}
    .crumbs{color:var(--muted); font-size:.95rem}
    .title{font-size:1.6rem; font-weight:800; letter-spacing:.3px; margin:.25rem 0 0}
    .badge{
      display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px; font-size:.85rem;
      border:1px solid var(--stroke); background:rgba(255,255,255,.05);
    }
    .ok{border-color: color-mix(in srgb, var(--success) 30%, transparent); color: color-mix(in srgb, var(--success) 92%, white 10%);}
    .ng{border-color: color-mix(in srgb, var(--danger) 30%, transparent); color: color-mix(in srgb, var(--danger) 92%, white 10%);}
    .card{background:var(--card); border:1px solid var(--stroke); border-radius:var(--radius); box-shadow:var(--shadow); backdrop-filter:blur(8px); padding:20px}
    .errorbox{margin:0 0 12px; padding:10px 14px; border-radius:12px; border:1px solid color-mix(in srgb, var(--danger) 30%, transparent); background:color-mix(in srgb, var(--danger) 12%, transparent)}
    .error{color: color-mix(in srgb, var(--danger) 92%, white 10%); font-size:.92rem; margin:.2rem 0 0}
    .help{color:var(--muted); font-size:.9rem}
    .form-grid{display:grid; grid-template-columns:1fr 1fr; gap:16px}
    .form-row{display:flex; flex-direction:column; gap:6px}
    .full{grid-column:1 / -1}
    label{font-weight:700}
    input[type=text],input[type=number],textarea{
      width:100%; padding:.7rem .8rem; border-radius:12px; border:1px solid var(--stroke);
      background:rgba(255,255,255,.04); color:var(--text); outline:none;
    }
    textarea{min-height:120px; resize:vertical}
    .row{display:flex; align-items:center; gap:10px}
    .switch{
      --w:50px; --h:28px;
      position:relative; width:var(--w); height:var(--h);
      background:rgba(255,255,255,.08); border:1px solid var(--stroke); border-radius:999px; cursor:pointer;
    }
    .switch input{display:none}
    .knob{
      position:absolute; top:3px; left:3px; width:calc(var(--h) - 6px); height:calc(var(--h) - 6px);
      background:#fff; border-radius:999px; transition:left .18s ease, background .18s ease;
    }
    .switch input:checked + .knob{ left:calc(var(--w) - var(--h) + 3px); background:linear-gradient(135deg, var(--accent1), var(--accent2));}
    .btn{
      appearance:none; border:1px solid var(--stroke); background:rgba(255,255,255,.06); color:var(--text);
      padding:12px 16px; border-radius:12px; cursor:pointer; font-weight:700; display:inline-flex; align-items:center; gap:8px;
      transition:transform .15s ease, background .15s ease;
    }
    .btn:hover{transform:translateY(-1px); background:rgba(255,255,255,.1)}
    .primary{border-color:transparent; background:linear-gradient(135deg, var(--accent1), var(--accent2)); color:#fff; box-shadow:0 10px 24px rgba(108,140,255,.35)}
    .muted{color:var(--muted)}
    .actions{display:flex; flex-wrap:wrap; gap:10px; justify-content:flex-end; margin-top:18px}
    @media(max-width:720px){ .form-grid{grid-template-columns:1fr} }
    .mono{font-variant-numeric:tabular-nums}
    a.link{color:#c9d4ff; text-decoration:none; font-weight:600}
    a.link:hover{text-decoration:underline}
  </style>
</head>
<body>
  <div class="shell">
    <div class="header">
      <div>
        <div class="crumbs"><a class="link" href="{{ url('/') }}">Home</a> / <a class="link" href="{{ route('shops.index') }}">設置店舗一覧</a> / 編集</div>
        <h1 class="title">設置店舗編集 <span class="muted">#{{ $shop->id }}</span></h1>
      </div>
      <div>
        @if($shop->is_deleted)
          <span class="badge ng">削除済み</span>
        @else
          <span class="badge ok">公開中</span>
        @endif
      </div>
    </div>

    @if($errors->any())
      <div class="card errorbox">
        @foreach($errors->all() as $error)
          <div class="error">• {{ $error }}</div>
        @endforeach
      </div>
    @endif

    <form class="card" method="POST" action="{{ route('shops.update', $shop->id) }}">
      @csrf
      @method('PUT')

      <div class="form-grid">
        <div class="form-row full">
          <label for="name">名前</label>
          <input type="text" id="name" name="name" value="{{ old('name', $shop->name) }}" required>
          @error('name') <div class="error">{{ $message }}</div> @enderror
        </div>

        <div class="form-row full">
          <label for="address">住所</label>
          <input type="text" id="address" name="address" value="{{ old('address', $shop->address) }}" required>
          @error('address') <div class="error">{{ $message }}</div> @enderror
        </div>

        <div class="form-row">
          <label for="lat">緯度 <span class="muted">(−90〜90)</span></label>
          <input type="number" step="0.0000001" inputmode="decimal" id="lat" name="lat" value="{{ old('lat', $shop->lat) }}" required>
          @error('lat') <div class="error">{{ $message }}</div> @enderror
          <div class="help">
            <a class="link" target="_blank" rel="noopener" href="https://www.google.com/maps/search/?api=1&query={{ old('lat', $shop->lat) }},{{ old('lng', $shop->lng) }}">Googleマップで確認</a>
          </div>
        </div>

        <div class="form-row">
          <label for="lng">経度 <span class="muted">(−180〜180)</span></label>
          <input type="number" step="0.0000001" inputmode="decimal" id="lng" name="lng" value="{{ old('lng', $shop->lng) }}" required>
          @error('lng') <div class="error">{{ $message }}</div> @enderror
        </div>

        <div class="form-row">
          <label for="price">価格 <span class="muted">(円)</span></label>
          <input type="number" id="price" name="price" min="0" step="1" value="{{ old('price', $shop->price) }}">
          @error('price') <div class="error">{{ $message }}</div> @enderror
        </div>

        <div class="form-row">
          <label for="number_of_machine">台数</label>
          <input type="number" id="number_of_machine" name="number_of_machine" min="0" max="255" step="1" value="{{ old('number_of_machine', $shop->number_of_machine) }}">
          @error('number_of_machine') <div class="error">{{ $message }}</div> @enderror
        </div>

        <div class="form-row full">
          <label for="description">説明</label>
          <textarea id="description" name="description">{{ old('description', $shop->description) }}</textarea>
          @error('description') <div class="error">{{ $message }}</div> @enderror
        </div>

        <div class="form-row full">
          <label class="row" for="is_deleted">
            <span>状態（公開/削除）</span>
            <span class="help">切り替えると一覧の表示制御に使われます</span>
          </label>
          <div class="row">
            <label class="switch" title="論理削除トグル">
              <input type="checkbox" id="is_deleted" name="is_deleted" value="1" {{ old('is_deleted', $shop->is_deleted) ? 'checked' : '' }}>
              <span class="knob"></span>
            </label>
            <span class="muted">{{ old('is_deleted', $shop->is_deleted) ? '削除済み' : '公開中' }}</span>
          </div>
        </div>
      </div>

      <div class="actions">
        <a class="btn" href="{{ route('shops.index') }}">キャンセル</a>
        <button class="btn primary" type="submit">更新する</button>
      </div>
    </form>

    <div class="actions" style="justify-content:space-between; margin-top:14px">
      <a class="link" href="{{ route('shops.index') }}">← 一覧に戻る</a>
      <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button class="btn" type="submit">ログアウト</button>
      </form>
    </div>
  </div>
</body>
</html>
