<?php

namespace App\Traits;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

trait DataTableTrait
{
    /**
     * Get the button access for a given request path.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    protected function applyDateRangeFilter($query, $column, $keyword)
    {
        if (strpos($keyword, ' to ') !== false) {
            [$startDate, $endDate] = explode(' to ', $keyword);
            $query->whereBetween($column, [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay(),
            ]);
        } else {
            $query->where($column, 'ilike', "%{$keyword}%");
        }
    }

    private function getDomConfig()
    {
        return
            '<"row"' .
            '<"col-sm-12 col-lg-6" l>' .
            '<"col-sm-12 col-lg-6" f>' .
            '><"table table-responsive" t>' .
            '<"row"' .
            '<"col-sm-12 col-md-6"i>' .
            '<"col-sm-12 col-md-6 d-flex justify-content-end"p>' .
            '>';
    }

    private function getScriptForSearchRow($skipLastColumn = true)
    {
        $script = '
        var table = this.api();
        var headerCells = $(table.table().header()).find("th");

        // Create a new row for search inputs in thead
        var searchRow = $("<tr></tr>");

        headerCells.each(function(index) {
            var column = table.column(index);
            var title = $(this).text();
            var th = $("<th></th>");

            // Skip first column (checkbox) and last column (actions)
            var skipLast = ' . ($skipLastColumn ? 'true' : 'false') . ';
            var isFirstColumn = index === 0;
            var isLastColumn = index === headerCells.length - 1;
            var shouldSkip = (isFirstColumn || (isLastColumn && skipLast));

            if (shouldSkip || title.toLowerCase().includes("action")) {
                // Just append empty th and continue to next iteration
                searchRow.append(th);
                return true; // continue to next iteration
            }

            if (title.toLowerCase() === "news status") {
                var select = $("<select>", {
                    "class": "form-select form-select-sm",
                });

                select.append($("<option>", {
                    value: "",
                    text: "select"
                }));

                var news = ["All", "Published", "Draft"];
                var newsVal = ["", true, false];

                news.forEach(function(text, index) {
                    select.append($("<option>", {
                        value: newsVal[index],
                        text: text
                    }));
                });

                th.html(select);

                select.on("change", function() {
                    column.search($(this).val()).draw();
                });
            // Check if the header text contains "Created Date, Updated Date, Published Date" or "Last Login" (case-insensitive)
            } else if (title.toLowerCase() === "status grn") {
                var select = $("<select>", {
                    "class": "form-select form-select-sm",
                });

                select.append($("<option>", {
                    value: "",
                    text: "select"
                }));

                var statuses = ["New", "Approved", "Dispute", "Closed"];
                var statusValues = [
                    "<span class=\"badge bg-info\">New</span>",
                    "<span class=\"badge bg-success\">Approved</span>",
                    "<span class=\"badge bg-warning\">Dispute</span>",
                    "<span class=\"badge bg-danger\">Closed</span>"
                ];

                statuses.forEach(function(text, index) {
                    select.append($("<option>", {
                        value: statusValues[index],
                        text: text
                    }));
                });

                th.html(select);

                select.on("change", function() {
                    column.search($(this).val()).draw();
                });
            } else if (title.toLowerCase() === "status pkp") {
                var select = $("<select>", {
                    "class": "form-select form-select-sm",
                });

                select.append($("<option>", {
                    value: "",
                    text: "select"
                }));

                var statuses = ["PKP", "Non PKP"];
                var statusValues = [true, false];

                statuses.forEach(function(text, index) {
                    select.append($("<option>", {
                        value: statusValues[index],
                        text: text
                    }));
                });

                th.html(select);

                select.on("change", function() {
                    column.search($(this).val()).draw();
                });
            } else if ( /published date/i.test(title) ||
                /updated at/i.test(title) ||
                /updated date/i.test(title) ||
                /modified at/i.test(title) ||
                /modified date/i.test(title) ||
                /created date/i.test(title) ||
                /created at/i.test(title) ||
                /last login/i.test(title) ||
                /Begin Effective Date/i.test(title) ||
                /End Effective Date/i.test(title) ||
                /grn date/i.test(title)) {
                var input = $("<input>", {
                    "class": "form-control daterange-input",
                    "placeholder": "Select date",
                    "type": "text"
                });

                th.html(input);

                // Initialize Daterangepicker
                input.daterangepicker({
                    autoUpdateInput: false,
                    locale: {
                        autoApply: true,
                        cancelLabel: "Clear",
                        format: "YYYY-MM-DD"
                    }
                });

                // Event when a date range is applied
                input.on("apply.daterangepicker", function(ev, picker) {
                    var startDate = picker.startDate.format("YYYY-MM-DD");
                    var endDate = picker.endDate.format("YYYY-MM-DD");
                    column.search(startDate + " to " + endDate).draw();
                    $(this).val(startDate + " - " + endDate);
                });

                // Event for clearing the date picker
                input.on("cancel.daterangepicker", function(ev, picker) {
                    column.search("").draw();
                    $(this).val("");
                });
            } else {
                var input = $("<input>", {
                    "class": "form-control form-control-sm",
                    "placeholder": "Search " + title,
                    "type": "text"
                });

                th.html(input);

                input.on("keyup change", function() {
                    if (column.search() !== this.value) {
                        column.search(this.value).draw();
                    }
                });
            }

            searchRow.append(th);
        });

        // Append search row to thead
        $(table.table().header()).append(searchRow);
    ';

        return $script;
    }

    private function applyDataTableSearchFilter($query, Request $request, array $searchableColumns, array $relationshipColumns = [])
    {
        if ($request->has('search.value') && $request->input('search.value')) {
            $globalSearch = $request->input('search.value');
            $query->where(function ($q) use ($globalSearch, $searchableColumns, $relationshipColumns) {
                foreach ($searchableColumns as $column) {
                    $q->orWhere($column, 'ilike', "%{$globalSearch}%");
                }

                // Handle global search for relationship columns, including nested relationships
                foreach ($relationshipColumns as $relation => $columns) {
                    $q->orWhereHas($relation, function ($relationQuery) use ($columns, $globalSearch) {
                        foreach ($columns as $column) {
                            if (strpos($column, '.') !== false) {
                                $this->applyNestedWhereHas($relationQuery, $column, $globalSearch);
                            } else {
                                $relationQuery->orWhere($column, 'ilike', "%{$globalSearch}%");
                            }
                        }
                    });
                }
            });
        }

        if ($request->has('columns')) {
            foreach ($request->input('columns') as $column) {
                if (! empty($column['search']['value'])) {
                    $columnName = $column['data'];
                    $searchValue = $column['search']['value'];

                    if (strpos($searchValue, ' to ') !== false) {
                        [$startDate, $endDate] = explode(' to ', $searchValue);
                        $query->whereBetween($columnName, [
                            Carbon::parse($startDate)->startOfDay(),
                            Carbon::parse($endDate)->endOfDay(),
                        ]);
                    } elseif ($columnName == 'IDAY') {
                        $query->whereRaw("
                            CASE
                                WHEN \"IDAY\" = 0 THEN 'Sunday'
                                WHEN \"IDAY\" = 1 THEN 'Monday'
                                WHEN \"IDAY\" = 2 THEN 'Tuesday'
                                WHEN \"IDAY\" = 3 THEN 'Wednesday'
                                WHEN \"IDAY\" = 4 THEN 'Thursday'
                                WHEN \"IDAY\" = 5 THEN 'Friday'
                                WHEN \"IDAY\" = 6 THEN 'Saturday'
                                ELSE 'Unknown'
                            END ILIKE ?
                        ", ["%{$searchValue}%"]);
                    } else {
                        $this->applyNestedWhereHas($query, $columnName, $searchValue);
                    }
                }
            }
        }

        return $query;
    }

    private function applyNestedWhereHas($query, $column, $searchValue)
    {
        // Extract the relationship path and the field
        $parts = explode('.', $column);
        $field = array_pop($parts); // Get the last part as the field (e.g., VDAILYREQUESTNO)
        $relationPath = implode('.', $parts); // Remaining parts form the relationship path

        // Apply the whereHas query for the nested relationship
        $query->whereHas($relationPath, function ($relationQuery) use ($field, $searchValue) {
            $relationQuery->where($field, 'ilike', "%{$searchValue}%");
        });
    }

    /**
     * For buttons that are displayed at the top of table.
     * These buttons can include such as Export, Create, etc.
     *
     * @param  array  $buttons
     * @return string HTML String
     */
    private function pageButtons($buttons)
    {
        $user = Auth::user();
        $services = collect($user->serviceNames());
        $html = '';

        foreach ($buttons as $button) {
            $id = $button['id'] ?? '';
            $class = $button['class'] ?? 'btn btn-primary';
            $url = $button['url'] ?? 'javascript:void(0)';
            $dataAttributes = $button['data'] ?? [];
            $icon = $button['icon'] ?? null;
            $name = $button['text'] ?? 'Button';

            if (isset($button['service']) && ! $services->contains($button['service'])) {
                continue;
            }

            $attributes = collect($dataAttributes)->map(fn($value, $key) => 'data-' . $key . '="' . $value . '"')->implode(' ');

            $btn = "
                <button class='{$class}' id='$id' href='{$url}' {$attributes}>
                    <i class='icon-base ti tabler-{$icon} me-2'></i>
                    $name
                </button>
            ";

            if (isset($button['custom'])) {
                $btn = $button['custom'];
            }

            $html .= $btn;
        }

        return $html;
    }

