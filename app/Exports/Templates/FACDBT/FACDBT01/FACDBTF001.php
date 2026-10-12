<?php

namespace App\Exports\Templates\FACDBT\FACDBT01;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;

class FACDBTF001 implements FromArray, WithHeadings, ShouldAutoSize, WithEvents
{
    public function array(): array
    {
        return [];
    }

    public function headings(): array
    {
        return [
            'Location No',
            'Location Group',
            'Site',
            'Warehouse',
            'Location Description',
            'Location Group Description',
        ];
    }

    public function registerEvents(): array
    {
        return [];
    }
}