<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'username',
        'firstname',
        'lastname',
        'email',
        'password',
        'role_id',
        'team_id',     // 🔥 NEU
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'user_permissions');
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    // 🔥 NEU
    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function hasPermission(string $permission): bool
    {
        // 🔥 Direkte User Permission
        if ($this->permissions()->where('key', $permission)->exists()) {
            return true;
        }

        // 🔥 Rollen Permission
        if ($this->role && $this->role->permissions()->where('key', $permission)->exists()) {
            return true;
        }

        return false;
    }

    public function allPermissions(): array
    {
        $permissions = [];

        foreach ($this->permissions as $permission) {
            $permissions[] = $permission->key;
        }

        if ($this->role) {
            foreach ($this->role->permissions as $permission) {
                $permissions[] = $permission->key;
            }
        }

        return array_values(array_unique($permissions));
    }
}