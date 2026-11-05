<?php

namespace App\Services\FACDBT;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;

/**
 * Nama tabel milik FACDBT (Design Architecture Guidance Database, lihat docs/FACDBT/facdbt_rename_tables.sql).
 * Semua tabel punya kolom audit VCREA, DCREA, VMODI, DMODI, VDELETE, DDELETE; baris dengan DDELETE terisi
 * sudah dihapus (soft delete) dan tidak dipakai.
 */
final class FacdbtTable
{
    public const LOCATIONS       = 'FACDBT_MSHLOCATIONS';
    public const PARTS           = 'FACDBT_MSHPARTS';
    public const UNIT_COSTS      = 'FACDBT_MSHUNITCOSTS';
    public const GROUP_LOCATIONS = 'FACDBT_MSHGROUPLOCATIONS';
    public const EVENTS          = 'FACDBT_MSHEVENTS';
    public const EVENT_PARTS     = 'FACDBT_MSDEVENTPARTS';
    public const HISTORY         = 'FACDBT_HISTAKINGDATAS';

    public const DELETED_AT = 'DDELETE';
    public const DELETED_BY = 'VDELETE';

    /**
     * Query baris yang belum dihapus.
     */
    public static function active(Connection $db, string $table, ?string $alias = null): Builder
    {
        return $db->table($alias ? "{$table} as {$alias}" : $table)
            ->whereNull(($alias ? "{$alias}." : '') . self::DELETED_AT);
    }

    /**
     * Nilai kolom soft delete untuk update massal.
     *
     * @return array{DDELETE: string, VDELETE: string}
     */
    public static function deleteValues(?string $user = null): array
    {
        return [self::DELETED_AT => now()->format('Y-m-d H:i:s'), self::DELETED_BY => $user ?? self::user()];
    }

    /**
     * Username untuk kolom audit: user login, atau SYSTEM (command / scheduler).
     */
    public static function user(): string
    {
        return (string) (auth()->user()->VUSERNAME ?? 'SYSTEM');
    }
}
