<?php
namespace Tests\Unit;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductOverviewPriceTest extends TestCase
{
    public function test_saved_price_revision_without_workspace_renders_safely_as_unverified(): void
    {
        Schema::create('business_settings',function(Blueprint $t){$t->id();$t->string('type');$t->text('value');});
        DB::table('business_settings')->insert(['type'=>'system_default_currency','value'=>'1']);
        session()->put('system_default_currency_info',['id'=>1]);
        session()->put('currency_code','EGP');session()->put('currency_symbol','EGP');
        $data=['product'=>(object)['added_by'=>'seller'],'original'=>['unit_price'=>80],'submission'=>null];
        $html=view('admin-views.product.partials._original-price',$data)->render();
        $this->assertStringContainsString('80',$html);
        $this->assertStringContainsString('غير موثّق تاريخيًا',$html);
        $data['submission']=(object)['baseline_source'=>'seller_submission'];
        $this->assertStringNotContainsString('غير موثّق',view('admin-views.product.partials._original-price',$data)->render());
        $data['original']=null;
        $this->assertStringContainsString('غير موثّق',view('admin-views.product.partials._original-price',$data)->render());
    }
}
