<?php

namespace App\Services\FACDBT;

/**
 * Perhitungan Absolute Variance Dashboard (FACDBTD004), dipakai controller (ringkasan & chart)
 * dan DataTable (detail part per lokasi).
 *
 * Variance dihitung per (lokasi, part): qty taking - qty book, amount = variance qty x unit cost.
 * - Nett              : jumlah variance (plus & minus saling menutup), juga per area
 * - Positive / Negative: variance per (lokasi, part) yang lebih / kurang; negative bertanda minus
 * - Absolute          : positive + |negative|, sehingga lebih di satu lokasi tidak menutup kurang di lokasi lain
 * Tabel detail: satu baris per (part, lokasi); tabel biru diurutkan dari part dengan variance plus
 * terbesar, tabel merah dari part dengan variance minus terbesar.
 */
class AbsoluteVarianceService extends VarianceDashboardService
{
    protected function cachePrefix(): string
    {
        return 'FACDBTD004:data:v8';
    }

    protected function emptyResult(): array
    {
        return [
            'nett_qty'        => 0,
            'nett_amount'     => 0,
            'absolute_qty'    => 0,
            'absolute_amount' => 0,
            'by_area'         => [],
            'positive'        => ['qty' => 0, 'amount' => 0, 'rows' => []],
            'negative'        => ['qty' => 0, 'amount' => 0, 'rows' => []],
        ];
    }

    protected function build(iterable $lines, array $names): array
    {
        $perArea  = [];
        $positive = [];
        $negative = [];
        $nettQty  = 0.0;
        $nettAmt  = 0.0;

        foreach ($lines as [$location, $part, $qtyBook, $qtyTaking, $unitCost, $area]) {
            $varQty = $qtyTaking - $qtyBook;
            $varAmt = $varQty * $unitCost;

            // Tabel hanya untuk variance + / -: lokasi-part dengan Var Qty 0 tidak ditampilkan
            if (round($varQty, 2) == 0) {
                continue;
            }

            $perArea[$area] = ($perArea[$area] ?? 0.0) + $varAmt;
            $nettQty += $varQty;
            $nettAmt += $varAmt;

            $line = ['part_no' => (string) $part, 'location_no' => (string) $location, 'qty' => $varQty, 'amount' => $varAmt];
            if ($this->isNegative($varQty, $varAmt)) {
                $negative[] = $line;
            } else {
                $positive[] = $line;
            }
        }

        $positiveDetail = self::locationDetail($positive, $names, false);
        $negativeDetail = self::locationDetail($negative, $names, true);

        return [
            'nett_qty'        => round($nettQty, 2),
            'nett_amount'     => round($nettAmt, 2),
            'absolute_qty'    => round($positiveDetail['qty'] - $negativeDetail['qty'], 2),
            'absolute_amount' => round($positiveDetail['amount'] - $negativeDetail['amount'], 2),
            'by_area'         => $this->areaSeries($perArea),
            'positive'        => $positiveDetail,
            'negative'        => $negativeDetail,
        ];
    }

    /**
     * Baris detail per (part, lokasi), dikelompokkan per part.
     * Positive: part dengan total variance terbesar dulu; negative: part paling minus dulu.
     *
     * @param  array<int, array{part_no: string, location_no: string, qty: float, amount: float}>  $lines
     * @return array{qty: float, amount: float, rows: array<int, array>}
     */
    public static function locationDetail(array $lines, array $names, bool $negative): array
    {
        $partTotals = [];
        foreach ($lines as $line) {
            $partTotals[$line['part_no']] = ($partTotals[$line['part_no']] ?? 0.0) + $line['amount'];
        }

        $direction = $negative ? 1 : -1;
        usort($lines, fn ($a, $b) => $direction * ($partTotals[$a['part_no']] <=> $partTotals[$b['part_no']])
            ?: strcmp($a['part_no'], $b['part_no'])
            ?: $direction * ($a['amount'] <=> $b['amount'])
            ?: strcmp($a['location_no'], $b['location_no']));

        $rows = array_map(fn ($line) => [
            'part_no'     => $line['part_no'],
            'name'        => $names[$line['part_no']] ?? 'UNKNOWN',
            'location_no' => $line['location_no'],
            'qty'         => round($line['qty'], 2),
            'amount'      => round($line['amount'], 2),
        ], $lines);

        return [
            'qty'    => round(array_sum(array_column($lines, 'qty')), 2),
            'amount' => round(array_sum(array_column($lines, 'amount')), 2),
            'rows'   => $rows,
        ];
    }
}
