<?php

namespace App\Observers;

use App\Models\Order;
use App\Traits\PushNotificationTrait;

class OrderObserver
{
    use PushNotificationTrait;
    /**
     * Handle the Order "created" event.
     */
    public function created(Order $order): void
    {
//        $order->flushCache();
    }

    public function updating(Order $order): void
    {
        if($order->isDirty('order_status')){
            $before=clone $order; $before->setRawAttributes($order->getOriginal(),true);
            app(\App\Services\VendorFulfillmentDecisionService::class)->assertAggregateProgress($before,(string)$order->order_status);
        }
    }

    /**
     * Handle the Order "updated" event.
     */
    public function updated(Order $order): void
    {
        if($order->wasChanged('order_status') && $order->order_status==='canceled') {
            app(\App\Services\VendorFulfillmentDecisionService::class)->synchronizeExternalCancellation($order);
        }
//        $order->flushCache();
    }

    /**
     * Handle the Order "deleted" event.
     */
    public function deleted(Order $order): void
    {
        //
    }

    /**
     * Handle the Order "restored" event.
     */
    public function restored(Order $order): void
    {
        //
    }

    /**
     * Handle the Order "force deleted" event.
     */
    public function forceDeleted(Order $order): void
    {
        //
    }
}
