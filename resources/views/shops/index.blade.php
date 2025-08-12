<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ショップ一覧</title>
</head>
<body>
    <h1>ショップ一覧</h1>

    @if(session('success'))
        <p style="color: green;">{{ session('success') }}</p>
    @endif

    <table border="1">
        <thead>
            <tr>
                <th>ID</th>
                <th>店舗名</th>
                <th>住所</th>
                <th>緯度</th>
                <th>経度</th>
                <th>価格</th>
                <th>台数</th>
                <th>説明</th>
                <th>論理削除</th>
            </tr>
        </thead>
        <tbody>
            @foreach($shops as $shop)
                <tr>
                    <td>{{ $shop->id }}</td>
                    <td>
                        <a href="{{ route('shops.edit', $shop->id) }}">
                            {{ $shop->name }}
                        </a>
                    </td>
                    <td>{{ $shop->address }}</td>
                    <td>{{ $shop->lat }}</td>
                    <td>{{ $shop->lng }}</td>
                    <td>{{ $shop->price }}</td>
                    <td>{{ $shop->number_of_machine }}</td>
                    <td>{{ $shop->description }}</td>
                    <td>
                        @if($shop->is_deleted)
                            <span style="color: red;">削除済み</span>
                        @else
                        @endif
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
