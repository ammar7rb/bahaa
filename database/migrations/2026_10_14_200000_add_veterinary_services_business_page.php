<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Preserve any page already managed by the administrator, including its publication status.
        if (!DB::table('business_pages')->where('slug', 'veterinary-services')->exists()) {
            if (DB::table('business_pages')->whereIn('title', ['خدمات بيطرية', 'الخدمات البيطرية', 'Veterinary Services'])->exists()) {
                throw new RuntimeException('A veterinary business page already exists with a different slug. Review it before adding veterinary-services.');
            }

            $arabic = '<h2>المستلزمات الطبية البيطرية</h2><p>لا توجد مستلزمات معروضة حاليًا في هذا القسم.</p>'
                . '<h2>مستلزمات الحيوانات الأليفة</h2><p>لا توجد مستلزمات معروضة حاليًا في هذا القسم.</p>';
            $english = '<h2>Veterinary Medical Supplies</h2><p>No supplies are currently listed in this section.</p>'
                . '<h2>Pet Supplies</h2><p>No supplies are currently listed in this section.</p>';

            DB::table('business_pages')->insert([
                'title' => 'خدمات بيطرية',
                'slug' => 'veterinary-services',
                'description' => $arabic,
                'translations' => json_encode([
                    'ar' => ['title' => 'خدمات بيطرية', 'description' => $arabic],
                    'en' => ['title' => 'Veterinary Services', 'description' => $english],
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'status' => 1,
                'default_status' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Cache::forget(CACHE_FOR_BUSINESS_PAGES_LIST);
    }

    public function down(): void
    {
        // Keep administrator edits and page history when rolling back the navigation change.
    }
};
