<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for the `users` table.
 *
 * Stores Telegram user information, including language preference,
 * current step, and temporary data. The `temp` column is stored as
 * JSON and automatically cast to/from an array by Eloquent.
 */
class User extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id', 'username', 'first_name', 'last_name', 'language', 'step', 'temp',
    ];

    /**
     * Attribute casting.
     *
     * @var array
     */
    protected $casts = [
        'user_id' => 'integer',
        'status'  => 'boolean',
        'temp'    => 'array',
    ];
}
