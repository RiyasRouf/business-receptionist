<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    protected $fillable = ['name', 'color', 'tagline'];

    public static function current(): self
    {
        // firstOrCreate only passes attributes on insert and doesn't
        // re-fetch the row, so relying on the migration's column
        // defaults left name/color/tagline unset on the in-memory
        // model the first time this ran — spell them out here instead.
        return self::firstOrCreate(['id' => 1], [
            'name' => 'Business AI',
            'color' => '#6366F1',
            'tagline' => 'AI Business Receptionist Platform',
        ]);
    }
}
