<?php

namespace App\Exports\Templates\HITUAM\HITUAM01;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;

class HITUAMF002 implements FromArray, WithHeadings, ShouldAutoSize, WithEvents
{
    public function array(): array
    {
        return [];
    }

    public function headings(): array
    {
        return [
            'App ID',
            'Name',
            'Description',
            'Flag',
            'URL',
            'Icon',
            'Order',
            'Parent Menu',
            'Application Name',
            'Type',
            'Env App',
        ];
    }

    public function registerEvents(): array
    {
        return [];
    }
}
