<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PasswordResetToken extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'token_hash',
        'expires_at',
        'used_at',
        'created_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function (PasswordResetToken $token) {
            $token->{$token->getKeyName()} ??= (string) Str::uuid();
            $token->created_at ??= now();
        });
    }
}
