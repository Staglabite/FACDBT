<?php

namespace App\Exports\Templates\HITUAM\HITUAM01;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;

class HITUAMF005 implements FromArray, WithHeadings, ShouldAutoSize, WithEvents
{
    public function array(): array
    {
        return [];
    }

    public function headings(): array
    {
        return [
            'User Type',
            'Username',
            'Email',
            'NPK',
            'Password',
            'Role Names',
            'Supplier',
            'User Supplier',
        ];
    }

    public function registerEvents(): array
    {
        return [];
    }
}
