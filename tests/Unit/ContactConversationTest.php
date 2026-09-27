<?php
namespace Tests\Unit;

use App\Http\Controllers\Web\ContactConversationController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ContactConversationTest extends TestCase
{
    protected function setUp(): void {
        parent::setUp();
        Schema::create('contacts', function(Blueprint $t) { $t->id(); $t->boolean('seen')->default(true); $t->timestamps(); });
        Schema::create('contact_conversation_messages', function(Blueprint $t) { $t->id(); $t->integer('contact_id'); $t->string('sender'); $t->text('body'); $t->timestamps(); });
        DB::table('contacts')->insert(['id'=>1]);
    }
    protected function tearDown(): void {
        Schema::dropIfExists('contact_conversation_messages'); Schema::dropIfExists('contacts'); parent::tearDown();
    }
    public function test_requester_stays_in_conversation_and_receives_admin_reply(): void {
        $session=app('session')->driver(); $session->put('contact_conversation_ids',[1]);
        $request=Request::create('/contact/conversation/1','POST',['message'=>'متابعة طلبي']); $request->setLaravelSession($session);
        $controller=new ContactConversationController();
        $response=$controller->reply($request,1);
        $this->assertSame(route('contact.conversation',1),$response->getTargetUrl());
        $this->assertSame('متابعة طلبي',DB::table('contact_conversation_messages')->value('body'));
        $this->assertEquals(0,DB::table('contacts')->value('seen'));
        DB::table('contact_conversation_messages')->insert(['contact_id'=>1,'sender'=>'admin','body'=>'رد الدعم']);
        $request->headers->set('Accept','application/json');
        $messages=$controller->show($request,1)->getData(true)['messages'];
        $this->assertCount(2,$messages);
        $this->assertSame('رد الدعم',$messages[1]['body']);
    }
    public function test_other_session_cannot_read_or_reply_using_contact_id(): void {
        $session=app('session')->driver(); $session->put('contact_conversation_ids',[]);
        $request=Request::create('/contact/conversation/1','POST',['message'=>'unauthorized']); $request->setLaravelSession($session);
        foreach(['show','reply'] as $action) {
            try { (new ContactConversationController())->$action($request,1); $this->fail('Unauthorized access allowed'); }
            catch(HttpException $e) { $this->assertSame(404,$e->getStatusCode()); }
        }
        $this->assertSame(0,DB::table('contact_conversation_messages')->count());
    }
}
