<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Validation\ValidationException;

#[Fillable(['name', 'email', 'password', 'role', 'club_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_COACH = 'tecnico';

    /**
     * Vocabulario en PHP, no un enum de base de datos — mismo criterio que
     * `GameEvent::TYPES` y `SquadMembership::TYPES`.
     */
    public const ROLES = [
        self::ROLE_ADMIN => 'Administrador',
        self::ROLE_COACH => 'Director técnico',
    ];

    /**
     * Invariante de entidad: un técnico dirige un club, y un administrador no
     * dirige ninguno. Va aquí y no en el esquema porque una restricción de
     * base de datos no sabe decir "obligatorio sólo si el rol es este" sin
     * recurrir a un CHECK que MySQL y SQLite tratan distinto (design D2 de la
     * Fase 2).
     */
    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if ($user->role === self::ROLE_COACH && $user->club_id === null) {
                throw ValidationException::withMessages([
                    'club_id' => 'Un director técnico tiene que dirigir un club.',
                ]);
            }

            if ($user->role === self::ROLE_ADMIN && $user->club_id !== null) {
                throw ValidationException::withMessages([
                    'club_id' => 'Un administrador no dirige ningún club.',
                ]);
            }
        });
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isCoach(): bool
    {
        return $this->role === self::ROLE_COACH;
    }

    /**
     * Whether this user may open the Filament panel at all (design D1, D9).
     *
     * Implementing FilamentUser is what makes panel access an application
     * decision instead of an environment one: without it, Filament's
     * Authenticate middleware denies every user in any environment other than
     * `local`, so the first `APP_ENV=production` deploy 403s the owner out of
     * their own panel.
     *
     * Returning true unconditionally is sound only while `users` can be
     * populated exclusively by `php artisan make:filament-user` — there is no
     * registration route and the panel does not enable one.
     * PanelAccessTest::test_no_user_creation_path_exists_outside_administrator_action()
     * asserts that precondition and fails the suite the day it stops holding.
     *
     * Desde la Fase 9 hay dos paneles y la puerta depende de cuál se abre. Esto
     * sigue sin ser el control de permisos: un técnico entra en `/club` y ahí
     * dentro lo que puede tocar lo deciden las políticas por registro. Lo que
     * esta puerta impide es que un rol abra el panel del otro, donde ni
     * siquiera existen los recursos que le corresponden.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'admin' => $this->isAdmin(),
            'club' => $this->isCoach(),
            default => false,
        };
    }

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
        ];
    }
}
