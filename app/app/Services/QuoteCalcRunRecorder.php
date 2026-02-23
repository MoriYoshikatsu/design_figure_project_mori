<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class QuoteCalcRunRecorder
{
    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $steps
     * @param array<string, mixed> $output
     * @param array<string, mixed> $context
     */
    public function record(
        int $quoteId,
        string $eventType,
        array $input,
        array $steps,
        array $output,
        ?int $triggeredBy = null,
        bool $isImportant = true,
        ?string $sourceType = null,
        ?int $sourceId = null,
        array $context = []
    ): int {
        if (!Schema::hasTable('quote_calc_runs') || !Schema::hasTable('quote_calc_run_details')) {
            return 0;
        }

        return DB::transaction(function () use (
            $quoteId,
            $eventType,
            $input,
            $steps,
            $output,
            $triggeredBy,
            $isImportant,
            $sourceType,
            $sourceId,
            $context
        ) {
            DB::table('quotes')
                ->where('id', $quoteId)
                ->lockForUpdate()
                ->first(['id']);

            $lastRunNo = (int)(DB::table('quote_calc_runs')
                ->where('quote_id', $quoteId)
                ->max('run_no') ?? 0);
            $runNo = $lastRunNo + 1;

            $runId = (int)DB::table('quote_calc_runs')->insertGetId([
                'quote_id' => $quoteId,
                'run_no' => $runNo,
                'event_type' => strtoupper(trim($eventType)),
                'is_important' => $isImportant,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'triggered_by' => $triggeredBy,
                'subtotal_raw' => $this->asNullableNumber($output['subtotal_raw'] ?? null),
                'unit_price_rounded' => $this->asNullableNumber($output['unit_price_rounded'] ?? null),
                'recomputed_total' => $this->asNullableNumber($output['recomputed_total'] ?? null),
                'adjusted_total' => $this->asNullableNumber($output['adjusted_total'] ?? null),
                'tax_rate' => $this->asNullableNumber($output['tax_rate'] ?? ($input['tax_rate'] ?? null)),
                'tax_amount' => $this->asNullableNumber($output['tax_amount'] ?? null),
                'grand_total' => $this->asNullableNumber($output['grand_total'] ?? ($output['total'] ?? null)),
                'rounding_currency' => $this->asNullableString($output['rounding_currency'] ?? ($input['rounding_currency'] ?? null)),
                'rounding_unit' => $this->asNullableNumber($output['rounding_unit'] ?? ($input['rounding_unit'] ?? null)),
                'rounding_mode' => $this->asNullableString($output['rounding_mode'] ?? ($input['rounding_mode'] ?? null)),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('quote_calc_run_details')->insert([
                'run_id' => $runId,
                'input_json' => json_encode($input, JSON_UNESCAPED_UNICODE),
                'step_json' => json_encode($steps, JSON_UNESCAPED_UNICODE),
                'output_json' => json_encode($output, JSON_UNESCAPED_UNICODE),
                'context_json' => json_encode($context, JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $runId;
        });
    }

    /**
     * @param array<string, mixed> $calculation
     */
    public function recordFromCalculation(
        int $quoteId,
        string $eventType,
        array $calculation,
        ?int $triggeredBy = null,
        bool $isImportant = true,
        ?string $sourceType = null,
        ?int $sourceId = null,
        array $context = []
    ): int {
        $input = is_array($calculation['pricing_input'] ?? null) ? $calculation['pricing_input'] : [];
        $steps = is_array($calculation['pricing_steps'] ?? null) ? $calculation['pricing_steps'] : [];
        $output = is_array($calculation['pricing_output'] ?? null) ? $calculation['pricing_output'] : [];

        return $this->record(
            $quoteId,
            $eventType,
            $input,
            $steps,
            $output,
            $triggeredBy,
            $isImportant,
            $sourceType,
            $sourceId,
            $context
        );
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    public function recordFromSnapshot(
        int $quoteId,
        string $eventType,
        array $snapshot,
        ?int $triggeredBy = null,
        bool $isImportant = true,
        ?string $sourceType = null,
        ?int $sourceId = null,
        array $context = []
    ): int {
        $input = is_array($snapshot['pricing_input'] ?? null) ? $snapshot['pricing_input'] : [];
        $steps = is_array($snapshot['pricing_steps'] ?? null) ? $snapshot['pricing_steps'] : [];
        $output = is_array($snapshot['pricing_output'] ?? null) ? $snapshot['pricing_output'] : [];

        if (empty($output)) {
            $totals = is_array($snapshot['totals'] ?? null) ? $snapshot['totals'] : [];
            $output = [
                'adjusted_total' => $totals['subtotal'] ?? null,
                'tax_amount' => $totals['tax'] ?? null,
                'grand_total' => $totals['total'] ?? null,
                'tax_rate' => $input['tax_rate'] ?? null,
                'rounding_currency' => $input['rounding_currency'] ?? null,
                'rounding_unit' => $input['rounding_unit'] ?? null,
                'rounding_mode' => $input['rounding_mode'] ?? null,
            ];
        }

        return $this->record(
            $quoteId,
            $eventType,
            $input,
            $steps,
            $output,
            $triggeredBy,
            $isImportant,
            $sourceType,
            $sourceId,
            $context
        );
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    public function recordLegacyBaseline(int $quoteId, array $snapshot, ?int $triggeredBy = null): int
    {
        return $this->recordFromSnapshot(
            $quoteId,
            'LEGACY_BASELINE',
            $snapshot,
            $triggeredBy,
            true,
            'backfill',
            null,
            ['reason' => 'legacy baseline backfill']
        );
    }

    private function asNullableNumber(mixed $value): ?float
    {
        return is_numeric($value) ? (float)$value : null;
    }

    private function asNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }
}
