<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

class PublicProductCodeService
{
    public function assign(Product $product): string
    {
        return DB::transaction(function () use ($product): string {
            $locked = Product::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($product->id);
            if ($locked->public_code) {
                $product->public_code = $locked->public_code;
                $product->syncOriginalAttribute('public_code');
                return $locked->public_code;
            }

            $scope = $locked->added_by === 'seller' ? 'seller:'.$locked->user_id : 'admin';
            DB::table('public_product_code_sequences')->insertOrIgnore(['scope' => $scope, 'next_sequence' => 1]);
            $counter = DB::table('public_product_code_sequences')->where('scope', $scope)->lockForUpdate()->first();
            $number = (int) $counter->next_sequence;
            $code = $locked->added_by === 'seller' ? 'P'.$locked->user_id.'-'.$number : 'A-'.$number;
            DB::table('public_product_code_sequences')->where('scope', $scope)->update(['next_sequence' => $number + 1]);
            $locked->forceFill(['public_code' => $code])->save();
            $product->public_code = $code;
            $product->syncOriginalAttribute('public_code');

            return $code;
        }, 3);
    }
}
