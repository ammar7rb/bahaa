<?php
namespace Tests\Unit;
use App\Services\CustomerTransferInformationService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
class CustomerTransferInformationTest extends TestCase
{
    public function test_sender_number_is_canonical_and_name_is_preserved(): void {
        $this->assertSame(['sender_phone'=>'01012345678','sender_name'=>'حساب العميل'],app(CustomerTransferInformationService::class)->sender(['sender_phone'=>'٠١٠ ١٢٣٤-٥٦٧٨','sender_name'=>'  حساب العميل  ']));
    }
    public function test_invalid_sender_phone_is_rejected(): void {
        $this->expectException(ValidationException::class);app(CustomerTransferInformationService::class)->sender(['sender_phone'=>'01312345678','sender_name'=>'حساب']);
    }
    public function test_sender_account_name_is_required(): void {
        $this->expectException(ValidationException::class);app(CustomerTransferInformationService::class)->sender(['sender_phone'=>'01012345678','sender_name'=>' ']);
    }
    public function test_only_recipient_number_is_copyable(): void {
        $service=app(CustomerTransferInformationService::class);
        foreach(['رقم المحفظة','عنوان الدفع','account_number','phone'] as $label) $this->assertTrue($service->recipientIsNumber(['input_name'=>$label]));
        foreach(['صاحب الحساب','account_holder','wallet_name','اسم البنك'] as $label) $this->assertFalse($service->recipientIsNumber(['input_name'=>$label]));
    }
}
