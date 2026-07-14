@extends('layouts.admin')

@section('title', '設置店舗編集')

@section('content')
  <div class="page-header">
    <div>
      <div class="crumbs">TOP / 設置店舗一覧 / 編集</div>
      <h1 class="page-title">設置店舗編集 <span class="muted">#{{ $shop->id }}</span></h1>
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
    <div class="card errorbox form-card">
      @foreach($errors->all() as $error)
        <div class="error">• {{ $error }}</div>
      @endforeach
    </div>
  @endif

  <form class="card form-card" method="POST" action="{{ route('shops.update', $shop->id) }}">
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
        <label for="prefecture_code">都道府県コード</label>
        <input type="number" id="prefecture_code" name="prefecture_code" min="1" max="47" step="1" value="{{ old('prefecture_code', $shop->prefecture_code) }}">
        @error('prefecture_code') <div class="error">{{ $message }}</div> @enderror
      </div>

      <div class="form-row">
        <label for="lat">緯度 <span class="muted">(−90〜90)</span></label>
        <input type="number" step="0.0000001" inputmode="decimal" id="lat" name="lat" value="{{ old('lat', $shop->lat) }}" required>
        @error('lat') <div class="error">{{ $message }}</div> @enderror
        <div class="help">
          <a target="_blank" rel="noopener" href="https://www.google.com/maps/search/?api=1&query={{ old('lat', $shop->lat) }},{{ old('lng', $shop->lng) }}">Googleマップで確認</a>
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

    <div class="actions" style="justify-content:flex-end; margin-top:18px">
      <a class="btn" href="{{ route('shops.index') }}">キャンセル</a>
      <button class="btn primary" type="submit">更新する</button>
    </div>
  </form>

  <div style="margin-top:14px">
    <a href="{{ route('shops.index') }}">← 一覧に戻る</a>
  </div>
@endsection
