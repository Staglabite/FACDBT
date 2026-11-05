<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BaseModel extends Model
{
    use Auditable, SoftDeletes;

    protected $primaryKey = 'IID';

    const CREATED_AT = 'DCREA';

    const UPDATED_AT = 'DMODI';

    const DELETED_AT = 'DDELETE';
}
