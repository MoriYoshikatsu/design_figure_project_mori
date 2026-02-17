<?php
use Illuminate\Support\Facades\Route;
use App\Services\SvgRenderer;
use App\Livewire\Configurator;
use Illuminate\Http\Request;
use App\Models\ConfiguratorSession;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\SkuController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\PriceBookController;
use App\Http\Controllers\PriceBookItemController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\TemplateVersionController;
use App\Http\Controllers\ChangeRequestReviewController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\ChangeRequestController;
use App\Http\Controllers\QuoteController;
use App\Services\GuestAccountClaimService;
use App\Services\SnapshotPdfService;

// Route::get('/', function () {
//     return view('landing');
// });

Route::middleware(['auth'])->group(function () {
    Route::view('/user/settings', 'auth.settings')->name('user.settings');
});

Route::get('/configurator', Configurator::class)->name('configurator');

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

    $accountEmails = DB::table('account_user as au3')
        ->join('users as u3', 'u3.id', '=', 'au3.user_id')
        ->whereColumn('au3.account_id', 'q.account_id')
        ->selectRaw("string_agg(distinct u3.email, ', ' order by u3.email)");

    $quote = DB::table('quotes as q')
        ->leftJoin('accounts as a', 'a.id', '=', 'q.account_id')
        ->leftJoin('configurator_sessions as cs', 'cs.id', '=', 'q.session_id')
        ->select('q.*')
        ->addSelect('a.internal_name as account_internal_name')
        ->selectSub($accountUserName, 'account_user_name')
        ->selectSub($accountEmails, 'account_emails')
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
    $internalName = trim((string)($quote->account_internal_name ?? ''));
    $userName = trim((string)($quote->account_user_name ?? ''));
    $quote->account_name = $internalName !== '' ? $internalName : ($userName !== '' ? $userName : '-');
    $quote->account_name_source = 'internal_name';

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
    return view('quote_show', [
        'quote' => $quote,
        'accountMembers' => $accountMembers,
        'snapshot' => $snapshot,
        'svg' => $svg,
        'totals' => $totals,
    ]);
})->middleware(['auth', 'account.route'])->name('quotes.show');

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

    $accountEmails = DB::table('account_user as au3')
        ->join('users as u3', 'u3.id', '=', 'au3.user_id')
        ->whereColumn('au3.account_id', 'q.account_id')
        ->selectRaw("string_agg(distinct u3.email, ', ' order by u3.email)");

    $quote = DB::table('quotes as q')
        ->leftJoin('accounts as a', 'a.id', '=', 'q.account_id')
        ->leftJoin('configurator_sessions as cs', 'cs.id', '=', 'q.session_id')
        ->select('q.*')
        ->addSelect('a.internal_name as account_internal_name')
        ->selectSub($accountUserName, 'account_user_name')
        ->selectSub($accountEmails, 'account_emails')
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
    $internalName = trim((string)($quote->account_internal_name ?? ''));
    $userName = trim((string)($quote->account_user_name ?? ''));
    $quote->account_name = $internalName !== '' ? $internalName : ($userName !== '' ? $userName : '-');
    $quote->account_name_source = 'internal_name';

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
})->middleware(['auth', 'account.route'])->name('quotes.snapshot.pdf');

