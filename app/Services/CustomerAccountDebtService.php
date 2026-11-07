<?php

namespace App\Services;

use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\{DB, Schema};

class CustomerAccountDebtService
{
    public function create(int $customerId, string $amount, string $reason, int $adminId, string $key): int
    {
        if (!in_array(config('customer_accounts.debt_allocation'), ['oldest_first'], true)) {
            throw new DomainException('إضافة الديون غير متاحة حتى اعتماد ترتيب استيفائها من الشحن.');
        }
        $units = $this->units($amount);
        if ($units <= 0 || !trim($reason) || $adminId <= 0) throw new DomainException('أدخل مبلغًا موجبًا وسبب الدين.');
        return DB::transaction(function () use ($customerId, $amount, $reason, $adminId, $key) {
            $customer = User::lockForUpdate()->findOrFail($customerId);
            if ($customer->account_deleted_at) throw new DomainException('الحساب مغلق.');
            $prior = DB::table('customer_account_debts')->where('request_key', $key)->first();
            if ($prior) {
                if ((int)$prior->customer_id !== $customerId || (int)$prior->created_by_admin_id !== $adminId || $this->units($prior->amount) !== $this->units($amount) || $prior->reason !== trim($reason)) throw new DomainException('مرجع الطلب مستخدم لعملية أخرى.');
                return (int)$prior->id;
            }
            return DB::table('customer_account_debts')->insertGetId(['customer_id'=>$customerId, 'amount'=>$amount,
                'reason'=>trim($reason), 'created_by_admin_id'=>$adminId, 'request_key'=>$key, 'created_at'=>now(), 'updated_at'=>now()]);
        }, 3);
    }

    /** Called only inside the confirmed funding transaction, with the customer already locked. */
    public function allocate(int $customerId, string|float $gross, string $reference): array
    {
        $remaining = $this->units($gross);
        $result = ['gross'=>$remaining/1000, 'recovered'=>0, 'net'=>$remaining/1000, 'remaining_debt'=>0, 'allocations'=>[]];
        if (!Schema::hasTable('customer_account_debts')) return $result;
        $debts = DB::table('customer_account_debts')->where('customer_id',$customerId)->whereColumn('amount','>','paid_amount')->orderBy('created_at')->orderBy('id')->lockForUpdate()->get();
        if ($debts->isEmpty()) return $result;
        if (config('customer_accounts.debt_allocation') !== 'oldest_first') throw new DomainException('يلزم اعتماد قاعدة توزيع السداد قبل معالجة شحن حساب عليه ديون.');
        $total = 0;
        foreach ($debts as $debt) {
            $due = $this->units($debt->amount)-$this->units($debt->paid_amount);
            $total += $due;
            $paid = min($remaining, $due);
            if ($paid === 0) continue;
            DB::table('customer_debt_repayments')->insert(['customer_id'=>$customerId,'debt_id'=>$debt->id,'funding_reference'=>$reference,'amount'=>$paid/1000,'debt_before'=>$due/1000,'debt_after'=>($due-$paid)/1000,'created_at'=>now()]);
            DB::table('customer_account_debts')->where('id',$debt->id)->update(['paid_amount'=>($this->units($debt->paid_amount)+$paid)/1000,
                'settled_at'=>$paid===$due?now():null,'updated_at'=>now()]);
            $result['allocations'][]=['debt_id'=>$debt->id,'previous'=>$due/1000,'paid'=>$paid/1000,'remaining'=>($due-$paid)/1000];
            $remaining -= $paid;
        }
        $result['net']=$remaining/1000;
        $result['recovered']=$result['gross']-$result['net'];
        $result['remaining_debt']=$total/1000-$result['recovered'];
        return $result;
    }

    public function archive(int $customerId, int $id, int $adminId): void
    {
        DB::transaction(function () use ($customerId,$id,$adminId) {
            User::lockForUpdate()->findOrFail($customerId);
            $debt=DB::table('customer_account_debts')->where('customer_id',$customerId)->where('id',$id)->lockForUpdate()->first();
            abort_unless($debt,404);
            if ($this->units($debt->amount)!==$this->units($debt->paid_amount) || !$debt->settled_at) throw new DomainException('لا يمكن أرشفة دين غير مسدد بالكامل.');
            if (!$debt->archived_at) DB::table('customer_account_debts')->where('id',$id)->update(['archived_at'=>now(),'archived_by_admin_id'=>$adminId,'updated_at'=>now()]);
        },3);
    }

    private function units(mixed $amount): int
    {
        if (!is_numeric($amount) || !is_finite((float)$amount) || (float)$amount < 0 || (float)$amount > 10000000000) throw new DomainException('قيمة مالية غير صالحة.');
        return (int)round((float)$amount*1000);
    }
}
