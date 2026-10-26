<?php

namespace App\Services\FACDBT;

/**
 * Nama area dari SIM_INV_GROUP_LOCATION.location_group, dengan beberapa group digabung menjadi satu area.
 * Dipakai semua dashboard FACDBT04 supaya area yang ditampilkan dan difilter selalu sama.
 */
class AreaName
{
    /** @var array<string, string> [location_group => area gabungan] */
    public const MERGE = [
        'QA-INC' => 'QA',
        'QA-INH' => 'QA',
    ];

    /**
     * location_group -> nama area (sudah di-trim); kosong tetap '' supaya pemanggil bisa membedakannya.
     */
    public static function of(?string $group): string
    {
        $group = trim((string) $group);

        return self::MERGE[strtoupper($group)] ?? $group;
    }

    /**
     * Ekspresi SQL yang sama dengan of() untuk kolom $column.
     */
    public static function sql(string $column): string
    {
        $cases = '';
        foreach (self::MERGE as $from => $to) {
            $cases .= " WHEN UPPER(TRIM({$column})) = '{$from}' THEN '{$to}'";
        }

        return "CASE{$cases} ELSE TRIM({$column}) END";
    }

    /**
     * Semua location_group yang digabung ke $area (untuk filter per area di SQL), termasuk $area sendiri.
     *
     * @return array<int, string>
     */
    public static function groupsOf(string $area): array
    {
        $area = trim($area);

        return array_values(array_unique([$area, ...array_keys(self::MERGE, strtoupper($area), true)]));
    }

    /**
     * Daftar area unik (terurut) dari daftar location_group.
     *
     * @param  iterable<string|null>  $groups
     * @return array<int, string>
     */
    public static function list(iterable $groups): array
    {
        $areas = [];
        foreach ($groups as $group) {
            $area = self::of($group);
            if ($area !== '') {
                $areas[$area] = true;
            }
        }

        $areas = array_map('strval', array_keys($areas));
        sort($areas, SORT_NATURAL | SORT_FLAG_CASE);

        return $areas;
    }
}
