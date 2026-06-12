<?php

namespace App\Exports\Templates\HITUAM\HITUAM01;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;

class HITUAMF001 implements FromArray, WithHeadings, ShouldAutoSize, WithEvents
{
    public function array(): array
    {
        return [];
    }

    public function headings(): array
    {
        return [
            'Code',
            'Description',
            'Prefix',
            'PIC',
            'Portal Name',
            'Operational',
            'Standardization',
            'Portal Access',
            'Host',
            'Publish',
            'Database',
            'Order',
            'Icon',
        ];
    }

    public function registerEvents(): array
    {
        return [];
    }
}
