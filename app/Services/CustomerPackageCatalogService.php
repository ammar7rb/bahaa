<?php
namespace App\Services;
use App\Models\CustomerPurchasePackage;
use Illuminate\Database\Eloquent\Builder;

class CustomerPackageCatalogService
{
    public function query(): Builder
    {
        $query=CustomerPurchasePackage::where('is_custom',0);
        return \Illuminate\Support\Facades\Schema::hasColumn('customer_purchase_packages','customer_tier')
            ? $query->whereIn('customer_tier',['silver','gold','diamond'])
            : $query->whereRaw('LOWER(name) IN (?,?,?)',['silver','gold','diamond']);
    }
    public function available(): Builder { return $this->query()->where('status',1)->orderBy('sort_order')->orderBy('id'); }
    public function token(object $item, string $kind): string
    {
        $fields=$kind==='offer'?['id','name','price','allowance','active']:['id','name','package_price','purchase_limit','renewal_price','status','customer_tier'];
        $values=[];foreach($fields as $field)$values[$field]=$item->$field;
        return hash('sha256',json_encode([$kind,$values],JSON_UNESCAPED_UNICODE|JSON_PRESERVE_ZERO_FRACTION));
    }
}
