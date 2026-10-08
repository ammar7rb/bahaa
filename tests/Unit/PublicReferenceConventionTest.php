<?php
namespace Tests\Unit;

use App\Services\AdministrativeReferenceService as Reference;
use App\Models\CustomerBalanceDeposit;
use App\Models\CustomerInsuranceLedgerEntry;
use App\Models\SellerBalanceDeposit;
use App\Models\SellerLedgerEntry;
use App\Models\Product;
use App\Models\PostPurchaseInvoice;
use App\Models\WithdrawRequest;
use Tests\TestCase;

class PublicReferenceConventionTest extends TestCase
{
    public function test_every_requested_prefix_and_vendor_search(): void
    {
        foreach (['seller'=>'M','customer'=>'C','product'=>'P','invoice'=>'IV','customer_balance_deposit'=>'ADC','customer_insurance_deposit'=>'AIC','seller_balance_deposit'=>'ADM','seller_insurance_deposit'=>'AIM','seller_payment'=>'MP','seller_insurance_payment'=>'MIP','customer_insurance_payment'=>'CIP'] as $type=>$prefix) {
            $this->assertSame($prefix.'42', Reference::format($type,42));
        }
        $this->assertSame(['type'=>'sellers','id'=>42],Reference::parse('m-42'));
        $this->assertSame(['type'=>'sellers','id'=>42],Reference::parse('V42'));
        $this->assertNull(Reference::parse('MIP42'));
    }
    public function test_display_numbers_preserve_numeric_keys_and_gateway_references(): void
    {
        $deposit=new SellerBalanceDeposit(['metadata'=>['wallet_target'=>'insurance'],'transaction_reference'=>'bank-proof-123']);$deposit->id=42;
        $this->assertSame('AIM42',$deposit->transaction_number);
        $this->assertSame('bank-proof-123',$deposit->transaction_reference);
        $this->assertSame(42,$deposit->id);
        $deposit->metadata=['wallet_target'=>'operating'];$this->assertSame('ADM42',$deposit->transaction_number);
        $customer=new CustomerBalanceDeposit(['wallet_type'=>'purchase']);$customer->id=7;
        $this->assertSame('ADC7',$customer->transaction_number);
        $customer->wallet_type='insurance';$this->assertSame('AIC7',$customer->transaction_number);
        $product=new Product();$product->id=9;$this->assertSame('P9',$product->administrative_reference);
        $invoice=new PostPurchaseInvoice();$invoice->id=11;$this->assertSame('IV11',$invoice->invoice_number);
        $withdrawal=new WithdrawRequest(['seller_id'=>2,'approved'=>0]);$withdrawal->id=5;
        $this->assertSame('MP5',$withdrawal->transaction_number);$this->assertSame(0,$withdrawal->approved);
    }
    public function test_ledger_display_labels_do_not_mutate_hashed_or_idempotent_fields(): void
    {
        $entry=new SellerLedgerEntry(['bucket'=>'order_insurance_credit','direction'=>'credit','event_type'=>'seller_order_insurance_credit_released','reference_code'=>'ORD-987654','group_key'=>'original-group','entry_hash'=>'original-hash']);$entry->id=3;
        $this->assertSame('MIP3',$entry->transaction_number);
        $this->assertSame('ORD-987654',$entry->reference_code);
        $this->assertSame('original-hash',$entry->entry_hash);
        $entry->reference_type=SellerBalanceDeposit::class;$entry->reference_id=8;
        $this->assertSame('AIM8',$entry->transaction_number);
        $entry->bucket='operating';$this->assertSame('ADM8',$entry->transaction_number);
        $entry->reference_type=WithdrawRequest::class;$entry->reference_id=12;
        $this->assertSame('MP12',$entry->transaction_number);
        $entry->reference_type=null;$entry->bucket='order_insurance_credit';$entry->event_type='opening_balance';
        $this->assertSame('WLT-3',$entry->transaction_number);
        $customer=new CustomerInsuranceLedgerEntry(['entry_type'=>'matured_credit','credit'=>20,'reference'=>'immutable-return']);$customer->id=4;
        $this->assertSame('CIP4',$customer->transaction_number);$this->assertSame('immutable-return',$customer->reference);
    }
}
