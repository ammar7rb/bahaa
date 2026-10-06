<?php
namespace App\Http\Controllers\Admin\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Models\VendorSupportThread;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VendorSupportController extends Controller
{
    public function index(Request $request)
    {
        $threads = VendorSupportThread::with('seller.shop')->latest('updated_at')->paginate(20);
        $sellers = Seller::select('id', 'f_name', 'l_name')->with('shop:id,seller_id,name')->orderBy('f_name')->get();
        return view('admin-views.vendor.support.index', compact('threads', 'sellers'));
    }
    public function store(Request $request)
    {
        $data = $request->validate(['seller_id' => 'required|integer|exists:sellers,id', 'subject' => 'required|string|max:191', 'body' => 'required|string|max:10000']);
        $thread = DB::transaction(function () use ($data) {
            $thread = VendorSupportThread::create(['seller_id' => $data['seller_id'], 'created_by_admin_id' => auth('admin')->id(), 'subject' => $data['subject']]);
            $thread->messages()->create(['admin_id' => auth('admin')->id(), 'body' => $data['body']]);
            return $thread;
        });
        return redirect()->route('admin.vendor-support.show', $thread);
    }
    public function show(VendorSupportThread $thread)
    {
        $thread->load(['seller.shop', 'messages']);
        return view('admin-views.vendor.support.show', compact('thread'));
    }
    public function reply(Request $request, VendorSupportThread $thread)
    {
        $data = $request->validate(['body' => 'required|string|max:10000']);
        DB::transaction(function () use ($thread, $data) {
            $locked = VendorSupportThread::lockForUpdate()->findOrFail($thread->id);
            abort_unless($locked->status === 'open', 422);
            $locked->messages()->create(['admin_id' => auth('admin')->id(), 'body' => $data['body']]);
            $locked->touch();
        });
        return back();
    }
    public function status(Request $request, VendorSupportThread $thread)
    {
        $data = $request->validate(['status' => 'required|in:open,closed']);
        DB::transaction(fn () => VendorSupportThread::lockForUpdate()->findOrFail($thread->id)->update($data));
        return back();
    }
}
