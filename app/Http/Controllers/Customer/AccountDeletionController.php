<?php
namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\CustomerAccountDeletionService;
use Illuminate\Http\Request;

class AccountDeletionController extends Controller
{
    private function id(Request $r): int { return (int) ($r->is('api/*') ? $r->user('api')->id : auth('customer')->id()); }
    public function show() { return view('customer-account.delete', ['stage' => 1, 'token' => null]); }
    public function begin(Request $r, CustomerAccountDeletionService $service) {
        try {
        $data = $r->validate(['password' => 'required|string|max:128']);
        $token = $service->begin($this->id($r), $data['password']);
        return $r->is('api/*') ? response()->json(['flow_token' => $token, 'stage' => 2]) : view('customer-account.delete', ['stage' => 2, 'token' => $token]);
        } catch (\Illuminate\Validation\ValidationException $e) { return $this->error($r, 1, null, $e); }
    }
    public function reason(Request $r, CustomerAccountDeletionService $service) {
        try {
        $data = $r->validate(['flow_token' => 'required|string|size:64', 'reason' => 'required|string|max:5000']);
        $service->reason($this->id($r), $data['flow_token'], $data['reason']);
        return $r->is('api/*') ? response()->json(['stage' => 3]) : view('customer-account.delete', ['stage' => 3, 'token' => $data['flow_token']]);
        } catch (\Illuminate\Validation\ValidationException $e) { return $this->error($r, 2, $r->input('flow_token'), $e); }
    }
    public function finish(Request $r, CustomerAccountDeletionService $service) {
        try {
        $data = $r->validate(['flow_token' => 'required|string|size:64', 'password' => 'required|string|max:128']);
        $service->finish($this->id($r), $data['flow_token'], $data['password']);
        if ($r->is('api/*')) return response()->json(['deleted' => true]);
        auth('customer')->logout(); $r->session()->invalidate(); $r->session()->regenerateToken();
        return redirect()->route('home');
        } catch (\Illuminate\Validation\ValidationException $e) { return $this->error($r, 3, $r->input('flow_token'), $e); }
    }

    private function error(Request $r, int $stage, ?string $token, \Illuminate\Validation\ValidationException $e) {
        if ($r->is('api/*')) throw $e;
        $errors = (new \Illuminate\Support\ViewErrorBag())->put('default', new \Illuminate\Support\MessageBag($e->errors()));
        return response()->view('customer-account.delete', compact('stage','token','errors'), 422);
    }
}
