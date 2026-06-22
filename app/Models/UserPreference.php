<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPreference extends Model
{
    protected $fillable = ['user_id', 'key', 'value'];

    // =========================================
    // 🔥 Helpers
    // =========================================

    public static function getValue(int $userId, string $key, ?string $default = null): ?string
    {
        return static::where('user_id', $userId)
            ->where('key', $key)
            ->value('value') ?? $default;
    }

    public static function setValue(int $userId, string $key, ?string $value): void
    {
        static::updateOrCreate(
            ['user_id' => $userId, 'key' => $key],
            ['value' => $value]
        );
    }
}