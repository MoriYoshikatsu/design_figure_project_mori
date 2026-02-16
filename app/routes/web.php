<?php
use Illuminate\Support\Facades\Route;
use App\Services\SvgRenderer;
use App\Livewire\Configurator;
use Illuminate\Http\Request;
use App\Models\ConfiguratorSession;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Admin\AdminSkuController;
use App\Http\Controllers\Admin\AdminAccountController;
use App\Http\Controllers\Admin\AdminPriceBookController;
use App\Http\Controllers\Admin\AdminPriceBookItemController;
use App\Http\Controllers\Admin\AdminTemplateController;
use App\Http\Controllers\Admin\AdminTemplateVersionController;
use App\Http\Controllers\Admin\AdminChangeRequestController;
use App\Http\Controllers\Admin\AdminAuditLogController;
use App\Http\Controllers\Ops\ConfiguratorSessionController;
use App\Http\Controllers\Ops\ChangeRequestController as OpsChangeRequestController;
use App\Http\Controllers\Ops\QuoteController;
use App\Services\GuestAccountClaimService;
use App\Services\SnapshotPdfService;

Route::get('/', function () {
    return view('landing');
});

Route::get('/configurator', Configurator::class);

Route::post('/configurator/autosave', function (Request $request) {

    $sid = (int)$request->input('session_id');
    $userId = (int)($request->user()?->id ?? 0);

    // Cookie（保存）と一致しないsidを拒否（簡易防御）
    $cookieSid = (int)$request->cookie('config_session_id');
    if ($cookieSid !== $sid) {
        abort(403);
    }

    $session = ConfiguratorSession::find($sid);
    if (!$session) {
        abort(404);
    }

    if ($userId > 0) {
        $belongsToUser = DB::table('account_user')
            ->where('account_id', (int)$session->account_id)
            ->where('user_id', $userId)
            ->exists();
        if (!$belongsToUser) {
            abort(403);
        }
    } else {
        // 未ログイン時は未紐付けaccount（account_userレコードなし）のセッションだけ許可
        $linkedToAnyUser = DB::table('account_user')
            ->where('account_id', (int)$session->account_id)
            ->exists();
        if ($linkedToAnyUser) {
            abort(403);
        }
    }

    $configJson = (string)$request->input('config_json', '{}');
    $config = json_decode($configJson, true);
    if (!is_array($config)) $config = [];

    $payload = [
        'config' => $config,
        'status' => 'DRAFT',
    ];
    if ($request->has('memo')) {
        $memo = trim((string)$request->input('memo', ''));
        $payload['memo'] = $memo === '' ? null : $memo;
    }

    // ここではconfig/memoだけ保存（derived/errorsは次回表示時に再計算でOK）
    ConfiguratorSession::where('id', $sid)->update($payload);

    return response()->noContent(); // 空でOK
})->name('configurator.autosave');

