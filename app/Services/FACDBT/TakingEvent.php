<?php

namespace App\Services\FACDBT;

use Carbon\Carbon;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;

/**
 * Event taking (Master Event, FACDBTF006): jenis taking satu periode dan data yang dihitung dashboard.
 *
 * - PRE    (Pre-Taking)            : semua book ditransfer, yang dihitung hanya pasangan Part No + Location No
 *                                    di FACDBT_MSDEVENTPARTS (part yang sama di lokasi lain tidak ikut)
 * - TWH    (Taking TWH / Indirect) : hanya part yang PART_NO-nya mengandung "TW1" (huruf besar/kecil sama)
 * - DIRECT (Taking Besar / Direct) : semua part kecuali yang PART_NO-nya mengandung "TW1"
 * Periode tanpa event: semua data.
 */
class TakingEvent
{
    public const TABLE = FacdbtTable::EVENTS;
    public const PART_TABLE = FacdbtTable::EVENT_PARTS;

    public const PRE = 'PRE';
    public const TWH = 'TWH';
    public const DIRECT = 'DIRECT';

    // Penanda part TWH / Indirect di PART_NO
    public const TWH_PART_MARK = 'TW1';

    /** @var array<string, string> [kode => nama] */
    public const TYPES = [
        self::PRE    => 'Pre-Taking',
        self::TWH    => 'Taking TWH/Indirect',
        self::DIRECT => 'Taking Besar/Direct',
    ];

    public function __construct(
        public readonly int $id,
        public readonly string $type,
    ) {}

    public function name(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /**
     * Event periode dengan TAKING_DATE tersebut; null bila belum ditetapkan.
     */
    public static function forPeriod(Connection $db, ?object $period): ?self
    {
        if (!$period || empty($period->TAKING_DATE) || !self::installed($db)) {
            return null;
        }

        $row = FacdbtTable::active($db, self::TABLE)
            ->whereDate('DTAKINGDATE', Carbon::parse($period->TAKING_DATE)->toDateString())
            ->first(['IID', 'VEVENTTYPE']);

        return $row && isset(self::TYPES[strtoupper((string) $row->VEVENTTYPE)])
            ? new self((int) $row->IID, strtoupper((string) $row->VEVENTTYPE))
            : null;
    }

    /**
     * Tabel event sudah dibuat (docs/FACDBT/facdbt_rename_tables.sql)? Sebelum dibuat, dashboard tetap
     * berjalan seperti tanpa event.
     */
    public static function installed(Connection $db): bool
    {
        return SchemaCheck::hasTable($db, self::TABLE) && SchemaCheck::hasTable($db, self::PART_TABLE);
    }

    /**
     * Batasi query book / taking / revisi ke data event ini.
     *
     * @param  string  $partColumn  kolom PART_NO pada query (mis. 'PART_NO' atau 't.PART_NO');
     *                              kolom lokasi = LOCATION_NO dengan alias yang sama (mis. 't.LOCATION_NO')
     */
    public static function scope(Builder $query, ?self $event, string $partColumn = 'PART_NO'): Builder
    {
        $locationColumn = preg_replace('/PART_NO$/i', 'LOCATION_NO', $partColumn);

        $twh = "'%" . self::TWH_PART_MARK . "%'";

        return match ($event?->type) {
            self::TWH    => $query->whereRaw("UPPER({$partColumn}) LIKE {$twh}"),
            self::DIRECT => $query->whereRaw("UPPER({$partColumn}) NOT LIKE {$twh}"),
            self::PRE    => self::prePartScope($query, $event, $partColumn, $locationColumn),
            default      => $query,
        };
    }

    /**
     * Pre-Taking: (PART_NO, LOCATION_NO) ada di daftar part event. Row constructor IN dipakai supaya MySQL lama
     * memakai semijoin, bukan subquery per baris.
     */
    private static function prePartScope(Builder $query, self $event, string $partColumn, string $locationColumn): Builder
    {
        $list = $query->newQuery()
            ->from(self::PART_TABLE)
            ->select('VPARTNO', 'VLOCATIONNO')
            ->where('IEVENTID', $event->id)
            ->whereNull(FacdbtTable::DELETED_AT);

        return $query->whereRaw("({$partColumn}, {$locationColumn}) IN ({$list->toSql()})", $list->getBindings());
    }

    /**
     * Penanda perubahan event & daftar part untuk versi data dashboard.
     */
    public static function signature(Connection $db, ?object $period): string
    {
        $event = self::forPeriod($db, $period);
        if (!$event) {
            return 'none';
        }

        // Daftar part aktif: jumlah, IID terbesar, dan waktu tambah / ubah terakhir (daftar yang diganti / dipulihkan)
        $parts = $event->type === self::PRE
            ? FacdbtTable::active($db, self::PART_TABLE)
                ->where('IEVENTID', $event->id)
                ->selectRaw('COUNT(*) as n, MAX(IID) as last, MAX(COALESCE(DMODI, DCREA)) as changed')
                ->first()
            : null;

        return implode(':', [$event->id, $event->type, $parts->n ?? 0, $parts->last ?? 0, $parts->changed ?? '']);
    }
}
