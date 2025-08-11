@extends('layouts.admin')
@section('title','ログイン')

@section('content')
<div class="flex items-center justify-center min-h-[80vh]">
    <div class="bg-white shadow-lg rounded-lg w-full max-w-md p-8">
        <h1 class="text-2xl font-bold text-gray-800 mb-6 text-center">管理画面ログイン</h1>

        {{-- エラーメッセージ --}}
        @if ($errors->any())
            <div class="mb-4 bg-red-50 border border-red-200 text-red-700 text-sm rounded p-3">
                <ul class="list-disc pl-5 space-y-1">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- セッション系メッセージ --}}
        @if (session('status'))
            <div class="mb-4 bg-green-50 border border-green-200 text-green-700 text-sm rounded p-3">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="space-y-5">
            @csrf

            <div>
                <label class="block text-gray-700 font-medium mb-1">メールアドレス</label>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus
                       class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500 p-2">
            </div>

            <div>
                <label class="block text-gray-700 font-medium mb-1">パスワード</label>
                <input type="password" name="password" required
                       class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500 p-2">
            </div>

            <div class="flex items-center justify-between text-sm">
                <label class="inline-flex items-center">
                    <input type="checkbox" name="remember" class="text-indigo-600 border-gray-300 rounded">
                    <span class="ml-2 text-gray-700">ログイン状態を保持</span>
                </label>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="text-indigo-600 hover:underline">
                        パスワードをお忘れですか？
                    </a>
                @endif
            </div>

            <button type="submit"
                    class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg p-2 transition-colors">
                ログイン
            </button>
        </form>
    </div>
</div>
@endsection
