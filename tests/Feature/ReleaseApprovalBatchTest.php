<?php

namespace Tests\Feature;

use App\Services\ReleaseDataPreparationService;
use App\Services\ReleaseReadinessService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReleaseApprovalBatchTest extends TestCase
{
    protected function tearDown(): void
    {
        foreach ([
            'release_data_migration_items', 'release_data_migration_runs', 'seller_settlements',
            'seller_order_insurances', 'order_insurances', 'customer_purchase_package_subscriptions',
            'seller_package_subscriptions', 'seller_packages', 'products', 'migrations',
        ] as $table) Schema::dropIfExists($table);
        parent::tearDown();
    }

    public function test_legacy_release_data_is_audited_applied_idempotently_and_rolled_back(): void
    {
        $this->createReleaseDataTables();
        DB::table('products')->insert([
            ['id'=>1,'added_by'=>'seller','status'=>1,'featured'=>1,'production_date'=>null,'expiry_date'=>null],
            ['id'=>2,'added_by'=>'seller','status'=>1,'featured'=>1,'production_date'=>'2025-01-01','expiry_date'=>'2025-02-01'],
        ]);
        DB::table('seller_packages')->insert(['id'=>1,'package_validity_days'=>30,'duration_unit'=>'months','duration_value'=>1,'search_priority'=>7,'cancellation_effect'=>'immediate']);
        DB::table('seller_package_subscriptions')->insert(['id'=>1,'seller_package_id'=>1,'package_validity_days'=>30,'search_priority'=>100,'cancellation_effect'=>'end_of_period']);
        DB::table('customer_purchase_package_subscriptions')->insert(['id'=>1]);
        DB::table('order_insurances')->insert(['id'=>1,'payment_status'=>'paid','maturity_days'=>90,'created_at'=>'2026-01-01 00:00:00']);
        DB::table('seller_order_insurances')->insert(['id'=>1,'payment_status'=>'paid','reuse_after_days'=>90,'paid_at'=>'2026-01-01 00:00:00','created_at'=>'2026-01-01 00:00:00']);
        DB::table('seller_settlements')->insert(['id'=>1,'pre_due_days_snapshot'=>5]);

        $service = app(ReleaseDataPreparationService::class);
        $audit = $service->audit();
        $this->assertSame(1, $audit['products_missing_required_dates']);
        $this->assertSame(1, $audit['published_expired_products']);
        $this->assertSame(1, $audit['customer_package_history_retained']);

        $result = $service->apply('acceptance-001');
        $this->assertSame(1, $result['changed']['products_quarantined_missing_dates']);
        $this->assertSame(1, $result['changed']['expired_products_unpublished']);
        $this->assertSame(1, $result['changed']['seller_subscription_snapshots_filled']);
        $this->assertSame(0, $result['after']['products_missing_required_dates']);
        $this->assertSame(0, $result['after']['customer_insurances_missing_maturity']);
        $this->assertSame(0, $result['after']['seller_insurances_missing_reuse_date']);
        $this->assertSame(0, $result['after']['seller_settlements_missing_snapshot']);
        $this->assertSame('months', DB::table('seller_package_subscriptions')->value('duration_unit'));
        $this->assertSame(7, (int) DB::table('seller_package_subscriptions')->value('search_priority'));

        try {
            $service->apply('acceptance-001');
            $this->fail('A release batch key must not be applied twice.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('release_batch_key_already_exists', $exception->getMessage());
        }

        $rollback = $service->rollback('acceptance-001');
        $this->assertSame(6, $rollback['restored_records']);
        $this->assertSame(1, (int) DB::table('products')->where('id', 1)->value('status'));
        $this->assertNull(DB::table('order_insurances')->value('matures_at'));
        $this->assertNull(DB::table('seller_order_insurances')->value('reusable_at'));
        $this->assertSame('rolled_back', DB::table('release_data_migration_runs')->value('status'));
    }

    public function test_release_journey_has_an_automated_trace_for_every_required_stage(): void
    {
        $journey = config('release_acceptance.journey');
        $this->assertSame([
            'seller_registration','seller_activation','seller_package_purchase','product_five_images_and_dates',
            'product_review_and_publish','customer_activation','customer_order_insurance','shipping_quote_and_decision',
            'seller_order_insurance','delivery_and_customer_confirmation','settlement_and_release','withdrawal_method',
            'return_and_financial_reversal','financial_reconciliation','vendor_and_customer_app_contracts',
        ], array_keys($journey));
        foreach ($journey as $testFile) $this->assertFileExists(base_path($testFile));
    }

    public function test_non_strict_release_readiness_can_report_local_only_warnings(): void
    {
        Schema::create('migrations', function (Blueprint $table): void {
            $table->id(); $table->string('migration'); $table->integer('batch');
        });
        $files = array_keys(app('migrator')->getMigrationFiles(database_path('migrations')));
        foreach ($files as $migration) DB::table('migrations')->insert(['migration'=>$migration,'batch'=>1]);
        config()->set('release.required_schema', []);

        $result = app(ReleaseReadinessService::class)->inspect(false, 'E:\\project');
        $this->assertTrue($result['ready'], json_encode($result['checks'], JSON_PRETTY_PRINT));
        $this->assertSame(0, $result['summary']['failed']);
        $this->assertSame('pass', $result['checks']['critical_schedules']['status']);
        $this->assertSame('pass', $result['checks']['payment_webhook_hooks']['status']);
        $this->assertSame('warning', $result['checks']['open_requirements']['status']);
        $this->assertSame('pass', $result['checks']['vendor_app_contract']['status']);
        $this->assertSame('pass', $result['checks']['customer_app_contract']['status']);
    }

    private function createReleaseDataTables(): void
    {
        Schema::create('products', function (Blueprint $t): void { $t->id(); $t->string('added_by')->nullable(); $t->boolean('status'); $t->boolean('featured'); $t->date('production_date')->nullable(); $t->date('expiry_date')->nullable(); $t->string('seller_review_status')->nullable(); $t->text('seller_review_reason')->nullable(); });
        Schema::create('seller_packages', function (Blueprint $t): void { $t->id(); $t->unsignedInteger('package_validity_days')->nullable(); $t->string('duration_unit')->nullable(); $t->unsignedInteger('duration_value')->nullable(); $t->unsignedInteger('search_priority')->default(100); $t->string('cancellation_effect')->default('end_of_period'); });
        Schema::create('seller_package_subscriptions', function (Blueprint $t): void { $t->id(); $t->unsignedBigInteger('seller_package_id')->nullable(); $t->unsignedInteger('package_validity_days')->nullable(); $t->string('duration_unit')->nullable(); $t->unsignedInteger('duration_value')->nullable(); $t->unsignedInteger('search_priority')->default(100); $t->string('cancellation_effect')->default('end_of_period'); $t->json('metadata')->nullable(); });
        Schema::create('customer_purchase_package_subscriptions', fn (Blueprint $t) => $t->id());
        Schema::create('order_insurances', function (Blueprint $t): void { $t->id(); $t->string('payment_status'); $t->unsignedInteger('maturity_days')->default(90); $t->timestamp('matures_at')->nullable(); $t->json('metadata')->nullable(); $t->timestamps(); });
        Schema::create('seller_order_insurances', function (Blueprint $t): void { $t->id(); $t->string('payment_status'); $t->unsignedInteger('reuse_after_days')->default(90); $t->timestamp('reusable_at')->nullable(); $t->timestamp('paid_at')->nullable(); $t->json('metadata')->nullable(); $t->timestamps(); });
        Schema::create('seller_settlements', function (Blueprint $t): void { $t->id(); $t->string('timezone_snapshot')->nullable(); $t->unsignedTinyInteger('pre_due_days_snapshot')->default(5); $t->json('metadata')->nullable(); });
        Schema::create('release_data_migration_runs', function (Blueprint $t): void { $t->id(); $t->string('batch_key')->unique(); $t->string('status'); $t->json('summary')->nullable(); $t->timestamp('started_at'); $t->timestamp('completed_at')->nullable(); $t->timestamp('rolled_back_at')->nullable(); $t->unsignedBigInteger('initiated_by_admin_id')->nullable(); $t->timestamps(); });
        Schema::create('release_data_migration_items', function (Blueprint $t): void { $t->id(); $t->unsignedBigInteger('release_data_migration_run_id'); $t->string('action'); $t->string('table_name'); $t->unsignedBigInteger('record_id'); $t->json('before_values'); $t->json('after_values'); $t->string('status'); $t->timestamps(); });
    }
}
