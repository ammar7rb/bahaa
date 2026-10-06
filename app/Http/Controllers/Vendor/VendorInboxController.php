<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Services\VendorInboxService;
use Illuminate\Http\Request;

class VendorInboxController extends Controller
{
    public function counts(Request $request, VendorInboxService $service)
    {
        $vendorInbox = $service->snapshot(auth('seller')->user());
        $result = $vendorInbox['counts'];
        foreach (['messages', 'notifications'] as $channel) {
            $result['previews'][$channel] = view('layouts.vendor.partials.v2._inbox-previews', compact('vendorInbox', 'channel'))->render();
        }
        return response()->json($result)->header('Cache-Control', 'private, no-store');
    }
    public function index(Request $request, VendorInboxService $service)
    {
        $seller = $request->seller ?? auth('seller')->user();
        abort_unless($seller, 401);
        $data = $service->snapshot($seller);
        if ($request->is('api/*')) return response()->json($data)->header('Cache-Control', 'private, no-store');
        $kind = $request->routeIs('vendor.messages') ? 'messages' : 'notifications';
        return view('vendor-views.account.inbox', compact('data', 'kind'));
    }

    public function read(Request $request, VendorInboxService $service)
    {
        $input = $request->validate(['kind' => 'required|in:message,notification,order_alert', 'id' => 'required|string|max:100']);
        $seller = $request->seller ?? auth('seller')->user();
        abort_unless($seller, 401);
        $service->read($seller, $input['kind'], $input['id']);
        return $request->is('api/*') ? response()->json(['counts' => $service->snapshot($seller)['counts']]) : back();
    }

    public function thread(Request $request, int $thread, VendorInboxService $service)
    {
        $seller = $request->seller ?? auth('seller')->user();
        abort_unless($seller, 401);
        $data = $service->thread($seller, $thread);
        return $request->is('api/*') ? response()->json($data)->header('Cache-Control', 'private, no-store')
            : view('vendor-views.account.thread', compact('data'));
    }
}
