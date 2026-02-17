@extends('admin.layout')

@section('content')
    @php
        $code = trim((string) $__env->yieldContent('code'));
        $title = trim((string) $__env->yieldContent('title'));
        $message = trim((string) $__env->yieldContent('message'));
        $jpMessage = trim((string) $__env->yieldContent('jp_message'));

        if ($jpMessage === '') {
            $jpMap = [
                '401' => 'ログインが必要です。',
                '402' => 'お支払いが必要なため、このページを表示できません。',
                '403' => 'このページへのアクセスは許可されていません。',
                '404' => 'ページが見つかりません。',
                '419' => 'セッションの有効期限が切れました。',
                '429' => 'リクエストが多すぎます。しばらく待って再試行してください。',
                '500' => 'サーバー内部でエラーが発生しました。',
                '503' => '現在サービスを利用できません。',
            ];
            $jpMessage = $jpMap[$code] ?? 'エラーが発生しました。';
        }

        $enMessage = $message !== '' ? $message : ($title !== '' ? $title : 'Error');
    @endphp

    <section style="margin-top:24px; padding:20px; border:1px solid #d1d5db; background:#f9fafb;">
        <div style="font-size:40px; font-weight:700; line-height:1;">{{ $code !== '' ? $code : 'Error' }}</div>
        <p style="margin-top:10px; font-size:18px; font-weight:600;">{{ $jpMessage }}</p>

        @if($code === '403')
            <p style="margin-top:8px; color:#b91c1c; font-weight:600;">
                アクセス権限については管理者（社長）へ直接ご相談ください。
            </p>
        @endif

        <p class="muted" style="margin-top:8px;">{{ $enMessage }}</p>
    </section>
@endsection
