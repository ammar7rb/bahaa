<?php

namespace App\Services;

use App\Utils\Helpers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Read existing events directly. Rendering and polling never insert notifications. */
class AdminNotificationInbox
{
    public const CATEGORIES = ['vendor_deposits', 'customer_deposits', 'medical', 'vendor_messages', 'customer_messages', 'support'];

    public function items(): \Illuminate\Support\Collection
    {
        abort_unless(auth('admin')->check(), 403);
        $items = collect();
        $workspace = app(AdminWorkspaceService::class);
        if (Helpers::module_permission_check('people')) {
            foreach (['vendor' => 'seller_balance_deposits', 'customer' => 'customer_balance_deposits'] as $audience => $table) {
                if (!Schema::hasTable($table)) continue;
                $q = DB::table($table);
                if ($audience === 'vendor') $q->where(fn ($q) => $q->whereNull('metadata->wallet_target')->orWhere('metadata->wallet_target', '!=', 'insurance')->orWhereNotNull('metadata->insurance_retirement'));
                else $q->where('wallet_type', 'purchase');
                $rows = $q->get();
                $foreign = $audience === 'vendor' ? 'seller_id' : 'customer_id';
                $actors = DB::table($audience === 'vendor' ? 'sellers' : 'users')->whereIn('id', $rows->pluck($foreign)->unique())->get(['id','f_name','l_name'])->keyBy('id');
                foreach ($rows as $row) {
                    $actorId = $audience === 'vendor' ? $row->seller_id : $row->customer_id;
                    $actor = $actors->get($actorId);
                    $items->push($this->item("deposit:$audience:$row->id", $audience.'_deposits', translate('inbox_'.$audience.'_deposits'), trim(($actor->f_name ?? '').' '.($actor->l_name ?? '')).' · '.translate($audience).' #'.$actorId, number_format($row->amount, 2).' '.($row->currency_code ?? '').' · '.translate($row->status), $row->created_at, $workspace->paymentUrl($table, $row), 'credit-card'));
                }
            }
        }
        if (Helpers::module_permission_check('people') || Helpers::module_permission_check('catalog')) {
            // One item per inbound source message; admin replies cannot create an event.
            $sources = [
                ['contacts', 'contact', 'message', null, null],
                ['contact_conversation_messages', 'contact', 'body', 'contact_id', ['sender' => 'requester']],
                ['support_tickets', 'ticket', 'description', null, null],
                ['support_ticket_convs', 'ticket', 'customer_message', 'support_ticket_id', null],
                ['vendor_support_messages', 'vendor', 'body', 'vendor_support_thread_id', null],
                ['chattings', 'chat', 'message', 'user_id', ['admin_id' => 0, 'sent_by_customer' => 1]],
            ];
            $conversations = $workspace->conversations()->get()->keyBy(fn ($r) => "$r->kind:$r->id");
            $inquiries = Schema::hasTable('medical_device_inquiries') ? DB::table('medical_device_inquiries')->get()->keyBy('support_ticket_id') : collect();
            foreach ($sources as [$table, $kind, $body, $foreign, $filters]) {
                if (!Schema::hasTable($table)) continue;
                $q = DB::table($table)->whereNotNull($body);
                if ($filters) $q->where($filters);
                if ($table === 'vendor_support_messages') $q->where(fn ($q) => $q->whereNull('admin_id')->orWhere('admin_id', 0));
                // Some channels store the initial message in both the parent and message table.
                if ($table === 'contact_conversation_messages') $q->whereNotExists(fn ($q) => $q->selectRaw('1')->from('contacts as original')->whereColumn('original.id', 'contact_conversation_messages.contact_id')->whereColumn('original.message', 'contact_conversation_messages.body')->whereRaw("contact_conversation_messages.id = (SELECT MIN(first_message.id) FROM contact_conversation_messages first_message WHERE first_message.contact_id = original.id AND first_message.sender = 'requester')"));
                if ($table === 'support_ticket_convs') $q->whereNotExists(fn ($q) => $q->selectRaw('1')->from('support_tickets as original')->whereColumn('original.id', 'support_ticket_convs.support_ticket_id')->whereColumn('original.description', 'support_ticket_convs.customer_message')->whereRaw('support_ticket_convs.id = (SELECT MIN(first_message.id) FROM support_ticket_convs first_message WHERE first_message.support_ticket_id = original.id AND first_message.customer_message IS NOT NULL)'));
                foreach ($q->get() as $message) {
                    $id = $foreign ? $message->$foreign : $message->id;
                    $conversation = $conversations->get("$kind:$id");
                    if (!$conversation) continue; // excludes activation conversations
                    $inquiry = $kind === 'ticket' ? $inquiries->get($id) : null;
                    if ($inquiry && !Helpers::module_permission_check('catalog')) continue;
                    if (!$inquiry && !Helpers::module_permission_check('people')) continue;
                    $category = $inquiry ? 'medical' : ($kind === 'contact' || $kind === 'ticket' ? 'support' : $conversation->audience.'_messages');
                    if (!$inquiry && $conversation->audience === 'vendor') $category = 'vendor_messages';
                    $url = $inquiry ? route('admin.operations.medical-devices.inquiry', $inquiry->id) : $workspace->conversationUrl($conversation);
                    $items->push($this->item("$table:$message->id", $category, $inquiry ? $inquiry->device_name.' #'.$inquiry->device_id : ($conversation->subject ?: translate('messages')), $conversation->sender.' · '.translate($conversation->audience).($inquiry ? ' #'.$inquiry->customer_id : ''), $message->$body, $message->created_at ?? $conversation->updated_at, $url, $inquiry ? 'stethoscope' : 'comment'));
                }
            }
        }
        $read = Schema::hasTable('admin_notification_reads') ? DB::table('admin_notification_reads')->where('admin_id', auth('admin')->id())->pluck('event_key')->flip() : collect();
        return $items->unique('key')->map(fn ($item) => $item + ['unread' => !$read->has($item['key'])])->sortByDesc('time')->values();
    }

    private function item(string $key, string $category, string $title, string $detail, ?string $preview, $time, string $url, string $icon): array
    {
        return compact('key', 'category', 'title', 'detail', 'url', 'icon') + ['badge' => translate('inbox_'.$category), 'preview' => \Illuminate\Support\Str::limit(strip_tags($preview ?? ''), 120), 'time' => $time ? \Carbon\Carbon::parse($time)->toIso8601String() : null];
    }

    public function snapshot(?string $category = null): array
    {
        $items = $this->items();
        return ['count' => $items->where('unread', true)->count(), 'items' => ($category ? $items->where('category', $category) : $items)->take(20)->values()->all()];
    }
}
