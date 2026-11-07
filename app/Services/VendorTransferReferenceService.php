<?php
namespace App\Services;
use DomainException;
use Illuminate\Support\Facades\{DB, Schema};

class VendorTransferReferenceService
{
    public function reserve(int $methodId, string $reference, string $type, int $id, bool $confirmed = false): void
    {
        if (!Schema::hasTable('vendor_transfer_references')) return;
        $reference = mb_strtoupper(preg_replace('/\s+/u', '', strtr(trim($reference), array_combine(preg_split('//u', '٠١٢٣٤٥٦٧٨٩', -1, PREG_SPLIT_NO_EMPTY), str_split('0123456789')))));
        if ($methodId < 1 || $reference === '' || mb_strlen($reference) > 191) throw new DomainException('رقم التحويل وطريقة الدفع مطلوبان.');
        DB::table('vendor_transfer_references')->insertOrIgnore(['method_id'=>$methodId, 'reference_hash'=>hash('sha256', $reference),
            'reference'=>$reference, 'source_type'=>$type, 'source_id'=>$id, 'status'=>'reserved', 'created_at'=>now(), 'updated_at'=>now()]);
        $row = DB::table('vendor_transfer_references')->where('method_id', $methodId)->where('reference_hash', hash('sha256', $reference))->lockForUpdate()->first();
        if (!$row || $row->source_type !== $type || (int)$row->source_id !== $id) throw new DomainException('رقم التحويل مستخدم بالفعل مع طريقة الدفع نفسها.');
        if ($confirmed) DB::table('vendor_transfer_references')->where('id', $row->id)->update(['status'=>'confirmed', 'updated_at'=>now()]);
    }
    public function release(string $type, int $id): void
    {
        if (Schema::hasTable('vendor_transfer_references')) DB::table('vendor_transfer_references')->where('source_type',$type)->where('source_id',$id)->where('status','reserved')->delete();
    }
}
