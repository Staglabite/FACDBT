<?php

namespace App\Models\HITUAM01;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
// use App\Models\FACTWM01\FACTWM_MSHSUPPLIER_COMMUNICATION_METHOD as SupplierUser;
use App\Models\HITUAM01\HITUAM_MSDUSER_REMEMBER_TOKEN as UserRememberToken;
use App\Models\HITUAM01\HITUAM_MSHMENU as Menu;
use App\Models\HITUAM01\HITUAM_MSHROLE as Role;
use App\Models\HITUAM01\HITUAM_MSHSERVICE as Service;
use App\Policies\UserPolicy;
use App\Traits\Auditable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\HasApiTokens;

use function Symfony\Component\Clock\now;

#[UsePolicy(UserPolicy::class)]
class HITUAM_MSHUSER extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use Auditable, HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $connection = 'hituam';

    protected $primaryKey = 'IID';

    const CREATED_AT = 'DCREA';

    const UPDATED_AT = 'DMODI';

    const DELETED_AT = 'DDELETE';

    protected $table = 'HITUAM_MSHUSER';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'VEMPNO',
        'VUSERNAME',
        'VPASSWORD',
        'VPHONE',
        'VEMAIL',
        'VPHOTO',
        'VLDAP',
        'DLASTLOGIN',
        'VSESSIONTOKEN',
        'VMODI',
        'DMODI',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'VPASSWORD',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'VPASSWORD' => 'hashed',
        ];
    }

    public function rememberTokens()
    {
        return $this->hasMany(UserRememberToken::class, 'user_id', 'IID');
    }

    public function getRememberToken()
    {
        $token = $this->rememberTokens()
            ->where('application', config('app.code'))
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();

        return $token?->token;
    }

    public function setRememberToken($value)
    {
        UserRememberToken::updateOrCreate(
            [
                'user_id' => $this->IID,
                'application' => config('app.code'),
            ],
            [
                'token' => $value,
                'expires_at' => Carbon::now()->addHour(2),
            ]
        );
    }

    public function getRememberTokenName()
    {
        return 'remember_token';
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'HITUAM_MSHUSERROLES',
            'VUSERNAME',   // pivot → user
            'VROLE',   // pivot → role
            'VUSERNAME',   // PK user
            'VROLENAME'    // PK role
        );
    }

    public function getRoleNames()
    {
        return $this->roles()->pluck('VROLENAME');
    }

    public function getRoleIds()
    {
        return $this->roles()->pluck('HITUAM_MSHROLES.NID');
    }

    // public function supplierUser()
    // {
    //     return $this->hasOne(SupplierUser::class, 'IUSER_ID', 'IID');
    // }

    public function services()
    {
        return Service::whereHas('roles', function ($query) {
            $query->whereIn('IROLE_ID', $this->roles()->pluck('IROLE_ID'));
        });
    }

    public function serviceNames(): array
    {
        // dd($this->roles()->pluck('HITUAM_MSHROLES.VROLENAME'));
        return HITUAM_MSHROLE_SERVICE::query()
            ->with('service')
            ->whereHas('service', function ($query) {
                $query->where('DBEGINEFF', '<=', now())
                    ->where('DENDEFF', '>=', now());
            })
            ->whereIn('VROLE', $this->roles()
                ->pluck('HITUAM_MSHROLES.VROLENAME')
                ->toArray())
            ->get()
            ->map(function ($item) {
                return $item->service->VNAME;
            })->toArray();
    }

    public function menuIds(): array
    {
        return $this->services()->get()->pluck('VMENUID')->unique()->toArray();
    }

    public function menus()
    {
        return Menu::query()->whereIn('IID', $this->menuIds())->get();
    }

    public function assignRoles(array $roles)
    {
        $roles = Role::query()->whereIn('VROLENAME', $roles)->pluck('VROLENAME')->toArray();

        if (empty($roles)) {
            return;
        }

        $this->roles()->attach($roles);
    }

    public function syncRoles(array $roles)
    {
        $roles = Role::query()->whereIn('VROLENAME', $roles)->pluck('VROLENAME')->toArray();
        
        $this->roles()->syncWithPivotValues(
        $roles,
        [
            'VCREA' => Auth::user()?->VUSERNAME ?? 'SYSTEM',
            'DCREA' => now(),
        ]
    );

        $this->roles()->sync($roles);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->VCREA = Auth::user()?->VUSERNAME ?? 'SYSTEM';

            // Jangan isi DMODI pada create, hanya DCREA saja
            $model->VMODI = null;
            $model->DMODI = null;
        });

        static::updating(function ($model) {
            $model->VMODI = Auth::user()?->VUSERNAME ?? 'SYSTEM';
            $model->DMODI = now();
        });
    }
}
