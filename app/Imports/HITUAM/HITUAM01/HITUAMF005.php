<?php

namespace App\Imports\HITUAM\HITUAM01;

use App\Models\FACTWM01\FACTWM_MSHSUPPLIER_COMMUNICATION_METHOD as SupplierUser;
use App\Models\HITUAM01\HITUAM_MSHROLE as Role;
use App\Models\HITUAM01\HITUAM_MSHUSER as User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;
use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;

class HITUAMF005 implements ToCollection, WithHeadingRow, WithStartRow
{
    protected $logs = [];

    protected $totalError = 0;

    protected $createdBy = null;

    protected $columns = [
        'user_type',
        'username',
        'email',
        'npk',
        'password',
        'role_names',
        'supplier',
        'user_supplier',
    ];

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
        $seen = [
            'username' => [],
            'email' => [],
            'npk' => [],
        ];

        foreach ($collection as $row) {
            $row['row'] = $currentRowIndex;
            $row = $row->toArray();

            $row['supplier'] = $row['supplier'] ?? $row['supplier_code'] ?? $row['supplier_id'] ?? null;
            $row['user_supplier'] = $row['user_supplier'] ?? $row['supplier_username'] ?? $row['user_supplier_id'] ?? null;

            foreach ($this->columns as $col) {
                $row[$col] = isset($row[$col]) && $row[$col] !== '' ? trim((string) $row[$col]) : null;
            }

            if ($row['user_type'] !== null) {
                $row['user_type'] = strtolower($row['user_type']);
            }

            $this->setLog($row);
            $this->required($row, ['user_type', 'username', 'email']);
            $this->validateUserType($row);
            $this->validateEmail($row);
            $this->validateTypeSpecificFields($row);
            $this->validateRoles($row);
            $this->validateDuplicateInFile($row, $seen, 'username');
            $this->validateDuplicateInFile($row, $seen, 'email');
            $this->validateDuplicateInFile($row, $seen, 'npk');
            $this->mapRelations($row);

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

    protected function validateUserType(array $row): void
    {
        if ($row['user_type'] !== null && ! in_array($row['user_type'], ['internal', 'external'], true)) {
            $this->setLog($row, 'The user_type field must be internal or external.');
        }
    }

    protected function validateEmail(array $row): void
    {
        if (! empty($row['email']) && ! filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            $this->setLog($row, 'The email field must be a valid email address.');
        }
    }

    protected function validateTypeSpecificFields(array $row): void
    {
        if ($row['user_type'] === 'internal') {
            if (empty($row['npk'])) {
                $this->setLog($row, 'The npk field is required for internal users.');
            }

            if (empty($row['password'])) {
                $this->setLog($row, 'The password field is required for internal users.');
            }
        }

        if ($row['user_type'] === 'external') {
            if (empty($row['supplier'])) {
                $this->setLog($row, 'The supplier field is required for external users.');
            }

            if (empty($row['user_supplier'])) {
                $this->setLog($row, 'The user_supplier field is required for external users.');
            }
        }
    }

    protected function validateRoles(array $row): void
    {
        if (empty($row['role_names'])) {
            return;
        }

        $roles = $this->parseRoleNames($row['role_names']);

        if (empty($roles)) {
            $this->setLog($row, 'Please provide at least one role in role_names.');
            return;
        }

        $existingRoles = Role::query()->whereIn('VROLENAME', $roles)->pluck('VROLENAME')->toArray();
        $missingRoles = array_diff($roles, $existingRoles);

        foreach ($missingRoles as $role) {
            $this->setLog($row, "Role {$role} was not found in master role data.");
        }
    }

    protected function validateDuplicateInFile(array $row, array &$seen, string $field): void
    {
        if (empty($row[$field])) {
            return;
        }

        $key = strtolower((string) $row[$field]);

        if (isset($seen[$field][$key])) {
            $this->setLog($row, "Duplicate {$field} {$row[$field]} found in row {$seen[$field][$key]}.");
            return;
        }

        $seen[$field][$key] = $row['row'];
    }

    public function mapRelations($row)
    {
        $this->checkExistingUser($row, 'VUSERNAME', 'username', 'username');
        $this->checkExistingUser($row, 'VEMAIL', 'email', 'email');
        $this->checkExistingUser($row, 'VEMPNO', 'npk', 'NPK');

        if ($row['user_type'] === 'external' && ! empty($row['supplier']) && ! empty($row['user_supplier'])) {
            $supplierUser = SupplierUser::query()
                ->where('VSUPPLIER_CODE', $row['supplier'])
                ->where(function ($query) use ($row) {
                    $query->where('VNAME', $row['user_supplier'])
                        ->orWhere('VUSERNAME', $row['user_supplier']);
                })
                ->first();

            if (! $supplierUser) {
                $this->setLog($row, 'Supplier user mapping was not found for the provided supplier and user_supplier.');
                return;
            }

            if (! empty($supplierUser->IUSER_ID)) {
                $this->setLog($row, 'The selected supplier user is already assigned to another user.');
            }
        }
    }

    protected function checkExistingUser(array $row, string $column, string $inputField, string $label): void
    {
        if (empty($row[$inputField])) {
            return;
        }

        $user = User::withTrashed()->where($column, $row[$inputField])->first();

        if (! $user) {
            return;
        }

        if ($user->DDELETE !== null) {
            $this->setLog($row, "This {$label} already exists but the account has been deleted.");
            return;
        }

        $this->setLog($row, "This {$label} is already in use.");
    }

    protected function parseRoleNames(?string $roleNames): array
    {
        if ($roleNames === null) {
            return [];
        }

        return collect(explode(',', $roleNames))
            ->map(fn($role) => trim($role))
            ->filter()
            ->unique()
            ->values()
            ->toArray();
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
