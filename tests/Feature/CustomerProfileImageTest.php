<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Schema, Storage};
use Tests\TestCase;
class CustomerProfileImageTest extends TestCase
{
    protected function setUp(): void {
        parent::setUp();
        if (!defined('DOMAIN_POINTED_DIRECTORY')) define('DOMAIN_POINTED_DIRECTORY','public');
        Schema::create('users', function (Blueprint $t) { $t->id(); $t->string('image')->nullable(); $t->boolean('is_active')->default(true); $t->timestamps(); });
        Schema::create('storages', function (Blueprint $t) { $t->id(); $t->string('data_type'); $t->unsignedBigInteger('data_id'); $t->string('key'); $t->string('value'); $t->timestamps(); });
        Schema::create('business_settings', function (Blueprint $t) { $t->id(); $t->string('type'); $t->text('value'); });
        DB::table('users')->insert([['id'=>1],['id'=>2]]); Storage::fake('public'); config(['filesystems.disks.default'=>'public']);
    }
    public function test_gallery_upload_updates_the_same_profile_image_and_cannot_target_another_customer(): void {
        $this->actingAs(User::find(1),'api')->postJson('http://localhost/api/v1/customer/profile-image', ['customer_id'=>2, 'image'=>UploadedFile::fake()->image('avatar.png',80,80)])
            ->assertOk()->assertJsonPath('image_full_url.status',200);
        $name = DB::table('users')->where('id',1)->value('image');
        $this->assertStringEndsWith('.webp',$name); Storage::disk('public')->assertExists('profile/'.$name);
        $this->assertNull(DB::table('users')->where('id',2)->value('image'));
        $this->assertSame(User::find(1)->image_full_url['key'],$name);
    }
    public function test_upload_rejects_non_images_and_requires_authentication(): void {
        $this->postJson('http://localhost/api/v1/customer/profile-image',['image'=>UploadedFile::fake()->create('image.php',1,'application/x-php')])->assertUnauthorized();
        $this->actingAs(User::find(1),'api')->postJson('http://localhost/api/v1/customer/profile-image',['image'=>UploadedFile::fake()->create('image.php',1,'application/x-php')])->assertUnprocessable();
        $this->assertNull(DB::table('users')->where('id',1)->value('image'));
    }
}
