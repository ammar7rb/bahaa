<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerBalanceDeposit extends Model
{
    protected $guarded = ['id'];
    protected $appends = ['transaction_number'];

    public function getTransactionNumberAttribute(): string
    {
        return \App\Services\AdministrativeReferenceService::format($this->wallet_type === 'insurance' ? 'customer_insurance_deposit' : 'customer_balance_deposit', (int) $this->id);
    }
    protected $casts = ['amount' => 'float', 'submitted_amount' => 'float', 'method_information' => 'array', 'reviewed_at' => 'datetime'];
    public function customer(): BelongsTo { return $this->belongsTo(User::class, 'customer_id'); }
}
