<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for the `jobs` table.
 *
 * The underlying migration uses plain integer timestamps (only
 * `created_at`, no `updated_at`), so Eloquent's automatic timestamp
 * management is disabled and every value is inserted explicitly by
 * DatabaseQueue.
 */
class Job extends Model
{
    protected $table = 'jobs';

    protected $fillable = [
        'queue', 'payload', 'attempts', 'reserved_at', 'available_at', 'created_at',
    ];

    public $timestamps = false;

    protected $casts = [
        'attempts'     => 'integer',
        'reserved_at'  => 'integer',
        'available_at' => 'integer',
        'created_at'   => 'integer',
    ];
}
