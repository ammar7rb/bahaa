<?php
namespace Tests\Unit;

use App\Http\Controllers\Customer\NotificationCenterController;
use App\Http\Controllers\Customer\SupportConversationController;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CustomerNotificationCenterTest extends TestCase
{
    public function test_notifications_include_admin_broadcast_and_own_notices_only(): void
    {
        Schema::create('notifications',function(Blueprint $t){$t->id();$t->unsignedBigInteger('customer_id')->nullable();$t->string('sent_to');$t->string('sent_by');$t->string('title');$t->text('description');$t->integer('status');$t->timestamps();});
        $user=new User();$user->id=1;auth('customer')->setUser($user);
        foreach([[null,'customer','admin',1],[1,'customer','admin',1],[2,'customer','admin',1],[null,'seller','admin',1],[1,'customer','admin',0],[1,'customer','seller',1],[null,'customer','system',1]] as $row)
            DB::table('notifications')->insert(array_combine(['customer_id','sent_to','sent_by','status'],$row)+['title'=>'Notice','description'=>'Message','created_at'=>now(),'updated_at'=>now()]);
        $data=app(NotificationCenterController::class)->index()->getData();
        $this->assertSame([7,2,1],$data['notifications']->pluck('id')->all());
        foreach(['customer.notifications.index','customer.messages.index','account-tickets'] as $name)
            $this->assertContains('customer',app('router')->getRoutes()->getByName($name)->gatherMiddleware());
    }

    public function test_support_message_list_preserves_own_history_and_excludes_other_customer(): void
    {
        Schema::create('support_tickets',function(Blueprint $t){$t->id();$t->unsignedBigInteger('customer_id');$t->string('subject');$t->text('description');$t->string('status');$t->timestamp('hidden_from_subject_at')->nullable();$t->timestamps();});
        $user=new User();$user->id=1;auth('customer')->setUser($user);
        foreach([[1,'closed',null],[1,'open',null],[2,'open',null],[1,'open',now()]] as $row)
            DB::table('support_tickets')->insert(['customer_id'=>$row[0],'status'=>$row[1],'hidden_from_subject_at'=>$row[2],'subject'=>'Support','description'=>'History','created_at'=>now(),'updated_at'=>now()]);
        $ids=app(SupportConversationController::class)->index()->getData()['conversations']->pluck('id')->sort()->values()->all();
        $this->assertSame([1,2],$ids);
    }
}
