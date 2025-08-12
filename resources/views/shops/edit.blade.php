<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ショップ編集</title>
</head>
<body>
    <h1>ショップ編集</h1>

    @if($errors->any())
        <ul>
            @foreach($errors->all() as $error)
                <li style="color: red;">{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form action="{{ route('shops.update', $shop->id) }}" method="POST">
        @csrf
        @method('PUT')

        <label for="name">名前:</label>
        <input type="text" id="name" name="name" value="{{ old('name', $shop->name) }}" required><br>

        <label for="address">住所:</label>
        <input type="text" id="address" name="address" value="{{ old('address', $shop->address) }}" required><br>

        <label for="lat">緯度:</label>
        <input type="text" id="lat" name="lat" value="{{ old('lat', $shop->lat) }}" required><br>

        <label for="lng">経度:</label>
        <input type="text" id="lng" name="lng" value="{{ old('lng', $shop->lng) }}" required><br>

        <label for="price">価格:</label>
        <input type="text" id="price" name="price" value="{{ old('price', $shop->price) }}" ><br>

        <label for="number_of_machine">台数:</label>
        <input type="text" id="number_of_machine" name="number_of_machine" value="{{ old('number_of_machine', $shop->number_of_machine) }}" ><br>

        <label for="description">説明:</label>
        <textarea id="description" name="description" >{{ old('description', $shop->description) }}</textarea><br>

        <label for="is_deleted">論理削除:</label>
        <input type="checkbox" id="is_deleted" name="is_deleted" value="1" {{ $shop->is_deleted ? 'checked' : '' }}>

        <button type="submit">更新</button>
    </form>
</body>
</html>
