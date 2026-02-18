<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\DslEngine;
use App\Services\SnapshotPdfService;
use App\Services\SvgRenderer;
use Illuminate\Support\Facades\DB;

final class ChangeRequestController extends Controller
{
    public function show(int $id, SvgRenderer $renderer)
    {
        $req = $this->findRequest($id);
        if (!$req) abort(404);

        $rawProposed = $this->decodeJson($req->proposed_json) ?? [];
        $proposed = $this->normalizeProposalPayload($req, $rawProposed);
        $operation = strtoupper((string)($req->operation ?? 'UPDATE'));
        $entityType = strtolower((string)($req->entity_type ?? ''));
        $isSnapshotEntity = in_array($entityType, ['configurator_session', 'quote'], true) && $operation === 'UPDATE';
        $config = [];
        $derived = [];
        $errors = [];
        $snapshot = [];
        $baseConfig = [];
        $baseDerived = [];
        $baseErrors = [];
        $baseSnapshot = [];
        if ($isSnapshotEntity && $entityType === 'configurator_session') {
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
        } elseif ($isSnapshotEntity && $entityType === 'quote') {
            $snapshot = is_array($proposed['snapshot'] ?? null) ? $proposed['snapshot'] : [];
            $baseSnapshot = is_array($proposed['base_snapshot'] ?? null) ? $proposed['base_snapshot'] : [];
            $config = is_array($snapshot['config'] ?? null) ? $snapshot['config'] : [];
            $derived = is_array($snapshot['derived'] ?? null) ? $snapshot['derived'] : [];
            $errors = is_array($snapshot['validation_errors'] ?? null) ? $snapshot['validation_errors'] : [];
            $baseConfig = is_array($baseSnapshot['config'] ?? null) ? $baseSnapshot['config'] : [];
            $baseDerived = is_array($baseSnapshot['derived'] ?? null) ? $baseSnapshot['derived'] : [];
            $baseErrors = is_array($baseSnapshot['validation_errors'] ?? null) ? $baseSnapshot['validation_errors'] : [];
        }

        $svg = '';
        $baseSvg = '';
        if ($isSnapshotEntity) {
            $renderDerived = $this->augmentDerivedForRender($config, $derived);
            $svg = $renderer->render($config, $renderDerived, $errors);
            if (!empty($baseConfig)) {
                $baseRenderDerived = $this->augmentDerivedForRender($baseConfig, $baseDerived);
                $baseSvg = $renderer->render($baseConfig, $baseRenderDerived, $baseErrors);
            }
        }

        [$changeBefore, $changeAfter] = $this->extractChangePayload($req, $rawProposed, $proposed);
        $changeRows = $this->buildChangeRows($changeBefore, $changeAfter, $operation);
        $changedCount = count(array_filter($changeRows, static fn (array $row): bool => (bool)($row['changed'] ?? false)));
        $canApprove = false;
        $user = auth()->user();
        if ($user) {
            /** @var \App\Services\WorkPermissionService $workPermissionService */
            $workPermissionService = app(\App\Services\WorkPermissionService::class);
            $approveRequest = \Illuminate\Http\Request::create(
                route('work.change-requests.approve', $req->id, false),
                'POST'
            );
            $approveRequest->setUserResolver(static fn () => $user);
            $canApprove = $workPermissionService->allowsRequest($approveRequest, (int)$user->id);
        }
        $quoteSummaryContext = $entityType === 'quote'
            ? $this->buildQuoteSummaryContext((int)$req->entity_id)
            : [];

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
            'canApprove' => $canApprove,
            'operationLabel' => $this->operationLabel($operation),
            'statusLabel' => $this->statusLabel((string)($req->status ?? '')),
            'entityTypeLabel' => $this->entityTypeLabel($entityType),
            'targetLabel' => $this->targetLabel((int)($req->entity_id ?? 0), $operation),
            'requestHeading' => $this->entityTypeLabel($entityType) . ' / ' . $this->operationLabel($operation) . '申請',
            'isSnapshotEntity' => $isSnapshotEntity,
            'changeRows' => $changeRows,
            'changedCount' => $changedCount,
            'changeContextItems' => $this->buildChangeContextItems($entityType, $changeBefore, $changeAfter),
            'snapshotPdfUrl' => route('work.change-requests.snapshot.pdf', $req->id),
            'baseSnapshotPdfUrl' => route('work.change-requests.snapshot-base.pdf', $req->id),
            'compareSnapshotPdfUrl' => route('work.change-requests.snapshot-compare.pdf', $req->id),
            'memoUpdateUrl' => route('work.change-requests.memo.update', $req->id),
            'quoteSummaryContext' => $quoteSummaryContext,
        ]);
    }

    public function downloadSnapshotPdf(int $id, SvgRenderer $renderer, SnapshotPdfService $pdfService)
    {
        $req = $this->findRequest($id);
        if (!$req) abort(404);

        $proposed = $this->normalizeProposalPayload($req, $this->decodeJson($req->proposed_json) ?? []);
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

    public function downloadBaseSnapshotPdf(int $id, SvgRenderer $renderer, SnapshotPdfService $pdfService)
    {
        $req = $this->findRequest($id);
        if (!$req) abort(404);

        $proposed = $this->normalizeProposalPayload($req, $this->decodeJson($req->proposed_json) ?? []);
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

    public function downloadComparisonPdf(int $id, SvgRenderer $renderer, SnapshotPdfService $pdfService)
    {
        $req = $this->findRequest($id);
        if (!$req) abort(404);

        $proposed = $this->normalizeProposalPayload($req, $this->decodeJson($req->proposed_json) ?? []);
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

    /**
     * @return array<string, mixed>
     */
    private function buildQuoteSummaryContext(int $quoteId): array
    {
        if ($quoteId <= 0) {
            return [];
        }

        $quote = DB::table('quotes as q')
            ->leftJoin('accounts as a', 'a.id', '=', 'q.account_id')
            ->where('q.id', $quoteId)
            ->select(
                'q.id',
                'q.status',
                'q.account_id',
                'a.internal_name as account_internal_name',
                'a.assignee_name'
            )
            ->first();
        if (!$quote) {
            return [];
        }

        $accountId = (int)($quote->account_id ?? 0);
        $accountUserName = '';
        $accountEmails = '';
        if ($accountId > 0) {
            $accountUserName = (string)(DB::table('account_user as au')
                ->join('users as u', 'u.id', '=', 'au.user_id')
                ->where('au.account_id', $accountId)
                ->orderByRaw("
                    case au.role
                        when 'customer' then 1
                        when 'admin' then 2
                        when 'sales' then 3
                        else 9
                    end
                ")
                ->orderBy('au.user_id')
                ->value('u.name') ?? '');

            $emailRow = DB::table('account_user as au')
                ->join('users as u', 'u.id', '=', 'au.user_id')
                ->where('au.account_id', $accountId)
                ->selectRaw("string_agg(distinct u.email, ', ' order by u.email) as emails")
                ->first();
            $accountEmails = (string)($emailRow->emails ?? '');
        }

        $requestCount = (int)DB::table('change_requests')
            ->where('entity_type', 'quote')
            ->where('entity_id', $quoteId)
            ->count();

        $internalName = trim((string)($quote->account_internal_name ?? ''));
        $userName = trim($accountUserName);
        $assignee = trim((string)($quote->assignee_name ?? ''));
        $emails = trim($accountEmails);

        return [
            'quote_id' => (int)($quote->id ?? 0),
            'status' => (string)($quote->status ?? ''),
            'account_internal_name' => $internalName !== '' ? $internalName : '-',
            'account_user_name' => $userName !== '' ? $userName : '-',
            'assignee_name' => $assignee !== '' ? $assignee : '-',
            'customer_emails' => $emails !== '' ? $emails : '-',
            'request_count' => $requestCount,
        ];
    }

    private function decodeJson(mixed $value): ?array
    {
        if (is_array($value)) return $value;
        if ($value === null) return null;
        $decoded = json_decode((string)$value, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $proposed
     * @return array<string, mixed>
     */
    private function normalizeProposalPayload(object $req, array $proposed): array
    {
        $before = is_array($proposed['before'] ?? null) ? $proposed['before'] : null;
        $after = is_array($proposed['after'] ?? null) ? $proposed['after'] : null;
        if ($before === null && $after === null) {
            return $proposed;
        }

        $entityType = strtolower((string)($req->entity_type ?? ''));
        if ($entityType === 'quote') {
            $proposed['base_snapshot'] = is_array($before['snapshot'] ?? null) ? $before['snapshot'] : [];
            $proposed['snapshot'] = is_array($after['snapshot'] ?? null) ? $after['snapshot'] : [];
            return $proposed;
        }

        if ($entityType === 'configurator_session') {
            $proposed['base_config'] = is_array($before['config'] ?? null) ? $before['config'] : [];
            $proposed['config'] = is_array($after['config'] ?? null) ? $after['config'] : [];
            return $proposed;
        }

        return $proposed;
    }

    /**
     * @param array<string, mixed> $rawProposed
     * @param array<string, mixed> $normalizedProposed
     * @return array{0:mixed,1:mixed}
     */
    private function extractChangePayload(object $req, array $rawProposed, array $normalizedProposed): array
    {
        $hasBefore = array_key_exists('before', $rawProposed);
        $hasAfter = array_key_exists('after', $rawProposed);
        if ($hasBefore || $hasAfter) {
            return [
                $rawProposed['before'] ?? null,
                $rawProposed['after'] ?? null,
            ];
        }

        $entityType = strtolower((string)($req->entity_type ?? ''));
        if ($entityType === 'quote') {
            return [
                ['snapshot' => is_array($normalizedProposed['base_snapshot'] ?? null) ? $normalizedProposed['base_snapshot'] : []],
                ['snapshot' => is_array($normalizedProposed['snapshot'] ?? null) ? $normalizedProposed['snapshot'] : []],
            ];
        }
        if ($entityType === 'configurator_session') {
            return [
                ['config' => is_array($normalizedProposed['base_config'] ?? null) ? $normalizedProposed['base_config'] : []],
                ['config' => is_array($normalizedProposed['config'] ?? null) ? $normalizedProposed['config'] : []],
            ];
        }

        return [null, null];
    }

    /**
     * @return array<int, array{path:string,before:mixed,after:mixed,changed:bool}>
     */
    private function buildChangeRows(mixed $before, mixed $after, string $operation): array
    {
        $beforeFlat = [];
        $afterFlat = [];

        if ($before !== null) {
            $this->flattenPayload('', $before, $beforeFlat);
        }
        if ($after !== null) {
            $this->flattenPayload('', $after, $afterFlat);
        }

        $keys = array_values(array_unique(array_merge(array_keys($beforeFlat), array_keys($afterFlat))));
        sort($keys);

        $rows = [];
        foreach ($keys as $key) {
            $beforeExists = array_key_exists($key, $beforeFlat);
            $afterExists = array_key_exists($key, $afterFlat);
            if ($operation === 'CREATE' && !$afterExists) {
                continue;
            }
            if ($operation === 'DELETE' && !$beforeExists) {
                continue;
            }

            $beforeValue = $beforeExists ? $beforeFlat[$key] : null;
            $afterValue = $afterExists ? $afterFlat[$key] : null;
            $rows[] = [
                'path' => $key !== '' ? $key : '(root)',
                'before' => $beforeValue,
                'after' => $afterValue,
                'changed' => $beforeExists !== $afterExists || $beforeValue !== $afterValue,
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $out
     */
    private function flattenPayload(string $prefix, mixed $value, array &$out): void
    {
        if (!is_array($value)) {
            $out[$prefix] = $value;
            return;
        }
        if ($value === []) {
            $out[$prefix] = [];
            return;
        }

        foreach ($value as $k => $v) {
            $key = $prefix === '' ? (string)$k : ($prefix . '.' . $k);
            $this->flattenPayload($key, $v, $out);
        }
    }

    private function operationLabel(string $operation): string
    {
        return match (strtoupper($operation)) {
            'CREATE' => '作成',
            'DELETE' => '削除',
            default => '更新',
        };
    }

    private function statusLabel(string $status): string
    {
        return match (strtoupper($status)) {
            'PENDING' => '承認待ち',
            'APPROVED' => '承認済み',
            'REJECTED' => '却下',
            default => $status,
        };
    }

    private function entityTypeLabel(string $entityType): string
    {
        return match (strtolower($entityType)) {
            'account' => 'アカウント',
            'account_user_memo' => 'アカウントメモ',
            'account_sales_route_permission' => 'アカウント権限ルート',
            'account_sales_route_permission_sync' => 'アカウント権限チェック同期',
            'sku' => 'SKU',
            'price_book' => '価格表',
            'price_book_item' => '価格表明細',
            'product_template' => 'テンプレート',
            'product_template_version' => 'テンプレート版',
            'quote' => '見積',
            'configurator_session' => '仕様書セッション',
            default => $entityType,
        };
    }

    private function targetLabel(int $entityId, string $operation): string
    {
        if (strtoupper($operation) === 'CREATE' && $entityId <= 0) {
            return '新規（承認後に採番）';
        }
        if ($entityId <= 0) {
            return '-';
        }
        return '#' . $entityId;
    }

    /**
     * @return array<int, array{label:string,value:string}>
     */
    private function buildChangeContextItems(string $entityType, mixed $before, mixed $after): array
    {
        $payload = is_array($after) && !empty($after) ? $after : (is_array($before) ? $before : []);
        if (!is_array($payload)) {
            return [];
        }

        $items = [];

        if (isset($payload['price_book_id']) && is_numeric((string)$payload['price_book_id'])) {
            $priceBookId = (int)$payload['price_book_id'];
            $book = DB::table('price_books')->where('id', $priceBookId)->first(['id', 'name', 'version']);
            $value = $book ? ('#' . $book->id . ' ' . $book->name . ' v' . $book->version) : ('#' . $priceBookId);
            $items[] = ['label' => '価格表', 'value' => $value];
        }

        if (isset($payload['sku_id']) && is_numeric((string)$payload['sku_id'])) {
            $skuId = (int)$payload['sku_id'];
            $sku = DB::table('skus')->where('id', $skuId)->first(['id', 'sku_code', 'name']);
            $value = $sku ? ('#' . $sku->id . ' ' . $sku->sku_code . ' ' . $sku->name) : ('#' . $skuId);
            $items[] = ['label' => 'SKU', 'value' => $value];
        }

        if (isset($payload['template_id']) && is_numeric((string)$payload['template_id'])) {
            $templateId = (int)$payload['template_id'];
            $template = DB::table('product_templates')->where('id', $templateId)->first(['id', 'template_code', 'name']);
            $value = $template ? ('#' . $template->id . ' ' . $template->template_code . ' ' . $template->name) : ('#' . $templateId);
            $items[] = ['label' => 'テンプレート', 'value' => $value];
        }

        if (isset($payload['template_version_id']) && is_numeric((string)$payload['template_version_id'])) {
            $versionId = (int)$payload['template_version_id'];
            $version = DB::table('product_template_versions')->where('id', $versionId)->first(['id', 'template_id', 'version']);
            $value = $version ? ('#' . $version->id . ' (template_id=' . $version->template_id . ', v' . $version->version . ')') : ('#' . $versionId);
            $items[] = ['label' => 'テンプレート版', 'value' => $value];
        }

        if (isset($payload['account_id']) && is_numeric((string)$payload['account_id'])) {
            $accountId = (int)$payload['account_id'];
            $account = DB::table('accounts')->where('id', $accountId)->first(['id', 'internal_name']);
            $value = $account ? ('#' . $account->id . ' ' . (string)($account->internal_name ?? '')) : ('#' . $accountId);
            $items[] = ['label' => 'アカウント', 'value' => trim($value)];
        }

        if (strtolower($entityType) === 'account_sales_route_permission') {
            $method = strtoupper((string)($payload['http_method'] ?? ''));
            $pattern = trim((string)($payload['uri_pattern'] ?? ''));
            if ($method !== '' || $pattern !== '') {
                $items[] = ['label' => '対象ルート', 'value' => trim($method . ' ' . $pattern)];
            }
        }

        return $items;
    }

    private function findRequest(int $id): ?object
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
            )
            ->where('cr.id', $id)
            ->first();
    }
}
