<?php

namespace App\Console\Commands;

use App\Services\EgyptPhoneService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditCustomerPhones extends Command
{
    protected $signature = 'customers:audit-phones {--apply-canonical : Populate canonical identities} {--apply-local : Archive and replace valid customer phones with their canonical 11-digit value}';
    protected $description = 'Check customer phone collisions before migrating identities';

    public function handle(EgyptPhoneService $phones): int
    {
        return DB::transaction(function () use ($phones) {
            $groups = [];
            $invalid = 0;
            $query = DB::table('users')->select('id', 'phone')->orderBy('id');
            if ($this->option('apply-canonical') || $this->option('apply-local')) $query->lockForUpdate();
            foreach ($query->get() as $user) {
                if (!$phones->isValid($user->phone)) { $invalid++; continue; }
                $groups[$phones->normalizeLocal($user->phone)][] = $user->id;
            }
            $collisions = array_filter($groups, fn ($ids) => count($ids) > 1);
            $this->info('Valid identities: '.count($groups).'; invalid records: '.$invalid.'; collisions: '.count($collisions));
            if ($collisions) {
                $this->error('No changes applied. Resolve colliding accounts before migration.');
                return self::FAILURE;
            }
            if ($this->option('apply-canonical') || $this->option('apply-local')) {
                foreach ($groups as $phone => $ids) {
                    $update = ['canonical_phone' => $phone];
                    if ($this->option('apply-local')) {
                        $original = DB::table('users')->where('id',$ids[0])->value('phone');
                        DB::table('customer_financial_archives')->insertOrIgnore([
                            'source_table'=>'customer_phone_identity','source_id'=>(string)$ids[0],
                            'snapshot'=>json_encode(['phone'=>$original,'canonical_phone'=>$phone]),'reason'=>'canonical_phone_migration','archived_at'=>now(),
                        ]);
                        $update['phone'] = $phone;
                    }
                    DB::table('users')->where('id', $ids[0])->update($update);
                }
                $this->info('Canonical identities populated. Originals archived on local migration; invalid records unchanged.');
            }
            return self::SUCCESS;
        });
    }
}
