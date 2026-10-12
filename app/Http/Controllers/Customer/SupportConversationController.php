<?php
namespace App\Http\Controllers\Customer;
use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use Illuminate\Http\Request;
class SupportConversationController extends Controller
{
    public function index() {
        return view('customer-support.index', ['conversations' => SupportTicket::visibleToSubject()->where('customer_id', auth('customer')->id())->latest('updated_at')->get()]);
    }
    public function report(Request $request) {
        $data = $request->validate(['message' => 'required|string|min:3|max:5000']);
        $id = $request->is('api/*') ? $request->user('api')->id : auth('customer')->id();
        $record = SupportTicket::create(['customer_id' => $id, 'subject' => mb_substr(trim($data['message']), 0, 100),
            'type' => 'problem_report', 'priority' => 'normal', 'description' => trim($data['message']), 'status' => 'open', 'attachment' => []]);
        return $request->is('api/*') ? response()->json(['conversation' => $record], 201) : redirect()->route('support-ticket.index', $record->id);
    }
    public function show(Request $request, int $id) {
        $conversation = SupportTicket::with('conversations')->visibleToSubject()->where('customer_id', auth('customer')->id())->findOrFail($id);
        return view('customer-support.conversation', compact('conversation'));
    }
}
