<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\CustomerCommunicationService;
use Illuminate\Http\Request;

class CommunicationController extends Controller
{
    public function index(Request $request, CustomerCommunicationService $service)
    {
        $id = (int) ($request->is('api/*') ? $request->user('api')?->id : auth('customer')->id());
        abort_unless($id, 401);
        $data = $request->validate(['type' => 'nullable|in:messages,notifications', 'page' => 'nullable|integer|min:1|max:100000']);
        $notifications = $service->notifications($id);
        $messages = $service->messages($id);
        if (isset($data['type'])) {
            $records = ($data['type'] === 'messages' ? $messages : $notifications)->paginate(20, ['*'], 'page', $data['page'] ?? 1);
            return response()->json(['items' => $records->getCollection()->map(fn ($item) => $data['type'] === 'messages'
                ? $service->messageItem($item) : $service->notificationItem($item, $id))->values(),
                'current_page' => $records->currentPage(), 'last_page' => $records->lastPage(), 'total' => $records->total()])
                ->header('Cache-Control', 'private, no-store');
        }
        return response()->json([
            'notifications' => ['total' => (clone $notifications)->count(),
                'items' => $notifications->limit(5)->get()->map(fn ($item) => $service->notificationItem($item, $id))->values()],
            'messages' => ['total' => (clone $messages)->count(),
                'items' => $messages->limit(5)->get()->map(fn ($item) => $service->messageItem($item))->values()],
        ])->header('Cache-Control', 'private, no-store');
    }
}
