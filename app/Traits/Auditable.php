<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;

trait Auditable
{
    /**
     * Initialize the trait for an instance.
     */
    public function initializeAuditable()
    {
        // Add the audit fields to the fillable array
        $fillable = [
            $this->getCreatedByField(),
            $this->getUpdatedByField(),
        ];

        // Add deleted_by to fillable if model uses soft deletes
        if ($this->usesSoftDeletes()) {
            $fillable[] = $this->getDeletedByField();
        }

        $this->mergeFillable($fillable);
    }

    /**
     * Boot the trait.
     *
     * @return void
     */
    public static function bootAuditable()
    {
        // Add created_by when creating a new record
        static::creating(function ($model) {
            $createdByField = $model->getCreatedByField();

            if (! $model->isDirty($createdByField) && Auth::check()) {
                $model->{$createdByField} = Auth::user()->VUSERNAME;
            }
        });

        // Add updated_by when updating a record
        static::updating(function ($model) {
            $updatedByField = $model->getUpdatedByField();

            if (Auth::check()) {
                $model->{$updatedByField} = Auth::user()->VUSERNAME;
            }
        });

        // Add deleted_by when soft deleting a record
        static::deleting(function ($model) {
            // Only set deleted_by if the model uses soft deletes
            if ($model->usesSoftDeletes() && Auth::check()) {
                $deletedByField = $model->getDeletedByField();

                // Update the deleted_by field before the soft delete occurs
                $model->withoutEvents(function () use ($model, $deletedByField) {
                    $model->updateQuietly([$deletedByField => Auth::user()->VUSERNAME]);
                });
            }
        });
    }

    /**
     * Check if the model uses soft deletes.
     *
     * @return bool
     */
    public function usesSoftDeletes()
    {
        return in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses_recursive(static::class));
    }

    /**
     * Bulk soft delete with proper auditing
     *
     * @param  string|null  $deletedBy
     * @return int
     */
    public static function auditableBulkDelete(array $ids, $deletedBy = null)
    {
        if (empty($ids)) {
            return 0;
        }

        $instance = new static;

        if (! $instance->usesSoftDeletes()) {
            // If not using soft deletes, use regular delete
            return static::whereIn('id', $ids)->delete();
        }

        $deletedBy = $deletedBy ?: (Auth::check() ? Auth::user()->VUSERNAME : null);

        return static::whereIn('id', $ids)->update([
            $instance->getDeletedAtColumn() => now(),
            $instance->getDeletedByField() => $deletedBy,
        ]);
    }

    /**
     * Bulk soft delete (alias for auditableBulkDelete)
     *
     * @return int
     */
    public static function bulkSoftDelete(array $ids)
    {
        return static::auditableBulkDelete($ids);
    }

    /**
     * Delete with proper auditing (works for both single and collections)
     *
     * @return int
     */
    public static function deleteWithAudit(array $ids)
    {
        // For better performance on large datasets, use bulk operation
        if (count($ids) > 10) {
            return static::auditableBulkDelete($ids);
        }

        // For smaller datasets, use individual deletes to ensure all events fire
        $models = static::whereIn('id', $ids)->get();
        $deletedCount = 0;

        foreach ($models as $model) {
            if ($model->delete()) {
                $deletedCount++;
            }
        }

        return $deletedCount;
    }

    /**
     * Bulk delete without auditable
     */
    public static function bulkDelete(array $ids)
    {
        return static::whereIn('id', $ids)->delete();
    }

    /**
     * Get the field name for who created the record.
     *
     * @return string
     */
    public function getCreatedByField()
    {
        return defined('static::CREATED_BY') ? static::CREATED_BY : 'VCREA';
    }

    /**
     * Get the field name for who updated the record.
     *
     * @return string
     */
    public function getUpdatedByField()
    {
        return defined('static::UPDATED_BY') ? static::UPDATED_BY : 'VMODI';
    }

    /**
     * Get the field name for who deleted the record.
     *
     * @return string
     */
    public function getDeletedByField()
    {
        return defined('static::DELETED_BY') ? static::DELETED_BY : 'VDELETE';
    }

    /**
     * Set the created_by attribute manually.
     *
     * @param  string  $userName
     * @return $this
     */
    public function setCreatedBy($userName)
    {
        $this->{$this->getCreatedByField()} = $userName;

        return $this;
    }

    /**
     * Set the updated_by attribute manually.
     *
     * @param  string  $userName
     * @return $this
     */
    public function setUpdatedBy($userName)
    {
        $this->{$this->getUpdatedByField()} = $userName;

        return $this;
    }

    /**
     * Set the deleted_by attribute manually.
     *
     * @param  string  $userName
     * @return $this
     */
    public function setDeletedBy($userName)
    {
        $this->{$this->getDeletedByField()} = $userName;

        return $this;
    }

    /**
     * Merge fillable properties.
     *
     * @return void
     */
    public function mergeFillable(array $fillable)
    {
        $this->fillable = array_merge($this->fillable ?? [], $fillable);
    }
}
