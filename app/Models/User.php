<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Role $role
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Store> $stores
 * @property-read Collection<int, Trainee> $assignedTrainees
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'role' => Role::class,
        ];
    }

    /**
     * The stores this user (manager) belongs to. Empty for super admins.
     *
     * @return BelongsToMany<Store, $this>
     */
    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'manager_store');
    }

    /**
     * Trainees explicitly assigned to this manager.
     *
     * @return BelongsToMany<Trainee, $this>
     */
    public function assignedTrainees(): BelongsToMany
    {
        return $this->belongsToMany(Trainee::class, 'manager_trainee');
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === Role::SuperAdmin;
    }

    public function isManager(): bool
    {
        return $this->role === Role::Manager;
    }

    /**
     * Whether this user is allowed to switch between stores at all — super
     * admins always can; a manager only needs it when assigned to more than
     * one store (a single-store manager is already implicitly scoped).
     */
    public function canFilterByStore(): bool
    {
        return $this->isSuperAdmin() || $this->stores()->count() > 1;
    }

    /**
     * Resolve a requested store id against what this user may actually filter
     * by. Super admins may pick any store; a manager may only pick one of
     * their own. Returns null (no filter / "all") when the request is empty
     * or not permitted.
     */
    public function resolveStoreFilter(?int $requestedStoreId): ?int
    {
        if ($requestedStoreId === null) {
            return null;
        }

        if ($this->isSuperAdmin()) {
            return $requestedStoreId;
        }

        return $this->stores()->whereKey($requestedStoreId)->exists() ? $requestedStoreId : null;
    }
}
