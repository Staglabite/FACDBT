<?php

namespace App\DataTables\Original\FACDBT04;

use App\Services\FACDBT\AbsoluteVarianceService;
use App\Services\FACDBT\VarianceDashboardService;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;

// Detail part per lokasi Absolute Variance Dashboard; turunannya menentukan tabel lebih (positive) / kurang (negative).
// Urutan baris mengikuti service (part dengan variance terbesar dulu), part yang sama ditampilkan berkelompok.
abstract class FACDBTD004DataTable extends VarianceDetailDataTable
{
    protected function service(): VarianceDashboardService
    {
        return app(AbsoluteVarianceService::class);
    }

    protected function tablePrefix(): string
    {
        return 'facdbtd004';
    }

    protected function routePrefix(): string
    {
        return 'variance-part-location';
    }

    public function html(): HtmlBuilder
    {
        return parent::html()->parameters([
            // Urutan sudah ditentukan service, jadi pengurutan per kolom dimatikan
            'ordering' => false,
            'order' => [],
            // Part No & Part Name hanya ditulis di baris pertama tiap part (baris berikutnya = lokasi lain)
            'drawCallback' => '
                function () {
                    var previous = null;
                    $(this.api().table().body()).find("tr").each(function () {
                        var cells = $(this).children("td");
                        if (cells.length < 3) return;
                        var partNo = cells.eq(0).text();
                        $(this).toggleClass("av-part-continued", partNo === previous);
                        previous = partNo;
                    });
                }
            ',
        ]);
    }

    public function getColumns(): array
    {
        return [
            Column::make('part_no')->title('Part No')->addClass('av-part-cell'),
            Column::make('name')->title('Part Name')->addClass('av-part-cell'),
            Column::make('location_no')->title('Location No')->addClass('av-location-cell'),
        ];
    }
}
