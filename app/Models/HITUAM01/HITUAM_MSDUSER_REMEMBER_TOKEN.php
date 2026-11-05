<?php

namespace App\Models\HITUAM01;

use Illuminate\Database\Eloquent\Model;

class HITUAM_MSDUSER_REMEMBER_TOKEN extends Model
{
    protected $connection = 'hituam';

    protected $table = 'remember_tokens';

    protected $fillable = [
        'user_id',
        'application',
        'token',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(HITUAM_MSHUSER::class, 'user_id', 'IID');
    }
}
