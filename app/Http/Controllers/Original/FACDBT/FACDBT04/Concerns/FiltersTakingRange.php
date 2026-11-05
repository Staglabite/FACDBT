<?php

namespace App\Http\Controllers\Original\FACDBT\FACDBT04\Concerns;

use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Filter rentang waktu taking (Dari - Sampai) untuk dashboard FACDBT04.
 * Hanya data taking yang di-scan di rentang ini yang dihitung; book tetap penuh.
 * Tanggal tidak diketik pengguna: dipilih dari hari-hari periode taking (TAKING_DATE s/d CLOSE_DATE).
 */
trait FiltersTakingRange
{
    /**
     * Pilihan hari periode taking, contoh [['value' => '2026-03-06', 'label' => 'Hari 1 (6 Mar)'], ...].
     * Taking 1 hari menghasilkan satu pilihan (pilihan hari disembunyikan di tampilan).
     */
    private function timeRangePayload(string $takingDate, string $closeDate): array
    {
        $day  = Carbon::parse($takingDate)->startOfDay();
        $last = Carbon::parse($closeDate)->startOfDay();

        $days = [];
        for ($n = 1; $day->lte($last) && $n <= 7; $n++, $day->addDay()) {
            $days[] = [
                'value' => $day->format('Y-m-d'),
                'label' => "Hari {$n} (" . $day->format('j M') . ')',
            ];
        }

        return ['days' => $days];
    }

    /**
     * Rentang [dari, sampai] dari request `from` / `to` (format "Y-m-d H:i"), dibatasi ke periode taking.
     * Tanpa filter: seluruh periode taking. "Sampai" mencakup seluruh menit tersebut (detik 59).
     *
     * @return array{0: string, 1: string}
     */
    private function takingRange(Request $request, string $takingDate, string $closeDate): array
    {
        $from = $this->parseRangeMinute($request->get('from'));
        $to   = $this->parseRangeMinute($request->get('to'));

        if ($from !== null && $to !== null && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        $from = $from !== null ? "{$from}:00" : $takingDate;
        $to   = $to !== null ? "{$to}:59" : $closeDate;

        return [max($from, $takingDate), min($to, $closeDate)];
    }

    /**
     * "2026-03-06 13:00" -> "2026-03-06 13:00" (tanpa detik); nilai tidak valid -> null.
     */
    private function parseRangeMinute($value): ?string
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
