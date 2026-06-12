<?php

namespace App\DataTables\Original\HITUAM01;

use App\Traits\DataTableTrait;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\QueryDataTable;
use Yajra\DataTables\Services\DataTable;

class HITUAMF012Datatable extends DataTable
{
    use DataTableTrait;

    /**
     * Build DataTable class.
     *
     * @param QueryBuilder $query Results from query() method.
     */
    // public function dataTable(QueryBuilder $query): QueryDataTable
    // {
    //     return (new QueryDataTable($query))
    //         ->addIndexColumn()
    //         ->filterColumn('invoice_no', function ($query, $keyword) {
    //             $query->where('invoice_no', 'ilike', "%{$keyword}%");
    //         })
    //         ->filterColumn('tax_invoice_no', function ($query, $keyword) {
    //             $query->where('tax_invoice_no', 'ilike', "%{$keyword}%");
    //         })
    //         ->filterColumn('vendor', function ($query, $keyword) {
    //             $query->where('vendor', 'ilike', "%{$keyword}%");
    //         })
    //         ->filterColumn('bs_no', function ($query, $keyword) {
    //             $query->where('bs_no', 'ilike', "%{$keyword}%");
    //         })
    //         ->filterColumn('grn', function ($query, $keyword) {
    //             $query->where('grn', 'ilike', "%{$keyword}%");
    //         })
    //         ->filterColumn('status_grn', function ($query, $keyword) {
    //             $query->where('status_grn', 'ilike', "%{$keyword}%");
    //         })
    //         ->filterColumn('status', function ($query, $keyword) {
    //             $query->where('status', 'ilike', "%{$keyword}%");
    //         })
    //         ->orderColumn('invoice_no', 'invoice_no $1')
    //         ->orderColumn('tax_invoice_no', 'tax_invoice_no $1')
    //         ->orderColumn('vendor', 'vendor $1')
    //         ->orderColumn('bs_no', 'bs_no $1')
    //         ->orderColumn('grn', 'grn $1')
    //         ->orderColumn('status_grn', 'status_grn $1')
    //         ->orderColumn('status', 'status $1')
    //         ->orderColumn('release_date', 'release_date $1')
    //         ->editColumn('status', function ($row) {
    //             $color = $this->colorBadge($row->status);

    //             return '<span class="badge bg-' . $color . '">' . $row->status . '</span>';
    //         })
    //         ->editColumn('release_date', function ($row) {
    //             return $row->release_date
    //                 ? Carbon::parse($row->release_date)->format('Y-m-d')
    //                 : '-';
    //         })
    //         ->editColumn('status_grn', function ($row) {
    //             return $row->status_grn ?: '-';
    //         })
    //         ->editColumn('grn', function ($row) {
    //             return $row->grn ?: '-';
    //         })
    //         ->filterColumn('release_date', function ($query, $keyword) {
    //             $keyword = trim((string) $keyword);

    //             if ($keyword === '') {
    //                 return;
    //             }

    //             if (str_contains($keyword, ' to ')) {
    //                 $this->applyDateRangeFilter($query, 'release_date', $keyword);
    //                 return;
    //             }

    //             $query->whereDate('release_date', Carbon::parse($keyword)->toDateString());
    //         })
    //         ->rawColumns(['status']);
    // }

    /**
     * Get query source of dataTable.
     *
     * @return QueryBuilder
     */
    // public function query(): QueryBuilder
    // {
    //     $request = $this->request();
    //     // $poQuery = $this->getPOQuery();
    //     // $nonPoQuery = $this->getNonPOQuery();
    //     // $unionQuery = $poQuery->unionAll($nonPoQuery);

    //     $query = DB::query()
    //         ->fromSub($unionQuery, 'combined')
    //         ->when(
    //             Auth::user()?->supplierUser?->VSUPPLIER_CODE,
    //             fn($q, $supplierCode) => $q->where('supplier_code', $supplierCode)
    //         )
    //         ->where('VSTATUS', 'submit');

    //     if (! $request->has('order') || empty($request->input('order'))) {
    //         $query->orderBy('DMODI', 'desc');
    //     }

