<?php

namespace Tests\Feature;

use App\Services\QuoteCalcHistoryService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class QuoteCalcHistoryServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareTables();
    }

    public function test_marks_latest_applied_run_as_current_version(): void
    {
        $this->insertRun(1, 1, 'ISSUE', true);
        $this->insertRun(2, 1, 'EDIT_REQUEST_SUBMIT', true);
        $this->insertRun(3, 1, 'EDIT_REQUEST_REJECT', true);
        $this->insertRun(4, 1, 'EDIT_REQUEST_SUBMIT', true);
        $approvedRunId = $this->insertRun(5, 1, 'EDIT_REQUEST_APPROVE', true);
        $this->insertRun(6, 1, 'EDIT_REQUEST_SUBMIT', true);

        $runs = app(QuoteCalcHistoryService::class)->getRunsForPage(1, true);

        $currentRun = collect($runs)->first(fn (array $run): bool => !empty($run['is_current_version']));
        $latestSubmit = collect($runs)->first(fn (array $run): bool => (int)$run['run_no'] === 6);

        $this->assertNotNull($currentRun);
        $this->assertSame($approvedRunId, (int)$currentRun['id']);
        $this->assertSame('現行版', $currentRun['version_state_label']);
        $this->assertSame('未反映', $latestSubmit['version_state_label'] ?? null);
        $this->assertFalse(!empty($latestSubmit['is_current_version']));
    }

    public function test_drawer_data_keeps_current_version_visible_even_when_older_than_limit(): void
    {
        $this->insertRun(1, 2, 'ISSUE', true);
        $currentRunId = $this->insertRun(2, 2, 'EDIT_REQUEST_APPROVE', true);

        for ($runNo = 3; $runNo <= 35; $runNo++) {
            $this->insertRun($runNo, 2, 'EDIT_REQUEST_SUBMIT', true);
        }

        $drawer = app(QuoteCalcHistoryService::class)->getDrawerData(2, false);
        $importantRuns = is_array($drawer['important_runs'] ?? null) ? $drawer['important_runs'] : [];
        $currentRun = collect($importantRuns)->first(fn (array $run): bool => !empty($run['is_current_version']));

        $this->assertNotNull($currentRun);
        $this->assertSame($currentRunId, (int)$currentRun['id']);
        $this->assertSame('現行版', $currentRun['version_state_label']);
    }

    private function prepareTables(): void
    {
        Schema::dropIfExists('quote_calc_run_details');
        Schema::dropIfExists('quote_calc_runs');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::create('quote_calc_runs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('quote_id');
            $table->integer('run_no');
            $table->string('event_type', 64);
            $table->boolean('is_important')->default(false);
            $table->string('source_type', 64)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->unsignedBigInteger('triggered_by')->nullable();
            $table->decimal('subtotal_raw', 16, 4)->nullable();
            $table->decimal('unit_price_rounded', 16, 4)->nullable();
            $table->decimal('recomputed_total', 16, 4)->nullable();
            $table->decimal('adjusted_total', 16, 4)->nullable();
            $table->decimal('tax_rate', 8, 6)->nullable();
            $table->decimal('tax_amount', 16, 4)->nullable();
            $table->decimal('grand_total', 16, 4)->nullable();
            $table->string('rounding_currency', 16)->nullable();
            $table->decimal('rounding_unit', 12, 4)->nullable();
            $table->string('rounding_mode', 32)->nullable();
            $table->timestamps();
        });

        Schema::create('quote_calc_run_details', function (Blueprint $table) {
            $table->unsignedBigInteger('run_id')->primary();
            $table->text('input_json')->nullable();
            $table->text('step_json')->nullable();
            $table->text('output_json')->nullable();
            $table->text('context_json')->nullable();
            $table->timestamps();
        });

        DB::table('users')->insert([
            'id' => 1,
            'name' => 'Tester',
            'email' => 'tester@example.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertRun(int $runNo, int $quoteId, string $eventType, bool $isImportant): int
    {
        $runId = (int) DB::table('quote_calc_runs')->insertGetId([
            'quote_id' => $quoteId,
            'run_no' => $runNo,
            'event_type' => $eventType,
            'is_important' => $isImportant,
            'source_type' => 'change_request',
            'source_id' => 1000 + $runNo,
            'triggered_by' => 1,
            'adjusted_total' => 1000 + $runNo,
            'tax_amount' => 100 + $runNo,
            'grand_total' => 1100 + $runNo,
            'created_at' => now()->addSeconds($runNo),
            'updated_at' => now()->addSeconds($runNo),
        ]);

        DB::table('quote_calc_run_details')->insert([
            'run_id' => $runId,
            'input_json' => '{}',
            'step_json' => '{}',
            'output_json' => '{}',
            'context_json' => '{}',
            'created_at' => now()->addSeconds($runNo),
            'updated_at' => now()->addSeconds($runNo),
        ]);

        return $runId;
    }
}
