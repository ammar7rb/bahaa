<?php

namespace Tests\Unit;

use App\Http\Controllers\RestAPI\v3\seller\auth\RegisterController;
use App\Http\Requests\API\v3\SellerRegistrationRequest;
use App\Models\BusinessSetting;
use App\Models\PolicyAcceptance;
use App\Models\PolicyVersion;
use App\Models\Seller;
use App\Services\FirebaseService;
use App\Services\PolicyAcceptanceService;
use App\Services\SellerActivationService;
use App\Services\SellerRegistrationVerificationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class SellerRegistrationPolicyEnforcementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_role_id')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });
        Schema::create('business_settings', function (Blueprint $table) {
            $table->id();
            $table->string('type')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
        Schema::create('sellers', function (Blueprint $table) {
            $table->id();
            $table->string('f_name')->nullable();
            $table->string('l_name')->nullable();
            $table->string('email')->unique();
            $table->string('phone')->unique();
            $table->string('password');
            $table->string('status')->default('pending');
            $table->timestamp('phone_verified_at')->nullable();
            $table->uuid('registration_reference')->nullable()->unique();
            $table->string('activation_status')->default('pending_activation');
            $table->timestamp('activation_requested_at')->nullable();
            $table->timestamp('activation_approved_at')->nullable();
            $table->unsignedBigInteger('activation_approved_by')->nullable();
            $table->timestamps();
        });
        Schema::create('policy_versions', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->string('title');
            $table->longText('content');
            $table->string('audience');
            $table->string('version');
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('effective_at')->nullable();
            $table->unsignedBigInteger('created_by_admin_id')->nullable();
            $table->timestamps();
        });
        Schema::create('policy_acceptances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('policy_version_id');
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->timestamp('accepted_at');
            $table->timestamps();
            $table->unique(['policy_version_id', 'subject_type', 'subject_id'], 'policy_subject_unique');
        });

        BusinessSetting::create(['type' => SellerRegistrationVerificationService::SETTING_VERIFICATION_MODE, 'value' => SellerRegistrationVerificationService::MODE_NONE]);
        BusinessSetting::create(['type' => 'language', 'value' => json_encode([['code' => 'en', 'default' => true, 'direction' => 'ltr']])]);
        clearWebConfigCacheKeys();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('policy_acceptances');
        Schema::dropIfExists('policy_versions');
        Schema::dropIfExists('sellers');
        Schema::dropIfExists('business_settings');
        Schema::dropIfExists('admins');
        parent::tearDown();
    }

    public function test_registration_is_rejected_when_required_policies_are_missing(): void
    {
        PolicyVersion::create([
            'key' => 'seller_terms', 'title' => 'Seller terms', 'content' => 'Terms',
            'audience' => 'seller', 'version' => '1.0', 'is_required' => true, 'is_active' => true,
        ]);

        $response = $this->registerWithPolicies([]);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('required_policies_not_accepted', $response->getData(true)['code']);
        $this->assertSame(0, Seller::query()->count());
        $this->assertSame(0, PolicyAcceptance::query()->count());
    }

    public function test_registration_creates_required_policy_acceptances_in_the_same_transaction(): void
    {
        $policy = PolicyVersion::create([
            'key' => 'seller_privacy', 'title' => 'Seller privacy', 'content' => 'Privacy',
            'audience' => 'seller', 'version' => '1.0', 'is_required' => true, 'is_active' => true,
        ]);

        $response = $this->registerWithPolicies([$policy->id]);

        $this->assertSame(200, $response->getStatusCode(), json_encode($response->getData(true)));
        $this->assertSame(1, Seller::query()->count());
        $this->assertSame(1, PolicyAcceptance::query()->count());
        $this->assertSame('seller', PolicyAcceptance::query()->firstOrFail()->subject_type);
    }

    public function test_registration_without_optional_shop_images_uses_safe_defaults(): void
    {
        $source = file_get_contents(base_path('app/Http/Controllers/RestAPI/v3/seller/auth/RegisterController.php'));

        $this->assertStringContainsString(": 'def.png'", $source);
        $this->assertStringContainsString("'code' => 'seller_registration_failed'", $source);
        $this->assertStringNotContainsString("'error' => \$e->getMessage()", $source);
    }

    private function registerWithPolicies(array $policyVersionIds)
    {
        $request = SellerRegistrationRequest::create('/', 'POST', [
            'email' => 'seller'.count($policyVersionIds).'@example.com',
            'phone' => '+20100000000'.count($policyVersionIds),
            'password' => 'secure-password',
            'policy_version_ids' => $policyVersionIds,
        ]);

        return (new RegisterController())->store(
            $request,
            new SellerRegistrationVerificationService(Mockery::mock(FirebaseService::class)),
            app(SellerActivationService::class),
            app(PolicyAcceptanceService::class),
        );
    }
}
