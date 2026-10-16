<?php

namespace App\Console\Commands\FACDBT;

use App\Services\FACDBT\TakingHistoryService;
use Illuminate\Console\Command;

/**
 * Simpan hasil taking periode yang sudah ditutup ke SIM_INV_TAKING_DATA_HISTORY (FACDBTD006).
 *
 *   php artisan facdbt:taking-history                          -> semua periode tertutup yang belum ada di history
 *   php artisan facdbt:taking-history --taking-date=2026-03-06 -> periode tertentu (history-nya dibuat ulang)
 */
class SnapshotTakingHistory extends Command
{
    protected $signature = 'facdbt:taking-history {--taking-date= : TAKING_DATE periode yang dibuat ulang (Y-m-d)}';

    protected $description = 'Simpan hasil taking periode yang sudah ditutup ke history (SIM_INV_TAKING_DATA_HISTORY)';

    public function handle(TakingHistoryService $service): int
    {
        ini_set('memory_limit', '1024M');

        if ($takingDate = $this->option('taking-date')) {
            $period = $service->findPeriod($takingDate);
            if (!$period) {
                $this->error("Periode dengan TAKING_DATE {$takingDate} tidak ditemukan.");

                return self::FAILURE;
            }
            $periods = [$period];
        } else {
            $periods = $service->pendingPeriods();
        }

        if (empty($periods)) {
            $this->info('Tidak ada periode tertutup yang perlu disimpan ke history.');

            return self::SUCCESS;
        }

        foreach ($periods as $period) {
            $count = $service->snapshot($period, 'SYSTEM');
            $this->info("Periode {$period->MONTH}/{$period->YEAR} (taking {$period->TAKING_DATE}): {$count} baris history disimpan.");
        }

        return self::SUCCESS;
    }
}
