<?php

namespace App\Services;

use App\Models\CompanyMedicalDevice;
use App\Models\MedicalDeviceInquiry;
use App\Models\MedicalDeviceReportPurchase;
use App\Models\SupportTicketConv;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class MedicalDeviceReportService
{
    public function pay(int $deviceId, int $customerId, float $quotedAmount): MedicalDeviceReportPurchase
    {
        return DB::transaction(function () use ($deviceId, $customerId, $quotedAmount) {
            $customer = User::lockForUpdate()->findOrFail($customerId);
            if (!$customer->is_active || $customer->account_deleted_at) $this->deny('الحساب موقوف. تواصل مع الإدارة.');
            $device = CompanyMedicalDevice::where('is_published', true)->lockForUpdate()->findOrFail($deviceId);
            $existing = MedicalDeviceReportPurchase::where('customer_id', $customerId)->where('device_id', $deviceId)->first();
            if ($existing) return $existing;
            $amount = round((float) $device->report_price, 3);
            if (!is_finite($amount) || $amount <= 0) $this->deny('سعر التقرير غير متاح حاليًا.');
            if (!is_finite($quotedAmount) || round($quotedAmount, 3) !== $amount) $this->deny('تغير سعر التقرير. راجع السعر الحالي قبل الدفع.');
            if (round((float) $customer->wallet_balance, 3) < $amount) $this->deny('رصيد المحفظة لا يكفي لسداد ثمن التقرير الفني.');
            $inquiry = app(MedicalDeviceInquiryService::class)->submit($deviceId, $customerId);
            if (!$inquiry->support_ticket_id || !$inquiry->conversation()->exists()) {
                $ticket = \App\Models\SupportTicket::create(['customer_id' => $customerId,
                    'subject' => \Illuminate\Support\Str::limit('تقرير فني: '.$device->name, 150, ''),
                    'description' => $device->name, 'type' => 'medical_device', 'purpose' => 'medical_device', 'priority' => 'low', 'status' => 'open']);
                $inquiry->update(['support_ticket_id' => $ticket->id]);
            }
            $inquiry->conversation()->update(['status' => 'open', 'closed_at' => null, 'archived_at' => null, 'hidden_from_subject_at' => null]);
            $balance = round((float) $customer->wallet_balance - $amount, 3);
            $customer->update(['wallet_balance' => $balance]);
            $reference = 'medical-report-'.$customerId.'-'.$deviceId;
            $receipt = WalletTransaction::create(['user_id' => $customerId, 'transaction_id' => $reference,
                'reference' => $reference, 'transaction_type' => 'medical_report', 'payment_method' => 'purchase_wallet',
                'credit' => 0, 'debit' => $amount, 'admin_bonus' => 0, 'balance' => $balance]);
            $purchase = MedicalDeviceReportPurchase::create(['device_id' => $deviceId, 'customer_id' => $customerId,
                'inquiry_id' => $inquiry->id, 'wallet_transaction_id' => $receipt->id, 'amount' => $amount]);
            app(CustomerPaymentNotificationService::class)->notify($customerId, 'medical-report-'.$purchase->id.'-paid', 'تم سداد ثمن التقرير الفني. سترسل الإدارة التقرير في محادثتك.');
            return $purchase;
        }, 3);
    }

    public function requestPurchase(int $deviceId, int $customerId): MedicalDeviceReportPurchase
    {
        return DB::transaction(function () use ($deviceId, $customerId) {
            $customer = User::lockForUpdate()->findOrFail($customerId);
            if (!$customer->is_active || $customer->account_deleted_at) $this->deny('الحساب موقوف. تواصل مع الإدارة.');
            $purchase = MedicalDeviceReportPurchase::where('device_id', $deviceId)->where('customer_id', $customerId)->lockForUpdate()->firstOrFail();
            if (!$purchase->purchase_requested_at) {
                $purchase->update(['purchase_requested_at' => now()]);
                $inquiry = MedicalDeviceInquiry::findOrFail($purchase->inquiry_id);
                if ($inquiry->support_ticket_id) {
                    SupportTicketConv::create(['support_ticket_id' => $inquiry->support_ticket_id, 'admin_id' => 0,
                        'customer_message' => 'أرغب في شراء هذا الجهاز، برجاء التواصل معي.', 'attachment' => []]);
                    DB::table('support_tickets')->where('id', $inquiry->support_ticket_id)->update(['status' => 'open', 'closed_at' => null, 'archived_at' => null, 'hidden_from_subject_at' => null, 'updated_at' => now()]);
                }
            }
            return $purchase;
        }, 3);
    }

    public function sendReport(MedicalDeviceInquiry $inquiry, UploadedFile $file, int $adminId): void
    {
        $path = $file->store('medical-device-reports', 'local');
        try {
            DB::transaction(function () use ($inquiry, $file, $adminId, $path) {
                $purchase = MedicalDeviceReportPurchase::where('inquiry_id', $inquiry->id)->lockForUpdate()->firstOrFail();
                $ticket = $inquiry->conversation()->lockForUpdate()->firstOrFail();
                if ((int) $ticket->customer_id !== (int) $purchase->customer_id) $this->deny('تعذر تحديد صاحب التقرير.');
                $ticket->update(['status' => 'open', 'closed_at' => null, 'archived_at' => null, 'hidden_from_subject_at' => null]);
                SupportTicketConv::create(['support_ticket_id' => $ticket->id, 'admin_id' => $adminId,
                    'admin_message' => 'تم إرسال التقرير الفني للجهاز.', 'attachment' => [[
                        'file_name' => basename($path), 'storage' => 'medical-report',
                        'original_name' => basename(str_replace('\\', '/', $file->getClientOriginalName())),
                        'medical_report_purchase_id' => $purchase->id,
                    ]]]);
            }, 3);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
    }

    private function deny(string $message): never
    {
        throw ValidationException::withMessages(['medical_report' => $message]);
    }
}
