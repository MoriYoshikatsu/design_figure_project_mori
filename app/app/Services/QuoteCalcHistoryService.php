<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class QuoteCalcHistoryService
{
    /**
     * @var array<int, string>
     */
    private const APPLIED_EVENT_TYPES = [
        'ISSUE',
        'EDIT_DIRECT_APPLY',
        'EDIT_REQUEST_APPROVE',
        'LEGACY_BASELINE',
    ];

    /**
     * @return array<string, mixed>
     */
    public function getDrawerData(int $quoteId, bool $includeAllRuns = false): array
    {
        $currentRunId = $this->resolveCurrentRunId($quoteId);
        $importantRuns = $this->fetchRuns($quoteId, true, 30, $currentRunId);
        $allRuns = $includeAllRuns ? $this->fetchRuns($quoteId, false, 200, $currentRunId) : [];

        return [
            'important_runs' => $importantRuns,
            'all_runs' => $allRuns,
            'current_run' => $this->findCurrentRun($allRuns !== [] ? $allRuns : $importantRuns),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRunsForPage(int $quoteId, bool $includeAllRuns = true): array
    {
        return $this->fetchRuns($quoteId, !$includeAllRuns, 400, $this->resolveCurrentRunId($quoteId));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchRuns(int $quoteId, bool $importantOnly, int $limit, int $currentRunId = 0): array
    {
        if (!Schema::hasTable('quote_calc_runs') || !Schema::hasTable('quote_calc_run_details')) {
            return [];
        }

        $query = $this->baseRunsQuery($quoteId)
            ->orderBy('r.run_no', 'desc')
            ->limit($limit);

        if ($importantOnly) {
            $query->where('r.is_important', true);
        }

        $runs = $query->get();

        if (
            $currentRunId > 0
            && !$runs->contains(fn (object $run): bool => (int)$run->id === $currentRunId)
        ) {
            $currentRun = $this->baseRunsQuery($quoteId)
                ->where('r.id', $currentRunId)
                ->first();
            if ($currentRun) {
                $runs->push($currentRun);
            }
        }

        $runs = $runs
            ->unique(fn (object $run): int => (int)$run->id)
            ->sortByDesc(fn (object $run): int => (int)$run->run_no)
            ->values();

        if ($runs->isEmpty()) {
            return [];
        }

        $runIds = $runs->pluck('id')->map(fn ($v): int => (int)$v)->all();
        $details = DB::table('quote_calc_run_details')
            ->whereIn('run_id', $runIds)
            ->get(['run_id', 'input_json', 'step_json', 'output_json', 'context_json'])
            ->keyBy('run_id');

        $rows = [];
        foreach ($runs as $run) {
            $detail = $details->get((int)$run->id);
            $eventType = (string)$run->event_type;
            $isAppliedVersion = $this->isAppliedEvent($eventType);
            $isCurrentVersion = (int)$run->id === $currentRunId;

            $rows[] = [
                'id' => (int)$run->id,
                'quote_id' => (int)$run->quote_id,
                'run_no' => (int)$run->run_no,
                'event_type' => $eventType,
                'event_label' => $this->eventLabel($eventType),
                'is_important' => (bool)$run->is_important,
                'source_type' => $run->source_type !== null ? (string)$run->source_type : null,
                'source_id' => $run->source_id !== null ? (int)$run->source_id : null,
                'triggered_by' => $run->triggered_by !== null ? (int)$run->triggered_by : null,
                'triggered_by_name' => $run->triggered_by_name !== null ? (string)$run->triggered_by_name : null,
                'triggered_by_email' => $run->triggered_by_email !== null ? (string)$run->triggered_by_email : null,
                'subtotal_raw' => $run->subtotal_raw !== null ? (float)$run->subtotal_raw : null,
                'unit_price_rounded' => $run->unit_price_rounded !== null ? (float)$run->unit_price_rounded : null,
                'recomputed_total' => $run->recomputed_total !== null ? (float)$run->recomputed_total : null,
                'adjusted_total' => $run->adjusted_total !== null ? (float)$run->adjusted_total : null,
                'tax_rate' => $run->tax_rate !== null ? (float)$run->tax_rate : null,
                'tax_amount' => $run->tax_amount !== null ? (float)$run->tax_amount : null,
                'grand_total' => $run->grand_total !== null ? (float)$run->grand_total : null,
                'rounding_currency' => $run->rounding_currency !== null ? (string)$run->rounding_currency : null,
                'rounding_unit' => $run->rounding_unit !== null ? (float)$run->rounding_unit : null,
                'rounding_mode' => $run->rounding_mode !== null ? (string)$run->rounding_mode : null,
                'created_at' => (string)$run->created_at,
                'input' => $this->decodeJson($detail?->input_json ?? null),
                'steps' => $this->decodeJson($detail?->step_json ?? null),
                'output' => $this->decodeJson($detail?->output_json ?? null),
                'context' => $this->decodeJson($detail?->context_json ?? null),
                'is_applied_version' => $isAppliedVersion,
                'is_current_version' => $isCurrentVersion,
                'version_state_label' => $this->versionStateLabel($eventType, $isCurrentVersion, $isAppliedVersion),
                'version_state_tone' => $this->versionStateTone($eventType, $isCurrentVersion, $isAppliedVersion),
            ];
        }

        return $rows;
    }

    private function resolveCurrentRunId(int $quoteId): int
    {
        if ($quoteId <= 0 || !Schema::hasTable('quote_calc_runs')) {
            return 0;
        }

        return (int) (DB::table('quote_calc_runs')
            ->where('quote_id', $quoteId)
            ->whereIn('event_type', self::APPLIED_EVENT_TYPES)
            ->orderByDesc('run_no')
            ->value('id') ?? 0);
    }

    private function isAppliedEvent(string $eventType): bool
    {
        return in_array(strtoupper($eventType), self::APPLIED_EVENT_TYPES, true);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findCurrentRun(array $runs): ?array
    {
        foreach ($runs as $run) {
            if (!empty($run['is_current_version'])) {
                return $run;
            }
        }

        return null;
    }

    private function versionStateLabel(string $eventType, bool $isCurrentVersion, bool $isAppliedVersion): ?string
    {
        if ($isCurrentVersion) {
            return '現行版';
        }

        return match (strtoupper($eventType)) {
            'EDIT_REQUEST_SUBMIT' => '未反映',
            'EDIT_REQUEST_REJECT' => '却下',
            default => $isAppliedVersion ? '旧版' : null,
        };
    }

    private function versionStateTone(string $eventType, bool $isCurrentVersion, bool $isAppliedVersion): string
    {
        if ($isCurrentVersion) {
            return 'current';
        }

        return match (strtoupper($eventType)) {
            'EDIT_REQUEST_SUBMIT' => 'pending',
            'EDIT_REQUEST_REJECT' => 'rejected',
            default => $isAppliedVersion ? 'historical' : 'neutral',
        };
    }

    private function baseRunsQuery(int $quoteId)
    {
        return DB::table('quote_calc_runs as r')
            ->leftJoin('users as u', 'u.id', '=', 'r.triggered_by')
            ->where('r.quote_id', $quoteId)
            ->select(
                'r.id',
                'r.quote_id',
                'r.run_no',
                'r.event_type',
                'r.is_important',
                'r.source_type',
                'r.source_id',
                'r.triggered_by',
                'r.subtotal_raw',
                'r.unit_price_rounded',
                'r.recomputed_total',
                'r.adjusted_total',
                'r.tax_rate',
                'r.tax_amount',
                'r.grand_total',
                'r.rounding_currency',
                'r.rounding_unit',
                'r.rounding_mode',
                'r.created_at'
            )
            ->addSelect('u.name as triggered_by_name', 'u.email as triggered_by_email');
    }

    private function eventLabel(string $eventType): string
    {
        return match (strtoupper($eventType)) {
            'ISSUE' => '見積発行',
            'EDIT_REQUEST_SUBMIT' => '変更申請送信',
            'EDIT_DIRECT_APPLY' => '即時反映',
            'EDIT_REQUEST_APPROVE' => '変更申請承認',
            'EDIT_REQUEST_REJECT' => '変更申請却下',
            'LEGACY_BASELINE' => '既存見積の基準化',
            default => $eventType,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if ($value === null) {
            return [];
        }

        $decoded = json_decode((string)$value, true);
        return is_array($decoded) ? $decoded : [];
    }
}