    //     return $query;
    // }

    // private function getPOQuery()
    // {
    //     return DB::table('FACTWM_TRHVERIFY_PO as p')
    //         ->leftJoin('FACTWM_MSHSUPPLIERS as fm', 'fm.VSUPPLIER_CODE', '=', 'p.VSUPPLIER_CODE')
    //         ->leftJoin('FACTWM_TRHGR_NOTES as gr', function ($join) {
    //             $join->whereRaw(
    //                 'p."VGR_NUMBER_IID" IS NOT NULL
    //                 AND json_array_length(p."VGR_NUMBER_IID") > 0
    //                 AND gr."IID" = ANY (
    //                     array_remove(
    //                         regexp_split_to_array(
    //                             regexp_replace(p."VGR_NUMBER_IID"::text, \'[\\[\\]\\s]\', \'\', \'g\'),
    //                             \',\'
    //                         ),
    //                         \'\'
    //                     )::int[]
    //                 )'
    //             );
    //         })
    //         ->select([
    //             DB::raw('p."IID" AS "id"'),
    //             DB::raw('p."VINVOICE_NUMBER" AS "invoice_no"'),
    //             DB::raw('p."VTAX_INVOICE_NUMBER" AS "tax_invoice_no"'),
    //             DB::raw('fm."VNAME" AS "vendor"'),
    //             DB::raw('fm."VSUPPLIER_CODE" AS "supplier_code"'),
    //             DB::raw('p."VBILLING_STATEMENT" AS "bs_no"'),
    //             DB::raw('gr."VGR_NUMBER" AS "grn"'),
    //             DB::raw('gr."VSTATUS" AS "status_grn"'),
    //             DB::raw('p."VSTATUS_INVOICE" AS "status"'),
    //             DB::raw('p."DAPPROVED" AS "release_date"'),
    //             DB::raw('p."VSTATUS" AS "VSTATUS"'),
    //             DB::raw('p."DMODI" AS "DMODI"'),
    //         ]);
    // }

    // private function getNonPOQuery()
    // {
    //     return DB::table('FACTWM_TRHVERIFY_NON_PO as p')
    //         ->leftJoin('FACTWM_MSHSUPPLIERS as fm', 'fm.VSUPPLIER_CODE', '=', 'p.VSUPPLIER_CODE')
    //         ->select([
    //             DB::raw('p."IID" AS "id"'),
    //             DB::raw('p."VINV_NO_SUPPLIER" AS "invoice_no"'),
    //             DB::raw('p."VTAX_NUMBER" AS "tax_invoice_no"'),
    //             DB::raw('fm."VNAME" AS "vendor"'),
    //             DB::raw('fm."VSUPPLIER_CODE" AS "supplier_code"'),
    //             DB::raw('p."VBILLING_STATEMENT" AS "bs_no"'),
    //             DB::raw('NULL AS "grn"'),
    //             DB::raw('NULL AS "status_grn"'),
    //             DB::raw('p."VSTATUS_INVOICE" AS "status"'),
    //             DB::raw('p."DAPPROVED" AS "release_date"'),
    //             DB::raw('p."VSTATUS" AS "VSTATUS"'),
    //             DB::raw('p."DMODI" AS "DMODI"'),
    //         ]);
    // }

