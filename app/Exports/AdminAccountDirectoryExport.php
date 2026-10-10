<?php

namespace App\Exports;

use App\Services\AdminAccountDirectoryService;
use Maatwebsite\Excel\Concerns\{FromGenerator, WithHeadings, WithColumnWidths, WithStyles, WithCustomValueBinder};
use PhpOffice\PhpSpreadsheet\Cell\{Cell, DataType, DefaultValueBinder};
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AdminAccountDirectoryExport extends DefaultValueBinder implements FromGenerator, WithHeadings, WithColumnWidths, WithStyles, WithCustomValueBinder
{
    public function __construct(private AdminAccountDirectoryService $directory, private array $filters) {}

    public function headings(): array
    {
        return array_map('translate',['SL','directory_identifier','directory_name','directory_store_label','directory_type','email','phone','directory_extra_contacts','directory_status','directory_joined','directory_orders_count','directory_active_products_count']);
    }

    public function generator(): \Generator
    {
        $number=0;
        foreach ($this->directory->query($this->filters)->lazy(500)->chunk(500) as $batch) {
            $contacts=$this->directory->contacts(collect($batch->all()));
            foreach ($batch as $account) yield [
                ++$number,($account->kind==='customer'?'c':'m').$account->id,
                trim($account->f_name.' '.$account->l_name),$account->store_name ?? '',translate($account->kind),
                $account->email ?? '',$account->phone ?? '',implode("\n",$contacts[$account->kind.':'.$account->id] ?? []),
                translate('directory_'.$account->account_status),$account->created_at ?? '',(int)$account->orders_count,$account->kind==='vendor'?(int)$account->active_products_count:'',
            ];
        }
    }

    public function bindValue(Cell $cell,$value): bool
    {
        // Treat account data as text: preserve phone zeroes and never execute formulas.
        if (in_array($cell->getColumn(),['A','K','L'],true) && is_int($value)) $cell->setValueExplicit($value,DataType::TYPE_NUMERIC);
        else $cell->setValueExplicit((string)$value,DataType::TYPE_STRING);
        return true;
    }

    public function columnWidths(): array
    {
        return ['A'=>8,'B'=>15,'C'=>26,'D'=>26,'E'=>15,'F'=>34,'G'=>22,'H'=>30,'I'=>18,'J'=>24,'K'=>16,'L'=>18];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->setRightToLeft(session('direction','rtl')==='rtl');
        $sheet->freezePane('C2');
        $sheet->setAutoFilter($sheet->calculateWorksheetDimension());
        $sheet->getStyle($sheet->calculateWorksheetDimension())->getAlignment()->setWrapText(true)->setVertical('center');
        $sheet->getDefaultRowDimension()->setRowHeight(32);
        $sheet->setShowGridlines(false);
        return [1=>['font'=>['bold'=>true,'color'=>['rgb'=>'FFFFFF']],'fill'=>['fillType'=>'solid','startColor'=>['rgb'=>'2454A6']]]];
    }
}
