<?php

namespace App\Services;

use App\Models\ShippingMethod;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CustomerThreeStepShippingService
{
    private const SETTING_METHOD_IDS = 'customer_three_step_shipping_method_ids';
    private ?array $syncedMethodIds = null;

    public function isEnabled(): bool
    {
        return (bool) getWebConfig(name: 'customer_three_step_shipping_status');
    }

    public function getAvailableMethodsForSeller(int|string|null $sellerId, string $sellerType): Collection
    {
        if (!$this->isEnabled() || $sellerType !== 'admin') {
            return ShippingMethod::where(['status' => 1])
                ->when($sellerType === 'admin', fn ($query) => $query->where('creator_type', 'admin'))
                ->when($sellerType !== 'admin', fn ($query) => $query->where(['creator_id' => $sellerId, 'creator_type' => $sellerType]))
                ->get();
        }

        $this->syncMethods();

        return $this->getConfiguredMethodsQuery()
            ->get()
            ->filter(fn (ShippingMethod $method) => $this->isMethodCurrentlyAvailable($method))
            ->values();
    }

    public function getAllVisibleMethods(): Collection
    {
        if (!$this->isEnabled()) {
            return ShippingMethod::where(['status' => 1])->get();
        }

        $this->syncMethods();
        $threeStepIds = $this->getStoredMethodIds();

        return ShippingMethod::where(['status' => 1])
            ->where(function ($query) use ($threeStepIds) {
                $query->where('creator_type', '!=', 'admin')
                    ->orWhereIn('id', $threeStepIds);
            })
            ->get()
            ->filter(fn (ShippingMethod $method) => $this->isMethodCurrentlyAvailable($method))
            ->values();
    }

    public function getThreeStepOptionsWithAvailability(): Collection
    {
        $this->syncMethods();
        $ids = $this->getStoredMethodIds();
        $orderedIds = implode(',', array_map('intval', $ids));

        return ShippingMethod::whereIn('id', $ids)
            ->when($orderedIds, fn ($query) => $query->orderByRaw("FIELD(id, {$orderedIds})"))
            ->get()
            ->map(function (ShippingMethod $method) {
                return [
                    'id' => $method->id,
                    'title' => $method->title,
                    'duration' => $method->duration,
                    'cost' => (float) $method->cost,
                    'option_key' => $this->getOptionKeyForMethod($method),
                    'enabled' => (bool) $method->status,
                    'available' => $this->isMethodCurrentlyAvailable($method),
                ];
            })
            ->values();
    }

    public function resolveSelectableMethod(int|string $methodId): ?ShippingMethod
    {
        $method = ShippingMethod::where('id', $methodId)->first();

        if (!$method || !$method->status) {
            return null;
        }

        if (!$this->isEnabled()) {
            return $method;
        }

        $this->syncMethods();

        return $this->isMethodCurrentlyAvailable($method) ? $method : null;
    }

    public function isMethodCurrentlyAvailable(ShippingMethod $method): bool
    {
        if (!$this->isEnabled()) {
            return (bool) $method->status;
        }

        $optionKey = $this->getOptionKeyForMethod($method);
        if (!$optionKey) {
            return $method->creator_type !== 'admin' && (bool) $method->status;
        }

        $config = $this->getOptionConfig($optionKey);
        if (!$config['status']) {
            return false;
        }

        if ($optionKey === 'same_day') {
            return $this->isBeforeSameDayCutoff();
        }

        return true;
    }

    public function syncMethods(): array
    {
        $storedIds = $this->getStoredMethodIds();
        $syncedIds = [];

        foreach ($this->getOptionKeys() as $optionKey) {
            $config = $this->getOptionConfig($optionKey);
            $method = isset($storedIds[$optionKey]) ? ShippingMethod::find($storedIds[$optionKey]) : null;

            if (!$method) {
                $method = ShippingMethod::where([
                    'creator_id' => 1,
                    'creator_type' => 'admin',
                    'title' => $config['title'],
                ])->first();
            }

            $data = [
                'creator_id' => 1,
                'creator_type' => 'admin',
                'title' => $config['title'],
                'duration' => $config['duration'],
                'cost' => $config['cost'],
                'status' => $config['status'],
            ];

            if ($method) {
                $method->update($data);
            } else {
                $method = ShippingMethod::create($data);
            }

            $syncedIds[$optionKey] = $method->id;
        }

        DB::table('business_settings')->updateOrInsert(
            ['type' => self::SETTING_METHOD_IDS],
            ['value' => json_encode($syncedIds), 'updated_at' => now(), 'created_at' => now()]
        );

        $this->syncedMethodIds = $syncedIds;

        return $syncedIds;
    }

    public function getOptionKeyForMethod(ShippingMethod $method): ?string
    {
        $storedIds = $this->getStoredMethodIds();
        foreach ($storedIds as $optionKey => $methodId) {
            if ((int) $methodId === (int) $method->id) {
                return $optionKey;
            }
        }

        return null;
    }

    public function getSameDayCutoff(): string
    {
        return getWebConfig(name: 'customer_shipping_same_day_cutoff') ?: '12:00';
    }

    private function getConfiguredMethodsQuery()
    {
        $ids = $this->getStoredMethodIds();
        $orderedIds = implode(',', array_map('intval', $ids));

        return ShippingMethod::whereIn('id', $ids)
            ->where('creator_type', 'admin')
            ->where('status', 1)
            ->when($orderedIds, fn ($query) => $query->orderByRaw("FIELD(id, {$orderedIds})"));
    }

    private function getStoredMethodIds(): array
    {
        if ($this->syncedMethodIds !== null) {
            return $this->syncedMethodIds;
        }

        $setting = getWebConfig(name: self::SETTING_METHOD_IDS);
        $ids = is_array($setting) ? $setting : json_decode($setting ?: '[]', true);

        return is_array($ids) ? array_filter($ids) : [];
    }

    private function getOptionKeys(): array
    {
        return ['same_day', 'next_day', 'normal'];
    }

    private function getOptionConfig(string $optionKey): array
    {
        $prefix = 'customer_shipping_' . $optionKey;
        $defaults = [
            'same_day' => ['title' => 'Same Day Delivery', 'duration' => 'Same day'],
            'next_day' => ['title' => 'Next Day Delivery', 'duration' => 'Next day'],
            'normal' => ['title' => 'Normal Delivery', 'duration' => '3 days'],
        ];

        return [
            'status' => (bool) getWebConfig(name: $prefix . '_status'),
            'title' => getWebConfig(name: $prefix . '_title') ?: $defaults[$optionKey]['title'],
            'duration' => getWebConfig(name: $prefix . '_duration') ?: $defaults[$optionKey]['duration'],
            'cost' => (float) (getWebConfig(name: $prefix . '_cost') ?? 0),
        ];
    }

    private function isBeforeSameDayCutoff(): bool
    {
        $cutoff = $this->getSameDayCutoff();
        $now = Carbon::now();

        try {
            $cutoffTime = Carbon::createFromFormat('H:i', $cutoff)->setDate($now->year, $now->month, $now->day);
        } catch (\Throwable) {
            $cutoffTime = Carbon::createFromFormat('H:i', '12:00')->setDate($now->year, $now->month, $now->day);
        }

        return $now->lt($cutoffTime);
    }
}
