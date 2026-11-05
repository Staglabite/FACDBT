<?php

namespace App\Exports\Templates\HITUAM\HITUAM01;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;

class HITUAMF003 implements FromArray, WithHeadings, ShouldAutoSize, WithEvents
{

    public function array(): array
    {
        return [];
    }

    public function headings(): array
    {
        return [
            'Service Name',
            'Service Description',
            'Service URL',
            'HTTP Method',
            'Application Name',
            'Menu Name',
            'Begin Effective Date (YYYY-MM-DD)',
            'End Effective Date (YYYY-MM-DD)',
        ];
    }

    public function registerEvents(): array
    {
        return [];
    }
}
