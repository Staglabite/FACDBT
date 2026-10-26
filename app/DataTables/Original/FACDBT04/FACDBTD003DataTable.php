<?php

namespace App\DataTables\Original\FACDBT04;

use App\Services\FACDBT\NettVarianceService;
use App\Services\FACDBT\VarianceDashboardService;

// Detail part Nett Variance Dashboard; turunannya menentukan tabel positif / negatif
abstract class FACDBTD003DataTable extends VarianceDetailDataTable
{
    protected function service(): VarianceDashboardService
    {
        return app(NettVarianceService::class);
    }

    protected function tablePrefix(): string
    {
        return 'facdbtd003';
    }

    protected function routePrefix(): string
    {
        return 'taking-qty-amount';
    }

    // Urutan awal: tabel positif dari amount terbesar, tabel negatif dari yang paling minus
    // (judul kolom tetap bisa diklik untuk mengurutkan ulang)
    protected function orderDirection(): string
    {
        return $this->type === 'positive' ? 'desc' : 'asc';
    }
}
