<?php

namespace Tests\Unit;

use App\Services\SellerProductContentPolicyService;
use Tests\TestCase;

class SellerProductContentPolicyServiceTest extends TestCase
{
    public function test_it_detects_phone_email_links_and_direct_contact_attempts(): void
    {
        $violations = app(SellerProductContentPolicyService::class)->inspect([
            'name' => ['قفازات طبية'],
            'description' => ['اتصل بنا على 01012345678 أو seller@example.com أو https://wa.me/2010'],
        ]);

        $this->assertContains('phone_number', $violations);
        $this->assertContains('email_address', $violations);
        $this->assertContains('external_link', $violations);
        $this->assertContains('direct_contact', $violations);
    }

    public function test_it_detects_written_phone_prefix_and_allows_plain_product_information(): void
    {
        $service = app(SellerProductContentPolicyService::class);

        $this->assertContains('written_phone_number', $service->inspect([
            'description' => ['للتواصل: صفر واحد صفر ثم باقي الرقم'],
        ]));
        $this->assertSame([], $service->inspect([
            'name' => ['شاش طبي معقم'],
            'description' => ['عبوة 100 قطعة للاستخدام الطبي.'],
        ]));
    }

    public function test_it_detects_real_arabic_contact_words_and_written_numbers(): void
    {
        $violations = app(SellerProductContentPolicyService::class)->inspect([
            'description' => ['للتواصل اتصل بنا على واتساب: صفر واحد صفر اثنان ثلاثة أربعة خمسة'],
        ]);

        $this->assertContains('direct_contact', $violations);
        $this->assertContains('written_phone_number', $violations);
    }

    public function test_it_counts_only_distinct_non_empty_image_references(): void
    {
        $count = app(SellerProductContentPolicyService::class)->countImages(
            [['image_name' => 'one.webp'], ['image_name' => 'two.webp']],
            json_encode([['image_name' => 'two.webp'], ['image_name' => 'three.webp'], ['image_name' => '']])
        );

        $this->assertSame(3, $count);
    }
}
