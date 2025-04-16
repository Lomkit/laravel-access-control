<?php

namespace Lomkit\Access\Tests\Support\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Lomkit\Access\Tests\Support\Database\Factories\ClientFactory;
use Lomkit\Access\Tests\Support\Database\Factories\UserFactory;

class Client extends Authenticatable
{
    use HasFactory;

    protected static function newFactory()
    {
        return new ClientFactory();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
    ];

    public function models() {
        return $this->hasMany(Model::class, 'client_id');
    }

    public function users() {
        return $this->hasMany(User::class, 'client_id');
    }
}
