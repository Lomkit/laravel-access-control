<?php

namespace Lomkit\Access\Tests\Support\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Lomkit\Access\Tests\Support\Database\Factories\UserFactory;

class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;

    protected static function newFactory()
    {
        return new UserFactory();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'should_client',
        'should_global',
        'should_own',
        'should_shared',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'should_shared'     => 'bool',
        'should_global'     => 'bool',
        'should_client'     => 'bool',
        'should_own'        => 'bool',
    ];
}
