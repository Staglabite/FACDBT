<?php

namespace App\Services\FACDBT;

use Illuminate\Database\Connection;

/**
 * Cek tabel / kolom ada di database, untuk fitur yang aktif setelah script SQL dijalankan
 * (mis. docs/FACDBT/sim_inv_taking_event.sql).
 *
 * Tidak memakai Schema::hasColumn(): di Laravel 12 method itu membaca information_schema.columns.generation_expression
 * yang tidak ada di MySQL / MariaDB versi lama (error 1054). Di SQLite (unit test) memakai schema builder biasa.
 * Hasil disimpan per request.
 */
class SchemaCheck
{
    /** @var array<string, bool> */
    private static array $cache = [];

    public static function hasTable(Connection $db, string $table): bool
    {
        return self::$cache["{$db->getName()}|{$table}"] ??= $db->getDriverName() === 'sqlite'
            ? $db->getSchemaBuilder()->hasTable($table)
            : $db->table('information_schema.tables')
                ->whereRaw('table_schema = DATABASE()')
                ->where('table_name', $table)
                ->exists();
    }

    public static function hasColumn(Connection $db, string $table, string $column): bool
    {
        return self::$cache["{$db->getName()}|{$table}|{$column}"] ??= $db->getDriverName() === 'sqlite'
            ? $db->getSchemaBuilder()->hasColumn($table, $column)
            : $db->table('information_schema.columns')
                ->whereRaw('table_schema = DATABASE()')
                ->where('table_name', $table)
                ->where('column_name', $column)
                ->exists();
    }
}
