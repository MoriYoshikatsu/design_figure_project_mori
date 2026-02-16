<?php

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Services\SnapshotPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ConfiguratorSessionController extends Controller
{
    public function index()
    {
        $accountEmails = DB::table('account_user as au')
            ->join('users as u', 'u.id', '=', 'au.user_id')
            ->whereColumn('au.account_id', 'cs.account_id')
            ->selectRaw("string_agg(distinct u.email, ', ' order by u.email)");

        $sessions = DB::table('configurator_sessions as cs')
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
            ->orderBy('cs.id', 'desc')
            ->limit(200)
            ->get();

        return view('ops.sessions.index', ['sessions' => $sessions]);
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

        return view('ops.sessions.show', [
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
        $session = DB::table('configurator_sessions')->where('id', $id)->first();
        if (!$session) abort(404);

        $config = $this->decodeJson($session->config) ?? [];
        $connectors = is_array($config['connectors'] ?? null) ? $config['connectors'] : [];
        $connectorOptions = DB::table('skus')
            ->whereRaw('upper(category) = ?', ['CONNECTOR'])
            ->where('active', true)
            ->orderBy('name')
            ->get(['sku_code', 'name']);

        return view('ops.sessions.edit-request', [
            'session' => $session,
            'configJson' => json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'connectorOptions' => $connectorOptions,
            'simple' => [
                'mfdCount' => $config['mfdCount'] ?? null,
                'tubeCount' => $config['tubeCount'] ?? null,
                'connectors_mode' => $connectors['mode'] ?? null,
                'connectors_left' => $connectors['leftSkuCode'] ?? null,
                'connectors_right' => $connectors['rightSkuCode'] ?? null,
            ],
        ]);
    }

    public function storeEditRequest(Request $request, int $id)
    {
        $session = DB::table('configurator_sessions')->where('id', $id)->first();
        if (!$session) abort(404);

        $data = $request->validate([
            'config_json' => 'nullable|string',
            'comment' => 'nullable|string',
            'mfd_count' => 'nullable|integer|min:1|max:10',
            'tube_count' => 'nullable|integer|min:0',
            'connectors_mode' => 'nullable|string',
            'connectors_left' => 'nullable|string',
            'connectors_right' => 'nullable|string',
        ]);

        $decoded = null;
        if (!empty($data['config_json'])) {
            $decoded = json_decode($data['config_json'], true);
            if (!is_array($decoded)) {
                return back()->withErrors(['config_json' => 'configはJSON形式で入力してください'])->withInput();
            }
        } else {
            $decoded = $this->decodeJson($session->config) ?? [];
            if (isset($data['mfd_count'])) {
                $decoded['mfdCount'] = (int)$data['mfd_count'];
            }
            if (isset($data['tube_count'])) {
                $decoded['tubeCount'] = (int)$data['tube_count'];
            }
            if (!empty($data['connectors_mode']) || isset($data['connectors_left']) || isset($data['connectors_right'])) {
                $decoded['connectors'] = is_array($decoded['connectors'] ?? null) ? $decoded['connectors'] : [];
                if (!empty($data['connectors_mode'])) {
                    $decoded['connectors']['mode'] = $data['connectors_mode'];
                }
                if (array_key_exists('connectors_left', $data)) {
                    $decoded['connectors']['leftSkuCode'] = $data['connectors_left'] !== '' ? $data['connectors_left'] : null;
                }
                if (array_key_exists('connectors_right', $data)) {
                    $decoded['connectors']['rightSkuCode'] = $data['connectors_right'] !== '' ? $data['connectors_right'] : null;
                }
            }
        }

        $baseConfig = $this->decodeJson($session->config) ?? [];

        DB::table('change_requests')->insert([
            'entity_type' => 'configurator_session',
            'entity_id' => $id,
            'proposed_json' => json_encode([
                'config' => $decoded,
                'base_config' => $baseConfig,
            ], JSON_UNESCAPED_UNICODE),
            'status' => 'PENDING',
            'requested_by' => (int)$request->user()->id,
            'comment' => $data['comment'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('ops.sessions.show', $id)->with('status', '承認リクエストを送信しました');
    }

    public function updateMemo(Request $request, int $id)
    {
        $session = DB::table('configurator_sessions')->where('id', $id)->first();
        if (!$session) abort(404);

        $data = $request->validate([
            'memo' => 'nullable|string|max:5000',
        ]);
        $memo = trim((string)($data['memo'] ?? ''));
        if ($memo === '') $memo = null;

        DB::table('configurator_sessions')->where('id', $id)->update([
            'memo' => $memo,
            'updated_at' => now(),
        ]);

        return redirect()->route('ops.sessions.show', $id)->with('status', 'セッションメモを更新しました');
    }

    private function decodeJson(mixed $value): ?array
    {
        if (is_array($value)) return $value;
        if ($value === null) return null;
        $decoded = json_decode((string)$value, true);
        return is_array($decoded) ? $decoded : null;
    }
}
