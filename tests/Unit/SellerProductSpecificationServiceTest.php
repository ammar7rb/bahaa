<?php

namespace Tests\Unit;

use App\Services\SellerProductSpecificationService;
use Tests\TestCase;

class SellerProductSpecificationServiceTest extends TestCase
{
    public function test_optional_specifications_default_to_piece_without_dimensions(): void
    {
        $service = app(SellerProductSpecificationService::class);

        $this->assertSame([], $service->validate([]));
        $this->assertSame('piece', $service->normalize([])['sale_unit_type']);
        $this->assertNull($service->normalize([])['weight']);
    }

    public function test_package_requires_piece_count_and_measurements_require_valid_units(): void
    {
        $service = app(SellerProductSpecificationService::class);

        $errors = $service->validate([
            'sale_unit_type' => 'box',
            'length' => 10,
            'weight' => 2,
        ]);

        $this->assertSame('seller_product_pieces_per_unit_required', $errors['pieces_per_unit']);
        $this->assertSame('seller_product_dimension_unit_required', $errors['dimension_unit']);
        $this->assertSame('seller_product_weight_unit_required', $errors['weight_unit']);
    }

    public function test_valid_box_and_measurements_are_normalized(): void
    {
        $service = app(SellerProductSpecificationService::class);
        $input = [
            'sale_unit_type' => 'box',
            'pieces_per_unit' => 12,
            'length' => 10,
            'width' => 5,
            'height' => 3,
            'dimension_unit' => 'cm',
            'weight' => 1.5,
            'weight_unit' => 'kg',
        ];

        $this->assertSame([], $service->validate($input));
        $this->assertSame(12, $service->normalize($input)['pieces_per_unit']);
    }
}
