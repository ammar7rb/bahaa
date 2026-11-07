<?php

namespace App\Services;

use App\Models\AccountActivationCase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ActivationWorkspaceService
{
    public function summaries(Collection $cases, int $adminId): Collection
    {
        $reads = DB::table('activation_conversation_reads')->where('admin_id', $adminId)
            ->whereIn('account_activation_case_id', $cases->pluck('id'))->get()->keyBy('account_activation_case_id');

        $latest = collect();
        $unreadCounts = collect();
        foreach (['support_ticket', 'seller_activation_ticket'] as $source) {
            $group = $cases->where('source_type', $source);
            if ($group->isEmpty()) continue;
            $customer = $source === 'support_ticket';
            $table = $customer ? 'support_ticket_convs' : 'seller_activation_ticket_messages';
            $foreignKey = $customer ? 'support_ticket_id' : 'seller_activation_ticket_id';
            $ids = $group->pluck('source_id');
            $lastIds = DB::table($table)->selectRaw('MAX(id)')->whereIn($foreignKey, $ids)->groupBy($foreignKey);
            $latest[$source] = DB::table($table)->whereIn('id', $lastIds)->get()->keyBy($foreignKey);
            $incoming = DB::table($table)->whereIn($foreignKey, $ids);
            $customer ? $incoming->whereNull('admin_id') : $incoming->where('sender_type', 'seller');
            $incoming->where(function ($q) use ($group, $foreignKey, $reads) {
                foreach ($group as $case) {
                    $q->orWhere(fn ($row) => $row->where($foreignKey, $case->source_id)
                        ->where('id', '>', (int) ($reads->get($case->id)?->last_message_id ?? 0)));
                }
            });
            $unreadCounts[$source] = $incoming->selectRaw($foreignKey.', COUNT(*) as unread')->groupBy($foreignKey)->get()->keyBy($foreignKey);
        }
        $tickets = DB::table('support_tickets')->whereIn('id', $cases->where('source_type', 'support_ticket')->pluck('source_id'))->get()->keyBy('id');

        return $cases->mapWithKeys(function (AccountActivationCase $case) use ($reads, $latest, $unreadCounts, $tickets) {
            $read = $reads->get($case->id);
            $row = $latest->get($case->source_type)?->get($case->source_id);
            $ticket = $case->source_type === 'support_ticket' ? $tickets->get($case->source_id) : null;
            $last = $row ? ['body' => $row->body ?? $row->admin_message ?? $row->customer_message ?? '', 'created_at' => $row->created_at]
                : ($ticket && $ticket->description ? ['body' => $ticket->description, 'created_at' => $ticket->created_at] : null);
            $unread = (int) ($unreadCounts->get($case->source_type)?->get($case->source_id)?->unread ?? 0);
            if (!$read && $ticket?->description) $unread++;
            return [$case->id => ['last' => $last, 'unread' => $unread]];
        });
    }

    public function markRead(AccountActivationCase $case, int $adminId, int $messageId): void
    {
        DB::transaction(function () use ($case, $adminId, $messageId) {
            AccountActivationCase::query()->whereKey($case->id)->lockForUpdate()->firstOrFail();
            $query = DB::table('activation_conversation_reads')->where('account_activation_case_id', $case->id)->where('admin_id', $adminId);
            $previous = $query->first();
            DB::table('activation_conversation_reads')->updateOrInsert([
                'account_activation_case_id' => $case->id, 'admin_id' => $adminId,
            ], [
                'last_message_id' => max((int) ($previous->last_message_id ?? 0), $messageId),
                'created_at' => $previous->created_at ?? now(), 'updated_at' => now(),
            ]);
        });
    }
}
