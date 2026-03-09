<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $primaryKey = 'user_id';

    protected $fillable = [
        'full_name',
        'email',
        'password_hash',
        'role',
        'status',
    ];

    protected $hidden = [
        'password_hash',
    ];

    /**
     * Laravel expects the password field to be called "password".
     * Our column is `password_hash`, so we map Auth to that.
     */
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }
}
