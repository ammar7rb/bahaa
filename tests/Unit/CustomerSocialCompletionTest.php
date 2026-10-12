<?php
namespace Tests\Unit;

use App\Contracts\Repositories\CustomerRepositoryInterface;
use App\Contracts\Repositories\LoginSetupRepositoryInterface;
use App\Contracts\Repositories\PhoneOrEmailVerificationRepositoryInterface;
use App\Http\Controllers\RestAPI\v1\auth\SocialAuthController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CustomerSocialCompletionTest extends TestCase
{
    protected function setUp(): void {
        parent::setUp();
        Schema::create('business_settings',function(Blueprint $t){$t->id();$t->string('type');$t->text('value')->nullable();});
        session()->put('local','en');
    }
    protected function tearDown(): void {Schema::dropIfExists('business_settings');parent::tearDown();}
    private function controller(): SocialAuthController {
        return new SocialAuthController($this->createMock(CustomerRepositoryInterface::class),$this->createMock(PhoneOrEmailVerificationRepositoryInterface::class),$this->createMock(LoginSetupRepositoryInterface::class));
    }
    public function test_social_completion_requires_both_contacts_and_consent(): void {
        foreach(['phone','email','terms_accepted'] as $missing){
            $data=['name'=>'Customer','email'=>'person@example.com','phone'=>'+201012345678','terms_accepted'=>1];unset($data[$missing]);
            $this->assertSame(403,$this->controller()->registrationWithSocialMedia(Request::create('/','POST',$data))->getStatusCode());
        }
    }
    public function test_contacts_alone_cannot_create_or_take_over_a_social_account(): void {
        $data=['name'=>'Customer','email'=>'person@example.com','phone'=>'+201012345678','terms_accepted'=>1];
        $this->assertSame(401,$this->controller()->registrationWithSocialMedia(Request::create('/','POST',$data))->getStatusCode());
        Cache::put('social_registration:'.hash('sha256','proof'),['email'=>'other@example.com','medium'=>'google','social_id'=>'provider-id'],60);
        $data['social_token']='proof';
        $this->assertSame(401,$this->controller()->registrationWithSocialMedia(Request::create('/','POST',$data))->getStatusCode());
    }
    public function test_email_only_confirmation_cannot_issue_a_login_token(): void {
        $this->assertSame(401,$this->controller()->existingAccountCheck(Request::create('/','POST',['email'=>'person@example.com','user_response'=>1,'medium'=>'google']))->getStatusCode());
    }
}
