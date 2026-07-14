@extends('layouts.admin')

@section('title', '楽曲編集')

@section('content')
  <div class="page-header">
    <div>
      <div class="crumbs">TOP / 楽曲一覧 / 編集</div>
      <h1 class="page-title">楽曲編集 <span class="muted">#{{ $song->id }}</span></h1>
    </div>
  </div>

  @if($errors->any())
    <div class="card errorbox form-card">
      @foreach($errors->all() as $error)
        <div class="error">• {{ $error }}</div>
      @endforeach
    </div>
  @endif

  <form class="card form-card" method="POST" action="{{ route('songs.update', $song->id) }}">
    @csrf
    @method('PUT')

    <div class="form-grid" style="grid-template-columns:1fr">
      <div class="form-row">
        <label for="title">曲名</label>
        <input type="text" id="title" name="title" value="{{ old('title', $song->title) }}" required>
        @error('title') <div class="error">{{ $message }}</div> @enderror
      </div>

      <div class="form-row">
        <label for="jiriki_rank">地力ランク</label>
        <select id="jiriki_rank" name="jiriki_rank" required>
          @foreach($jirikiRanks as $rank)
            <option value="{{ $rank }}" @selected(old('jiriki_rank', $song->jiriki_rank) === $rank)>{{ $rank }}</option>
          @endforeach
        </select>
        @error('jiriki_rank') <div class="error">{{ $message }}</div> @enderror
      </div>
    </div>

    <div class="actions" style="justify-content:flex-end; margin-top:18px">
      <a class="btn" href="{{ route('songs.index') }}">キャンセル</a>
      <button class="btn primary" type="submit">更新する</button>
    </div>
  </form>

  <div style="margin-top:14px">
    <a href="{{ route('songs.index') }}">← 一覧に戻る</a>
  </div>
@endsection
