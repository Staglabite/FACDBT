<?php

namespace App\DataTables\Original\FACDBT04;

use App\Services\FACDBT\NettVarianceService;
use App\Services\FACDBT\VarianceDashboardService;

// Detail part Nett Variance Dashboard; turunannya menentukan tabel positif / negatif
abstract class FACDBTD004DataTable extends VarianceDetailDataTable
{
    protected function service(): VarianceDashboardService
    {
        return app(NettVarianceService::class);
    }

    protected function tablePrefix(): string
    {
        return 'facdbtd004';
    }

    protected function routePrefix(): string
    {
        return 'nett-variance-dashboard';
    }

    // Tabel positif dari amount terbesar, tabel negatif dari yang paling minus
    protected function orderDirection(): string
    {
        return $this->type === 'positive' ? 'desc' : 'asc';
    }
}
