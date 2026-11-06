<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\Seller;
use App\Models\SellerPackageSubscription;
use App\Models\VendorAdvertisingSlot;
use App\Services\VendorAdvertisingSlotService;
use App\Services\VendorSlotReservationService;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VendorSlotReservationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('sellers', fn (Blueprint $t) => [$t->id(), $t->string('activation_status')->nullable(), $t->timestamps()]);
        Schema::create('products', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('user_id'), $t->string('added_by'), $t->string('name'), $t->decimal('unit_price'), $t->integer('status')->default(0), $t->integer('request_status')->default(0), $t->timestamps()]);
        Schema::create('seller_package_subscriptions', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('seller_id'); $t->string('payment_status'); $t->string('status');
            $t->timestamp('starts_at'); $t->timestamp('expires_at'); $t->integer('product_limit');
            $t->integer('used_product_limit')->default(0); $t->json('metadata')->nullable(); $t->timestamps();
        });
        (require database_path('migrations/2026_10_14_000001_create_vendor_advertising_slots.php'))->up();
        Schema::table('vendor_advertising_slots', fn (Blueprint $t) => $t->unsignedBigInteger('extra_ad_purchase_id')->nullable());
        (require database_path('migrations/2026_10_18_030000_add_vendor_slot_reservations.php'))->up();
        config(['vendor_advertising.enabled' => true, 'vendor_advertising.creation_slots_enabled' => true,
            'vendor_advertising.reservation_idle_minutes' => 0, 'vendor_product_workspace.enabled' => false]);
        DB::table('sellers')->insert(['id' => 1, 'activation_status' => 'active']);
    }

    private function cycle(array $changes = []): SellerPackageSubscription
    {
        $id = DB::table('seller_package_subscriptions')->insertGetId(array_merge(['seller_id' => 1, 'payment_status' => 'paid', 'status' => 'active',
            'starts_at' => now()->subDay(), 'expires_at' => now()->addDays(29), 'product_limit' => 1], $changes));
        return SellerPackageSubscription::findOrFail($id);
    }
    private function slot(): VendorAdvertisingSlot
    {
        return app(VendorAdvertisingSlotService::class)->provision($this->cycle())->first();
    }
    private function product(): int
    {
        return DB::table('products')->insertGetId(['user_id' => 1, 'added_by' => 'seller', 'name' => 'Saved vendor content', 'unit_price' => 100, 'status' => 1, 'request_status' => 1]);
    }
    private function reserve(VendorAdvertisingSlot $slot, string $token): void
    {
        app(VendorSlotReservationService::class)->reserve(Seller::findOrFail(1), $slot->id, $token);
    }
    public function test_open_reserves_without_creating_product_and_repeat_is_idempotent(): void
    {
        $slot = $this->slot(); $token = (string) Str::uuid();
        $this->reserve($slot, $token); $this->reserve($slot, $token);
        $this->assertSame(0, DB::table('products')->count());
        $this->assertSame(hash('sha256', $token), $slot->fresh()->reservation_token_hash);
        $this->assertArrayNotHasKey('reservation_token_hash', $slot->fresh()->toArray());
        $this->assertSame($slot->expires_at->toISOString(), $slot->fresh()->reservation_expires_at->toISOString());
    }
    public function test_another_form_cannot_take_the_last_reserved_slot(): void
    {
        $slot = $this->slot(); $this->reserve($slot, (string) Str::uuid());
        $this->expectException(ValidationException::class);
        $this->reserve($slot, (string) Str::uuid());
    }
    public function test_cancel_returns_empty_slot_and_stale_cancel_cannot_release_a_new_form(): void
    {
        $slot = $this->slot(); $first = (string) Str::uuid(); $second = (string) Str::uuid();
        $service = app(VendorSlotReservationService::class);
        $this->reserve($slot, $first); $service->cancel(Seller::findOrFail(1), $slot->id, $first);
        $this->assertFalse($service->isReserved($slot->fresh()));
        $this->reserve($slot, $second); $service->cancel(Seller::findOrFail(1), $slot->id, $first);
        $this->assertSame(hash('sha256', $second), $slot->fresh()->reservation_token_hash);
    }
    public function test_page_reload_rotates_its_token_without_being_cancelled_by_the_old_page(): void
    {
        $slot = $this->slot(); $first = (string) Str::uuid(); $second = (string) Str::uuid();
        $service = app(VendorSlotReservationService::class); $seller = Seller::findOrFail(1);
        $this->reserve($slot, $first);
        $service->reserve($seller, $slot->id, $second, $first);
        $service->cancel($seller, $slot->id, $first);
        $this->assertSame(hash('sha256', $second), $slot->fresh()->reservation_token_hash);
    }
    public function test_save_assigns_and_consumes_once_inside_the_product_transaction(): void
    {
        $slot = $this->slot(); $token = (string) Str::uuid(); $this->reserve($slot, $token);
        request()->merge(['slot_id' => $slot->id, 'slot_reservation_token' => $token]);
        $product = DB::transaction(fn () => Product::create(['user_id' => 1, 'added_by' => 'seller', 'name' => 'Draft', 'unit_price' => 100, 'status' => 0]));
        $this->assertSame($product->id, $slot->fresh()->product_id);
        $this->assertNull($slot->fresh()->reservation_token_hash);
        $this->assertSame($product->id, (int) app(VendorSlotReservationService::class)->receipt(1, $slot->id, $token)->product_id);
        app(VendorSlotReservationService::class)->cancel(Seller::findOrFail(1), $slot->id, $token);
        $this->assertSame($product->id, $slot->fresh()->product_id);
        $this->expectException(ValidationException::class);
        DB::transaction(fn () => Product::create(['user_id' => 1, 'added_by' => 'seller', 'name' => 'Duplicate', 'unit_price' => 100]));
    }
    public function test_later_failure_rolls_back_product_assignment_and_receipt_together(): void
    {
        $slot = $this->slot(); $token = (string) Str::uuid(); $this->reserve($slot, $token);
        request()->merge(['slot_id' => $slot->id, 'slot_reservation_token' => $token]);
        try {
            DB::transaction(function () {
                Product::create(['user_id' => 1, 'added_by' => 'seller', 'name' => 'Draft', 'unit_price' => 100]);
                throw new DomainException('Simulate failed related data');
            });
        } catch (DomainException $e) { $this->assertSame('Simulate failed related data', $e->getMessage()); }
        $this->assertSame(0, DB::table('products')->count());
        $this->assertSame(0, DB::table('vendor_slot_creation_receipts')->count());
        $this->assertNull($slot->fresh()->product_id);
        $this->assertTrue(app(VendorSlotReservationService::class)->isReserved($slot->fresh()));
    }
    public function test_expiry_during_form_prevents_save_without_inserting_product(): void
    {
        $slot = $this->slot(); $token = (string) Str::uuid(); $this->reserve($slot, $token);
        request()->merge(['slot_id' => $slot->id, 'slot_reservation_token' => $token]);
        DB::table('seller_package_subscriptions')->where('id', $slot->seller_package_subscription_id)->update(['expires_at' => now()]);
        try { DB::transaction(fn () => Product::create(['user_id' => 1, 'added_by' => 'seller', 'name' => 'Too late', 'unit_price' => 100])); $this->fail('Expired subscription accepted'); }
        catch (ValidationException $e) { $this->assertSame(0, DB::table('products')->count()); }
    }
    public function test_replacement_and_removal_stop_old_ad_without_deleting_or_extending(): void
    {
        $slot = $this->slot(); $old = $this->product(); $new = $this->product();
        $service = app(VendorAdvertisingSlotService::class); $seller = Seller::findOrFail(1);
        $service->assign($seller, $slot->id, $old); $service->assign($seller, $slot->id, $new);
        $this->assertSame(0, (int) DB::table('products')->where('id', $old)->value('status'));
        $service->assign($seller, $slot->id, null);
        $this->assertSame(2, DB::table('products')->count());
        $this->assertSame(0, (int) DB::table('products')->where('id', $new)->value('status'));
        $this->assertSame($slot->expires_at->toISOString(), $slot->fresh()->expires_at->toISOString());
    }
    public function test_renewal_restores_only_basic_assignments_without_extra_products(): void
    {
        $previous = $this->cycle(['starts_at' => now()->subDays(30), 'expires_at' => now(), 'status' => 'expired']);
        $basic = app(VendorAdvertisingSlotService::class)->provision($previous)->first();
        $one = $this->product(); $two = $this->product();
        $basic->update(['product_id' => $one]);
        VendorAdvertisingSlot::create(['seller_id' => 1, 'seller_package_subscription_id' => $previous->id, 'position' => 2, 'extra_ad_purchase_id' => 9, 'product_id' => $two, 'starts_at' => $previous->starts_at, 'expires_at' => $previous->expires_at]);
        $next = $this->cycle(['starts_at' => now()]);
        app(VendorAdvertisingSlotService::class)->restorePrevious($next);
        $this->assertSame([$one], VendorAdvertisingSlot::where('seller_package_subscription_id', $next->id)->pluck('product_id')->all());
        $this->assertSame(2, DB::table('products')->count());
    }
    public function test_rejection_frees_an_additional_slot_and_retains_product(): void
    {
        $slot = $this->slot(); $product = $this->product(); $slot->update(['product_id' => $product, 'extra_ad_purchase_id' => 9]);
        DB::transaction(fn () => app(VendorAdvertisingSlotService::class)->releaseRejected(Product::withoutGlobalScopes()->findOrFail($product)));
        $this->assertNull($slot->fresh()->product_id);
        $this->assertSame(1, DB::table('products')->count());
        $this->assertSame(0, (int) DB::table('products')->value('status'));
    }
    public function test_a_basic_product_awaiting_review_keeps_its_slot_on_renewal_without_becoming_public(): void
    {
        $previous = $this->cycle(['starts_at'=>now()->subDays(30),'expires_at'=>now(),'status'=>'expired']);
        $slot = app(VendorAdvertisingSlotService::class)->provision($previous)->first();
        $product = $this->product();
        DB::table('products')->where('id',$product)->update(['request_status'=>0,'status'=>0]);
        $slot->update(['product_id'=>$product]);
        $next = $this->cycle(['starts_at'=>now()]);
        app(VendorAdvertisingSlotService::class)->restorePrevious($next);
        $this->assertSame($product, VendorAdvertisingSlot::where('seller_package_subscription_id',$next->id)->value('product_id'));
        $this->assertFalse(app(\App\Services\VendorAdvertisingAccessService::class)->visible(Product::withoutGlobalScopes()->findOrFail($product), true));
    }

    public function test_two_independent_processes_compete_for_one_slot_without_double_booking(): void
    {
        $slot = $this->slot();
        $folder = sys_get_temp_dir().'/vendor-slot-race-'.Str::uuid(); mkdir($folder);
        $file = $folder.'/database.sqlite'; $barrier = $folder.'/start'; $worker = $folder.'/worker.php';
        DB::connection()->getPdo()->exec('VACUUM INTO '.DB::connection()->getPdo()->quote($file));
        $root = var_export(base_path(), true);
        file_put_contents($worker, '<?php require '.$root.'."/vendor/autoload.php"; $app=require '.$root.'."/bootstrap/app.php"; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); config(["database.default"=>"sqlite","database.connections.sqlite.database"=>$argv[1],"vendor_advertising.enabled"=>true,"vendor_advertising.creation_slots_enabled"=>true,"vendor_product_workspace.enabled"=>false]); Illuminate\\Support\\Facades\\DB::purge(); Illuminate\\Support\\Facades\\DB::connection()->getPdo()->exec("PRAGMA busy_timeout=5000"); $until=microtime(true)+10; while(!file_exists($argv[4])&&microtime(true)<$until)usleep(1000); try{app(App\\Services\\VendorSlotReservationService::class)->reserve(App\\Models\\Seller::findOrFail(1),(int)$argv[2],$argv[3]);echo "won";}catch(Illuminate\\Validation\\ValidationException|Illuminate\\Database\\QueryException $e){echo "lost";}');
        $processes = []; $tokens = [(string) Str::uuid(), (string) Str::uuid()];
        try {
            foreach ($tokens as $token) {
                $process = proc_open([PHP_BINARY, $worker, $file, (string) $slot->id, $token, $barrier], [0 => ['pipe','r'],1 => ['pipe','w'],2 => ['pipe','w']], $pipes, base_path());
                $this->assertIsResource($process); fclose($pipes[0]); $processes[] = [$process, $pipes];
            }
            touch($barrier); $results = [];
            foreach ($processes as [$process, $pipes]) {
                $results[] = trim(stream_get_contents($pipes[1])); $stderr = stream_get_contents($pipes[2]);
                fclose($pipes[1]); fclose($pipes[2]); $this->assertSame(0, proc_close($process), $stderr);
            }
            sort($results); $this->assertSame(['lost', 'won'], $results);
            $pdo = new \PDO('sqlite:'.$file);
            $hash = $pdo->query('select reservation_token_hash from vendor_advertising_slots')->fetchColumn();
            $this->assertContains($hash, array_map(fn ($token) => hash('sha256', $token), $tokens));
            $this->assertSame(0, (int) $pdo->query('select count(*) from products')->fetchColumn());
            $pdo = null;
        } finally {
            foreach ([$worker, $barrier, $file, $file.'-journal', $file.'-shm', $file.'-wal'] as $path) if (is_file($path)) unlink($path);
            rmdir($folder);
        }
    }

    public function test_ordinary_creation_without_slot_does_not_consume_advertising_capacity(): void
    {
        $this->slot();
        Product::create(['user_id'=>1,'added_by'=>'seller','name'=>'Ordinary submission','unit_price'=>100]);
        $this->assertSame(1, DB::table('products')->count());
        $this->assertNull(DB::table('vendor_advertising_slots')->value('product_id'));
        $this->assertSame(0, DB::table('vendor_slot_creation_receipts')->count());
    }

    public function test_stale_manual_assignment_cannot_replace_a_newer_assignment(): void
    {
        $slot = $this->slot(); $first = $this->product(); $second = $this->product();
        $seller = Seller::findOrFail(1);
        app(VendorAdvertisingSlotService::class)->assign($seller, $slot->id, $first);
        $request = \Illuminate\Http\Request::create('/api/v3/seller/packages/slots/'.$slot->id, 'POST', ['seller'=>$seller,'product_id'=>$second,'slot_revision'=>0]);
        $response = app(\App\Http\Controllers\RestAPI\v3\seller\VendorAdvertisingSlotController::class)->update($request, $slot->id);
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame($first, $slot->fresh()->product_id);
        $this->assertSame(1, (int) $slot->fresh()->assignment_revision);
    }

    public function test_foreign_slot_cannot_be_reserved(): void
    {
        $this->slot();
        DB::table('sellers')->insert(['id'=>2,'activation_status'=>'active']);
        $foreign = app(VendorAdvertisingSlotService::class)->provision($this->cycle(['seller_id'=>2]))->first();
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->reserve($foreign, (string) Str::uuid());
    }
}