    private function actionButtons($data, $actions)
    {
        $user = Auth::user();
        $button = '';
        $services = collect($user->serviceNames());

        foreach ($actions as $action) {
            $class = $action['action'] . '-' . strtolower(last(explode(' ', $action['service'])));
            $class = $action['class'] ?? $class;
            $url = $action['url'] ?? 'javascript:void(0)';
            $id = $action['id'] ?? $data->IID;
            $dataAttributes = $action['data'] ?? [];
            $title = $action['title'] ?? '';
            $isDisabled = $action['disabled'] ?? false;

            if ($services->contains($action['service'])) {
                $disabledStyle = $isDisabled ? 'pointer-events: none; opacity: 0.1; cursor: not-allowed;' : '';
                $disabledAttr = $isDisabled ? 'aria-disabled="true"' : '';

                $customButton = '<a href="' . $url . '" class="' . $class . '" data-id="' . $id . '" ' . $disabledAttr . ' style="' . $disabledStyle . '" title="' . htmlspecialchars($title) . '" ' . collect($dataAttributes)->map(fn($value, $key) => 'data-' . $key . '="' . htmlspecialchars($value) . '"')->implode(' ') . '>
                                    <i class="icon-base ti tabler-' . ($action['icon'] ?? 'settings') . '" title="' . ($action['title'] ?? '') . '"></i>
                                </a>';

                if (isset($action['custom'])) {
                    $customButton = $action['custom'];
                }

                $button .= $customButton;
            }
        }

        return $button ?: '-';
    }
}
