<?php

namespace App\Exports\FACDBT\FACDBT04;

use App\Exports\FACDBT\FACDBT01\MasterDataExport;
use App\Services\FACDBT\TakingEvent;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export CSV History Taking (FACDBT_HISTAKINGDATAS).
 * COLUMNS juga dipakai sebagai kolom DataTable FACDBTD005, jadi tabel dan file selalu sama.
 */
class FACDBTD005
{
    /** @var array<string, string> [kolom database => judul kolom] */
    public const COLUMNS = [
        'DTAKINGTIME' => 'Time',
        'VSITE' => 'Site',
        'IMONTH' => 'Month',
        'IYEAR' => 'Year',
        'event' => 'Event',
        'VWAREHOUSE' => 'Warehouse',
        'VLOCGROUP' => 'Location Group',
        'VLOCATIONNO' => 'Location No',
        'VPRODFAMILY' => 'Product Family',
        'VACCGROUP' => 'Accounting Group',
        'VPARTNO' => 'Part No',
        'VPARTNAME' => 'Part Name',
        'NQTYBOOK' => 'Qty Bookkeeping',
        'NQTYTAKING' => 'Qty Taking',
        'VUOM' => 'UoM',
        'VLOTBATCHNO' => 'Lot Batch No',
        'VAREA' => 'Area',
        'NQTYVARIANCE' => 'Qty Variance',
        'MMATCOST' => 'Mat Cost',
        'MLABCOST' => 'Lab Cost',
        'MFOHCOST' => 'FOH Cost',
        'MAMOUNTBOOK' => 'Amount Book',
        'MAMOUNTTAKING' => 'Amount Taking',
        'MAMOUNTVARIANCE' => 'Amount Variance',
        'BITEMBOOK' => 'Item Book',
        'BITEMTAKING' => 'Item Taking',
        'VREMARKPARTLOC' => 'Remark Part Location',
        'VREMARKPARTNO' => 'Remark Part No',
        'VSEG' => 'Seg',
        'VPARTGROUP' => 'Part Group',
        'progress' => 'Progress %',
    ];

    /** Progress % = qty taking / qty book x 100; kosong bila qty book 0 */
    public const PROGRESS_SQL = 'CASE WHEN NQTYBOOK <> 0 THEN ROUND(NQTYTAKING / NQTYBOOK * 100, 2) END';

    /**
     * Nama event (Master Event) dari kolom VEVENTTYPE; kosong bila periode tanpa event.
     */
    public static function eventSql(): string
    {
        $cases = collect(TakingEvent::TYPES)->map(fn ($name, $code) => "WHEN '{$code}' THEN '{$name}'")->implode(' ');

        return "CASE VEVENTTYPE {$cases} END";
    }

    public static function download(Builder $query): StreamedResponse
    {
        $expressions = [
            'event'    => self::eventSql(),
            'progress' => self::PROGRESS_SQL,
        ];

        return (new MasterDataExport($query, self::COLUMNS, $expressions))
            ->download('History_Taking-' . date('YmdHis') . '.csv');
    }
}
