<?php

namespace App\Exports\Templates\FACDBT\FACDBT01;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;

class FACDBTF004 implements FromArray, WithHeadings, ShouldAutoSize, WithEvents
{
    public function array(): array
    {
        return [];
    }

    public function headings(): array
    {
        return [
            'INV_LIST_NO',
            'SEQ',
            'MONTH',
            'YEAR',
            'CONTRACT',
            'PART_NO',
            'LOCATION_NO',
            'LOT_BATCH_NO',
            'QUANTITY',
            'UNIT_COST',
            'UNIT_MEAS',
            'HANDLING_UNIT_ID',
            'HANDLING_UNIT_TYPE_ID',
            'NPK',
            'VERIFIED',
            'WDR',
            'WAREHOUSE',
            'BAY_NO',
            'ROW_NO',
            'TIER_NO',
            'BIN_NO',
            'AVAILABILITY_CONTROL_ID',
            'AVAILABILITY_CONTROL_DESC',
        ];
    }

    public function registerEvents(): array
    {
        return [];
    }
}