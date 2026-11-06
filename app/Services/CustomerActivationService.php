<?php
namespace App\Services;

use App\User as LegacyUser;
use App\Models\SupportTicket;
use App\Models\User;

/** Compatibility contract for older clients; account activation is retired. */
class CustomerActivationService
{
    public const PURPOSE = 'account_activation';
    public function __construct(private readonly WorkflowFeatureService $features) {}
    public function openOrCreateTicket(User|LegacyUser $customer): ?SupportTicket { return null; }
    public function ensureTicket(User|LegacyUser $customer): ?SupportTicket { return null; }
    public function status(User|LegacyUser $customer): array { return $this->payload($customer); }
    public function decide(SupportTicket $ticket, string $decision, ?string $note, int $adminId): SupportTicket { abort(410); }
    public function payload(User|LegacyUser $customer): array
    {
        return ['enabled'=>false, 'status'=>'active', 'is_active'=>true, 'activation_available'=>false, 'ticket_id'=>null,
            'banner'=>['visible'=>false, 'state'=>'not_required', 'message'=>'', 'action'=>null]];
    }
}
