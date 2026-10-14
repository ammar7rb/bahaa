<?php
namespace Tests\Unit;
use App\Models\User;
use App\Services\CustomerPasswordChangeService;
use App\Http\Controllers\Customer\OrderTrackingController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB,Hash};
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
class CustomerPasswordAndTrackingTest extends TestCase
{
    protected function setUp():void
    {
        parent::setUp();
        Schema::create('users',function(Blueprint $t){$t->id();$t->string('password');$t->timestamps();});
        User::forceCreate(['id'=>1,'password'=>Hash::make('TemporaryFromAdmin123')]);
        auth('customer')->setUser(User::find(1));
    }
    public function test_wrong_current_password_and_missing_confirmation_are_rejected():void
    {
        $old=User::find(1)->password;
        foreach([['password'=>'NewPassword123','current_password'=>'wrong','password_confirmation'=>'NewPassword123'],['password'=>'NewPassword123','current_password'=>'TemporaryFromAdmin123']] as $data) {
            try { app(CustomerPasswordChangeService::class)->password(User::find(1),Request::create('/','POST',$data));$this->fail('Unverified password change'); }
            catch(ValidationException $e){$this->assertNotEmpty($e->errors());}
            $this->assertSame($old,User::find(1)->password);
        }
    }
    public function test_admin_provided_password_can_be_replaced_and_nonpassword_edits_keep_hash():void
    {
        $user=User::find(1);$service=app(CustomerPasswordChangeService::class);
        $this->assertSame($user->password,$service->password($user,Request::create('/','POST')));
        $hash=$service->password($user,Request::create('/','POST',['password'=>'MyNewPassword123','current_password'=>'TemporaryFromAdmin123','password_confirmation'=>'MyNewPassword123']));
        $this->assertTrue(Hash::check('MyNewPassword123',$hash));
        $this->assertFalse(Hash::check('TemporaryFromAdmin123',$hash));
    }
    public function test_tracking_only_lists_customer_orders_and_rejects_another_customer_id():void
    {
        Schema::create('orders',function(Blueprint $t){$t->id();$t->unsignedBigInteger('customer_id');$t->timestamps();});
        DB::table('orders')->insert([['id'=>1,'customer_id'=>1],['id'=>2,'customer_id'=>2]]);
        $controller=app(OrderTrackingController::class);
        $this->assertSame([1],$controller->index(Request::create('/'))->getData()['orders']->pluck('id')->all());
        $this->assertContains('customer',app('router')->getRoutes()->getByName('customer.tracking.index')->gatherMiddleware());
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $controller->index(Request::create('/','GET',['order_id'=>2]));
    }
    public function test_forgot_password_for_logged_in_customer_routes_to_support():void
    {
        $controller=(new \ReflectionClass(\App\Http\Controllers\Customer\Auth\ForgotPasswordController::class))->newInstanceWithoutConstructor();
        $response=$controller->reset_password();
        $this->assertStringContainsString('/customer/password-recovery',$response->getTargetUrl());
    }
}
