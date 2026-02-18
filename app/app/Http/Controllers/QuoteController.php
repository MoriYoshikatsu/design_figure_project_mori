<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\SnapshotPdfService;
use App\Services\SvgRenderer;
use App\Services\WorkChangeRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class QuoteController extends Controller
{
    /** @var array<string, string> */
    private const SUMMARY_FIELD_LABELS = [
        'quote_id' => '見積ID',
        'status' => 'ステータス',
        'account_internal_name' => 'accounts.internal_name',
        'account_user_name' => 'users.name',
        'assignee_name' => '担当者',
        'customer_emails' => '登録メールアドレス',
        'request_count' => '承認リクエスト件数',
        'template_version_id' => 'ルールテンプレ',
        'price_book_id' => '納品物価格表',
        'subtotal' => '小計',
        'tax' => '税',
        'total' => '合計',
    ];
    /** @var array<int, string> */
    private const SUMMARY_DEFAULT_FIELDS = [
        'quote_id',
        'status',
        'account_internal_name',
        'account_user_name',
        'assignee_name',
        'customer_emails',
        'request_count',
        'template_version_id',
        'price_book_id',
        'subtotal',
        'tax',
        'total',
    ];

    public function index(Request $request)
    {
        $isDate = static fn (string $v): bool => (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $v);

        $q = trim((string)$request->input('q', ''));
        $status = (string)$request->input('status', '');
        $currency = (string)$request->input('currency', '');
        $accountId = (string)$request->input('account_id', '');
        $accountType = (string)$request->input('account_type', '');
        $assignee = trim((string)$request->input('assignee_name', ''));
        $hasMemo = (string)$request->input('has_memo', '');
        $totalMin = (string)$request->input('total_min', '');
        $totalMax = (string)$request->input('total_max', '');
        $createdFrom = (string)$request->input('created_from', '');
        $createdTo = (string)$request->input('created_to', '');
        $updatedFrom = (string)$request->input('updated_from', '');
        $updatedTo = (string)$request->input('updated_to', '');

        $accountEmails = DB::table('account_user as au')
            ->join('users as u', 'u.id', '=', 'au.user_id')
            ->whereColumn('au.account_id', 'q.account_id')
            ->selectRaw("string_agg(distinct u.email, ', ' order by u.email)");

        $query = DB::table('quotes as q')
            ->join('accounts as a', 'a.id', '=', 'q.account_id')
            ->whereNull('q.deleted_at')
            ->whereNull('a.deleted_at')
            ->select('q.*')
            ->selectRaw("
                coalesce(
                    nullif(a.internal_name, ''),
                    (
                        select u2.name
                        from account_user as au2
                        join users as u2 on u2.id = au2.user_id
                        where au2.account_id = a.id
                        order by
                            case au2.role
                                when 'customer' then 1
                                when 'admin' then 2
                                when 'sales' then 3
                                else 9
                            end,
                            au2.user_id
                        limit 1
                    ),
                    '-'
                ) as account_display_name
            ")
            ->addSelect('a.internal_name as account_name', 'a.internal_name as account_internal_name', 'a.assignee_name')
            ->selectSub($accountEmails, 'account_emails');

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->whereRaw('cast(q.id as text) ilike ?', ["%{$q}%"])
                    ->orWhereRaw('cast(q.account_id as text) ilike ?', ["%{$q}%"])
                    ->orWhere('q.status', 'ilike', "%{$q}%")
                    ->orWhere('q.currency', 'ilike', "%{$q}%")
                    ->orWhere('q.memo', 'ilike', "%{$q}%")
                    ->orWhereRaw('cast(q.total as text) ilike ?', ["%{$q}%"])
                    ->orWhere('a.internal_name', 'ilike', "%{$q}%")
                    ->orWhere('a.assignee_name', 'ilike', "%{$q}%")
                    ->orWhereExists(function ($sq) use ($q) {
                        $sq->selectRaw('1')
                            ->from('account_user as au')
                            ->join('users as u', 'u.id', '=', 'au.user_id')
                            ->whereColumn('au.account_id', 'q.account_id')
                            ->where(function ($userSub) use ($q) {
                                $userSub->where('u.name', 'ilike', "%{$q}%")
                                    ->orWhere('u.email', 'ilike', "%{$q}%");
                            });
                    });
            });
        }
        if ($status !== '') {
            $query->where('q.status', $status);
        }
        if ($currency !== '') {
            $query->where('q.currency', $currency);
        }
        if ($accountId !== '' && is_numeric($accountId)) {
            $query->where('q.account_id', (int)$accountId);
        }
        if ($accountType !== '') {
            $query->where('a.account_type', $accountType);
        }
        if ($assignee !== '') {
            $query->where('a.assignee_name', 'ilike', "%{$assignee}%");
        }
        if ($hasMemo === 'with') {
            $query->whereNotNull('q.memo')->where('q.memo', '<>', '');
        } elseif ($hasMemo === 'without') {
            $query->where(function ($sub) {
                $sub->whereNull('q.memo')->orWhere('q.memo', '');
            });
        }
        if ($totalMin !== '' && is_numeric($totalMin)) {
            $query->where('q.total', '>=', (float)$totalMin);
        }
        if ($totalMax !== '' && is_numeric($totalMax)) {
            $query->where('q.total', '<=', (float)$totalMax);
        }
        if ($createdFrom !== '' && $isDate($createdFrom)) {
            $query->whereDate('q.created_at', '>=', $createdFrom);
        }
        if ($createdTo !== '' && $isDate($createdTo)) {
            $query->whereDate('q.created_at', '<=', $createdTo);
        }
        if ($updatedFrom !== '' && $isDate($updatedFrom)) {
            $query->whereDate('q.updated_at', '>=', $updatedFrom);
        }
        if ($updatedTo !== '' && $isDate($updatedTo)) {
            $query->whereDate('q.updated_at', '<=', $updatedTo);
        }

        $quotes = $query->orderBy('q.id', 'desc')->limit(200)->get();

        $quoteIds = $quotes->pluck('id')->map(fn ($v) => (int)$v)->all();
        if (!empty($quoteIds)) {
            $pendingByQuote = DB::table('change_requests')
                ->where('entity_type', 'quote')
                ->where('status', 'PENDING')
                ->whereIn('operation', ['UPDATE', 'DELETE'])
                ->whereIn('entity_id', $quoteIds)
                ->orderByDesc('id')
                ->get(['entity_id', 'operation'])
                ->groupBy('entity_id');
            foreach ($quotes as $quote) {
                $rows = $pendingByQuote->get((int)$quote->id);
                if ($rows && !$rows->isEmpty()) {
                    $quote->pending_operation = (string)$rows->first()->operation;
                }
            }
        }

        $statusOptions = DB::table('quotes')
            ->whereNull('deleted_at')
            ->select('status')
            ->whereNotNull('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status')
            ->all();

        $currencyOptions = DB::table('quotes')
            ->whereNull('deleted_at')
            ->select('currency')
            ->whereNotNull('currency')
            ->where('currency', '<>', '')
            ->distinct()
            ->orderBy('currency')
            ->pluck('currency')
            ->all();

        $accountTypeOptions = DB::table('accounts')
            ->whereNull('deleted_at')
            ->select('account_type')
            ->whereNotNull('account_type')
            ->distinct()
            ->orderBy('account_type')
            ->pluck('account_type')
            ->all();

        return view('work.quotes.index', [
            'quotes' => $quotes,
            'filters' => [
                'q' => $q,
                'status' => $status,
                'currency' => $currency,
                'account_id' => $accountId,
                'account_type' => $accountType,
                'assignee_name' => $assignee,
                'has_memo' => $hasMemo,
                'total_min' => $totalMin,
                'total_max' => $totalMax,
                'created_from' => $createdFrom,
                'created_to' => $createdTo,
                'updated_from' => $updatedFrom,
                'updated_to' => $updatedTo,
            ],
            'statusOptions' => $statusOptions,
            'currencyOptions' => $currencyOptions,
            'accountTypeOptions' => $accountTypeOptions,
            'presenceOptions' => [
                'with' => 'あり',
                'without' => 'なし',
            ],
        ]);
    }

    public function show(int $id, SvgRenderer $renderer)
    {
        $accountEmails = DB::table('account_user as au')
            ->join('users as u', 'u.id', '=', 'au.user_id')
            ->whereColumn('au.account_id', 'q.account_id')
            ->selectRaw("string_agg(distinct u.email, ', ' order by u.email)");

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
            ->join('accounts as a', 'a.id', '=', 'q.account_id')
            ->leftJoin('configurator_sessions as cs', 'cs.id', '=', 'q.session_id')
            ->whereNull('q.deleted_at')
            ->whereNull('a.deleted_at')
            ->select('q.*')
            ->addSelect('a.internal_name as account_internal_name', 'a.assignee_name')
            ->selectSub($accountUserName, 'account_user_name')
            ->addSelect('cs.memo as session_memo')
            ->selectSub($accountEmails, 'account_emails')
            ->where('q.id', $id)
            ->first();
        if (!$quote) abort(404);

        $quoteMemo = trim((string)($quote->memo ?? ''));
        $sessionMemo = trim((string)($quote->session_memo ?? ''));
        $quote->display_memo = $quoteMemo !== '' ? $quoteMemo : $sessionMemo;

        $snapshot = $this->decodeJson($quote->snapshot) ?? [];
        $quote->account_display_name = $this->resolveAccountDisplayNameInternalFirst(
            (string)($quote->account_internal_name ?? ''),
            (string)($quote->account_user_name ?? '')
        );
        $quote->account_name = $quote->account_display_name;

        $config = $snapshot['config'] ?? [];
        $derived = $snapshot['derived'] ?? [];
        $errors = $snapshot['validation_errors'] ?? [];

        $svg = $renderer->render($config, $derived, $errors);
        $totals = $snapshot['totals'] ?? [];
        $requestCount = (int)DB::table('change_requests')
            ->where('entity_type', 'quote')
            ->where('entity_id', $id)
            ->count();
        $summaryItems = $this->buildQuoteSummaryItems($quote, $snapshot, $requestCount);

        $requests = DB::table('change_requests')
            ->where('change_requests.entity_type', 'quote')
            ->where('change_requests.entity_id', $id)
            ->leftJoin('users as requester', 'requester.id', '=', 'change_requests.requested_by')
            ->leftJoin('users as approver', 'approver.id', '=', 'change_requests.approved_by')
            ->select('change_requests.*')
            ->addSelect('requester.email as requested_by_email', 'approver.email as approved_by_email')
            ->selectSub(
                DB::table('account_user as au')
                    ->join('accounts as a', 'a.id', '=', 'au.account_id')
                    ->join('users as u', 'u.id', '=', 'au.user_id')
                    ->whereColumn('au.user_id', 'change_requests.requested_by')
                    ->selectRaw("coalesce(nullif(a.internal_name, ''), u.name)")
                    ->orderBy('au.account_id')
                    ->limit(1),
                'requested_by_account_display_name'
            )
            ->selectSub(
                DB::table('account_user as au')
                    ->join('accounts as a', 'a.id', '=', 'au.account_id')
                    ->whereColumn('au.user_id', 'change_requests.requested_by')
                    ->select('a.assignee_name')
                    ->orderBy('au.account_id')
                    ->limit(1),
                'requested_by_assignee_name'
            )
            ->selectSub(
                DB::table('account_user as au')
                    ->join('accounts as a', 'a.id', '=', 'au.account_id')
                    ->join('users as u', 'u.id', '=', 'au.user_id')
                    ->whereColumn('au.user_id', 'change_requests.approved_by')
                    ->selectRaw("coalesce(nullif(a.internal_name, ''), u.name)")
                    ->orderBy('au.account_id')
                    ->limit(1),
                'approved_by_account_display_name'
            )
            ->orderBy('change_requests.id', 'desc')
            ->get();

        return view('work.quotes.show', [
            'quote' => $quote,
            'snapshotJson' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'snapshot' => $snapshot,
            'configJson' => json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'derivedJson' => json_encode($derived, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'errorsJson' => json_encode($errors, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'totals' => [
                'subtotal' => $totals['subtotal'] ?? null,
                'tax' => $totals['tax'] ?? null,
                'total' => $totals['total'] ?? null,
            ],
            'summaryItems' => $summaryItems,
            'svg' => $svg,
            'requests' => $requests,
        ]);
    }

    public function edit(int $id)
    {
        $quote = DB::table('quotes as q')
            ->leftJoin('configurator_sessions as cs', 'cs.id', '=', 'q.session_id')
            ->whereNull('q.deleted_at')
            ->where('q.id', $id)
            ->select('q.*')
            ->addSelect('cs.memo as session_memo')
            ->first();
        if (!$quote) abort(404);

        $snapshot = $this->decodeJson($quote->snapshot) ?? [];
        $config = is_array($snapshot['config'] ?? null) ? $snapshot['config'] : [];
        $templateVersionId = (int)($snapshot['template_version_id'] ?? 0);
        $quoteMemo = trim((string)($quote->memo ?? ''));
        $sessionMemo = trim((string)($quote->session_memo ?? ''));
        $initialMemo = $quoteMemo !== '' ? $quoteMemo : $sessionMemo;
        $summaryFieldOptions = self::SUMMARY_FIELD_LABELS;
        $selectedSummaryFields = $this->resolveSummaryCardFields($snapshot);

        return view('work.quotes.edit', [
            'quote' => $quote,
            'initialConfig' => $config,
            'templateVersionId' => $templateVersionId,
            'initialMemo' => $initialMemo,
            'summaryFieldOptions' => $summaryFieldOptions,
            'selectedSummaryFields' => $selectedSummaryFields,
        ]);
    }

    public function editRequest(int $id)
    {
        $quote = DB::table('quotes')->whereNull('deleted_at')->where('id', $id)->first();
        if (!$quote) abort(404);

        $snapshot = $this->decodeJson($quote->snapshot) ?? [];
        $totals = $snapshot['totals'] ?? [];

        return view('work.quotes.edit-request', [
            'quote' => $quote,
            'snapshotJson' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'totals' => [
                'subtotal' => $totals['subtotal'] ?? null,
                'tax' => $totals['tax'] ?? null,
                'total' => $totals['total'] ?? null,
            ],
        ]);
    }

    public function storeEditRequest(Request $request, int $id)
    {
        $quote = DB::table('quotes')->whereNull('deleted_at')->where('id', $id)->first();
        if (!$quote) abort(404);

        $data = $request->validate([
            'snapshot_json' => 'nullable|string',
            'comment' => 'nullable|string',
            'subtotal' => 'nullable|numeric',
            'tax' => 'nullable|numeric',
            'total' => 'nullable|numeric',
        ]);

        $baseSnapshot = $this->decodeJson($quote->snapshot) ?? [];
        unset($baseSnapshot['account_display_name_source']);
        $summaryFields = $this->resolveSummaryCardFields($baseSnapshot);
        if (!array_key_exists('summary_card_fields', $baseSnapshot)) {
            $baseSnapshot['summary_card_fields'] = $summaryFields;
        }
        if (!array_key_exists('memo', $baseSnapshot)) {
            $baseSnapshot['memo'] = $quote->memo;
        }

        $decoded = null;
        if (!empty($data['snapshot_json'])) {
            $decoded = json_decode($data['snapshot_json'], true);
            if (!is_array($decoded)) {
                return back()->withErrors(['snapshot_json' => 'snapshotはJSON形式で入力してください'])->withInput();
            }
        } else {
            $decoded = $this->decodeJson($quote->snapshot) ?? [];
            $decoded['totals'] = is_array($decoded['totals'] ?? null) ? $decoded['totals'] : [];
            if (isset($data['subtotal'])) $decoded['totals']['subtotal'] = (float)$data['subtotal'];
            if (isset($data['tax'])) $decoded['totals']['tax'] = (float)$data['tax'];
            if (isset($data['total'])) $decoded['totals']['total'] = (float)$data['total'];
        }
        if (!array_key_exists('summary_card_fields', $decoded)) {
            $decoded['summary_card_fields'] = $summaryFields;
        }
        unset($decoded['account_display_name_source']);
        if (!array_key_exists('memo', $decoded)) {
            $decoded['memo'] = $quote->memo;
        }

        app(WorkChangeRequestService::class)->queueUpdate(
            'quote',
            $id,
            [
                'snapshot' => $baseSnapshot,
                'memo' => $quote->memo,
            ],
            [
                'snapshot' => $decoded,
            ],
            (int)$request->user()->id,
            (string)($data['comment'] ?? '')
        );

        return redirect()->route('work.quotes.show', $id)->with('status', '承認リクエストを送信しました');
    }

    public function updateMemo(Request $request, int $id)
    {
        $quote = DB::table('quotes')->whereNull('deleted_at')->where('id', $id)->first();
        if (!$quote) abort(404);

        $data = $request->validate([
            'memo' => 'nullable|string|max:5000',
        ]);
        $memo = trim((string)($data['memo'] ?? ''));
        if ($memo === '') $memo = null;

        app(WorkChangeRequestService::class)->queueUpdate(
            'quote',
            $id,
            [
                'snapshot' => $this->decodeJson($quote->snapshot) ?? [],
                'memo' => $quote->memo,
            ],
            [
                'memo' => $memo,
            ],
            (int)$request->user()->id,
            (string)$request->input('comment', '')
        );

        return redirect()->route('work.quotes.show', $id)->with('status', '見積メモの更新申請を送信しました');
    }

    private function decodeJson(mixed $value): ?array
    {
        if (is_array($value)) return $value;
        if ($value === null) return null;
        $decoded = json_decode((string)$value, true);
        return is_array($decoded) ? $decoded : null;
    }

    public function downloadSnapshotPdf(int $id, SvgRenderer $renderer, SnapshotPdfService $pdfService)
    {
        $accountEmails = DB::table('account_user as au')
            ->join('users as u', 'u.id', '=', 'au.user_id')
            ->whereColumn('au.account_id', 'q.account_id')
            ->selectRaw("string_agg(distinct u.email, ', ' order by u.email)");

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
            ->join('accounts as a', 'a.id', '=', 'q.account_id')
            ->leftJoin('configurator_sessions as cs', 'cs.id', '=', 'q.session_id')
            ->whereNull('q.deleted_at')
            ->whereNull('a.deleted_at')
            ->select('q.*')
            ->addSelect('a.internal_name as account_internal_name', 'a.assignee_name')
            ->selectSub($accountUserName, 'account_user_name')
            ->addSelect('cs.memo as session_memo')
            ->selectSub($accountEmails, 'account_emails')
            ->where('q.id', $id)
            ->first();
        if (!$quote) abort(404);

        $quoteMemo = trim((string)($quote->memo ?? ''));
        $sessionMemo = trim((string)($quote->session_memo ?? ''));
        $quote->display_memo = $quoteMemo !== '' ? $quoteMemo : $sessionMemo;

        $snapshot = $this->decodeJson($quote->snapshot) ?? [];
        $quote->account_display_name = $this->resolveAccountDisplayNameInternalFirst(
            (string)($quote->account_internal_name ?? ''),
            (string)($quote->account_user_name ?? '')
        );
        $quote->account_name = $quote->account_display_name;

        $config = $snapshot['config'] ?? [];
        $derived = $snapshot['derived'] ?? [];
        $errors = $snapshot['validation_errors'] ?? [];
        $requestCount = (int)DB::table('change_requests')
            ->where('entity_type', 'quote')
            ->where('entity_id', $id)
            ->count();
        $summaryItems = $this->buildQuoteSummaryItems($quote, $snapshot, $requestCount);

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

        return $pdfService->downloadSnapshotBundleUi([
            'title' => '見積 スナップショット',
            'panelTitle' => '見積スナップショット',
            'summaryItems' => $summaryItems,
            'includeAutoSummary' => false,
            'showMemoCard' => true,
            'memoValue' => $quote->display_memo ?? '',
            'memoLabel' => 'メモ',
            'showCreatorColumns' => false,
            'summaryTableColumns' => 4,
            'svg' => $svg,
            'snapshot' => $snapshot,
            'config' => is_array($config) ? $config : [],
            'derived' => is_array($derived) ? $derived : [],
            'errors' => is_array($errors) ? $errors : [],
        ], $filename);
    }

    private function resolveAccountDisplayNameInternalFirst(string $internalName, string $userName): string
    {
        $internalName = trim($internalName);
        $userName = trim($userName);

        if ($internalName !== '') {
            return $internalName;
        }
        if ($userName !== '') {
            return $userName;
        }

        return '-';
    }

    /**
     * @return array<int, string>
     */
    private function resolveSummaryCardFields(array $snapshot): array
    {
        $raw = $snapshot['summary_card_fields'] ?? [];
        if (!is_array($raw)) {
            return self::SUMMARY_DEFAULT_FIELDS;
        }

        return $this->normalizeSummaryFields($raw);
    }

    /**
     * @param array<int, mixed> $raw
     * @return array<int, string>
     */
    private function normalizeSummaryFields(array $raw): array
    {
        $allowed = array_keys(self::SUMMARY_FIELD_LABELS);
        $selected = [];
        foreach ($raw as $field) {
            $field = (string)$field;
            if ($field === 'account_display_name') {
                $field = 'account_internal_name';
            }
            if (!in_array($field, $allowed, true)) {
                continue;
            }
            if (!in_array($field, $selected, true)) {
                $selected[] = $field;
            }
        }

        return empty($selected) ? self::SUMMARY_DEFAULT_FIELDS : $selected;
    }

    /**
     * @return array<int, array{label:string,value:mixed}>
     */
    private function buildQuoteSummaryItems(object $quote, array $snapshot, int $requestCount): array
    {
        $fields = $this->resolveSummaryCardFields($snapshot);
        $totals = is_array($snapshot['totals'] ?? null) ? $snapshot['totals'] : [];

        $valueMap = [
            'quote_id' => $quote->id ?? '',
            'status' => $quote->status ?? '',
            'account_internal_name' => trim((string)($quote->account_internal_name ?? '')) !== '' ? (string)$quote->account_internal_name : '-',
            'account_user_name' => trim((string)($quote->account_user_name ?? '')) !== '' ? (string)$quote->account_user_name : '-',
            'assignee_name' => $quote->assignee_name ?? '-',
            'customer_emails' => $quote->account_emails ?? ($quote->customer_emails ?? '-'),
            'request_count' => $requestCount,
            'template_version_id' => $snapshot['template_version_id'] ?? '',
            'price_book_id' => $snapshot['price_book_id'] ?? '',
            'subtotal' => $totals['subtotal'] ?? '',
            'tax' => $totals['tax'] ?? '',
            'total' => $totals['total'] ?? '',
        ];

        $items = [];
        foreach ($fields as $field) {
            $items[] = [
                'label' => self::SUMMARY_FIELD_LABELS[$field],
                'value' => $valueMap[$field] ?? '',
            ];
        }

        return $items;
    }
}
