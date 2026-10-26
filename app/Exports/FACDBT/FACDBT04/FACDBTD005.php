<?php

namespace App\Exports\FACDBT\FACDBT04;

use App\Exports\FACDBT\FACDBT01\MasterDataExport;
use App\Services\FACDBT\TakingEvent;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export CSV History Taking (SIM_INV_TAKING_DATA_HISTORY).
 * COLUMNS juga dipakai sebagai kolom DataTable FACDBTD005, jadi tabel dan file selalu sama.
 */
class FACDBTD005
{
    /** @var array<string, string> [kolom database => judul kolom] */
    public const COLUMNS = [
        'time' => 'Time',
        'site' => 'Site',
        'MONTH' => 'Month',
        'YEAR' => 'Year',
        'event' => 'Event',
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
        'progress' => 'Progress %',
    ];

    /** Progress % = qty taking / qty book x 100; kosong bila qty book 0 */
    public const PROGRESS_SQL = 'CASE WHEN qty_book <> 0 THEN ROUND(qty_taking / qty_book * 100, 2) END';

    /**
     * Nama event (Master Event) dari kolom EVENT_TYPE; kosong bila kolom belum ditambahkan
     * (docs/FACDBT/sim_inv_taking_event.sql) atau periode tanpa event.
     */
    public static function eventSql(bool $hasEventColumn): string
    {
        if (!$hasEventColumn) {
            return 'NULL';
        }

        $cases = collect(TakingEvent::TYPES)->map(fn ($name, $code) => "WHEN '{$code}' THEN '{$name}'")->implode(' ');

        return "CASE EVENT_TYPE {$cases} END";
    }

    public static function download(Builder $query): StreamedResponse
    {
        $expressions = [
            'event'    => self::eventSql($query->getModel()->hasEventColumn()),
            'progress' => self::PROGRESS_SQL,
        ];

        return (new MasterDataExport($query, self::COLUMNS, $expressions))
            ->download('History_Taking-' . date('YmdHis') . '.csv');
    }
}
