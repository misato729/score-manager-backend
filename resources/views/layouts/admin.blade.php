@vite(['resources/css/app.css','resources/js/app.js'])

<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>@yield('title','Admin')</title>
  @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50">
  <header class="p-4 border-b bg-white">管理画面</header>
  <main class="p-6">@yield('content')</main>
  @stack('scripts')
</body>
</html>
