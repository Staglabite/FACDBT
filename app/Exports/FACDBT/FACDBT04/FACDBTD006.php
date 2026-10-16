<?php

namespace App\Exports\FACDBT\FACDBT04;

use App\Exports\FACDBT\FACDBT01\MasterDataExport;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export CSV History Taking (SIM_INV_TAKING_DATA_HISTORY).
 * COLUMNS juga dipakai sebagai kolom DataTable FACDBTD006, jadi tabel dan file selalu sama.
 */
class FACDBTD006
{
    /** @var array<string, string> [kolom database => judul kolom] */
    public const COLUMNS = [
        'time' => 'Time',
        'site' => 'Site',
        'MONTH' => 'Month',
        'YEAR' => 'Year',
        'warehouse' => 'Warehouse',
        'location_group' => 'Location Group',
        'location_no' => 'Location No',
        'product_family' => 'Product Family',
        'accounting_group' => 'Accounting Group',
        'part_no' => 'Part No',
        'part_name' => 'Part Name',
        'qty_book' => 'Qty Bookkeeping',
        'qty_taking' => 'Qty Taking',
        'uom' => 'UoM',
        'LOT_BATCH_NO' => 'Lot Batch No',
        'area' => 'Area',
        'qty_variance' => 'Qty Variance',
        'mat_cost' => 'Mat Cost',
        'lab_cost' => 'Lab Cost',
        'foh_cost' => 'FOH Cost',
        'amount_book' => 'Amount Book',
        'amount_taking' => 'Amount Taking',
        'amount_variance' => 'Amount Variance',
        'item_book' => 'Item Book',
        'item_taking' => 'Item Taking',
        'remark_part_location' => 'Remark Part Location',
        'remark_part_no' => 'Remark Part No',
        'seg' => 'Seg',
        'part_group' => 'Part Group',
    ];

    public static function download(Builder $query): StreamedResponse
    {
        return (new MasterDataExport($query, self::COLUMNS))
            ->download('History_Taking-' . date('YmdHis') . '.csv');
    }
}
