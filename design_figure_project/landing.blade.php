<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>デモポータル</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; padding:24px; max-width: 980px; margin: 0 auto; }
        .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px; }
        .card { border: 1px solid #e5e7eb; padding: 12px; border-radius: 8px; }
        .muted { color: #6b7280; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #e5e7eb; padding: 6px; text-align: left; }
        th { background: #f3f4f6; }
        .links a { margin-right: 12px; }
    </style>
</head>
<body>
    <h1>ログイン・運用ポータル</h1>
    <p class="muted">以下のアカウントは `RealDataSeeder` で作成されます。</p>

    <div class="links" style="margin: 12px 0;">
        <a href="{{ route('login') }}">ログイン</a>
        <a href="{{ route('register') }}">新規登録</a>
        <a href="{{ url('/configurator') }}">コンフィギュレータ</a>
    </div>

    <h2>役割別クイックリンク</h2>
    <div class="cards">
        <div class="card">
            <h3>顧客</h3>
            <div>アクセス: <a href="{{ url('/configurator') }}">/configurator</a></div>
            <div class="muted">全ユーザー利用可</div>
        </div>
        <div class="card">
            <h3>営業</h3>
            <div>アクセス: <a href="{{ url('/work/quotes') }}">/work/quotes</a></div>
            <div class="muted">権限: sales</div>
        </div>
        <div class="card">
            <h3>管理者</h3>
            <div>アクセス: <a href="{{ url('/work/change-requests') }}">/work/change-requests</a></div>
            <div class="muted">権限: admin</div>
        </div>
    </div>

    <h2 style="margin-top:20px;">ログイン情報</h2>
    <table>
        <thead>
            <tr>
                <th>権限</th>
                <th>名前</th>
                <th>メール</th>
                <th>パスワード</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>admin</td>
                <td>社長 管理者</td>
                <td>admin@example.com</td>
                <td>admin1234</td>
            </tr>
            <tr>
                <td>sales</td>
                <td>営業ユーザー</td>
                <td>sales@example.com</td>
                <td>sales1234</td>
            </tr>
            <tr>
                <td>customer</td>
                <td>顧客ユーザー</td>
                <td>customer@example.com</td>
                <td>customer1234</td>
            </tr>
        </tbody>
    </table>
</body>
</html>
