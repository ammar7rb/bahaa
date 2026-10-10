<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Class YourModel
 *
 * @property int $id
 * @property int $order_id
 * @property string $payment_info
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @package App\Models
 */
class OfflinePayments extends Model
{
    use HasFactory;

    public function toArray(): array
    {
        $data = parent::toArray();
        if (is_array($data['payment_info'] ?? null)) {
            unset($data['payment_info']['private_retry_proof'], $data['payment_info']['retry_key'], $data['payment_info']['retry_history']);
        }
        return $data;
    }

    protected $guarded = ['id'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'order_id',
        'payment_info',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'payment_info' => 'json',
    ];
}
