<?php
namespace Tests\Unit;
use App\Http\Controllers\Web\UserProfileController;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
class SigmaCareSupportDrawerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        session()->put('local', 'en');
        Schema::create('business_settings', function(Blueprint $t) { $t->increments('id'); $t->string('key'); $t->text('value')->nullable(); });
        DB::table('business_settings')->insert(['key'=>'language','value'=>'[{"code":"en","name":"English","status":1,"default":true}]']);
        Schema::create('support_tickets', function(Blueprint $t) {
            $t->increments('id');$t->string('subject');$t->string('type');$t->integer('customer_id')->nullable();$t->string('priority');$t->text('description');$t->text('attachment');$t->string('purpose')->nullable();$t->string('review_status')->nullable();$t->timestamps();
        });
    }
    private function request(array $changes=[]): Request
    {
        return Request::create('/ticket-submit','POST',$changes+['care_support_drawer'=>1,'ticket_subject'=>'Delivery question','ticket_type'=>'Info inquiry','ticket_priority'=>'Medium','ticket_description'=>'Please help with my order.']);
    }
    public function test_authenticated_ticket_reaches_existing_admin_queue_with_followup_link(): void
    {
        $user=new User;$user->id=27;$this->actingAs($user,'customer');
        $result=app(UserProfileController::class)->submitSupportTicket($this->request());
        $this->assertSame(201,$result->getStatusCode());
        $ticket=DB::table('support_tickets')->first();
        $this->assertSame(27,$ticket->customer_id);
        $this->assertSame('Please help with my order.',$ticket->subject);
        $this->assertSame('Website problem',$ticket->type);
        $this->assertSame('Medium',$ticket->priority);
        $this->assertSame($ticket->id,$result->getData()->ticket_id);
        $this->assertSame(route('support-ticket.index',$ticket->id),$result->getData()->url);
    }
    public function test_guest_cannot_submit_a_drawer_ticket(): void
    {
        try {app(UserProfileController::class)->submitSupportTicket($this->request());$this->fail('Guest accepted');}
        catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(401,$e->getStatusCode());}
        $this->assertSame(0,DB::table('support_tickets')->count());
    }
    public function test_bad_ticket_type_and_oversized_content_never_create_a_ticket(): void
    {
        $user=new User;$user->id=27;$this->actingAs($user,'customer');
        try {app(UserProfileController::class)->submitSupportTicket($this->request(['ticket_type'=>'account_activation','ticket_subject'=>str_repeat('x',161),'ticket_description'=>str_repeat('x',5001)]));$this->fail('Invalid accepted');}
        catch(ValidationException $e){$this->assertArrayHasKey('ticket_description',$e->errors());}
        $this->assertSame(0,DB::table('support_tickets')->count());
    }

    public function test_report_cannot_inject_activation_type_or_priority(): void
    {
        $user=new User;$user->id=27;$this->actingAs($user,'customer');
        app(UserProfileController::class)->submitSupportTicket($this->request(['ticket_type'=>'account_activation','ticket_priority'=>'Urgent']));
        $this->assertNull(DB::table('support_tickets')->value('purpose'));
        $this->assertSame('Medium',DB::table('support_tickets')->value('priority'));
    }
}
