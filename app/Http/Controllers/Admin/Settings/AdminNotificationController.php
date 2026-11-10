<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminNotificationController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['audience' => 'nullable|in:customer,vendor', 'q' => 'nullable|string|max:100']);
        $audience = $data['audience'] ?? 'customer';
        $q = trim($data['q'] ?? '');
        $accounts = $q === '' ? collect() : $this->accounts($audience)->where(function ($query) use ($q) {
            $query->where('f_name', 'like', "%{$q}%")->orWhere('l_name', 'like', "%{$q}%")
                ->orWhere('phone', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%");
        })->limit(30)->get(['id', 'f_name', 'l_name', 'phone', 'email']);
        $batches = DB::table('admin_notification_batches')->orderByDesc('id')->limit(20)->get();
        $outcomes = DB::table('admin_notification_recipients')->whereIn('batch_id', $batches->pluck('id'))
            ->groupBy('batch_id', 'status')->selectRaw('batch_id, status, COUNT(*) as total')->get()
            ->groupBy('batch_id');
        return view('admin-views.business-settings.notifications', [
            'audience' => $audience, 'q' => $q, 'accounts' => $accounts,
            'preview' => $request->session()->get('admin_notification_preview'),
            'batches' => $batches, 'outcomes' => $outcomes,
        ]);
    }

    public function preview(Request $request)
    {
        $data = $request->validate(['audience' => 'required|in:customer,vendor',
            'scope' => 'required|in:selected,all', 'account_ids' => 'required_if:scope,selected|array|max:500',
            'account_ids.*' => 'integer|distinct', 'title' => 'required|string|min:3|max:150',
            'body' => 'required|string|min:5|max:2000']);
        $ids = $data['scope'] === 'all' ? $this->accounts($data['audience'])->orderBy('id')->pluck('id')->all()
            : array_map('intval', $data['account_ids'] ?? []);
        if ($ids === [] || $this->accounts($data['audience'])->whereIn('id', $ids)->count() !== count($ids)) {
            throw ValidationException::withMessages(['account_ids' => 'حدد حسابات موجودة من الجمهور الصحيح.']);
        }
        $preview = ['key' => (string) Str::uuid(), 'audience' => $data['audience'],
            'scope' => $data['scope'], 'ids' => $ids, 'title' => trim($data['title']),
            'body' => trim($data['body']), 'created_at' => now()->timestamp];
        $request->session()->put('admin_notification_preview', $preview);
        return redirect()->route('admin.settings.notifications.index', ['audience' => $data['audience']]);
    }

    public function send(Request $request)
    {
        $data = $request->validate(['key' => 'required|uuid']);
        $preview = $request->session()->get('admin_notification_preview');
        abort_unless($preview && hash_equals($preview['key'], $data['key'])
            && now()->timestamp - $preview['created_at'] <= 900, 422);
        $batchId = DB::transaction(function () use ($preview) {
            $inserted = DB::table('admin_notification_batches')->insertOrIgnore([
                'request_key' => $preview['key'], 'admin_id' => auth('admin')->id(),
                'audience' => $preview['audience'], 'scope' => $preview['scope'],
                'title' => $preview['title'], 'body' => $preview['body'],
                'recipient_count' => count($preview['ids']), 'status' => 'queued',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $batch = DB::table('admin_notification_batches')->where('request_key', $preview['key'])->first();
            if (!$inserted) return $batch->id;
            foreach (array_chunk($preview['ids'], 500) as $ids) {
                DB::table('admin_notification_recipients')->insert(array_map(fn ($id) => [
                    'batch_id' => $batch->id, 'account_id' => $id, 'status' => 'pending',
                    'created_at' => now(), 'updated_at' => now(),
                ], $ids));
            }
            return $batch->id;
        }, 3);
        $request->session()->forget('admin_notification_preview');
        return redirect()->route('admin.settings.notifications.index')
            ->with('success', 'أُدرجت الإشعارات للإرسال إلى الجمهور المؤكد. رقم الدفعة #'.$batchId);
    }

    private function accounts(string $audience)
    {
        return $audience === 'vendor' ? Seller::query()
            : User::query()->whereNull('account_deleted_at')
                ->where(fn ($q) => $q->whereNull('email')->orWhere('email', '!=', 'walking@customer.com'));
    }
}
