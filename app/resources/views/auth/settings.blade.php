@extends('work.layout')

@section('content')
    @php
        $user = auth()->user();
        $profileErrors = $errors->updateProfileInformation;
        $passwordErrors = $errors->updatePassword;
    @endphp

    <h1>ユーザー設定</h1>
    <p class="muted">メールアドレス、アカウント名、パスワードを変更できます。</p>

    <section style="margin-top:16px; padding:16px; border:1px solid #d1d5db; background:#fff;">
        <h2 style="margin-top:0;">アカウント情報</h2>

        @if($profileErrors->any())
            <div style="margin:12px 0; padding:8px; background:#fee2e2; border:1px solid #dc2626;">
                <ul>
                    @foreach($profileErrors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('user-profile-information.update') }}">
            @csrf
            @method('PUT')
            <div style="margin-top:8px;">
                <label for="settings_name">アカウント名</label>
                <input id="settings_name" type="text" name="name" maxlength="255" required autocomplete="name" value="{{ old('name', $user?->name) }}">
            </div>
            <div style="margin-top:8px;">
                <label for="settings_email">メールアドレス</label>
                <input id="settings_email" type="email" name="email" maxlength="255" required autocomplete="email" value="{{ old('email', $user?->email) }}">
            </div>
            <div class="actions" style="margin-top:12px;">
                <button type="submit">アカウント情報を更新</button>
            </div>
        </form>
    </section>

    <section style="margin-top:16px; padding:16px; border:1px solid #d1d5db; background:#fff;">
        <h2 style="margin-top:0;">パスワード変更</h2>

        @if($passwordErrors->any())
            <div style="margin:12px 0; padding:8px; background:#fee2e2; border:1px solid #dc2626;">
                <ul>
                    @foreach($passwordErrors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('user-password.update') }}">
            @csrf
            @method('PUT')
            <div style="margin-top:8px;">
                <label for="settings_current_password">現在のパスワード</label>
                <input id="settings_current_password" type="password" name="current_password" required autocomplete="current-password">
            </div>
            <div style="margin-top:8px;">
                <label for="settings_password">新しいパスワード</label>
                <input id="settings_password" type="password" name="password" required autocomplete="new-password">
            </div>
            <div style="margin-top:8px;">
                <label for="settings_password_confirmation">新しいパスワード（確認）</label>
                <input id="settings_password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
            </div>
            <div class="actions" style="margin-top:12px;">
                <button type="submit">パスワードを更新</button>
            </div>
        </form>
    </section>
@endsection
