<?php

namespace Tests\Unit\FACDBT;

use App\Services\FACDBT\TakingEvent;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;
use PHPUnit\Framework\TestCase;

/**
 * Data yang dihitung dashboard per event (Master Event): Pre-Taking = part di daftar event,
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
            $db->statement('CREATE TABLE SIM_INV_TAKING_EVENT (IID INTEGER PRIMARY KEY, TAKING_DATE TEXT, EVENT_TYPE TEXT)');
            $db->statement('CREATE TABLE SIM_INV_TAKING_EVENT_PART (IID INTEGER PRIMARY KEY, EVENT_ID INTEGER, PART_NO TEXT)');
            $db->table('SIM_INV_TAKING_EVENT')->insert([
                ['IID' => 1, 'TAKING_DATE' => '2026-03-02', 'EVENT_TYPE' => 'PRE'],
                ['IID' => 2, 'TAKING_DATE' => '2026-03-05', 'EVENT_TYPE' => 'twh'],
            ]);
            $db->table('SIM_INV_TAKING_EVENT_PART')->insert([
                ['EVENT_ID' => 1, 'PART_NO' => 'P1'],
                ['EVENT_ID' => 2, 'PART_NO' => 'P3'],
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
        // Hanya part di daftar event 1
        $this->assertSame(['PROD-1A/P1', 'TWH-01/P1'], $this->rows($db, new TakingEvent(1, TakingEvent::PRE)));
    }

    public function test_event_of_period(): void
    {
        $db = $this->db('event_period');

        $pre = TakingEvent::forPeriod($db, (object) ['TAKING_DATE' => '2026-03-02 07:00:00']);
        $this->assertSame([1, TakingEvent::PRE, 'Pre-Taking'], [$pre->id, $pre->type, $pre->name()]);

        // Kode event disimpan huruf kecil tetap dikenali
        $this->assertSame(TakingEvent::TWH, TakingEvent::forPeriod($db, (object) ['TAKING_DATE' => '2026-03-05'])->type);
        $this->assertNull(TakingEvent::forPeriod($db, (object) ['TAKING_DATE' => '2026-04-01']));
        $this->assertNull(TakingEvent::forPeriod($db, null));

        $this->assertSame('1:PRE:1:1', TakingEvent::signature($db, (object) ['TAKING_DATE' => '2026-03-02']));
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
