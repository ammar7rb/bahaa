<?php

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BusinessSettingsAuditObserver
{
    public function created(Model $model): void { $this->record($model, 'created', null, $model->getAttributes()); }
    public function updated(Model $model): void
    {
        $changes = array_diff_key($model->getChanges(), array_flip(['updated_at']));
        if ($changes === []) return;
        $before = [];
        foreach (array_keys($changes) as $key) $before[$key] = $model->getRawOriginal($key);
        $this->record($model, 'updated', $before, $changes);
    }
    public function deleted(Model $model): void { $this->record($model, 'deleted', $model->getAttributes(), null); }

    private function record(Model $model, string $event, ?array $before, ?array $after): void
    {
        if (!auth('admin')->check() || !Schema::hasTable('business_setting_audits')) return;
        $kind = match (class_basename($model)) {
            'CustomerPurchasePackage', 'SellerPackage' => 'package',
            'OfflinePaymentMethod' => 'payment_destination',
            'BusinessPage' => 'legal_content',
            'Banner' => 'advertising_placement',
            'Category', 'Brand' => 'catalog',
            'CompanyMedicalDevice' => 'company_product',
            default => 'shipping',
        };
        DB::table('business_setting_audits')->insert(['kind' => $kind, 'subject_type' => class_basename($model).':'.$event,
            'subject_id' => $model->getKey(), 'before' => $before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE),
            'after' => $after === null ? null : json_encode($after, JSON_UNESCAPED_UNICODE),
            'admin_id' => auth('admin')->id(), 'created_at' => now()]);
    }
}
