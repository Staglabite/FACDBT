<?php

namespace App\DataTables\Original\FACDBT04;

use App\Services\FACDBT\NettVarianceService;
use App\Services\FACDBT\VarianceDashboardService;
use Yajra\DataTables\Html\Builder as HtmlBuilder;

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
        return 'taking-qty-amount';
    }

    // Tabel positif dari amount terbesar, tabel negatif dari yang paling minus
    protected function orderDirection(): string
    {
        return $this->type === 'positive' ? 'desc' : 'asc';
    }

    // Urutan hanya dari Var Amount (sudah diurutkan service); klik judul kolom tidak mengurutkan ulang
    public function html(): HtmlBuilder
    {
        return parent::html()->parameters([
            'ordering' => false,
            'order' => [],
        ]);
    }
}
