<?php

namespace App\Services\FACDBT;

use Carbon\Carbon;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;

/**
 * Event taking (Master Event, FACDBTF006): jenis taking satu periode dan data yang dihitung dashboard.
 *
 * - PRE    (Pre-Taking)            : semua book ditransfer, yang dihitung hanya part di SIM_INV_TAKING_EVENT_PART
 * - TWH    (Taking TWH / Indirect) : hanya lokasi yang LOCATION_NO-nya mengandung "TWH" (huruf besar/kecil sama)
 * - DIRECT (Taking Besar / Direct) : semua lokasi kecuali yang mengandung "TWH"
 * Periode tanpa event: semua data.
 */
class TakingEvent
{
    public const TABLE = 'SIM_INV_TAKING_EVENT';
    public const PART_TABLE = 'SIM_INV_TAKING_EVENT_PART';

    public const PRE = 'PRE';
    public const TWH = 'TWH';
    public const DIRECT = 'DIRECT';

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

        $row = $db->table(self::TABLE)
            ->whereDate('TAKING_DATE', Carbon::parse($period->TAKING_DATE)->toDateString())
            ->first(['IID', 'EVENT_TYPE']);

        return $row && isset(self::TYPES[strtoupper((string) $row->EVENT_TYPE)])
            ? new self((int) $row->IID, strtoupper((string) $row->EVENT_TYPE))
            : null;
    }

    /**
     * Tabel event sudah dibuat (docs/FACDBT/sim_inv_taking_event.sql)? Sebelum dibuat, dashboard tetap
     * berjalan seperti tanpa event.
     */
    public static function installed(Connection $db): bool
    {
        static $installed = [];

        return $installed[$db->getName()] ??= $db->getSchemaBuilder()->hasTable(self::TABLE)
            && $db->getSchemaBuilder()->hasTable(self::PART_TABLE);
    }

    /**
     * Batasi query book / taking / revisi ke data event ini.
     *
     * @param  string  $locationColumn  kolom LOCATION_NO pada query (mis. 'LOCATION_NO' atau 't.LOCATION_NO')
     * @param  string  $partColumn      kolom PART_NO pada query
     */
    public static function scope(Builder $query, ?self $event, string $locationColumn = 'LOCATION_NO', string $partColumn = 'PART_NO'): Builder
    {
        return match ($event?->type) {
            self::TWH    => $query->whereRaw("UPPER({$locationColumn}) LIKE '%TWH%'"),
            self::DIRECT => $query->whereRaw("UPPER({$locationColumn}) NOT LIKE '%TWH%'"),
            self::PRE    => $query->whereIn($partColumn, fn ($q) => $q->from(self::PART_TABLE)
                ->select('PART_NO')
                ->where('EVENT_ID', $event->id)),
            default      => $query,
        };
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

        $parts = $event->type === self::PRE
            ? $db->table(self::PART_TABLE)->where('EVENT_ID', $event->id)->selectRaw('COUNT(*) as n, MAX(IID) as last')->first()
            : null;

        return implode(':', [$event->id, $event->type, $parts->n ?? 0, $parts->last ?? 0]);
    }
}
