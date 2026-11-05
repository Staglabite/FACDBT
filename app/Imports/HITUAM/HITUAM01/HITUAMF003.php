<?php

namespace App\Imports\HITUAM\HITUAM01;

use App\Models\HITUAM01\HITUAM_MSHAPPLICATION as Application;
use App\Models\HITUAM01\HITUAM_MSHMENU;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use App\Models\HITUAM01\HITUAM_MSHSERVICE as Service;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Maatwebsite\Excel\Concerns\WithStartRow;
use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;

class HITUAMF003 implements ToCollection, WithHeadingRow, WithStartRow
{
    /**
     * @param Collection $collection
     */
    protected $logs = [];

    protected $totalError = 0;

    protected $createdBy = null;

    protected $columns = ['service_name', 'service_description', 'service_url', 'http_method', 'application_name', 'menu_name', 'begin_effective_date', 'end_effective_date'];

    public function startRow(): int
    {
        return 2;
    }

    /**
     * @param Collection $collection
     */
    public function collection(Collection $collection)
    {
        if ($collection === null || $collection->isEmpty()) {
            throw new SpreadsheetException('The file is empty', 422);
        }

        $this->createdBy = "'" . Auth::user()->VUSERNAME . "'";
        $currentRowIndex = $this->startRow();

        $seen = [];
        foreach ($collection as $row) {
            $row['row'] = $currentRowIndex;
            $row = $row->toArray();

            // Normalize heading variants from Excel, especially when headings include format hints
            $row['begin_effective_date'] = $row['begin_effective_date'] ?? $row['begin_effective_date_yyyy_mm_dd'] ?? $row['begin_effective_date_(yyyy_mm_dd)'] ?? null;
            $row['end_effective_date'] = $row['end_effective_date'] ?? $row['end_effective_date_yyyy_mm_dd'] ?? $row['end_effective_date_(yyyy_mm_dd)'] ?? null;

            foreach ($this->columns as $col) {
                // Trim the column values if they are not empty, otherwise set them to null
                $row[$col] = ! empty($row[$col]) ? trim($row[$col]) : null;
            }

            $row['begin_effective_date'] = $this->parseDate($row['begin_effective_date']);
            $row['end_effective_date'] = $this->parseDate($row['end_effective_date']);

            $this->setLog($row);
            $this->required($row, ['service_name', 'service_url', 'http_method', 'application_name', 'menu_name', 'begin_effective_date', 'end_effective_date']);

            $this->mapRelations($row);

            $key = $row['service_name'] . '_' . $row['http_method'] . '_' . $row['menu_name'];
            if (isset($seen[$key])) {
                $this->setLog($row, "Duplicate service entry for Service Name: {$row['service_name']}, HTTP Method: {$row['http_method']}, Menu Name: {$row['menu_name']} in row {$seen[$key]}.");
            } else {
                $seen[$key] = $currentRowIndex;
            }
            $currentRowIndex++;
        }
    }

    public function store()
    {
        return collect($this->logs)->values()->map(function ($item) {
            return Arr::only($item, [
                'service_name',
                'service_description',
                'service_url',
                'http_method',
                'application_name',
                'menu_name',
                'begin_effective_date',
                'end_effective_date'
            ]);
        })->toArray();
    }

    public function required($row, $columns)
    {
        $emptyColumns = array_filter($columns, fn($column) => ! isset($row[$column]) || empty($row[$column]));

        if (count($emptyColumns) > 0) {
            foreach ($emptyColumns as $col) {
                $this->setLog($row, "The {$col} field is required.");
            }
        }
    }

    protected function setLog($row, $error = null)
    {
        $unique = $row['row'];
        if (! isset($this->logs[$unique])) {
            $this->logs[$unique] = array_merge(Arr::only($row, $this->columns), [
                'row' => $unique,
                'error_count' => 0,
                'errors' => [],
                'created_by' => $this->createdBy,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            if ($error !== null) {
                $this->totalError++;
                $this->logs[$unique]['error_count']++;
                $this->logs[$unique]['errors'][] = $error;
            }
        }
    }

    public function mapRelations($row)
    {
        $errors = [];

        $serviceData = Service::where('VNAME', $row['service_name'])
            ->whereNull('DDELETE')
            ->first();

        if ($serviceData) {
            $errors[] = "Service with name {$row['service_name']} already exists.";
        }

        $application = Application::where('VPROJECTDESC', $row['application_name'])
            ->whereNull('DDELETE')
            ->first();
        if (! $application) {
            $errors[] = "Application {$row['application_name']} does not exist.";
        }

        $menu = null;
        if ($application) {
            $menu = HITUAM_MSHMENU::where('VAPPDESC', $row['menu_name'])
                ->where('NSORTPROJECT', $application->IID)
                ->whereNull('DDELETE')
                ->first();
        }

        if (! $menu) {
            $errors[] = "Menu with name {$row['menu_name']} does not exist for application {$row['application_name']}.";
        }

        if (! empty($errors)) {
            foreach ($errors as $error) {
                $this->setLog($row, $error);
            }
        }
    }

    public function getResult()
    {
        $correctLogs = collect($this->logs)->where('error_count', '=', 0)->count();
        $incorrectLogs = collect($this->logs)->where('error_count', '>', 0)->count();
        $totalError = $this->totalError;
        $errorLogs = collect($this->logs)->where('error_count', '>', 0);

        return (object) [
            'totalLogs' => count($this->logs),
            'totalCorrectLogs' => $correctLogs,
            'totalErrorLogs' => $incorrectLogs,
            'totalError' => $totalError,
            'logs' => array_values($this->logs),
            'errorLogs' => $errorLogs,
        ];
    }

    private function parseDate($value)
    {
        if (empty($value)) {
            return null;
        }

        if (is_numeric($value)) {
            return Date::excelToDateTimeObject($value)->format('Y-m-d');
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }
}
