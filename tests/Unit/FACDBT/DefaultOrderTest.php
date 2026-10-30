<?php

namespace Tests\Unit\FACDBT;

use App\DataTables\Original\FACDBT01\Concerns\AppliesDefaultOrder;
use PHPUnit\Framework\TestCase;

/**
 * Urutan bawaan tabel master hanya dipakai bila user belum mengklik judul kolom
 * (parameter seperti yang dikirim minifiedAjax(): orderable=true tidak dikirim).
 */
class DefaultOrderTest extends TestCase
{
    use AppliesDefaultOrder;

    private const COLUMNS = [
        ['data' => 'checkbox', 'orderable' => 'false', 'searchable' => 'false'],
        ['data' => 'location_no'],
        ['data' => 'site'],
    ];

    public function test_header_click_uses_clicked_column(): void
    {
        $this->assertTrue(self::requestHasColumnOrder(self::COLUMNS, [['column' => '2', 'dir' => 'desc']]));
        $this->assertTrue(self::requestHasColumnOrder([['data' => 'x', 'orderable' => 'true']], [['column' => '0', 'dir' => 'asc']]));
    }

    public function test_initial_load_uses_default_order(): void
    {
        // Urutan awal DataTables ke kolom checkbox (tidak bisa diurutkan) / tanpa urutan
        $this->assertFalse(self::requestHasColumnOrder(self::COLUMNS, [['column' => '0', 'dir' => 'asc']]));
        $this->assertFalse(self::requestHasColumnOrder(self::COLUMNS, []));
        $this->assertFalse(self::requestHasColumnOrder(self::COLUMNS, [['column' => '9', 'dir' => 'asc']]));
    }
}
