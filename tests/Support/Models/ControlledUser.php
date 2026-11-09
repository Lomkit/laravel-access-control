<?php

namespace Lomkit\Access\Tests\Support\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Lomkit\Access\Controls\HasControl;

class ControlledUser extends Authenticatable
{
    use HasControl;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];
}
