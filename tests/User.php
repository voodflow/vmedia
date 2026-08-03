<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Tests;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $guarded = [];

    protected $hidden = [
        'password',
    ];
}
