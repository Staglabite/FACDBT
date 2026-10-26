<?php

namespace App\Services\FACDBT;

/**
 * Perhitungan Nett Variance Dashboard (FACDBTD003), dipakai controller (ringkasan & chart)
 * dan DataTable (detail part positif / negatif).
 *
 * Variance qty = qty taking - qty book (dicocokkan per lokasi lalu dijumlah per part),
 * variance amount = variance qty x unit cost.
 * Part dengan nett variance amount tidak minus masuk "positive", yang minus masuk "negative".
 */
class NettVarianceService extends VarianceDashboardService
{
    protected function cachePrefix(): string
    {
        return 'FACDBTD003:data:v8';
    }

    protected function emptyResult(): array
    {
        return [
            'nett_qty'    => 0,
            'nett_amount' => 0,
            'by_area'     => [],
            'positive'    => ['qty' => 0, 'amount' => 0, 'rows' => []],
            'negative'    => ['qty' => 0, 'amount' => 0, 'rows' => []],
        ];
    }

    protected function build(iterable $lines, array $names): array
    {
        $perArea = [];
        $perPart = [];
        foreach ($lines as [, $part, $qtyBook, $qtyTaking, $unitCost, $area]) {
            $varQty = $qtyTaking - $qtyBook;
            $varAmt = $varQty * $unitCost;

            $perArea[$area] = ($perArea[$area] ?? 0.0) + $varAmt;
            $perPart[$part] ??= ['qty' => 0.0, 'amount' => 0.0];
            $perPart[$part]['qty']    += $varQty;
            $perPart[$part]['amount'] += $varAmt;
        }

        $negative = array_filter($perPart, fn ($p) => $this->isNegative($p['qty'], $p['amount']));
        $positive = array_diff_key($perPart, $negative);

        // Urutan tabel tetap: positive dari Var Amount terbesar, negative dari yang paling minus
        uasort($positive, fn ($a, $b) => $b['amount'] <=> $a['amount'] ?: $b['qty'] <=> $a['qty']);
        uasort($negative, fn ($a, $b) => $a['amount'] <=> $b['amount'] ?: $a['qty'] <=> $b['qty']);

        return [
            'nett_qty'    => round(array_sum(array_column($perPart, 'qty')), 2),
            'nett_amount' => round(array_sum(array_column($perPart, 'amount')), 2),
            'by_area'     => $this->areaSeries($perArea),
            'positive'    => $this->detail($positive, $names),
            'negative'    => $this->detail($negative, $names),
        ];
    }
}
