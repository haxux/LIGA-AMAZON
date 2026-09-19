<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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
     * This is deliberately NOT the place to restrict the planned `técnico`
     * role: a coach who uses panel features must be able to open the panel, so
     * any gate here would have to return true for them anyway. Their limits
     * belong to per-resource visibility and record policies — do not "fix"
     * this into an is_admin check.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return true;
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
