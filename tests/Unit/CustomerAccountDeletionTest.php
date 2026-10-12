<?php
namespace Tests\Unit;
use App\Services\CustomerAccountDeletionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Hash, Schema};
use Tests\TestCase;
class CustomerAccountDeletionTest extends TestCase
{
    protected function setUp(): void {
        parent::setUp();
        Schema::create('users', function (Blueprint $t) { $t->id(); $t->string('password'); $t->boolean('is_active')->default(true); foreach (['remember_token','temporary_token','cm_firebase_token'] as $c) $t->string($c)->nullable(); });
        Schema::create('orders', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('customer_id'); $t->boolean('is_guest')->default(false); $t->string('order_status'); });
        Schema::create('oauth_access_tokens', function (Blueprint $t) { $t->string('id')->primary(); $t->unsignedBigInteger('user_id'); $t->boolean('revoked')->default(false); });
        Schema::create('oauth_refresh_tokens', function (Blueprint $t) { $t->id(); $t->string('access_token_id'); $t->boolean('revoked')->default(false); });
        (require base_path('database/migrations/2026_10_12_110000_customer_account_deletion_flow.php'))->up();
        DB::table('users')->insert(['id'=>1, 'password'=>Hash::make('safe-password')]);
    }
    public function test_sequence_rechecks_password_and_retains_customer_and_financial_history(): void {
        $service = app(CustomerAccountDeletionService::class); $token = $service->begin(1, 'safe-password');
        try { $service->finish(1, $token, 'safe-password'); $this->fail(); } catch (\Illuminate\Validation\ValidationException) { $this->assertTrue(true); }
        try { $service->reason(1, $token, str_repeat(' ', 151).'قصير'); $this->fail(); } catch (\Illuminate\Validation\ValidationException) { $this->assertTrue(true); }
        try { $service->reason(1, $token, str_repeat("\u{00A0}", 151)); $this->fail(); } catch (\Illuminate\Validation\ValidationException) { $this->assertTrue(true); }
        $service->reason(1, $token, str_repeat('س', 150));
        try { $service->finish(1, $token, 'incorrect'); $this->fail(); } catch (\Illuminate\Validation\ValidationException) { $this->assertTrue(true); }
        DB::table('orders')->insert(['customer_id'=>1,'order_status'=>'delivered']); DB::table('oauth_access_tokens')->insert(['id'=>'token','user_id'=>1]);
        $service->finish(1, $token, 'safe-password');
        $this->assertNotNull(DB::table('users')->where('id',1)->value('account_deleted_at'));
        $this->assertEquals(0, DB::table('users')->where('id',1)->value('is_active'));
        $this->assertSame(1, DB::table('orders')->count()); $this->assertEquals(1, DB::table('oauth_access_tokens')->value('revoked'));
        $this->assertFalse(Hash::check('safe-password', DB::table('users')->value('password')));
    }
    public function test_expired_authorization_cannot_advance(): void {
        $service = app(CustomerAccountDeletionService::class); $token = $service->begin(1, 'safe-password');
        $this->travel(11)->minutes(); $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->reason(1, $token, str_repeat('س',150));
    }
    public function test_other_open_customer_sessions_are_ended_without_deleting_financial_owner(): void {
        DB::table('users')->where('id',1)->update(['account_deleted_at'=>now(),'is_active'=>0]);
        $this->actingAs(\App\Models\User::find(1),'customer');
        $request = \Illuminate\Http\Request::create('http://localhost/'); $request->setLaravelSession(app('session')->driver());
        $request->session()->put('customer_id',1);
        app(\App\Http\Middleware\EndDeletedCustomerSession::class)->handle($request,fn () => response('ok'));
        $this->assertFalse(auth('customer')->check()); $this->assertNull($request->session()->get('customer_id'));
        $this->assertSame(1,DB::table('users')->count());
    }
}
