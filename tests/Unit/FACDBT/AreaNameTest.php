<?php

namespace Tests\Unit\FACDBT;

use App\Services\FACDBT\AreaName;
use PHPUnit\Framework\TestCase;

/**
 * Penggabungan location_group menjadi area (QA-INC & QA-INH -> QA).
 */
class AreaNameTest extends TestCase
{
    public function test_merged_groups(): void
    {
        $this->assertSame('QA', AreaName::of('QA-INC'));
        $this->assertSame('QA', AreaName::of(' qa-inh '));
        $this->assertSame('PPC-D', AreaName::of(' PPC-D '));
        $this->assertSame('', AreaName::of(null));
    }

    public function test_list_is_unique_and_sorted(): void
    {
        $this->assertSame(['PPC-D', 'QA'], AreaName::list(['QA-INH', 'PPC-D', 'QA-INC', '', null, 'QA-INC']));
    }

    public function test_groups_of_area(): void
    {
        $this->assertSame(['QA', 'QA-INC', 'QA-INH'], AreaName::groupsOf('QA'));
        $this->assertSame(['PPC-D'], AreaName::groupsOf('PPC-D'));
    }
}