Route::middleware(['auth', 'work.access'])->prefix('work')->name('work.')->group(function () {
    Route::get('/accounts', [AccountController::class, 'index'])->name('accounts.index');
    Route::post('/accounts/edit-request/create', [AccountController::class, 'store'])->name('accounts.edit-request.create');
    Route::get('/accounts/{id}/edit', [AccountController::class, 'edit'])->name('accounts.edit');
    Route::get('/accounts/{id}/permissions', [AccountController::class, 'permissions'])->name('accounts.permissions');
    Route::put('/accounts/{id}', [AccountController::class, 'update'])->name('accounts.update');
    Route::post('/accounts/{id}/edit-request/update', [AccountController::class, 'update'])->name('accounts.edit-request.update');
    Route::post('/accounts/{id}/edit-request/delete', [AccountController::class, 'destroy'])->name('accounts.edit-request.delete');
    Route::put('/accounts/{id}/members/{userId}/memo', [AccountController::class, 'updateMemberMemo'])->name('accounts.members.memo.update');
    Route::post('/accounts/{id}/members/{userId}/edit-request/update-memo', [AccountController::class, 'updateMemberMemo'])->name('accounts.members.memo.edit-request.update');
    Route::post('/accounts/{id}/sales-route-permissions', [AccountController::class, 'storeSalesRoutePermission'])->name('accounts.sales-route-permissions.store');
    Route::post('/accounts/{id}/sales-route-permissions/edit-request/create', [AccountController::class, 'storeSalesRoutePermission'])->name('accounts.sales-route-permissions.edit-request.create');
    Route::put('/accounts/{id}/sales-route-permissions/{permId}', [AccountController::class, 'updateSalesRoutePermission'])->name('accounts.sales-route-permissions.update');
    Route::post('/accounts/{id}/sales-route-permissions/{permId}/edit-request/update', [AccountController::class, 'updateSalesRoutePermission'])->name('accounts.sales-route-permissions.edit-request.update');
    Route::delete('/accounts/{id}/sales-route-permissions/{permId}', [AccountController::class, 'destroySalesRoutePermission'])->name('accounts.sales-route-permissions.destroy');
    Route::post('/accounts/{id}/sales-route-permissions/{permId}/edit-request/delete', [AccountController::class, 'destroySalesRoutePermission'])->name('accounts.sales-route-permissions.edit-request.delete');

    Route::get('/skus', [SkuController::class, 'index'])->name('skus.index');
    Route::get('/skus/create', [SkuController::class, 'create'])->name('skus.create');
    Route::post('/skus', [SkuController::class, 'store'])->name('skus.store');
    Route::post('/skus/edit-request/create', [SkuController::class, 'store'])->name('skus.edit-request.create');
    Route::get('/skus/{id}/edit', [SkuController::class, 'edit'])->name('skus.edit');
    Route::put('/skus/{id}', [SkuController::class, 'update'])->name('skus.update');
    Route::post('/skus/{id}/edit-request/update', [SkuController::class, 'update'])->name('skus.edit-request.update');
    Route::delete('/skus/{id}', [SkuController::class, 'destroy'])->name('skus.destroy');
    Route::post('/skus/{id}/edit-request/delete', [SkuController::class, 'destroy'])->name('skus.edit-request.delete');

    Route::get('/price-books', [PriceBookController::class, 'index'])->name('price-books.index');
    Route::get('/price-books/create', [PriceBookController::class, 'create'])->name('price-books.create');
    Route::post('/price-books', [PriceBookController::class, 'store'])->name('price-books.store');
    Route::post('/price-books/edit-request/create', [PriceBookController::class, 'store'])->name('price-books.edit-request.create');
    Route::get('/price-books/{id}/edit', [PriceBookController::class, 'edit'])->name('price-books.edit');
    Route::put('/price-books/{id}', [PriceBookController::class, 'update'])->name('price-books.update');
    Route::post('/price-books/{id}/edit-request/update', [PriceBookController::class, 'update'])->name('price-books.edit-request.update');
    Route::delete('/price-books/{id}', [PriceBookController::class, 'destroy'])->name('price-books.destroy');
    Route::post('/price-books/{id}/edit-request/delete', [PriceBookController::class, 'destroy'])->name('price-books.edit-request.delete');

    Route::post('/price-books/{id}/items', [PriceBookItemController::class, 'store'])->name('price-books.items.store');
    Route::post('/price-books/{id}/items/edit-request/create', [PriceBookItemController::class, 'store'])->name('price-books.items.edit-request.create');
    Route::get('/price-books/{id}/items/{item}/edit', [PriceBookItemController::class, 'edit'])->name('price-books.items.edit');
    Route::put('/price-books/{id}/items/{item}', [PriceBookItemController::class, 'update'])->name('price-books.items.update');
    Route::post('/price-books/{id}/items/{item}/edit-request/update', [PriceBookItemController::class, 'update'])->name('price-books.items.edit-request.update');
    Route::delete('/price-books/{id}/items/{item}', [PriceBookItemController::class, 'destroy'])->name('price-books.items.destroy');
    Route::post('/price-books/{id}/items/{item}/edit-request/delete', [PriceBookItemController::class, 'destroy'])->name('price-books.items.edit-request.delete');

    Route::get('/templates', [TemplateController::class, 'index'])->name('templates.index');
    Route::get('/templates/create', [TemplateController::class, 'create'])->name('templates.create');
    Route::post('/templates', [TemplateController::class, 'store'])->name('templates.store');
    Route::post('/templates/edit-request/create', [TemplateController::class, 'store'])->name('templates.edit-request.create');
    Route::get('/templates/{id}/edit', [TemplateController::class, 'edit'])->name('templates.edit');
    Route::put('/templates/{id}', [TemplateController::class, 'update'])->name('templates.update');
    Route::post('/templates/{id}/edit-request/update', [TemplateController::class, 'update'])->name('templates.edit-request.update');
    Route::delete('/templates/{id}', [TemplateController::class, 'destroy'])->name('templates.destroy');
    Route::post('/templates/{id}/edit-request/delete', [TemplateController::class, 'destroy'])->name('templates.edit-request.delete');

    Route::post('/templates/{id}/versions', [TemplateVersionController::class, 'store'])->name('templates.versions.store');
    Route::post('/templates/{id}/versions/edit-request/create', [TemplateVersionController::class, 'store'])->name('templates.versions.edit-request.create');
    Route::get('/templates/{id}/versions/{version}/edit', [TemplateVersionController::class, 'edit'])->name('templates.versions.edit');
    Route::put('/templates/{id}/versions/{version}', [TemplateVersionController::class, 'update'])->name('templates.versions.update');
    Route::post('/templates/{id}/versions/{version}/edit-request/update', [TemplateVersionController::class, 'update'])->name('templates.versions.edit-request.update');
    Route::delete('/templates/{id}/versions/{version}', [TemplateVersionController::class, 'destroy'])->name('templates.versions.destroy');
    Route::post('/templates/{id}/versions/{version}/edit-request/delete', [TemplateVersionController::class, 'destroy'])->name('templates.versions.edit-request.delete');

    Route::get('/sessions', [SessionController::class, 'index'])->name('sessions.index');
    Route::get('/sessions/{id}', [SessionController::class, 'show'])->name('sessions.show');
    Route::get('/sessions/{id}/snapshot.pdf', [SessionController::class, 'downloadSnapshotPdf'])->name('sessions.snapshot.pdf');

    Route::get('/quotes', [QuoteController::class, 'index'])->name('quotes.index');
    Route::get('/quotes/{id}', [QuoteController::class, 'show'])->name('quotes.show');
    Route::get('/quotes/{id}/edit', [QuoteController::class, 'edit'])->name('quotes.edit');
    Route::get('/quotes/{id}/edit-request', [QuoteController::class, 'editRequest'])->name('quotes.edit-request');
    Route::post('/quotes/{id}/edit-request/store', [QuoteController::class, 'storeEditRequest'])->name('quotes.edit-request.store');
    Route::post('/quotes/{id}/edit-request/update', [QuoteController::class, 'storeEditRequest'])->name('quotes.edit-request.update');
    Route::get('/quotes/{id}/snapshot.pdf', [QuoteController::class, 'downloadSnapshotPdf'])->name('quotes.snapshot.pdf');
    Route::put('/quotes/{id}/display-name-source', [QuoteController::class, 'updateDisplayNameSource'])->name('quotes.display-name-source.update');
    Route::post('/quotes/{id}/display-name-source/edit-request/update', [QuoteController::class, 'updateDisplayNameSource'])->name('quotes.display-name-source.edit-request.update');
    Route::put('/quotes/{id}/summary-fields', [QuoteController::class, 'updateSummaryFields'])->name('quotes.summary-fields.update');
    Route::post('/quotes/{id}/summary-fields/edit-request/update', [QuoteController::class, 'updateSummaryFields'])->name('quotes.summary-fields.edit-request.update');
    Route::put('/quotes/{id}/memo', [QuoteController::class, 'updateMemo'])->name('quotes.memo.update');
    Route::post('/quotes/{id}/memo/edit-request/update', [QuoteController::class, 'updateMemo'])->name('quotes.memo.edit-request.update');

    Route::get('/change-requests', [ChangeRequestReviewController::class, 'index'])->name('change-requests.index');
    Route::get('/change-requests/{id}', [ChangeRequestController::class, 'show'])->name('change-requests.show');
    Route::get('/change-requests/{id}/snapshot.pdf', [ChangeRequestController::class, 'downloadSnapshotPdf'])->name('change-requests.snapshot.pdf');
    Route::get('/change-requests/{id}/snapshot-base.pdf', [ChangeRequestController::class, 'downloadBaseSnapshotPdf'])->name('change-requests.snapshot-base.pdf');
    Route::get('/change-requests/{id}/snapshot-compare.pdf', [ChangeRequestController::class, 'downloadComparisonPdf'])->name('change-requests.snapshot-compare.pdf');
    Route::put('/change-requests/{id}/memo', [ChangeRequestController::class, 'updateMemo'])->name('change-requests.memo.update');
    Route::post('/change-requests/{id}/approve', [ChangeRequestReviewController::class, 'approve'])->name('change-requests.approve');
    Route::post('/change-requests/{id}/reject', [ChangeRequestReviewController::class, 'reject'])->name('change-requests.reject');

    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::get('/audit-logs/{id}', [AuditLogController::class, 'show'])->name('audit-logs.show');
});
