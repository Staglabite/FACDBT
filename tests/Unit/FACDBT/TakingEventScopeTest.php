<?php

namespace Tests\Unit\FACDBT;

use App\Services\FACDBT\TakingEvent;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;
use PHPUnit\Framework\TestCase;

/**
 * Data yang dihitung dashboard per event (Master Event): Pre-Taking = pasangan part + lokasi di daftar event,
 * TWH = PART_NO mengandung "TW1" (huruf besar / kecil sama), Direct = selain itu, tanpa event = semua.
 */
class TakingEventScopeTest extends TestCase
{
    private function db(string $name, bool $withEventTables = true): Connection
    {
        $capsule = new Capsule;
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:'], $name);
        $db = $capsule->getConnection($name);

        $db->statement('CREATE TABLE SIM_INV_TAKING_DATA_TAB (LOCATION_NO TEXT, PART_NO TEXT)');
        $db->table('SIM_INV_TAKING_DATA_TAB')->insert([
            ['LOCATION_NO' => 'TWH-01', 'PART_NO' => 'P1'],
            ['LOCATION_NO' => 'L-02', 'PART_NO' => 'ab-tw1-9'],
            ['LOCATION_NO' => 'PROD-1A', 'PART_NO' => 'P1'],
            ['LOCATION_NO' => 'QA-01', 'PART_NO' => 'TW1-55'],
        ]);

        if ($withEventTables) {
            $audit = 'VCREA TEXT, DCREA TEXT, VMODI TEXT, DMODI TEXT, VDELETE TEXT, DDELETE TEXT';
            $db->statement("CREATE TABLE FACDBT_MSHEVENTS (IID INTEGER PRIMARY KEY, DTAKINGDATE TEXT, VEVENTTYPE TEXT, {$audit})");
            $db->statement("CREATE TABLE FACDBT_MSDEVENTPARTS (IID INTEGER PRIMARY KEY, IEVENTID INTEGER, VPARTNO TEXT, VLOCATIONNO TEXT, {$audit})");
            $db->table('FACDBT_MSHEVENTS')->insert([
                ['IID' => 1, 'DTAKINGDATE' => '2026-03-02', 'VEVENTTYPE' => 'PRE', 'DCREA' => '2026-03-01 08:00:00', 'DDELETE' => null],
                ['IID' => 2, 'DTAKINGDATE' => '2026-03-05', 'VEVENTTYPE' => 'twh', 'DCREA' => '2026-03-01 08:00:00', 'DDELETE' => null],
                // Event yang sudah dihapus (soft delete) tidak dipakai
                ['IID' => 3, 'DTAKINGDATE' => '2026-04-01', 'VEVENTTYPE' => 'TWH', 'DCREA' => '2026-03-01 08:00:00', 'DDELETE' => '2026-03-20 10:00:00'],
            ]);
            $db->table('FACDBT_MSDEVENTPARTS')->insert([
                ['IEVENTID' => 1, 'VPARTNO' => 'P1', 'VLOCATIONNO' => 'PROD-1A', 'DCREA' => '2026-03-01 08:00:00', 'DDELETE' => null],
                ['IEVENTID' => 1, 'VPARTNO' => 'TW1-55', 'VLOCATIONNO' => 'QA-01', 'DCREA' => '2026-03-01 08:00:00', 'DDELETE' => '2026-03-01 09:00:00'],
                ['IEVENTID' => 2, 'VPARTNO' => 'P3', 'VLOCATIONNO' => 'L-02', 'DCREA' => '2026-03-01 08:00:00', 'DDELETE' => null],
                // Part di daftar tetapi lokasinya tidak ada di data
                ['IEVENTID' => 1, 'VPARTNO' => 'ab-tw1-9', 'VLOCATIONNO' => 'PROD-2A', 'DCREA' => '2026-03-01 08:00:00', 'DDELETE' => '2026-03-01 09:00:00'],
            ]);
        }

        return $db;
    }

    private function rows(Connection $db, ?TakingEvent $event): array
    {
        return TakingEvent::scope($db->table('SIM_INV_TAKING_DATA_TAB'), $event)
            ->orderBy('LOCATION_NO')
            ->get()
            ->map(fn ($r) => "{$r->LOCATION_NO}/{$r->PART_NO}")
            ->all();
    }

    public function test_scope_per_event_type(): void
    {
        $db = $this->db('event_scope');

        $this->assertSame(['L-02/ab-tw1-9', 'PROD-1A/P1', 'QA-01/TW1-55', 'TWH-01/P1'], $this->rows($db, null));
        // Lokasi "TWH" tidak berpengaruh; yang dilihat PART_NO
        $this->assertSame(['L-02/ab-tw1-9', 'QA-01/TW1-55'], $this->rows($db, new TakingEvent(2, TakingEvent::TWH)));
        $this->assertSame(['PROD-1A/P1', 'TWH-01/P1'], $this->rows($db, new TakingEvent(2, TakingEvent::DIRECT)));
        // Hanya pasangan part + lokasi aktif di daftar event 1: P1 di TWH-01 tidak ikut, TW1-55 sudah dihapus dari daftar
        $this->assertSame(['PROD-1A/P1'], $this->rows($db, new TakingEvent(1, TakingEvent::PRE)));
        // Kolom dengan alias: lokasi memakai alias yang sama
        $this->assertSame(['PROD-1A/P1'], TakingEvent::scope($db->table('SIM_INV_TAKING_DATA_TAB as t'), new TakingEvent(1, TakingEvent::PRE), 't.PART_NO')
            ->get()->map(fn ($r) => "{$r->LOCATION_NO}/{$r->PART_NO}")->all());
    }

    public function test_event_of_period(): void
    {
        $db = $this->db('event_period');

        $pre = TakingEvent::forPeriod($db, (object) ['TAKING_DATE' => '2026-03-02 07:00:00']);
        $this->assertSame([1, TakingEvent::PRE, 'Pre-Taking'], [$pre->id, $pre->type, $pre->name()]);

        // Kode event disimpan huruf kecil tetap dikenali
        $this->assertSame(TakingEvent::TWH, TakingEvent::forPeriod($db, (object) ['TAKING_DATE' => '2026-03-05'])->type);
        // Event periode 2026-04-01 sudah dihapus
        $this->assertNull(TakingEvent::forPeriod($db, (object) ['TAKING_DATE' => '2026-04-01']));
        $this->assertNull(TakingEvent::forPeriod($db, null));

        $this->assertSame('1:PRE:1:1:2026-03-01 08:00:00', TakingEvent::signature($db, (object) ['TAKING_DATE' => '2026-03-02']));
        $this->assertSame('none', TakingEvent::signature($db, (object) ['TAKING_DATE' => '2026-04-01']));
    }

    public function test_without_event_tables_dashboards_show_all_data(): void
    {
        $db = $this->db('event_missing', false);

        $this->assertFalse(TakingEvent::installed($db));
        $this->assertNull(TakingEvent::forPeriod($db, (object) ['TAKING_DATE' => '2026-03-02']));
        $this->assertSame('none', TakingEvent::signature($db, (object) ['TAKING_DATE' => '2026-03-02']));
    }
}
