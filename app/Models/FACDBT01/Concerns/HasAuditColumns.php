<?php

namespace App\Models\FACDBT01\Concerns;

use App\Services\FACDBT\FacdbtTable;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Kolom audit wajib (VCREA, DCREA, VMODI, DMODI, VDELETE, DDELETE) dan soft delete untuk model tabel FACDBT.
 * - simpan baru  : VCREA / DCREA
 * - ubah         : VMODI / DMODI
 * - hapus        : VDELETE / DDELETE (baris tidak dihapus; query model otomatis melewatinya)
 * - pulihkan     : VDELETE / DDELETE dikosongkan (lihat saveRestoring())
 */
trait HasAuditColumns
{
    use SoftDeletes;

    public static function bootHasAuditColumns(): void
    {
        static::creating(function ($model) {
            $model->VCREA ??= FacdbtTable::user();
            $model->DCREA ??= now();
        });

        // Soft delete tidak lewat save() (lihat runSoftDelete), jadi hanya ubah / pulihkan yang mengisi VMODI / DMODI
        static::updating(function ($model) {
            $model->VMODI = FacdbtTable::user();
            $model->DMODI = now();
        });

        static::restoring(function ($model) {
            $model->{FacdbtTable::DELETED_BY} = null;
        });
    }

    public function getDeletedAtColumn()
    {
        return FacdbtTable::DELETED_AT;
    }

    /**
     * Soft delete: isi DDELETE dan VDELETE.
     */
    protected function runSoftDelete()
    {
        $columns = FacdbtTable::deleteValues();

        $this->setKeysForSaveQuery($this->newModelQuery())->update($columns);

        foreach ($columns as $column => $value) {
            $this->{$column} = $value;
        }
        $this->syncOriginalAttributes(array_keys($columns));

        $this->fireModelEvent('trashed', false);
    }

    /**
     * Simpan ke baris yang cocok dengan $match (termasuk yang sudah dihapus, yang lalu dipulihkan),
     * atau buat baris baru bila belum ada.
     */
    public static function saveRestoring(array $match, array $values): static
    {
        $model = static::withTrashed()->where($match)->first() ?? new static($match);
        $model->fill($values);

        if ($model->exists && $model->trashed()) {
            $model->{FacdbtTable::DELETED_AT} = null;
            $model->{FacdbtTable::DELETED_BY} = null;
        }

        $model->save();

        return $model;
    }

    /**
     * Catat perubahan (VMODI / DMODI) tanpa mengubah kolom lain, mis. saat daftar detailnya diganti.
     */
    public function touchAudit(): void
    {
        $this->forceFill(['VMODI' => FacdbtTable::user(), 'DMODI' => now()])->saveQuietly();
    }

    /**
     * Soft delete massal berdasarkan primary key.
     */
    public static function softDeleteKeys(array $keys): int
    {
        if (empty($keys)) {
            return 0;
        }

        return static::query()->whereKey($keys)->toBase()->update(FacdbtTable::deleteValues());
    }
}
