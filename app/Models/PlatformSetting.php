<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    protected $fillable = ['name', 'color', 'tagline'];

    public static function current(): self
    {
        return self::firstOrCreate(['id' => 1]);
    }
}
