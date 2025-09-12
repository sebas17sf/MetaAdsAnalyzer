<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'Users';
    protected $primaryKey = 'Id';
    public $timestamps = false;


    protected $fillable = [
        'Username',
        'PasswordHash',
        'Email',
        'Role',
        'CreatedAt',
        'LastLogin',
        'IsActive',
        'EmailConfirmed',
        'EmailVerificationToken',
        'EmailVerificationTokenExpires',
    ];

    protected $hidden = [
        'PasswordHash',
        'EmailVerificationToken',
    ];

    protected $casts = [
        'Id' => 'integer',
        'CreatedAt' => 'datetime',
        'LastLogin' => 'datetime',
        'IsActive' => 'boolean',
        'EmailConfirmed' => 'boolean',
        'EmailVerificationTokenExpires' => 'datetime',
    ];
}
