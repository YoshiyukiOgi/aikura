<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;

    protected $fillable = [
        'employee_id',
        'name',
        'email',
        'password',
        'is_active',
        'disabled_at',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'disabled_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function hasPermission(string $permissionCode): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return $this->roles()
            ->where('roles.is_active', true)
            ->whereHas('permissions', function ($query) use ($permissionCode): void {
                $query->where('code', $permissionCode)
                    ->where('permissions.is_active', true);
            })
            ->exists();
    }

    public function hasRole(string $roleCode): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return $this->roles()
            ->where('roles.code', $roleCode)
            ->where('roles.is_active', true)
            ->exists();
    }
}
