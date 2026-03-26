<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>新規登録</title>
    @livewireStyles
</head>
<body style="font-family: system-ui, -apple-system, sans-serif; padding:24px; max-width:520px; margin:0 auto;">
    <h1>新規登録</h1>

    @if($errors->any())
        <div style="margin:12px 0; padding:8px; background:#fee2e2; border:1px solid #dc2626;">
            <ul>
                @foreach($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('register') }}">
        @csrf
        <div style="margin-top:8px;">
            <label>名前</label>
            <input type="text" name="name" value="{{ old('name') }}" required style="width:100%;">
        </div>
        <div style="margin-top:8px;">
            <label>メールアドレス</label>
            <input type="email" name="email" value="{{ old('email') }}" required style="width:100%;">
        </div>
        <div style="margin-top:8px;">
            <label>パスワード</label>
            <input type="password" name="password" required style="width:100%;">
        </div>
        <div style="margin-top:8px;">
            <label>パスワード（確認）</label>
            <input type="password" name="password_confirmation" required style="width:100%;">
        </div>
        <div style="margin-top:12px;">
            <button type="submit">登録</button>
        </div>
    </form>

    <div style="margin-top:12px;">
        <a href="{{ route('login') }}">ログインへ戻る</a>
    </div>
    @include('partials.back_to_top')
    @livewireScripts
</body>
</html>
