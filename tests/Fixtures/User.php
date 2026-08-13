<?php

namespace Splicewire\Beam\Rank\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;

    protected $guarded = [];

    protected $table = 'users';
}
