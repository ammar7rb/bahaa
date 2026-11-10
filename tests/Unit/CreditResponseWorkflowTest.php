<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\Product\CreditManagementController;
use App\Models\Admin;
use App\Models\CreditRequest;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CreditResponseWorkflowTest extends TestCase
{
    public function test_response_records_author_and_communication_once(): void
    {
        (require base_path('database/migrations/2026_11_10_000002_create_credit_requests.php'))->up();
        Schema::create('support_ticket_convs', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('support_ticket_id');
            $table->unsignedBigInteger('admin_id'); $table->string('admin_message', 191); $table->timestamps();
        });
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('customer_id'); $table->string('subject');
        });
        DB::table('support_tickets')->insert(['id' => 7, 'customer_id' => 3, 'subject' => 'Credit product']);
        Schema::create('notifications', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('customer_id'); $table->string('customer_event_key')->unique();
            foreach (['sent_by','sent_to','title','description','image'] as $name) { $table->text($name); }
            $table->integer('status'); $table->integer('notification_count'); $table->timestamps();
        });
        $admin = new Admin(); $admin->forceFill(['id' => 9]);
        $this->actingAs($admin, 'admin');
        $credit = CreditRequest::create([
            'reference' => 'CR-TEST', 'customer_id' => 3, 'product_id' => 1, 'support_ticket_id' => 7,
            'product_code' => 'A-1', 'requested_product_price' => 100, 'status' => 'submitted', 'active_key' => '3:1',
        ]);
        $credit->setRelation('customer', null);
        $request = Request::create('/admin/products/credit-requests/1/respond', 'POST', ['response' => 'تمت مراجعة طلبك']);
        $controller = app(CreditManagementController::class);
        $controller->respond($request, $credit);
        $this->assertSame('responded', $credit->fresh()->status);
        $this->assertEquals(9, $credit->fresh()->responded_by_admin_id);
        $this->assertNull($credit->fresh()->active_key);
        $this->assertSame(1, DB::table('support_ticket_convs')->where('admin_id', 9)->count());
        $this->assertSame(1, DB::table('notifications')->where('customer_id', 3)->count());
        try {
            $controller->respond($request, $credit);
            $this->fail('Duplicate processing must be rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        $this->assertSame(1, DB::table('notifications')->count());
        Schema::dropIfExists('notifications'); Schema::dropIfExists('support_tickets'); Schema::dropIfExists('support_ticket_convs'); Schema::dropIfExists('credit_requests');
    }
}
