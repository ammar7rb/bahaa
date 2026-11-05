<?php

namespace Tests\Unit;

use App\Http\Controllers\Customer\AccountPhotoController;
use App\Models\User;
use App\Services\AccountPhotoAccess;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AccountPhotoPrivacyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');
        Schema::create('users', function (Blueprint $table) {
            $table->id(); $table->string('image')->nullable(); $table->timestamps();
        });
        DB::table('users')->insert([['id' => 1, 'image' => 'owner.webp'], ['id' => 2, 'image' => 'other.webp']]);
    }

    public function test_photo_move_is_previewed_then_verified_and_idempotent(): void
    {
        Storage::disk('public')->put('profile/owner.webp', 'photo-bytes');
        $this->artisan('accounts:privatize-photos')->assertSuccessful();
        Storage::disk('public')->assertExists('profile/owner.webp');
        Storage::disk('local')->assertMissing('private-account-photos/profile/owner.webp');
        $this->artisan('accounts:privatize-photos', ['--apply' => true])->assertSuccessful();
        Storage::disk('public')->assertMissing('profile/owner.webp');
        $this->assertSame('photo-bytes', Storage::disk('local')->get('private-account-photos/profile/owner.webp'));
        $this->artisan('accounts:privatize-photos', ['--apply' => true])->assertSuccessful();
    }

    public function test_owner_can_read_private_photo_without_public_cache(): void
    {
        $this->actingAs(User::findOrFail(1), 'customer');
        Storage::disk('local')->put('private-account-photos/profile/owner.webp', 'photo-bytes');
        $response = app(AccountPhotoController::class)(Request::create('/account-photos/customer/1'), 'customer', 1, app(AccountPhotoAccess::class));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function test_another_customer_cannot_read_the_photo(): void
    {
        $this->actingAs(User::findOrFail(2), 'customer');
        $this->assertFalse(app(AccountPhotoAccess::class)->canView('customer', 1));
        try {
            app(AccountPhotoController::class)(Request::create('/account-photos/customer/1'), 'customer', 1, app(AccountPhotoAccess::class));
            $this->fail('Another customer accessed the photo');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }
    }

    public function test_anonymous_request_cannot_read_the_photo(): void
    {
        $this->assertFalse(app(AccountPhotoAccess::class)->canView('customer', 1));
        $this->assertSame(404, app(AccountPhotoAccess::class)->link('customer', 1, 'owner.webp')['status']);
    }

    public function test_administration_can_view_both_account_types(): void
    {
        $this->actingAs((new \App\Models\Admin())->forceFill(['id' => 9]), 'admin');
        $this->assertTrue(app(AccountPhotoAccess::class)->canView('customer', 1));
        $this->assertTrue(app(AccountPhotoAccess::class)->canView('seller', 11));
        Storage::disk('local')->put('private-account-photos/profile/owner.webp', 'photo-bytes');
        $response = app(AccountPhotoController::class)(Request::create('/account-photos/customer/1'), 'customer', 1, app(AccountPhotoAccess::class));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_legacy_default_avatar_does_not_request_a_nonexistent_private_photo(): void
    {
        $this->actingAs(User::findOrFail(1), 'customer');
        $this->assertSame(['key' => null, 'path' => null, 'status' => 404], app(AccountPhotoAccess::class)->link('customer', 1, 'def.png'));
    }

    public function test_merchant_can_view_only_their_own_photo(): void
    {
        $this->actingAs((new \App\Models\Seller())->forceFill(['id' => 11]), 'seller');
        $this->assertTrue(app(AccountPhotoAccess::class)->canView('seller', 11));
        $this->assertFalse(app(AccountPhotoAccess::class)->canView('seller', 12));
        $this->assertFalse(app(AccountPhotoAccess::class)->canView('customer', 1));
    }
}
