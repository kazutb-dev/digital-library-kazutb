<?php

namespace App\Models\Library;

use Illuminate\Database\Eloquent\Model;

abstract class ReadOnlyPgsqlModel extends Model
{
    protected $connection = 'pgsql';

    protected $guarded = [];

    public $timestamps = false;

    protected static function booted(): void
    {
        static::creating(fn (): bool => false);
        static::updating(fn (): bool => false);
        static::deleting(fn (): bool => false);
    }
}
