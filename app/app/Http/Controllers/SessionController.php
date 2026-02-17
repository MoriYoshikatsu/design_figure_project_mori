<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\SnapshotPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class SessionController extends Controller
{
    public function index(Request $request)
    {
        $isDate = static fn (string $v): bool => (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $v);

        $q = trim((string)$request->input('q', ''));
        $status = (string)$request->input('status', '');
        $templateVersionId = (string)$request->input('template_version_id', '');
        $accountId = (string)$request->input('account_id', '');
        $accountType = (string)$request->input('account_type', '');
        $assignee = trim((string)$request->input('assignee_name', ''));
        $hasMemo = (string)$request->input('has_memo', '');
        $createdFrom = (string)$request->input('created_from', '');
        $createdTo = (string)$request->input('created_to', '');
        $updatedFrom = (string)$request->input('updated_from', '');
        $updatedTo = (string)$request->input('updated_to', '');

        $accountEmails = DB::table('account_user as au')
            ->join('users as u', 'u.id', '=', 'au.user_id')
            ->whereColumn('au.account_id', 'cs.account_id')
            ->selectRaw("string_agg(distinct u.email, ', ' order by u.email)");

        $query = DB::table('configurator_sessions as cs')
            ->join('accounts as a', 'a.id', '=', 'cs.account_id')
            ->select('cs.*')
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
                $sub->whereRaw('cast(cs.id as text) ilike ?', ["%{$q}%"])
                    ->orWhereRaw('cast(cs.account_id as text) ilike ?', ["%{$q}%"])
                    ->orWhereRaw('cast(cs.template_version_id as text) ilike ?', ["%{$q}%"])
                    ->orWhere('cs.status', 'ilike', "%{$q}%")
                    ->orWhere('cs.memo', 'ilike', "%{$q}%")
                    ->orWhere('a.internal_name', 'ilike', "%{$q}%")
                    ->orWhere('a.assignee_name', 'ilike', "%{$q}%")
                    ->orWhereExists(function ($sq) use ($q) {
                        $sq->selectRaw('1')
                            ->from('account_user as au')
                            ->join('users as u', 'u.id', '=', 'au.user_id')
                            ->whereColumn('au.account_id', 'cs.account_id')
                            ->where(function ($userSub) use ($q) {
                                $userSub->where('u.name', 'ilike', "%{$q}%")
                                    ->orWhere('u.email', 'ilike', "%{$q}%");
                            });
                    });
            });
        }
        if ($status !== '') {
            $query->where('cs.status', $status);
        }
        if ($templateVersionId !== '' && is_numeric($templateVersionId)) {
            $query->where('cs.template_version_id', (int)$templateVersionId);
        }
        if ($accountId !== '' && is_numeric($accountId)) {
            $query->where('cs.account_id', (int)$accountId);
        }
        if ($accountType !== '') {
            $query->where('a.account_type', $accountType);
        }
        if ($assignee !== '') {
            $query->where('a.assignee_name', 'ilike', "%{$assignee}%");
        }
        if ($hasMemo === 'with') {
            $query->whereNotNull('cs.memo')->where('cs.memo', '<>', '');
        } elseif ($hasMemo === 'without') {
            $query->where(function ($sub) {
                $sub->whereNull('cs.memo')->orWhere('cs.memo', '');
            });
        }
        if ($createdFrom !== '' && $isDate($createdFrom)) {
            $query->whereDate('cs.created_at', '>=', $createdFrom);
        }
        if ($createdTo !== '' && $isDate($createdTo)) {
            $query->whereDate('cs.created_at', '<=', $createdTo);
        }
        if ($updatedFrom !== '' && $isDate($updatedFrom)) {
            $query->whereDate('cs.updated_at', '>=', $updatedFrom);
        }
        if ($updatedTo !== '' && $isDate($updatedTo)) {
            $query->whereDate('cs.updated_at', '<=', $updatedTo);
        }

        $sessions = $query->orderBy('cs.id', 'desc')->limit(200)->get();

        $statusOptions = DB::table('configurator_sessions')
            ->select('status')
            ->whereNotNull('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status')
            ->all();

        $templateVersionOptions = DB::table('configurator_sessions')
            ->select('template_version_id')
            ->whereNotNull('template_version_id')
            ->distinct()
            ->orderBy('template_version_id', 'desc')
            ->pluck('template_version_id')
            ->all();

        $accountTypeOptions = DB::table('accounts')
            ->select('account_type')
            ->whereNotNull('account_type')
            ->distinct()
            ->orderBy('account_type')
            ->pluck('account_type')
            ->all();

        return view('work.sessions.index', [
            'sessions' => $sessions,
            'filters' => [
                'q' => $q,
                'status' => $status,
                'template_version_id' => $templateVersionId,
                'account_id' => $accountId,
                'account_type' => $accountType,
                'assignee_name' => $assignee,
                'has_memo' => $hasMemo,
                'created_from' => $createdFrom,
                'created_to' => $createdTo,
                'updated_from' => $updatedFrom,
                'updated_to' => $updatedTo,
            ],
            'statusOptions' => $statusOptions,
            'templateVersionOptions' => $templateVersionOptions,
            'accountTypeOptions' => $accountTypeOptions,
            'presenceOptions' => [
                'with' => 'あり',
                'without' => 'なし',
            ],
        ]);
    }

    public function show(int $id, \App\Services\SvgRenderer $renderer)
    {
        $accountEmails = DB::table('account_user as au')
            ->join('users as u', 'u.id', '=', 'au.user_id')
            ->whereColumn('au.account_id', 'cs.account_id')
            ->selectRaw("string_agg(distinct u.email, ', ' order by u.email)");

        $session = DB::table('configurator_sessions as cs')
            ->join('accounts as a', 'a.id', '=', 'cs.account_id')
            ->select('cs.*')
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
            ->selectSub($accountEmails, 'account_emails')
            ->where('cs.id', $id)
            ->first();
        if (!$session) abort(404);

        $config = $this->decodeJson($session->config) ?? [];
        $derived = $this->decodeJson($session->derived) ?? [];
        $errors = $this->decodeJson($session->validation_errors) ?? [];

        $requests = DB::table('change_requests')
            ->where('change_requests.entity_type', 'configurator_session')
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
            ->orderBy('change_requests.id', 'desc')
            ->get();

        $svg = $renderer->render($config, $derived, $errors);

        return view('work.sessions.show', [
            'session' => $session,
            'configJson' => json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'derivedJson' => json_encode($derived, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'errorsJson' => json_encode($errors, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'svg' => $svg,
            'requests' => $requests,
        ]);
    }

    public function downloadSnapshotPdf(int $id, \App\Services\SvgRenderer $renderer, SnapshotPdfService $pdfService)
    {
        $accountEmails = DB::table('account_user as au')
            ->join('users as u', 'u.id', '=', 'au.user_id')
            ->whereColumn('au.account_id', 'cs.account_id')
            ->selectRaw("string_agg(distinct u.email, ', ' order by u.email)");

        $session = DB::table('configurator_sessions as cs')
            ->join('accounts as a', 'a.id', '=', 'cs.account_id')
            ->select('cs.*')
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
            ->selectSub($accountEmails, 'account_emails')
            ->where('cs.id', $id)
            ->first();
        if (!$session) abort(404);

        $config = $this->decodeJson($session->config) ?? [];
        $derived = $this->decodeJson($session->derived) ?? [];
        $errors = $this->decodeJson($session->validation_errors) ?? [];
        $bom = is_array($derived['bom'] ?? null) ? $derived['bom'] : [];
        $pricingRaw = $derived['pricing'] ?? [];
        $pricingItems = is_array($pricingRaw['items'] ?? null) ? $pricingRaw['items'] : (is_array($pricingRaw) ? $pricingRaw : []);
        $totals = is_array($pricingRaw) ? [
            'subtotal' => $pricingRaw['subtotal'] ?? null,
            'tax' => $pricingRaw['tax'] ?? null,
            'total' => $pricingRaw['total'] ?? null,
        ] : [];

        $requestCount = (int)DB::table('change_requests')
            ->where('entity_type', 'configurator_session')
            ->where('entity_id', $id)
            ->count();

        $svg = $renderer->render($config, $derived, $errors);
        $snapshotView = [
            'template_version_id' => (int)$session->template_version_id,
            'price_book_id' => $pricingRaw['price_book_id'] ?? null,
            'config' => $config,
            'derived' => $derived,
            'validation_errors' => $errors,
            'bom' => $bom,
            'pricing' => $pricingItems,
            'totals' => $totals,
            'memo' => $session->memo,
        ];

        $filename = $pdfService->buildFilename(
            'configurator',
            (int)$session->account_id,
            (int)$session->template_version_id,
            ['bom' => $bom, 'template_version_id' => (int)$session->template_version_id],
            $config,
            $derived,
            (string)$session->updated_at
        );

        return $pdfService->downloadSnapshotBundleUi([
            'title' => '構成セッション スナップショット',
            'panelTitle' => '構成セッションスナップショット',
            'summaryItems' => [
                ['label' => 'セッションID', 'value' => $session->id],
                ['label' => 'ステータス', 'value' => $session->status],
                ['label' => 'アカウント表示名', 'value' => $session->account_display_name ?? ''],
                ['label' => '担当者', 'value' => $session->assignee_name ?? '-'],
                ['label' => '登録メールアドレス', 'value' => $session->account_emails ?? '-'],
                ['label' => '承認リクエスト件数', 'value' => $requestCount],
            ],
            'showMemoCard' => true,
            'memoValue' => $session->memo ?? '',
            'memoLabel' => 'メモ',
            'showCreatorColumns' => false,
            'summaryTableColumns' => 4,
            'svg' => $svg,
            'snapshot' => $snapshotView,
            'config' => $config,
            'derived' => $derived,
            'errors' => $errors,
        ], $filename);
    }

    public function editRequest(int $id)
    {
        abort(404);
    }

    public function storeEditRequest(Request $request, int $id)
    {
        abort(404);
    }

    public function updateMemo(Request $request, int $id)
    {
        abort(404);
    }

    private function decodeJson(mixed $value): ?array
    {
        if (is_array($value)) return $value;
        if ($value === null) return null;
        $decoded = json_decode((string)$value, true);
        return is_array($decoded) ? $decoded : null;
    }
}
