<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class WalletTransaction
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $transaction_id
 * @property string|null $reference
 * @property string|null $transaction_type
 * @property float $credit
 * @property float $debit
 * @property float $admin_bonus
 * @property float $balance
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @package App\Models
 */
class WalletTransaction extends Model
{
    public function getDisplayReferenceAttribute(): string
    {
        if ($this->transaction_type === 'add_fund' && preg_match('/^customer-offline-deposit-(\d+)$/', (string) $this->reference, $match)) {
            return \App\Services\AdministrativeReferenceService::format('customer_balance_deposit', (int) $match[1]);
        }
        return $this->transaction_type === 'add_fund' ? \App\Services\AdministrativeReferenceService::format('customer_balance_deposit', (int) $this->id) : (string) $this->transaction_id;
    }

    protected $appends = ['display_reference'];

    use HasFactory;

    protected $fillable = [
        'user_id',
        'transaction_id',
        'reference',
        'transaction_type',
        'credit',
        'debit',
        'admin_bonus',
        'balance',
        'payment_method',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'credit' => 'float',
        'debit' => 'float',
        'admin_bonus' => 'float',
        'balance' => 'float',
        'reference' => 'string',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
