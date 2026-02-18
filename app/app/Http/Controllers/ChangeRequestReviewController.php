<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\DslEngine;
use App\Services\SnapshotPdfService;
use App\Services\WorkChangeRequestApplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ChangeRequestReviewController extends Controller
{
    public function index(Request $request)
    {
        $isDate = static fn (string $v): bool => (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $v);

        $q = trim((string)$request->input('q', ''));
        $status = (string)$request->input('status', '');
        $operation = (string)$request->input('operation', '');
        $entityType = (string)$request->input('entity_type', '');
        $entityId = trim((string)$request->input('entity_id', ''));
        $requestedBy = (string)$request->input('requested_by', '');
        $approvedBy = (string)$request->input('approved_by', '');
        $requestedRole = (string)$request->input('requested_role', '');
        $approvalState = (string)$request->input('approval_state', '');
        $hasComment = (string)$request->input('has_comment', '');
        $hasMemo = (string)$request->input('has_memo', '');
        $createdFrom = (string)$request->input('created_from', '');
        $createdTo = (string)$request->input('created_to', '');
        $approvedFrom = (string)$request->input('approved_from', '');
        $approvedTo = (string)$request->input('approved_to', '');

        $query = $this->baseRequestQuery();
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->whereRaw('cast(cr.id as text) ilike ?', ["%{$q}%"])
                    ->orWhere('cr.entity_type', 'ilike', "%{$q}%")
                    ->orWhereRaw('cast(cr.entity_id as text) ilike ?', ["%{$q}%"])
                    ->orWhere('cr.operation', 'ilike', "%{$q}%")
                    ->orWhere('cr.status', 'ilike', "%{$q}%")
                    ->orWhere('cr.comment', 'ilike', "%{$q}%")
                    ->orWhere('cr.memo', 'ilike', "%{$q}%")
                    ->orWhereRaw('cast(cr.requested_by as text) ilike ?', ["%{$q}%"])
                    ->orWhereRaw('cast(cr.approved_by as text) ilike ?', ["%{$q}%"])
                    ->orWhere('requester.email', 'ilike', "%{$q}%")
                    ->orWhere('approver.email', 'ilike', "%{$q}%")
                    ->orWhereExists(function ($sq) use ($q) {
                        $sq->selectRaw('1')
                            ->from('account_user as au')
                            ->join('users as u', 'u.id', '=', 'au.user_id')
                            ->leftJoin('accounts as a', 'a.id', '=', 'au.account_id')
                            ->whereColumn('au.user_id', 'cr.requested_by')
                            ->where(function ($userSub) use ($q) {
                                $userSub->where('u.name', 'ilike', "%{$q}%")
                                    ->orWhere('u.email', 'ilike', "%{$q}%")
                                    ->orWhere('a.internal_name', 'ilike', "%{$q}%")
                                    ->orWhere('a.assignee_name', 'ilike', "%{$q}%");
                            });
                    })
                    ->orWhereExists(function ($sq) use ($q) {
                        $sq->selectRaw('1')
                            ->from('account_user as au')
                            ->join('users as u', 'u.id', '=', 'au.user_id')
                            ->leftJoin('accounts as a', 'a.id', '=', 'au.account_id')
                            ->whereColumn('au.user_id', 'cr.approved_by')
                            ->where(function ($userSub) use ($q) {
                                $userSub->where('u.name', 'ilike', "%{$q}%")
                                    ->orWhere('u.email', 'ilike', "%{$q}%")
                                    ->orWhere('a.internal_name', 'ilike', "%{$q}%")
                                    ->orWhere('a.assignee_name', 'ilike', "%{$q}%");
                            });
                    });
            });
        }
        if ($status !== '') {
            $query->where('cr.status', $status);
        }
        if ($operation !== '') {
            $query->where('cr.operation', $operation);
        }
        if ($entityType !== '') {
            $query->where('cr.entity_type', $entityType);
        }
        if ($entityId !== '') {
            if (preg_match('/^\d+$/', $entityId)) {
                $query->where('cr.entity_id', (int)$entityId);
            } else {
                $query->whereRaw('cast(cr.entity_id as text) ilike ?', ["%{$entityId}%"]);
            }
        }
        if ($requestedBy !== '' && is_numeric($requestedBy)) {
            $query->where('cr.requested_by', (int)$requestedBy);
        }
        if ($approvedBy !== '' && is_numeric($approvedBy)) {
            $query->where('cr.approved_by', (int)$approvedBy);
        }
        if ($requestedRole !== '') {
            $query->whereExists(function ($sq) use ($requestedRole) {
                $sq->selectRaw('1')
                    ->from('account_user as au')
                    ->whereColumn('au.user_id', 'cr.requested_by')
                    ->where('au.role', $requestedRole);
            });
        }
        if ($approvalState === 'approved') {
            $query->whereNotNull('cr.approved_by');
        } elseif ($approvalState === 'unapproved') {
            $query->whereNull('cr.approved_by');
        }
        if ($hasComment === 'with') {
            $query->whereNotNull('cr.comment')->where('cr.comment', '<>', '');
        } elseif ($hasComment === 'without') {
            $query->where(function ($sub) {
                $sub->whereNull('cr.comment')->orWhere('cr.comment', '');
            });
        }
        if ($hasMemo === 'with') {
            $query->whereNotNull('cr.memo')->where('cr.memo', '<>', '');
        } elseif ($hasMemo === 'without') {
            $query->where(function ($sub) {
                $sub->whereNull('cr.memo')->orWhere('cr.memo', '');
            });
        }
        if ($createdFrom !== '' && $isDate($createdFrom)) {
            $query->whereDate('cr.created_at', '>=', $createdFrom);
        }
        if ($createdTo !== '' && $isDate($createdTo)) {
            $query->whereDate('cr.created_at', '<=', $createdTo);
        }
        if ($approvedFrom !== '' && $isDate($approvedFrom)) {
            $query->whereDate('cr.approved_at', '>=', $approvedFrom);
        }
        if ($approvedTo !== '' && $isDate($approvedTo)) {
            $query->whereDate('cr.approved_at', '<=', $approvedTo);
        }

        $requests = $query
            ->orderByRaw("cr.status = 'PENDING' desc")
            ->orderBy('cr.id', 'desc')
            ->limit(300)
            ->get();

        $statusOptions = DB::table('change_requests')
            ->select('status')
            ->whereNotNull('status')
            ->distinct()
            ->pluck('status')
            ->all();
        $statusPriority = [
            'PENDING' => 1,
            'APPROVED' => 2,
            'REJECTED' => 3,
        ];
        usort($statusOptions, function ($a, $b) use ($statusPriority) {
            $pa = $statusPriority[$a] ?? 9;
            $pb = $statusPriority[$b] ?? 9;
            if ($pa === $pb) {
                return strcmp((string)$a, (string)$b);
            }
            return $pa <=> $pb;
        });

        $entityTypeOptions = DB::table('change_requests')
            ->select('entity_type')
            ->whereNotNull('entity_type')
            ->distinct()
            ->orderBy('entity_type')
            ->pluck('entity_type')
            ->all();

        $operationOptions = DB::table('change_requests')
            ->select('operation')
            ->whereNotNull('operation')
            ->distinct()
            ->pluck('operation')
            ->all();
        $operationPriority = [
            'CREATE' => 1,
            'UPDATE' => 2,
            'DELETE' => 3,
        ];
        usort($operationOptions, function ($a, $b) use ($operationPriority) {
            $pa = $operationPriority[$a] ?? 9;
            $pb = $operationPriority[$b] ?? 9;
            if ($pa === $pb) {
                return strcmp((string)$a, (string)$b);
            }
            return $pa <=> $pb;
        });

        $requestedByOptions = DB::table('change_requests')
            ->select('requested_by')
            ->whereNotNull('requested_by')
            ->distinct()
            ->orderBy('requested_by')
            ->pluck('requested_by')
            ->all();

        $approvedByOptions = DB::table('change_requests')
            ->select('approved_by')
            ->whereNotNull('approved_by')
            ->distinct()
            ->orderBy('approved_by')
            ->pluck('approved_by')
            ->all();

        return view('work.change-requests.index', [
            'requests' => $requests,
            'filters' => [
                'q' => $q,
                'status' => $status,
                'operation' => $operation,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'requested_by' => $requestedBy,
                'approved_by' => $approvedBy,
                'requested_role' => $requestedRole,
                'approval_state' => $approvalState,
                'has_comment' => $hasComment,
                'has_memo' => $hasMemo,
                'created_from' => $createdFrom,
                'created_to' => $createdTo,
                'approved_from' => $approvedFrom,
                'approved_to' => $approvedTo,
            ],
            'statusOptions' => $statusOptions,
            'operationOptions' => $operationOptions,
            'entityTypeOptions' => $entityTypeOptions,
            'requestedByOptions' => $requestedByOptions,
            'approvedByOptions' => $approvedByOptions,
            'requestedRoleOptions' => ['customer', 'sales', 'admin'],
            'approvalStateOptions' => [
                'approved' => '承認済み',
                'unapproved' => '未承認',
            ],
            'presenceOptions' => [
                'with' => 'あり',
                'without' => 'なし',
            ],
        ]);
    }

    public function show(int $id, \App\Services\SvgRenderer $renderer)
    {
        $req = $this->findRequest($id);
        if (!$req) abort(404);

        $proposed = $this->decodeJson($req->proposed_json) ?? [];
        $config = [];
        $derived = [];
        $errors = [];
        $snapshot = [];
        $baseConfig = [];
        $baseDerived = [];
        $baseErrors = [];
        $baseSnapshot = [];

        if ($req->entity_type === 'configurator_session') {
            $config = is_array($proposed['config'] ?? null) ? $proposed['config'] : [];
            $baseConfig = is_array($proposed['base_config'] ?? null) ? $proposed['base_config'] : [];
            $session = DB::table('configurator_sessions')->where('id', (int)$req->entity_id)->first();
            if ($session) {
                $dsl = $this->loadTemplateDsl((int)$session->template_version_id) ?? [];
                /** @var DslEngine $dslEngine */
                $dslEngine = app(DslEngine::class);
                $eval = $dslEngine->evaluate($config, $dsl);
                $derived = is_array($eval['derived'] ?? null) ? $eval['derived'] : [];
                $errors = is_array($eval['errors'] ?? null) ? $eval['errors'] : [];

                if (!empty($baseConfig)) {
                    $baseEval = $dslEngine->evaluate($baseConfig, $dsl);
                    $baseDerived = is_array($baseEval['derived'] ?? null) ? $baseEval['derived'] : [];
                    $baseErrors = is_array($baseEval['errors'] ?? null) ? $baseEval['errors'] : [];
                }
            }
        } elseif ($req->entity_type === 'quote') {
            $snapshot = is_array($proposed['snapshot'] ?? null) ? $proposed['snapshot'] : [];
            $baseSnapshot = is_array($proposed['base_snapshot'] ?? null) ? $proposed['base_snapshot'] : [];
            $config = is_array($snapshot['config'] ?? null) ? $snapshot['config'] : [];
            $derived = is_array($snapshot['derived'] ?? null) ? $snapshot['derived'] : [];
            $errors = is_array($snapshot['validation_errors'] ?? null) ? $snapshot['validation_errors'] : [];

            $baseConfig = is_array($baseSnapshot['config'] ?? null) ? $baseSnapshot['config'] : [];
            $baseDerived = is_array($baseSnapshot['derived'] ?? null) ? $baseSnapshot['derived'] : [];
            $baseErrors = is_array($baseSnapshot['validation_errors'] ?? null) ? $baseSnapshot['validation_errors'] : [];
        }

        $renderDerived = $this->augmentDerivedForRender($config, $derived);
        $svg = $renderer->render($config, $renderDerived, $errors);

        $baseSvg = '';
        if (!empty($baseConfig)) {
            $baseRenderDerived = $this->augmentDerivedForRender($baseConfig, $baseDerived);
            $baseSvg = $renderer->render($baseConfig, $baseRenderDerived, $baseErrors);
        }

        return view('work.change-requests.show', [
            'req' => $req,
            'proposedJson' => json_encode($proposed, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'configJson' => json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'derivedJson' => json_encode($derived, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'errorsJson' => json_encode($errors, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'snapshot' => $snapshot,
            'svg' => $svg,
            'baseSvg' => $baseSvg,
            'baseConfigJson' => json_encode($baseConfig, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'baseDerivedJson' => json_encode($baseDerived, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'baseErrorsJson' => json_encode($baseErrors, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'baseSnapshot' => $baseSnapshot,
            'memoUpdateUrl' => route('work.change-requests.memo.update', $req->id),
            'baseSnapshotPdfUrl' => route('work.change-requests.snapshot-base.pdf', $req->id),
            'compareSnapshotPdfUrl' => route('work.change-requests.snapshot-compare.pdf', $req->id),
        ]);
    }

    public function downloadSnapshotPdf(int $id, \App\Services\SvgRenderer $renderer, SnapshotPdfService $pdfService)
    {
        $req = $this->findRequest($id);
        if (!$req) abort(404);

        $proposed = $this->decodeJson($req->proposed_json) ?? [];
        $config = [];
        $derived = [];
        $errors = [];
        $accountId = null;
        $templateVersionId = null;
        $snapshotForName = [];
        $snapshotView = [];
        $memoValue = '';

        if ($req->entity_type === 'configurator_session') {
            $config = is_array($proposed['config'] ?? null) ? $proposed['config'] : [];
            $session = DB::table('configurator_sessions')->where('id', (int)$req->entity_id)->first();
            if ($session) {
                $accountId = (int)$session->account_id;
                $templateVersionId = (int)$session->template_version_id;
                $dsl = $this->loadTemplateDsl((int)$session->template_version_id) ?? [];
                /** @var DslEngine $dslEngine */
                $dslEngine = app(DslEngine::class);
                $eval = $dslEngine->evaluate($config, $dsl);
                $derived = is_array($eval['derived'] ?? null) ? $eval['derived'] : [];
                $errors = is_array($eval['errors'] ?? null) ? $eval['errors'] : [];

                /** @var \App\Services\BomBuilder $bomBuilder */
                $bomBuilder = app(\App\Services\BomBuilder::class);
                $bom = $bomBuilder->build($config, $derived, $dsl);
                /** @var \App\Services\PricingService $pricing */
                $pricing = app(\App\Services\PricingService::class);
                $pricingResult = $pricing->price((int)$session->account_id, $bom);
                $snapshotForName = [
                    'bom' => $bom,
                    'template_version_id' => (int)$session->template_version_id,
                ];
                $snapshotView = [
                    'template_version_id' => (int)$session->template_version_id,
                    'price_book_id' => $pricingResult['price_book_id'] ?? null,
                    'config' => $config,
                    'derived' => $derived,
                    'validation_errors' => $errors,
                    'bom' => $bom,
                    'pricing' => $pricingResult['items'] ?? [],
                    'totals' => [
                        'subtotal' => (float)($pricingResult['subtotal'] ?? 0),
                        'tax' => (float)($pricingResult['tax'] ?? 0),
                        'total' => (float)($pricingResult['total'] ?? 0),
                    ],
                    'memo' => $session->memo,
                ];
                $memoValue = (string)($session->memo ?? '');
            }
        } elseif ($req->entity_type === 'quote') {
            $snapshot = is_array($proposed['snapshot'] ?? null) ? $proposed['snapshot'] : [];
            $config = is_array($snapshot['config'] ?? null) ? $snapshot['config'] : [];
            $derived = is_array($snapshot['derived'] ?? null) ? $snapshot['derived'] : [];
            $errors = is_array($snapshot['validation_errors'] ?? null) ? $snapshot['validation_errors'] : [];
            $snapshotView = $snapshot;
            $snapshotForName = $snapshotView;
            $templateVersionId = (int)($snapshot['template_version_id'] ?? 0);
            $quote = DB::table('quotes')->where('id', (int)$req->entity_id)->first();
            if ($quote) {
                $accountId = (int)$quote->account_id;
                $memoValue = trim((string)($snapshotView['memo'] ?? $quote->memo ?? ''));
            }
        }

        if (!isset($snapshotView['config'])) $snapshotView['config'] = $config;
        if (!isset($snapshotView['derived'])) $snapshotView['derived'] = $derived;
        if (!isset($snapshotView['validation_errors'])) $snapshotView['validation_errors'] = $errors;
        if (!isset($snapshotView['bom']) || !is_array($snapshotView['bom'])) $snapshotView['bom'] = [];
        if (!isset($snapshotView['pricing']) || !is_array($snapshotView['pricing'])) $snapshotView['pricing'] = [];
        if (!isset($snapshotView['totals']) || !is_array($snapshotView['totals'])) $snapshotView['totals'] = [];
        if ($memoValue === '') {
            $memoValue = (string)($snapshotView['memo'] ?? $req->memo ?? '');
        }

        $renderDerived = $this->augmentDerivedForRender($config, $derived);
        $svg = $renderer->render($config, $renderDerived, $errors);

        $filename = $pdfService->buildFilename(
            'request',
            $accountId,
            $templateVersionId,
            $snapshotForName,
            $config,
            $derived,
            (string)$req->updated_at
        );

        return $pdfService->downloadSnapshotBundleUi([
            'title' => '編集承認リクエスト スナップショット',
            'panelTitle' => '申請内容（新しい版）',
            'summaryItems' => [
                ['label' => '対象', 'value' => $req->entity_type . ' #' . $req->entity_id],
                ['label' => 'ステータス', 'value' => $req->status],
                ['label' => '申請者', 'value' => $req->requested_by_account_display_name ?? ('ID: '.$req->requested_by)],
                ['label' => '承認者', 'value' => $req->approved_by_account_display_name ?? ($req->approved_by ? 'ID: '.$req->approved_by : '-')],
                ['label' => '担当者', 'value' => $req->requested_by_assignee_name ?? '-'],
                ['label' => 'コメント', 'value' => $req->comment ?? '（なし）'],
                ['label' => '申請日時', 'value' => (string)$req->created_at],
                ['label' => '更新日時', 'value' => (string)$req->updated_at],
            ],
            'showMemoCard' => true,
            'memoValue' => $memoValue,
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

    public function downloadBaseSnapshotPdf(int $id, \App\Services\SvgRenderer $renderer, SnapshotPdfService $pdfService)
    {
        $req = $this->findRequest($id);
        if (!$req) abort(404);

        $proposed = $this->decodeJson($req->proposed_json) ?? [];
        $config = [];
        $derived = [];
        $errors = [];
        $accountId = null;
        $templateVersionId = null;
        $snapshotForName = [];
        $snapshotView = [];
        $memoValue = '';

        if ($req->entity_type === 'configurator_session') {
            $config = is_array($proposed['base_config'] ?? null) ? $proposed['base_config'] : [];
            $session = DB::table('configurator_sessions')->where('id', (int)$req->entity_id)->first();
            if ($session) {
                $accountId = (int)$session->account_id;
                $templateVersionId = (int)$session->template_version_id;
                $dsl = $this->loadTemplateDsl((int)$session->template_version_id) ?? [];
                /** @var DslEngine $dslEngine */
                $dslEngine = app(DslEngine::class);
                $eval = $dslEngine->evaluate($config, $dsl);
                $derived = is_array($eval['derived'] ?? null) ? $eval['derived'] : [];
                $errors = is_array($eval['errors'] ?? null) ? $eval['errors'] : [];

                /** @var \App\Services\BomBuilder $bomBuilder */
                $bomBuilder = app(\App\Services\BomBuilder::class);
                $bom = $bomBuilder->build($config, $derived, $dsl);
                /** @var \App\Services\PricingService $pricing */
                $pricing = app(\App\Services\PricingService::class);
                $pricingResult = $pricing->price((int)$session->account_id, $bom);
                $snapshotForName = [
                    'bom' => $bom,
                    'template_version_id' => (int)$session->template_version_id,
                ];
                $snapshotView = [
                    'template_version_id' => (int)$session->template_version_id,
                    'price_book_id' => $pricingResult['price_book_id'] ?? null,
                    'config' => $config,
                    'derived' => $derived,
                    'validation_errors' => $errors,
                    'bom' => $bom,
                    'pricing' => $pricingResult['items'] ?? [],
                    'totals' => [
                        'subtotal' => (float)($pricingResult['subtotal'] ?? 0),
                        'tax' => (float)($pricingResult['tax'] ?? 0),
                        'total' => (float)($pricingResult['total'] ?? 0),
                    ],
                    'memo' => $session->memo,
                ];
                $memoValue = (string)($session->memo ?? '');
            }
        } elseif ($req->entity_type === 'quote') {
            $snapshot = is_array($proposed['base_snapshot'] ?? null) ? $proposed['base_snapshot'] : [];
            $config = is_array($snapshot['config'] ?? null) ? $snapshot['config'] : [];
            $derived = is_array($snapshot['derived'] ?? null) ? $snapshot['derived'] : [];
            $errors = is_array($snapshot['validation_errors'] ?? null) ? $snapshot['validation_errors'] : [];
            $snapshotView = $snapshot;
            $snapshotForName = $snapshotView;
            $templateVersionId = (int)($snapshot['template_version_id'] ?? 0);
            $quote = DB::table('quotes')->where('id', (int)$req->entity_id)->first();
            if ($quote) {
                $accountId = (int)$quote->account_id;
                $memoValue = trim((string)($snapshotView['memo'] ?? $quote->memo ?? ''));
            }
        }

        if (!isset($snapshotView['config'])) $snapshotView['config'] = $config;
        if (!isset($snapshotView['derived'])) $snapshotView['derived'] = $derived;
        if (!isset($snapshotView['validation_errors'])) $snapshotView['validation_errors'] = $errors;
        if (!isset($snapshotView['bom']) || !is_array($snapshotView['bom'])) $snapshotView['bom'] = [];
        if (!isset($snapshotView['pricing']) || !is_array($snapshotView['pricing'])) $snapshotView['pricing'] = [];
        if (!isset($snapshotView['totals']) || !is_array($snapshotView['totals'])) $snapshotView['totals'] = [];
        if ($memoValue === '') {
            $memoValue = (string)($snapshotView['memo'] ?? $req->memo ?? '');
        }

        $renderDerived = $this->augmentDerivedForRender($config, $derived);
        $svg = $renderer->render($config, $renderDerived, $errors);

        $filename = $pdfService->buildFilename(
            'request_base',
            $accountId,
            $templateVersionId,
            $snapshotForName,
            $config,
            $derived,
            (string)$req->created_at
        );

        return $pdfService->downloadSnapshotBundleUi([
            'title' => '編集承認リクエスト 初版スナップショット',
            'panelTitle' => '初版（申請時点の現行版）',
            'summaryItems' => [
                ['label' => '対象', 'value' => $req->entity_type . ' #' . $req->entity_id],
                ['label' => 'ステータス', 'value' => $req->status],
                ['label' => '申請者', 'value' => $req->requested_by_account_display_name ?? ('ID: '.$req->requested_by)],
                ['label' => '承認者', 'value' => $req->approved_by_account_display_name ?? ($req->approved_by ? 'ID: '.$req->approved_by : '-')],
                ['label' => '担当者', 'value' => $req->requested_by_assignee_name ?? '-'],
                ['label' => 'コメント', 'value' => $req->comment ?? '（なし）'],
                ['label' => '申請日時', 'value' => (string)$req->created_at],
                ['label' => '更新日時', 'value' => (string)$req->updated_at],
            ],
            'showMemoCard' => true,
            'memoValue' => $memoValue,
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

    public function downloadComparisonPdf(int $id, \App\Services\SvgRenderer $renderer, SnapshotPdfService $pdfService)
    {
        $req = $this->findRequest($id);
        if (!$req) abort(404);

        $proposed = $this->decodeJson($req->proposed_json) ?? [];
        $config = [];
        $derived = [];
        $errors = [];
        $snapshot = [];
        $baseConfig = [];
        $baseDerived = [];
        $baseErrors = [];
        $baseSnapshot = [];
        $accountId = null;
        $templateVersionId = null;
        $snapshotForName = [];

        if ($req->entity_type === 'configurator_session') {
            $config = is_array($proposed['config'] ?? null) ? $proposed['config'] : [];
            $baseConfig = is_array($proposed['base_config'] ?? null) ? $proposed['base_config'] : [];
            $session = DB::table('configurator_sessions')->where('id', (int)$req->entity_id)->first();
            if ($session) {
                $accountId = (int)$session->account_id;
                $templateVersionId = (int)$session->template_version_id;
                $dsl = $this->loadTemplateDsl((int)$session->template_version_id) ?? [];
                /** @var DslEngine $dslEngine */
                $dslEngine = app(DslEngine::class);
                $eval = $dslEngine->evaluate($config, $dsl);
                $derived = is_array($eval['derived'] ?? null) ? $eval['derived'] : [];
                $errors = is_array($eval['errors'] ?? null) ? $eval['errors'] : [];

                /** @var \App\Services\BomBuilder $bomBuilder */
                $bomBuilder = app(\App\Services\BomBuilder::class);
                $bom = $bomBuilder->build($config, $derived, $dsl);
                $snapshotForName = [
                    'bom' => $bom,
                    'template_version_id' => (int)$session->template_version_id,
                ];
                $snapshot = [
                    'template_version_id' => (int)$session->template_version_id,
                    'config' => $config,
                    'derived' => $derived,
                    'validation_errors' => $errors,
                    'bom' => $bom,
                    'pricing' => [],
                    'totals' => [],
                ];

                if (!empty($baseConfig)) {
                    $baseEval = $dslEngine->evaluate($baseConfig, $dsl);
                    $baseDerived = is_array($baseEval['derived'] ?? null) ? $baseEval['derived'] : [];
                    $baseErrors = is_array($baseEval['errors'] ?? null) ? $baseEval['errors'] : [];
                    $baseBom = $bomBuilder->build($baseConfig, $baseDerived, $dsl);
                    $baseSnapshot = [
                        'template_version_id' => (int)$session->template_version_id,
                        'config' => $baseConfig,
                        'derived' => $baseDerived,
                        'validation_errors' => $baseErrors,
                        'bom' => $baseBom,
                        'pricing' => [],
                        'totals' => [],
                    ];
                }
            }
        } elseif ($req->entity_type === 'quote') {
            $snapshot = is_array($proposed['snapshot'] ?? null) ? $proposed['snapshot'] : [];
            $baseSnapshot = is_array($proposed['base_snapshot'] ?? null) ? $proposed['base_snapshot'] : [];
            $config = is_array($snapshot['config'] ?? null) ? $snapshot['config'] : [];
            $derived = is_array($snapshot['derived'] ?? null) ? $snapshot['derived'] : [];
            $errors = is_array($snapshot['validation_errors'] ?? null) ? $snapshot['validation_errors'] : [];
            $baseConfig = is_array($baseSnapshot['config'] ?? null) ? $baseSnapshot['config'] : [];
            $baseDerived = is_array($baseSnapshot['derived'] ?? null) ? $baseSnapshot['derived'] : [];
            $baseErrors = is_array($baseSnapshot['validation_errors'] ?? null) ? $baseSnapshot['validation_errors'] : [];
            $snapshotForName = $snapshot;
            $templateVersionId = (int)($snapshot['template_version_id'] ?? 0);
            $quote = DB::table('quotes')->where('id', (int)$req->entity_id)->first();
            if ($quote) {
                $accountId = (int)$quote->account_id;
            }
        }

        $renderDerived = $this->augmentDerivedForRender($config, $derived);
        $svg = $renderer->render($config, $renderDerived, $errors);
        $baseSvg = '';
        if (!empty($baseConfig)) {
            $baseRenderDerived = $this->augmentDerivedForRender($baseConfig, $baseDerived);
            $baseSvg = $renderer->render($baseConfig, $baseRenderDerived, $baseErrors);
        }

        $snapshotView = is_array($snapshot) ? $snapshot : [];
        if (!isset($snapshotView['config'])) $snapshotView['config'] = $config;
        if (!isset($snapshotView['derived'])) $snapshotView['derived'] = $derived;
        if (!isset($snapshotView['validation_errors'])) $snapshotView['validation_errors'] = $errors;
        if (!isset($snapshotView['bom']) || !is_array($snapshotView['bom'])) $snapshotView['bom'] = [];
        if (!isset($snapshotView['pricing']) || !is_array($snapshotView['pricing'])) $snapshotView['pricing'] = [];
        if (!isset($snapshotView['totals']) || !is_array($snapshotView['totals'])) $snapshotView['totals'] = [];

        $baseSnapshotView = is_array($baseSnapshot) ? $baseSnapshot : [];
        if (!isset($baseSnapshotView['config'])) $baseSnapshotView['config'] = $baseConfig;
        if (!isset($baseSnapshotView['derived'])) $baseSnapshotView['derived'] = $baseDerived;
        if (!isset($baseSnapshotView['validation_errors'])) $baseSnapshotView['validation_errors'] = $baseErrors;
        if (!isset($baseSnapshotView['bom']) || !is_array($baseSnapshotView['bom'])) $baseSnapshotView['bom'] = [];
        if (!isset($baseSnapshotView['pricing']) || !is_array($baseSnapshotView['pricing'])) $baseSnapshotView['pricing'] = [];
        if (!isset($baseSnapshotView['totals']) || !is_array($baseSnapshotView['totals'])) $baseSnapshotView['totals'] = [];

        $filename = $pdfService->buildFilename(
            'request_compare',
            $accountId,
            $templateVersionId,
            $snapshotForName,
            $config,
            $derived,
            (string)$req->updated_at
        );

        return $pdfService->downloadChangeRequestComparison([
            'req' => $req,
            'svg' => $svg,
            'baseSvg' => $baseSvg,
            'snapshotView' => $snapshotView,
            'baseSnapshotView' => $baseSnapshotView,
            'config' => $config,
            'derived' => $derived,
            'errors' => $errors,
            'baseConfig' => $baseConfig,
            'baseDerived' => $baseDerived,
            'baseErrors' => $baseErrors,
        ], $filename);
    }

    public function approve(int $id)
    {
        $actorId = (int)auth()->id();

        DB::transaction(function () use ($id, $actorId) {
            $req = DB::table('change_requests')->where('id', $id)->lockForUpdate()->first();
            if (!$req) abort(404);
            if ($req->status !== 'PENDING') {
                return;
            }

            $proposed = $this->decodeJson($req->proposed_json) ?? [];
            $operation = strtoupper((string)($req->operation ?? 'UPDATE'));
            $isStructuredPayload = array_key_exists('before', $proposed) || array_key_exists('after', $proposed);

            if ($isStructuredPayload || $operation !== 'UPDATE' || !in_array((string)$req->entity_type, ['configurator_session', 'quote'], true)) {
                $appliedEntityId = app(WorkChangeRequestApplier::class)->apply($req, $proposed, $actorId);
                if ((int)$req->entity_id <= 0 && $appliedEntityId > 0) {
                    DB::table('change_requests')
                        ->where('id', $id)
                        ->update(['entity_id' => $appliedEntityId, 'updated_at' => now()]);
                }
            } elseif ($req->entity_type === 'configurator_session') {
                $this->applySessionChange((int)$req->entity_id, $proposed, $actorId);
            } elseif ($req->entity_type === 'quote') {
                $this->applyQuoteChange((int)$req->entity_id, $proposed, $actorId);
            }

            DB::table('change_requests')->where('id', $id)->update([
                'status' => 'APPROVED',
                'approved_by' => $actorId,
                'approved_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return redirect()->route('work.change-requests.index')->with('status', '承認しました');
    }

    public function reject(int $id)
    {
        $actorId = (int)auth()->id();
        DB::table('change_requests')->where('id', $id)->update([
            'status' => 'REJECTED',
            'approved_by' => $actorId,
            'approved_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('work.change-requests.index')->with('status', '却下しました');
    }

    public function updateMemo(\Illuminate\Http\Request $request, int $id)
    {
        $req = DB::table('change_requests')->where('id', $id)->first();
        if (!$req) abort(404);

        $data = $request->validate([
            'memo' => 'nullable|string|max:5000',
        ]);
        $memo = trim((string)($data['memo'] ?? ''));
        if ($memo === '') $memo = null;

        DB::table('change_requests')->where('id', $id)->update([
            'memo' => $memo,
            'updated_at' => now(),
        ]);

        return redirect()->route('work.change-requests.show', $id)->with('status', 'リクエストメモを更新しました');
    }

    private function applySessionChange(int $sessionId, array $proposed, int $actorId): void
    {
        $session = DB::table('configurator_sessions')->where('id', $sessionId)->lockForUpdate()->first();
        if (!$session) return;

        $config = $proposed['config'] ?? null;
        if (!is_array($config)) return;

        $before = [
            'config' => $this->decodeJson($session->config) ?? [],
            'derived' => $this->decodeJson($session->derived) ?? [],
            'validation_errors' => $this->decodeJson($session->validation_errors) ?? [],
            'status' => $session->status,
        ];

        $dsl = $this->loadTemplateDsl((int)$session->template_version_id) ?? [];
        /** @var DslEngine $dslEngine */
        $dslEngine = app(DslEngine::class);
        $eval = $dslEngine->evaluate($config, $dsl);
        $derived = is_array($eval['derived'] ?? null) ? $eval['derived'] : [];
        $errors = is_array($eval['errors'] ?? null) ? $eval['errors'] : [];

        DB::table('configurator_sessions')->where('id', $sessionId)->update([
            'config' => json_encode($config, JSON_UNESCAPED_UNICODE),
            'derived' => json_encode($derived, JSON_UNESCAPED_UNICODE),
            'validation_errors' => json_encode($errors, JSON_UNESCAPED_UNICODE),
            'status' => 'DRAFT',
            'updated_at' => now(),
        ]);

        $after = [
            'config' => $config,
            'derived' => $derived,
            'validation_errors' => $errors,
            'status' => 'DRAFT',
        ];

        $this->logAudit($actorId, 'CHANGE_REQUEST_APPROVED', 'configurator_session', $sessionId, $before, $after);
    }

    private function applyQuoteChange(int $quoteId, array $proposed, int $actorId): void
    {
        $quote = DB::table('quotes')->where('id', $quoteId)->lockForUpdate()->first();
        if (!$quote) return;

        $snapshot = $proposed['snapshot'] ?? null;
        if (!is_array($snapshot)) return;

        $before = [
            'snapshot' => $this->decodeJson($quote->snapshot) ?? [],
            'subtotal' => (float)$quote->subtotal,
            'tax_total' => (float)$quote->tax_total,
            'total' => (float)$quote->total,
        ];

        $totals = $snapshot['totals'] ?? [];
        $subtotal = isset($totals['subtotal']) ? (float)$totals['subtotal'] : (float)$quote->subtotal;
        $tax = isset($totals['tax']) ? (float)$totals['tax'] : (float)$quote->tax_total;
        $total = isset($totals['total']) ? (float)$totals['total'] : (float)$quote->total;

        DB::table('quotes')->where('id', $quoteId)->update([
            'snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
            'subtotal' => $subtotal,
            'tax_total' => $tax,
            'total' => $total,
            'updated_at' => now(),
        ]);

        $this->replaceQuoteItems($quoteId, $snapshot['bom'] ?? [], $snapshot['pricing'] ?? []);

        $after = [
            'snapshot' => $snapshot,
            'subtotal' => $subtotal,
            'tax_total' => $tax,
            'total' => $total,
        ];

        $this->logAudit($actorId, 'CHANGE_REQUEST_APPROVED', 'quote', $quoteId, $before, $after);
    }

    private function augmentDerivedForRender(array $config, array $derived): array
    {
        if (empty($derived['skuNameByCode'])) {
            $derived['skuNameByCode'] = $this->buildSkuNameMap();
        }
        if (empty($derived['skuSvgByCode'])) {
            $derived['skuSvgByCode'] = $this->buildSkuSvgMap();
        }
        return $derived;
    }

    private function buildSkuNameMap(): array
    {
        return DB::table('skus')->pluck('name', 'sku_code')->all();
    }

    private function buildSkuSvgMap(): array
    {
        $dir = public_path('sku-svg');
        if (!is_dir($dir)) return [];

        $map = [];
        $files = glob($dir . '/*.svg') ?: [];
        foreach ($files as $path) {
            $code = basename($path, '.svg');
            if ($code === '') continue;
            $map[$code] = '/sku-svg/' . $code . '.svg';
        }
        return $map;
    }

    private function replaceQuoteItems(int $quoteId, array $bom, array $pricingItems): void
    {
        DB::table('quote_items')->where('quote_id', $quoteId)->delete();

        $pricingBySort = [];
        foreach ($pricingItems as $pi) {
            if (!is_array($pi)) continue;
            $pricingBySort[(int)($pi['sort_order'] ?? 0)] = $pi;
        }

        $skuCodes = array_values(array_unique(array_filter(array_map(
            fn ($r) => is_array($r) ? ($r['sku_code'] ?? null) : null,
            $bom
        ))));

        $skuIdByCode = [];
        if (!empty($skuCodes)) {
            $skuIdByCode = DB::table('skus')
                ->whereIn('sku_code', $skuCodes)
                ->pluck('id', 'sku_code')
                ->all();
        }

        $rows = [];
        foreach ($bom as $row) {
            if (!is_array($row)) continue;
            $skuCode = (string)($row['sku_code'] ?? '');
            if ($skuCode === '') continue;
            $skuId = $skuIdByCode[$skuCode] ?? null;
            if (!$skuId) continue;

            $sort = (int)($row['sort_order'] ?? 0);
            $pricing = $pricingBySort[$sort] ?? null;

            $qty = $this->asNumber($row['quantity'] ?? 1);
            $unitPrice = $this->asNumber($pricing['unit_price'] ?? 0);
            $lineTotal = $this->asNumber($pricing['line_total'] ?? ($unitPrice * $qty));

            $rows[] = [
                'quote_id' => $quoteId,
                'sku_id' => $skuId,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
                'options' => json_encode($row['options'] ?? [], JSON_UNESCAPED_UNICODE),
                'source_path' => $row['source_path'] ?? null,
                'sort_order' => $sort,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (!empty($rows)) {
            DB::table('quote_items')->insert($rows);
        }
    }

    private function loadTemplateDsl(int $templateVersionId): ?array
    {
        $raw = DB::table('product_template_versions')
            ->where('id', $templateVersionId)
            ->value('dsl_json');
        if ($raw === null) return null;

        $dsl = is_array($raw) ? $raw : json_decode((string)$raw, true);
        if (!is_array($dsl)) return null;

        return $dsl;
    }

    private function logAudit(int $actorUserId, string $action, string $entityType, int $entityId, array $before, array $after): void
    {
        DB::table('audit_logs')->insert([
            'actor_user_id' => $actorUserId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'before_json' => json_encode($before, JSON_UNESCAPED_UNICODE),
            'after_json' => json_encode($after, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function decodeJson(mixed $value): ?array
    {
        if (is_array($value)) return $value;
        if ($value === null) return null;
        $decoded = json_decode((string)$value, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function asNumber(mixed $v): float
    {
        return is_numeric($v) ? (float)$v : 0.0;
    }

    private function findRequest(int $id): ?object
    {
        return $this->baseRequestQuery()
            ->where('cr.id', $id)
            ->first();
    }

    private function baseRequestQuery()
    {
        return DB::table('change_requests as cr')
            ->leftJoin('users as requester', 'requester.id', '=', 'cr.requested_by')
            ->leftJoin('users as approver', 'approver.id', '=', 'cr.approved_by')
            ->select('cr.*')
            ->addSelect('requester.email as requested_by_email', 'approver.email as approved_by_email')
            ->selectRaw("
                case
                    when cr.entity_type = 'quote' then (
                        select coalesce(
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
                        )
                        from quotes as q
                        join accounts as a on a.id = q.account_id
                        where q.id = cr.entity_id
                        limit 1
                    )
                    else (
                        select coalesce(nullif(a.internal_name, ''), u.name)
                        from account_user as au
                        join accounts as a on a.id = au.account_id
                        join users as u on u.id = au.user_id
                        where au.user_id = cr.requested_by
                        order by au.account_id
                        limit 1
                    )
                end as request_account_display_name
            ")
            ->selectRaw("
                case
                    when cr.entity_type = 'quote' then (
                        select string_agg(distinct u.email, ', ' order by u.email)
                        from quotes as q
                        join account_user as au on au.account_id = q.account_id
                        join users as u on u.id = au.user_id
                        where q.id = cr.entity_id
                    )
                    else requester.email
                end as request_account_email
            ")
            ->selectRaw("
                case
                    when cr.entity_type = 'quote' then (
                        select a.assignee_name
                        from quotes as q
                        join accounts as a on a.id = q.account_id
                        where q.id = cr.entity_id
                        limit 1
                    )
                    else (
                        select a.assignee_name
                        from account_user as au
                        join accounts as a on a.id = au.account_id
                        where au.user_id = cr.requested_by
                        order by au.account_id
                        limit 1
                    )
                end as request_account_assignee_name
            ")
            ->selectSub(
                DB::table('account_user as au')
                    ->join('accounts as a', 'a.id', '=', 'au.account_id')
                    ->join('users as u', 'u.id', '=', 'au.user_id')
                    ->whereColumn('au.user_id', 'cr.requested_by')
                    ->selectRaw("coalesce(nullif(a.internal_name, ''), u.name)")
                    ->orderBy('au.account_id')
                    ->limit(1),
                'requested_by_account_display_name'
            )
            ->selectSub(
                DB::table('account_user as au')
                    ->join('accounts as a', 'a.id', '=', 'au.account_id')
                    ->whereColumn('au.user_id', 'cr.requested_by')
                    ->select('a.assignee_name')
                    ->orderBy('au.account_id')
                    ->limit(1),
                'requested_by_assignee_name'
            )
            ->selectSub(
                DB::table('account_user as au')
                    ->whereColumn('au.user_id', 'cr.requested_by')
                    ->select('au.role')
                    ->orderByRaw("
                        case au.role
                            when 'customer' then 1
                            when 'sales' then 2
                            when 'admin' then 3
                            else 9
                        end
                    ")
                    ->orderBy('au.account_id')
                    ->limit(1),
                'requested_by_role'
            )
            ->selectSub(
                DB::table('account_user as au')
                    ->join('accounts as a', 'a.id', '=', 'au.account_id')
                    ->join('users as u', 'u.id', '=', 'au.user_id')
                    ->whereColumn('au.user_id', 'cr.approved_by')
                    ->selectRaw("coalesce(nullif(a.internal_name, ''), u.name)")
                    ->orderBy('au.account_id')
                    ->limit(1),
                'approved_by_account_display_name'
            );
    }
}
