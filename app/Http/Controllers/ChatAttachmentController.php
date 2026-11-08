<?php

namespace App\Http\Controllers;

use App\Models\AccountActivationCase;
use App\Models\SupportTicket;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatAttachmentController extends Controller
{
    public function customer(int $id, string $message, int $index): StreamedResponse
    {
        return $this->customerAttachment((int) auth('customer')->id(), $id, $message, $index);
    }

    public function customerApi(\Illuminate\Http\Request $request, int $id, string $message, int $index): StreamedResponse
    {
        return $this->customerAttachment((int) $request->user()->id, $id, $message, $index);
    }

    private function customerAttachment(int $customerId, int $id, string $message, int $index): StreamedResponse
    {
        $ticket = SupportTicket::query()->visibleToSubject()->whereKey($id)
            ->where('customer_id', $customerId)->firstOrFail();
        $attachments = $message === 'initial' ? $ticket->attachment
            : $ticket->conversations()->whereNull('customer_hidden_at')->whereKey($message)->firstOrFail()->attachment;

        $item = is_array($attachments) ? (array_values($attachments)[$index] ?? null) : null;
        if (is_array($item) && ($item['storage'] ?? null) === 'medical-report') {
            $purchase = \App\Models\MedicalDeviceReportPurchase::whereKey($item['medical_report_purchase_id'] ?? 0)
                ->where('customer_id', $ticket->customer_id)->firstOrFail();
            $inquiry = \App\Models\MedicalDeviceInquiry::whereKey($purchase->inquiry_id)->where('support_ticket_id', $ticket->id)->firstOrFail();
            $filename = (string) ($item['file_name'] ?? '');
            abort_unless(preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._-]*\z/', $filename), 404);
            $path = 'medical-device-reports/'.$filename;
            abort_unless(Storage::disk('local')->exists($path), 404);
            $name = basename(str_replace('\\', '/', (string) ($item['original_name'] ?? $filename)));
            return Storage::disk('local')->download($path, $name, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
        }

        return $this->download($attachments, $index, 'support-ticket');
    }

    public function activation(AccountActivationCase $activationCase, string $message, int $index): StreamedResponse
    {
        abort_unless(app(\App\Services\WorkflowFeatureService::class)->activationCenterEnabled(), 404);
        if ($activationCase->source_type === 'support_ticket') {
            $row = $message === 'initial'
                ? DB::table('support_tickets')->where('id', $activationCase->source_id)->first()
                : DB::table('support_ticket_convs')->where('support_ticket_id', $activationCase->source_id)->where('id', $message)->first();
            abort_unless($row, 404);
            return $this->download($row->attachment ?? [], $index, 'support-ticket');
        }
        abort_unless($activationCase->source_type === 'seller_activation_ticket' && ctype_digit($message), 404);
        $row = DB::table('seller_activation_ticket_messages')->where('seller_activation_ticket_id', $activationCase->source_id)
            ->where('id', $message)->first();
        abort_unless($row, 404);
        return $this->download($row->attachments ?? [], $index, 'seller-activation-ticket');
    }

    private function download(mixed $attachments, int $index, string $directory): StreamedResponse
    {
        if (is_string($attachments)) {
            $decoded = json_decode($attachments, true);
            $attachments = json_last_error() === JSON_ERROR_NONE ? $decoded : [$attachments];
        }
        abort_unless(is_array($attachments) && $index >= 0, 404);
        if (isset($attachments['file_name'])) $attachments = [$attachments];
        $item = array_values($attachments)[$index] ?? null;
        abort_if($item === null, 404);
        $item = is_array($item) ? $item : ['file_name' => $item];
        $filename = (string) ($item['file_name'] ?? '');
        // Only actual message files in their fixed directory; never a supplied URL or private evidence path.
        abort_unless(preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._-]*\z/', $filename), 404);
        $disk = (string) ($item['storage'] ?? 'public');
        abort_unless(in_array($disk, ['public', 's3'], true), 404);
        $path = $directory.'/'.$filename;
        abort_unless(Storage::disk($disk)->exists($path), 404);
        $name = basename(str_replace('\\', '/', (string) ($item['original_name'] ?? $filename)));
        return Storage::disk($disk)->download($path, $name, [
            'Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
