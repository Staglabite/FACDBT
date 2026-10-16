<?php

namespace App\Services\FACDBT;

/**
 * Perhitungan Absolute Variance Dashboard (FACDBTD005), dipakai controller (ringkasan & chart)
 * dan DataTable (detail part lebih / kurang).
 *
 * Variance dihitung per (lokasi, part): qty taking - qty book, amount = variance qty x unit cost.
 * Absolute variance = jumlah ABS(variance) per (lokasi, part), sehingga lebih di satu lokasi
 * tidak menutup kurang di lokasi lain.
 * - "positive" : variance lebih (taking > book), dijumlah per part
 * - "negative" : variance kurang (taking < book), dijumlah per part dalam nilai absolut
 * Absolute variance = positive + negative.
 */
class AbsoluteVarianceService extends VarianceDashboardService
{
    protected function cachePrefix(): string
    {
        return 'FACDBTD005:data:v2';
    }

    protected function emptyResult(): array
    {
        return [
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
        foreach ($lines as [, $part, $qtyBook, $qtyTaking, $unitCost, $area]) {
            $varQty = $qtyTaking - $qtyBook;
            $varAmt = $varQty * $unitCost;

            if ($varQty == 0 && $varAmt == 0) {
                continue;
            }

            $perArea[$area] = ($perArea[$area] ?? 0.0) + abs($varAmt);

            if ($this->isNegative($varQty, $varAmt)) {
                $this->addAbsolute($negative, $part, $varQty, $varAmt);
            } else {
                $this->addAbsolute($positive, $part, $varQty, $varAmt);
            }
        }

        $positiveDetail = $this->detail($positive, $names);
        $negativeDetail = $this->detail($negative, $names);

        return [
            'absolute_qty'    => round($positiveDetail['qty'] + $negativeDetail['qty'], 2),
            'absolute_amount' => round($positiveDetail['amount'] + $negativeDetail['amount'], 2),
            'by_area'         => $this->areaSeries($perArea),
            'positive'        => $positiveDetail,
            'negative'        => $negativeDetail,
        ];
    }

    /**
     * @param  array<string, array{qty: float, amount: float}>  $bucket
     */
    private function addAbsolute(array &$bucket, string $part, float $qty, float $amount): void
    {
        $bucket[$part] ??= ['qty' => 0.0, 'amount' => 0.0];
        $bucket[$part]['qty']    += abs($qty);
        $bucket[$part]['amount'] += abs($amount);
    }
}
