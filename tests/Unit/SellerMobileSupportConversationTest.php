<?php
namespace Tests\Unit;
use App\Http\Controllers\RestAPI\v3\seller\auth\SupportConversationController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SellerMobileSupportConversationTest extends TestCase
{
    protected function setUp(): void {
        parent::setUp();
        Schema::create('contacts', function (Blueprint $t) { $t->id(); $t->string('mobile_support_token_hash')->nullable()->unique(); $t->string('name'); $t->string('email'); $t->string('mobile_number'); $t->string('subject'); $t->text('message'); $t->boolean('seen'); $t->timestamps(); });
        Schema::create('contact_conversation_messages', function (Blueprint $t) { $t->id(); $t->integer('contact_id'); $t->string('sender'); $t->text('body'); $t->timestamps(); });
    }
    protected function tearDown(): void { Schema::dropIfExists('contact_conversation_messages'); Schema::dropIfExists('contacts'); parent::tearDown(); }
    private function request(string $token, array $data = []): Request { $request = Request::create('/', 'POST', $data); $request->headers->set('X-Support-Token', $token); return $request; }
    public function test_device_can_resume_its_own_ticket_without_login_and_creation_is_idempotent(): void {
        $controller = new SupportConversationController(); $token = str_repeat('a',64);
        $request = $this->request($token, ['name'=>'Seller', 'email'=>'test@example.com', 'mobile_number'=>'+201000000000', 'message'=>'Reset my password']);
        $first = $controller->create($request)->getData(true); $second = $controller->create($request)->getData(true);
        self::assertSame($first['id'], $second['id']); self::assertSame(1, DB::table('contacts')->count());
        self::assertNotSame($token, DB::table('contacts')->value('mobile_support_token_hash'));
        DB::table('contact_conversation_messages')->insert(['contact_id'=>$first['id'], 'sender'=>'admin', 'body'=>'Please verify ownership']);
        $reply = $controller->reply($this->request($token, ['message'=>'Here are my details']));
        self::assertCount(2, $reply->getData(true)['messages']); self::assertStringContainsString('no-store', $reply->headers->get('Cache-Control'));
    }
    public function test_another_device_cannot_read_ticket_by_knowing_email(): void {
        $controller = new SupportConversationController();
        $controller->create($this->request(str_repeat('a',64), ['name'=>'Seller','email'=>'test@example.com','mobile_number'=>'12345','message'=>'Help']));
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $controller->show($this->request(str_repeat('b',64), ['email'=>'test@example.com']));
    }
}