Route::get('/quotes/{id}', function ($id, SvgRenderer $renderer) {
    $userId = (int)auth()->id();
    if ($userId > 0) {
        $cookieSid = request()->cookie('config_session_id');
        $cookieSessionId = is_numeric($cookieSid) ? (int)$cookieSid : null;
        app(GuestAccountClaimService::class)->claimQuoteForUser((int)$id, $userId, $cookieSessionId);
    }

    $accountUserName = DB::table('account_user as au2')
        ->join('users as u2', 'u2.id', '=', 'au2.user_id')
        ->whereColumn('au2.account_id', 'a.id')
        ->orderByRaw("
            case au2.role
                when 'customer' then 1
                when 'admin' then 2
                when 'sales' then 3
                else 9
            end
        ")
        ->orderBy('au2.user_id')
        ->select('u2.name')
        ->limit(1);

    $quote = DB::table('quotes as q')
        ->leftJoin('accounts as a', 'a.id', '=', 'q.account_id')
        ->leftJoin('configurator_sessions as cs', 'cs.id', '=', 'q.session_id')
        ->select('q.*')
        ->addSelect('a.internal_name as account_internal_name')
        ->selectSub($accountUserName, 'account_user_name')
        ->addSelect('a.assignee_name as account_assignee_name')
        ->addSelect('cs.memo as session_memo')
        ->whereExists(function ($sq) use ($userId) {
            $sq->selectRaw('1')
                ->from('account_user as au')
                ->whereColumn('au.account_id', 'q.account_id')
                ->where('au.user_id', $userId);
        })
        ->where('q.id', (int)$id)
        ->first();
    if (!$quote) {
        abort(404);
    }

    $quoteMemo = trim((string)($quote->memo ?? ''));
    $sessionMemo = trim((string)($quote->session_memo ?? ''));
    $quote->display_memo = $quoteMemo !== '' ? $quoteMemo : $sessionMemo;

    $accountMembers = DB::table('account_user as au')
        ->join('users as u', 'u.id', '=', 'au.user_id')
        ->where('au.account_id', (int)$quote->account_id)
        ->select('u.name as user_name', 'u.email as user_email', 'au.role')
        ->orderByRaw("case au.role when 'admin' then 1 when 'sales' then 2 else 3 end")
        ->orderBy('u.id')
        ->get();

    $snapshot = json_decode($quote->snapshot ?? '', true);
    if (!is_array($snapshot)) $snapshot = [];
    $nameSource = (string)($snapshot['account_display_name_source'] ?? 'internal_name');
    if (!in_array($nameSource, ['internal_name', 'user_name'], true)) {
        $nameSource = 'internal_name';
    }
    $internalName = trim((string)($quote->account_internal_name ?? ''));
    $userName = trim((string)($quote->account_user_name ?? ''));
    if ($nameSource === 'user_name') {
        $quote->account_name = $userName !== '' ? $userName : ($internalName !== '' ? $internalName : '-');
    } else {
        $quote->account_name = $internalName !== '' ? $internalName : ($userName !== '' ? $userName : '-');
    }
    $quote->account_name_source = $nameSource;

    $config = $snapshot['config'] ?? [];
    $derived = $snapshot['derived'] ?? [];
    $errors = $snapshot['validation_errors'] ?? [];

    $svg = $renderer->render($config, $derived, $errors);

    $totals = $snapshot['totals'] ?? [
        'subtotal' => (float)($quote->subtotal ?? 0),
        'tax' => (float)($quote->tax_total ?? 0),
        'total' => (float)($quote->total ?? 0),
    ];
    // dd($quote);
    return view('quotes.show', [
        'quote' => $quote,
        'accountMembers' => $accountMembers,
        'snapshot' => $snapshot,
        'svg' => $svg,
        'totals' => $totals,
    ]);
})->middleware('auth')->name('quotes.show');

Route::get('/quotes/{id}/snapshot.pdf', function ($id, SvgRenderer $renderer, SnapshotPdfService $pdfService) {
    $userId = (int)auth()->id();
    if ($userId > 0) {
        $cookieSid = request()->cookie('config_session_id');
        $cookieSessionId = is_numeric($cookieSid) ? (int)$cookieSid : null;
        app(GuestAccountClaimService::class)->claimQuoteForUser((int)$id, $userId, $cookieSessionId);
    }

    $accountUserName = DB::table('account_user as au2')
        ->join('users as u2', 'u2.id', '=', 'au2.user_id')
        ->whereColumn('au2.account_id', 'a.id')
        ->orderByRaw("
            case au2.role
                when 'customer' then 1
                when 'admin' then 2
                when 'sales' then 3
                else 9
            end
        ")
        ->orderBy('au2.user_id')
        ->select('u2.name')
        ->limit(1);

    $quote = DB::table('quotes as q')
        ->leftJoin('accounts as a', 'a.id', '=', 'q.account_id')
        ->leftJoin('configurator_sessions as cs', 'cs.id', '=', 'q.session_id')
        ->select('q.*')
        ->addSelect('a.internal_name as account_internal_name')
        ->selectSub($accountUserName, 'account_user_name')
        ->addSelect('a.assignee_name as account_assignee_name')
        ->addSelect('cs.memo as session_memo')
        ->whereExists(function ($sq) use ($userId) {
            $sq->selectRaw('1')
                ->from('account_user as au')
                ->whereColumn('au.account_id', 'q.account_id')
                ->where('au.user_id', $userId);
        })
        ->where('q.id', (int)$id)
        ->first();
    if (!$quote) {
        abort(404);
    }

    $quoteMemo = trim((string)($quote->memo ?? ''));
    $sessionMemo = trim((string)($quote->session_memo ?? ''));
    $quote->display_memo = $quoteMemo !== '' ? $quoteMemo : $sessionMemo;

    $snapshot = json_decode($quote->snapshot ?? '', true);
    if (!is_array($snapshot)) $snapshot = [];
    $nameSource = (string)($snapshot['account_display_name_source'] ?? 'internal_name');
    if (!in_array($nameSource, ['internal_name', 'user_name'], true)) {
        $nameSource = 'internal_name';
    }
    $internalName = trim((string)($quote->account_internal_name ?? ''));
    $userName = trim((string)($quote->account_user_name ?? ''));
    if ($nameSource === 'user_name') {
        $quote->account_name = $userName !== '' ? $userName : ($internalName !== '' ? $internalName : '-');
    } else {
        $quote->account_name = $internalName !== '' ? $internalName : ($userName !== '' ? $userName : '-');
    }
    $quote->account_name_source = $nameSource;

    $config = $snapshot['config'] ?? [];
    $derived = $snapshot['derived'] ?? [];
    $errors = $snapshot['validation_errors'] ?? [];
    $totals = $snapshot['totals'] ?? [
        'subtotal' => (float)($quote->subtotal ?? 0),
        'tax' => (float)($quote->tax_total ?? 0),
        'total' => (float)($quote->total ?? 0),
    ];

    $svg = $renderer->render($config, $derived, $errors);

    $filename = $pdfService->buildFilename(
        'quote',
        (int)$quote->account_id,
        (int)($snapshot['template_version_id'] ?? 0),
        $snapshot,
        $config,
        $derived,
        (string)$quote->updated_at
    );

    return $pdfService->downloadQuoteUi([
        'quote' => $quote,
        'snapshot' => $snapshot,
        'svg' => $svg,
        'totals' => $totals,
    ], $filename);
})->middleware('auth')->name('quotes.snapshot.pdf');

Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/accounts', [AdminAccountController::class, 'index'])->name('admin.accounts.index');
    Route::get('/accounts/{id}/edit', [AdminAccountController::class, 'edit'])->name('admin.accounts.edit');
    Route::put('/accounts/{id}', [AdminAccountController::class, 'update'])->name('admin.accounts.update');
    Route::put('/accounts/{id}/members/{userId}/memo', [AdminAccountController::class, 'updateMemberMemo'])->name('admin.accounts.members.memo.update');

    Route::get('/skus', [AdminSkuController::class, 'index'])->name('admin.skus.index');
    Route::get('/skus/create', [AdminSkuController::class, 'create'])->name('admin.skus.create');
    Route::post('/skus', [AdminSkuController::class, 'store'])->name('admin.skus.store');
    Route::get('/skus/{id}/edit', [AdminSkuController::class, 'edit'])->name('admin.skus.edit');
    Route::put('/skus/{id}', [AdminSkuController::class, 'update'])->name('admin.skus.update');

    Route::get('/price-books', [AdminPriceBookController::class, 'index'])->name('admin.price-books.index');
    Route::get('/price-books/create', [AdminPriceBookController::class, 'create'])->name('admin.price-books.create');
    Route::post('/price-books', [AdminPriceBookController::class, 'store'])->name('admin.price-books.store');
    Route::get('/price-books/{id}/edit', [AdminPriceBookController::class, 'edit'])->name('admin.price-books.edit');
    Route::put('/price-books/{id}', [AdminPriceBookController::class, 'update'])->name('admin.price-books.update');

    Route::post('/price-books/{id}/items', [AdminPriceBookItemController::class, 'store'])->name('admin.price-books.items.store');
    Route::get('/price-books/{id}/items/{item}/edit', [AdminPriceBookItemController::class, 'edit'])->name('admin.price-books.items.edit');
    Route::put('/price-books/{id}/items/{item}', [AdminPriceBookItemController::class, 'update'])->name('admin.price-books.items.update');
    Route::delete('/price-books/{id}/items/{item}', [AdminPriceBookItemController::class, 'destroy'])->name('admin.price-books.items.destroy');

    Route::get('/templates', [AdminTemplateController::class, 'index'])->name('admin.templates.index');
    Route::get('/templates/create', [AdminTemplateController::class, 'create'])->name('admin.templates.create');
    Route::post('/templates', [AdminTemplateController::class, 'store'])->name('admin.templates.store');
    Route::get('/templates/{id}/edit', [AdminTemplateController::class, 'edit'])->name('admin.templates.edit');
    Route::put('/templates/{id}', [AdminTemplateController::class, 'update'])->name('admin.templates.update');

    Route::post('/templates/{id}/versions', [AdminTemplateVersionController::class, 'store'])->name('admin.templates.versions.store');
    Route::get('/templates/{id}/versions/{version}/edit', [AdminTemplateVersionController::class, 'edit'])->name('admin.templates.versions.edit');
    Route::put('/templates/{id}/versions/{version}', [AdminTemplateVersionController::class, 'update'])->name('admin.templates.versions.update');

    Route::get('/change-requests', [AdminChangeRequestController::class, 'index'])->name('admin.change-requests.index');
    Route::get('/change-requests/{id}', [AdminChangeRequestController::class, 'show'])->name('admin.change-requests.show');
    Route::get('/change-requests/{id}/snapshot.pdf', [AdminChangeRequestController::class, 'downloadSnapshotPdf'])->name('admin.change-requests.snapshot.pdf');
    Route::get('/change-requests/{id}/snapshot-base.pdf', [AdminChangeRequestController::class, 'downloadBaseSnapshotPdf'])->name('admin.change-requests.snapshot-base.pdf');
    Route::get('/change-requests/{id}/snapshot-compare.pdf', [AdminChangeRequestController::class, 'downloadComparisonPdf'])->name('admin.change-requests.snapshot-compare.pdf');
    Route::put('/change-requests/{id}/memo', [AdminChangeRequestController::class, 'updateMemo'])->name('admin.change-requests.memo.update');
    Route::post('/change-requests/{id}/approve', [AdminChangeRequestController::class, 'approve'])->name('admin.change-requests.approve');
    Route::post('/change-requests/{id}/reject', [AdminChangeRequestController::class, 'reject'])->name('admin.change-requests.reject');

    Route::get('/audit-logs', [AdminAuditLogController::class, 'index'])->name('admin.audit-logs.index');
});