    /**
     * Optional method if you want to use html builder.
     *
     * @return \Yajra\DataTables\Html\Builder
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('factwmf015-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->orderBy(7, 'desc')
            ->selectStyleSingle()
            ->parameters([
                'orderCellsTop' => true,
                'dom' => 'r' .
                    "<'table-responsive border-top'tr>" .
                    "<'d-flex align-items-center justify-content-center justify-content-lg-between flex-wrap gap-2 text-center px-6 mt-6'ip>",
                'language' => [
                    'emptyTable' => 'No data available',
                    'zeroRecords' => 'No matching records found',
                    'info' => 'Showing _START_ to _END_ of _TOTAL_ entries',
                    'infoEmpty' => 'Showing 0 to 0 of 0 entries',
                    'infoFiltered' => '(filtered from _MAX_ total entries)',
                    'lengthMenu' => 'Show _MENU_ entries',
                    'search' => 'Search:',
                    'paginate' => [
                        'first' => 'First',
                        'last' => 'Last',
                        'next' => 'Next',
                        'previous' => 'Previous',
                    ],
                ],
                'pageLength' => 10,
                'lengthMenu' => [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
                'responsive' => false,
                'autoWidth' => false,
                'processing' => false,
                'serverSide' => true,
                'initComplete' => '
                    function () {
                        var table = this.api();
                        ' . $this->getScriptForColumnSearch() . '
                    }
                ',
            ]);
    }

    /**
     * Get columns.
     *
     * @return array
     */
    protected function getColumns()
    {
        return [
            Column::make('invoice_no')->name('invoice_no')->title('Invoice No')->addClass('text-nowrap'),
            Column::make('tax_invoice_no')->name('tax_invoice_no')->title('Tax Invoice No')->addClass('text-nowrap'),
            Column::make('vendor')->name('vendor')->title('Supplier')->addClass('text-nowrap'),
            Column::make('bs_no')->name('bs_no')->title('BS No')->addClass('text-nowrap'),
            Column::make('grn')->name('grn')->title('GRN')->addClass('text-nowrap'),
            Column::make('status_grn')->name('status_grn')->title('Status GRN')->addClass('text-nowrap'),
            Column::make('status')->name('status')->title('Status')->addClass('text-nowrap'),
            Column::make('release_date')->name('release_date')->title('Release Date')->addClass('text-nowrap'),
        ];
    }

    /**
     * Get filename for export.
     *
     * @return string
     */
    protected function filename(): string
    {
        return 'FACTWMF015_' . date('YmdHis');
    }

    private function colorBadge(string $status): string
    {
        return match ($status) {
            'PAID' => 'success',
            'PRELIMENARY' => 'bg-blue',
            'FAILED' => 'danger',
            'REJECTED' => 'dark',
            'WAITING' => 'info',
            'ESCALATED' => 'secondary',
            default => 'warning',
        };
    }

    private function getScriptForColumnSearch(): string
    {
        return '
        var thead = $(table.table().header());
        var headerCells = thead.find("tr:first th");
        var searchRow = $("<tr></tr>");

        headerCells.each(function(index) {
            var column = table.column(index);
            var title = $(this).text().trim().toLowerCase();
            var th = $("<th></th>");

            if (title === "status") {
                var select = $("<select>", {
                    "class": "form-select form-select-sm"
                });

                select.append($("<option>", {
                    value: "",
                    text: "Select"
                }));

                ["PAID", "PRELIMENARY", "FAILED", "REJECTED", "PROCESSING", "ESCALATED", "WAITING"].forEach(function(type) {
                    select.append($("<option>", {
                        value: type,
                        text: type
                    }));
                });

                select.on("change", function() {
                    column.search($(this).val()).draw();
                });

                th.html(select);
                searchRow.append(th);
                return true;
            }

            if (title === "release date") {
                var input = $("<input>", {
                    "class": "form-control form-control-sm date-input",
                    "placeholder": "YYYY-MM-DD",
                    "readonly": true
                });

                input.daterangepicker({
                    singleDatePicker: true,
                    autoUpdateInput: false,
                    locale: {
                        cancelLabel: "Clear",
                        format: "YYYY-MM-DD"
                    }
                });

                input.on("apply.daterangepicker", function(ev, picker) {
                    var selectedDate = picker.startDate.format("YYYY-MM-DD");
                    column.search(selectedDate).draw();
                    $(this).val(selectedDate);
                });

                input.on("cancel.daterangepicker", function() {
                    column.search("").draw();
                    $(this).val("");
                });

                th.html(input);
                searchRow.append(th);
                return true;
            }

            var input = $("<input>", {
                "class": "form-control form-control-sm",
                "placeholder": "Search..."
            });

            input.on("keyup change", function() {
                column.search($(this).val()).draw();
            });

            th.html(input);
            searchRow.append(th);
        });

        thead.append(searchRow);
        ';
    }
}
