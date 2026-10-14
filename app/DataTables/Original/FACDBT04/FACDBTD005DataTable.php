<?php

namespace App\DataTables\Original\FACDBT04;

use App\Services\FACDBT\AbsoluteVarianceService;
use App\Services\FACDBT\VarianceDashboardService;

// Detail part Absolute Variance Dashboard; turunannya menentukan tabel lebih (positive) / kurang (negative)
abstract class FACDBTD005DataTable extends VarianceDetailDataTable
{
    protected function service(): VarianceDashboardService
    {
        return app(AbsoluteVarianceService::class);
    }

    protected function tablePrefix(): string
    {
        return 'facdbtd005';
    }

    protected function routePrefix(): string
    {
        return 'absolute-variance-dashboard';
    }
}
