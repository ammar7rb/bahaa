<?php

namespace App\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class VendorCancellationPenaltyService
{
    public function settings(): array
    {
        $row = Schema::hasTable('vendor_cancellation_penalty_settings') ? DB::table('vendor_cancellation_penalty_settings')->find(1) : null;
        return ['enabled'=>(bool)($row->enabled ?? false),'mode'=>$row->mode ?? 'fixed',
            'fixed_amount'=>(string)($row->fixed_amount ?? '0.00'),'percentage'=>(string)($row->percentage ?? '0.0000'),
            'revision'=>(int)($row->revision ?? 0),'currency'=>'EGP','base'=>'vendor_merchandise_entitlement',
            'trigger'=>'vendor_cancellation_after_explicit_acceptance'];
    }

    public function save(array $data, int $adminId): void
    {
        validator($data, ['enabled'=>'required|boolean','mode'=>'required|in:fixed,percentage',
            'fixed_amount'=>'nullable|numeric|min:0|max:999999999.99|decimal:0,2',
            'percentage'=>'nullable|numeric|min:0|max:100|decimal:0,4'])->validate();
        if ($data['enabled'] && (float)($data[$data['mode']==='fixed'?'fixed_amount':'percentage'] ?? 0) <= 0) {
            throw ValidationException::withMessages(['penalty'=>'حدد قيمة موجبة لطريقة حساب الغرامة المختارة.']);
        }
        DB::transaction(function () use ($data, $adminId) {
            // The singleton is inserted by migration/deployment before settings can be edited.
            DB::table('vendor_cancellation_penalty_settings')->insertOrIgnore(['id'=>1,'created_at'=>now(),'updated_at'=>now()]);
            $old=DB::table('vendor_cancellation_penalty_settings')->where('id',1)->lockForUpdate()->first();
            DB::table('vendor_cancellation_penalty_settings')->where('id',1)->update([
                'enabled'=>$data['enabled'],'mode'=>$data['mode'],'fixed_amount'=>$data['fixed_amount'] ?? 0,
                'percentage'=>$data['percentage'] ?? 0,'revision'=>$old->revision+1,'updated_by'=>$adminId,'updated_at'=>now(),
            ]);
        },3);
    }

    public function quote(mixed $vendorAmount): array
    {
        $rule=$this->settings();
        $base=BigDecimal::of((string)$vendorAmount)->toScale(8,RoundingMode::HalfUp);
        $amount=BigDecimal::zero();
        if ($rule['enabled']) $amount=$rule['mode']==='fixed' ? BigDecimal::of($rule['fixed_amount'])
            : $base->multipliedBy($rule['percentage'])->dividedBy(100,8,RoundingMode::HalfUp);
        return $rule+['base_amount'=>(string)$base,'assessed_amount'=>(string)$amount->toScale(2,RoundingMode::HalfUp)];
    }
}
