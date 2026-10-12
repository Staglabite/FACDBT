<?php

namespace App\Exports\Templates\FACDBT\FACDBT01;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;

class FACDBTF003 implements FromArray, WithHeadings, ShouldAutoSize, WithEvents
{
    public function array(): array
    {
        return [];
    }

    public function headings(): array
    {
        return [
            'Part No',
            'Description',
            'UOM',
            'Accounting Group',
            'On Hand Qty',
            'Material Cost',
            'Labor Cost',
            'FOH Cost',
        ];
    }

    public function registerEvents(): array
    {
        return [];
    }
}