<?php

namespace App\Models\HITUAM01;

use Illuminate\Database\Eloquent\Model;

class HITUAM_MSHROLE_ACCESS extends Model
{
    protected $connection = 'hituam';

    protected $table = 'HITUAM_MSHROLEACCESS';

    public $timestamps = false;

    const CREATED_AT = 'DCREA';

    const UPDATED_AT = 'DMODI';

    protected $fillable = [
        'VROLE',
        'VMENUID',
        'BSTATUS',
    ];

    public function role()
    {
        return $this->belongsTo(HITUAM_MSHROLE::class, 'VROLE', 'VROLENAME')
            ->whereNull('HITUAM_MSHROLES.DDELETE');
    }

    public function menu()
    {
        return $this->belongsTo(HITUAM_MSHMENU::class, 'VMENUID', 'VAPPID');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Jangan isi DMODI pada create, hanya DCREA saja
            $model->VMODI = null;
            $model->DMODI = null;
        });

        static::updating(function ($model) {
            $user = Auth::user()?->VUSERNAME ?? Auth::user()?->username ?? 'SYSTEM';

            $model->VMODI = $user;
            $model->DMODI = now();
        });
    }
}
