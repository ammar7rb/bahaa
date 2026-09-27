<?php
namespace Tests\Unit;

use App\Models\Contact;
use App\Services\ContactPasswordResetService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContactPasswordResetTest extends TestCase
{
    public function test_admin_can_reset_each_account_type_and_deliver_an_encrypted_message(): void
    {
        Schema::create('business_settings', function (Blueprint $t) { $t->id(); $t->string('type'); $t->text('value')->nullable(); });
        session()->put('local', 'en');
        foreach (['users','sellers'] as $table) Schema::create($table, function (Blueprint $t) {
            $t->id(); $t->string('email'); $t->string('password'); $t->string('remember_token')->nullable(); $t->timestamps();
        });
        Schema::create('contact_conversation_messages', function (Blueprint $t) {
            $t->id(); $t->integer('contact_id'); $t->string('sender'); $t->text('body'); $t->timestamps();
        });
        try {
            foreach (['users','sellers'] as $table) DB::table($table)->insert(['email'=>'test@example.com','password'=>Hash::make('OldPassword123')]);
            $contact=new Contact(['email'=>'test@example.com']); $contact->id=99;
            $service=new ContactPasswordResetService();
            $service->reset($contact,'seller','SellerPassword123',1);
            $this->assertTrue(Hash::check('SellerPassword123',DB::table('sellers')->value('password')));
            $this->assertTrue(Hash::check('OldPassword123',DB::table('users')->value('password')));
            $message=DB::table('contact_conversation_messages')->first();
            $this->assertStringNotContainsString('SellerPassword123',$message->body);
            $this->assertStringContainsString('SellerPassword123',$service::displayBody($message));
            $service->reset($contact,'customer','CustomerPassword123',1);
            $this->assertTrue(Hash::check('CustomerPassword123',DB::table('users')->value('password')));
            $this->assertNotEmpty(DB::table('users')->value('remember_token'));
            $this->assertSame(2,DB::table('contact_conversation_messages')->count());
        } finally {
            foreach (['contact_conversation_messages','users','sellers','business_settings'] as $table) Schema::dropIfExists($table);
        }
    }
}