Route::middleware(['auth', 'role:admin,sales'])->prefix('ops')->group(function () {
    Route::get('/configurator-sessions', [ConfiguratorSessionController::class, 'index'])->name('ops.sessions.index');
    Route::get('/configurator-sessions/{id}', [ConfiguratorSessionController::class, 'show'])->name('ops.sessions.show');
    Route::get('/configurator-sessions/{id}/snapshot.pdf', [ConfiguratorSessionController::class, 'downloadSnapshotPdf'])->name('ops.sessions.snapshot.pdf');
    Route::put('/configurator-sessions/{id}/memo', [ConfiguratorSessionController::class, 'updateMemo'])->name('ops.sessions.memo.update');

    Route::get('/change-requests/{id}', [OpsChangeRequestController::class, 'show'])->name('ops.change-requests.show');
    Route::get('/change-requests/{id}/snapshot.pdf', [OpsChangeRequestController::class, 'downloadSnapshotPdf'])->name('ops.change-requests.snapshot.pdf');
    Route::get('/change-requests/{id}/snapshot-base.pdf', [OpsChangeRequestController::class, 'downloadBaseSnapshotPdf'])->name('ops.change-requests.snapshot-base.pdf');
    Route::get('/change-requests/{id}/snapshot-compare.pdf', [OpsChangeRequestController::class, 'downloadComparisonPdf'])->name('ops.change-requests.snapshot-compare.pdf');
    Route::put('/change-requests/{id}/memo', [OpsChangeRequestController::class, 'updateMemo'])->name('ops.change-requests.memo.update');

    Route::get('/quotes', [QuoteController::class, 'index'])->name('ops.quotes.index');
    Route::get('/quotes/{id}', [QuoteController::class, 'show'])->name('ops.quotes.show');
    Route::get('/quotes/{id}/edit', [QuoteController::class, 'edit'])->name('ops.quotes.edit');
    Route::get('/quotes/{id}/snapshot.pdf', [QuoteController::class, 'downloadSnapshotPdf'])->name('ops.quotes.snapshot.pdf');
    Route::put('/quotes/{id}/display-name-source', [QuoteController::class, 'updateDisplayNameSource'])->name('ops.quotes.display-name-source.update');
    Route::put('/quotes/{id}/memo', [QuoteController::class, 'updateMemo'])->name('ops.quotes.memo.update');
});
