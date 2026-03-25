<?php

namespace Tests\Feature;

use App\Services\WorkChangeRequestApplier;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class WorkChangeRequestApplierAccountDefaultsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareTables();
    }

    public function test_account_create_persists_customer_factor_default(): void
    {
        $createdId = app(WorkChangeRequestApplier::class)->apply((object)[
            'entity_type' => 'account',
            'entity_id' => 0,
            'operation' => 'CREATE',
            'requested_by' => 0,
        ], [
            'after' => [
                'account_type' => 'B2B',
                'internal_name' => 'Sample Account',
                'assignee_name' => 'Sales One',
                'memo' => 'memo',
                'customer_factor_default' => 0.93,
            ],
        ], 1);

        $row = DB::table('accounts')->where('id', $createdId)->first();

        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(0.93, (float)$row->customer_factor_default, 0.000001);
    }

    public function test_account_update_persists_customer_factor_default(): void
    {
        $accountId = (int)DB::table('accounts')->insertGetId([
            'account_type' => 'B2B',
            'internal_name' => 'Before',
            'memo' => null,
            'assignee_name' => null,
            'customer_factor_default' => 1.0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(WorkChangeRequestApplier::class)->apply((object)[
            'entity_type' => 'account',
            'entity_id' => $accountId,
            'operation' => 'UPDATE',
            'requested_by' => 0,
        ], [
            'before' => [
                'account_type' => 'B2B',
                'internal_name' => 'Before',
                'memo' => null,
                'assignee_name' => null,
                'customer_factor_default' => 1.0,
            ],
            'after' => [
                'account_type' => 'B2B',
                'internal_name' => 'After',
                'memo' => 'updated',
                'assignee_name' => 'Sales Two',
                'customer_factor_default' => 0.88,
            ],
        ], 1);

        $row = DB::table('accounts')->where('id', $accountId)->first();

        $this->assertNotNull($row);
        $this->assertSame('After', $row->internal_name);
        $this->assertEqualsWithDelta(0.88, (float)$row->customer_factor_default, 0.000001);
    }

    private function prepareTables(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('accounts');

        Schema::create('accounts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('account_type', 10);
            $table->string('internal_name')->nullable();
            $table->text('memo')->nullable();
            $table->string('assignee_name')->nullable();
            $table->decimal('customer_factor_default', 10, 6)->default(1);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('action');
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->longText('before_json')->nullable();
            $table->longText('after_json')->nullable();
            $table->timestamps();
        });
    }
}
