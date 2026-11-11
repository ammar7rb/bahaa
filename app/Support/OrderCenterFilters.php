<?php

namespace App\Support;

class OrderCenterFilters
{
    public const OPTIONS = [
        'all' => ['كل الطلبات', 'fi-rr-shopping-cart'],
        'awaiting_admin' => ['مراجعة الإدارة', 'fi-rr-search'],
        'waiting_vendor' => ['بانتظار التاجر', 'fi-rr-hourglass-end'],
        'active' => ['قيد التنفيذ', 'fi-rr-truck-side'],
        'completed' => ['مكتملة', 'fi-rr-check-circle'],
        'stopped' => ['متوقفة', 'fi-rr-ban'],
        'guests' => ['طلبات الزوار', 'fi-rr-user-add'],
    ];

    public static function normalize(mixed $queue): string
    {
        if (!is_string($queue)) return 'all';
        $queue = ['sent_to_vendor'=>'waiting_vendor','overdue'=>'waiting_vendor','accepted'=>'active',
            'processing'=>'active','out_for_delivery'=>'active','rejected'=>'stopped','canceled'=>'stopped',
            'returned'=>'stopped','failed'=>'stopped','history'=>'completed'][$queue ?? ''] ?? $queue;
        return array_key_exists($queue ?? '', self::OPTIONS) ? $queue : 'all';
    }
}
