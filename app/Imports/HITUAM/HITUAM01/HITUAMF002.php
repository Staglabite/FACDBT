<?php

namespace App\Imports\HITUAM\HITUAM01;

use App\Enums\MenuFlag;
use App\Models\HITUAM01\HITUAM_MSHAPPLICATION as Application;
use App\Models\HITUAM01\HITUAM_MSHMENU as Menu;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;
use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;

class HITUAMF002 implements ToCollection, WithHeadingRow, WithStartRow
{
    protected $logs = [];

    protected $totalError = 0;

    protected $createdBy = null;

    protected $columns = ['app_id', 'name', 'description', 'flag', 'url', 'icon', 'order', 'parent_menu', 'application_name', 'type', 'env_app'];

    public function startRow(): int
    {
        return 2;
    }

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

            foreach ($this->columns as $col) {
                $row[$col] = isset($row[$col]) && $row[$col] !== '' ? trim((string) $row[$col]) : null;
            }

            $this->setLog($row);
            $this->required($row, ['app_id', 'name', 'description', 'flag', 'url', 'icon', 'order', 'application_name', 'type']);
            $this->validateFlag($row);
            $this->validateOrder($row);
            $this->mapRelations($row);

            $key = $row['app_id'];
            if (! empty($key)) {
                if (isset($seen[$key])) {
                    $this->setLog($row, "Duplicate app_id {$row['app_id']} found in row {$seen[$key]}.");
                } else {
                    $seen[$key] = $currentRowIndex;
                }
            }

            $currentRowIndex++;
        }
    }

    public function store()
    {
        return collect($this->logs)
            ->where('error_count', 0)
            ->values()
            ->map(function ($item) {
                return Arr::only($item, $this->columns);
            })
            ->toArray();
    }

    public function required($row, $columns)
    {
        $emptyColumns = array_filter($columns, fn($column) => ! isset($row[$column]) || $row[$column] === null || $row[$column] === '');

        foreach ($emptyColumns as $col) {
            $this->setLog($row, "The {$col} field is required.");
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
        }

        if ($error !== null) {
            $this->totalError++;
            $this->logs[$unique]['error_count']++;
            $this->logs[$unique]['errors'][] = $error;
        }
    }

    protected function validateFlag(array $row): void
    {
        if (empty($row['flag'])) {
            return;
        }

        $validFlags = collect(MenuFlag::cases())->map(fn($flag) => $flag->value)->all();

        if (! in_array($row['flag'], $validFlags, true)) {
            $this->setLog($row, 'Flag must be one of: ' . implode(', ', $validFlags) . '.');
        }
    }

    protected function validateOrder(array $row): void
    {
        if (! empty($row['order']) && ! is_numeric($row['order'])) {
            $this->setLog($row, 'The order field must be numeric.');
        }
    }

    public function mapRelations($row)
    {
        $menu = Menu::where('VAPPID', $row['app_id'])
            ->whereNull('DDELETE')
            ->first();
        if ($menu) {
            $this->setLog($row, "Menu with app_id {$row['app_id']} already exists.");
        }

        if (! empty($row['parent_menu'])) {
            $parentMenu = Menu::where('VAPPDESC', $row['parent_menu'])
                ->whereNull('DDELETE')
                ->first();
            if (! $parentMenu) {
                $this->setLog($row, "Parent menu {$row['parent_menu']} does not exist.");
            }
        }

        $application = Application::where('VPROJECTDESC', $row['application_name'])
            ->whereNull('DDELETE')
            ->first();
        if (! $application) {
            $this->setLog($row, "Application {$row['application_name']} does not exist.");
        }
    }

    public function getResult()
    {
        $correctLogs = collect($this->logs)->where('error_count', '=', 0)->count();
        $incorrectLogs = collect($this->logs)->where('error_count', '>', 0)->count();
        $errorLogs = collect($this->logs)->where('error_count', '>', 0);

        return (object) [
            'totalLogs' => count($this->logs),
            'totalCorrectLogs' => $correctLogs,
            'totalErrorLogs' => $incorrectLogs,
            'totalError' => $this->totalError,
            'logs' => array_values($this->logs),
            'errorLogs' => $errorLogs,
        ];
    }
}
