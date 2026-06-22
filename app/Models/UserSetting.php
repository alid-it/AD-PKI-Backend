<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSetting extends Model
{
    protected $fillable = [
        'user_id',
        'key',
        'value',
    ];

    public static function getValue(int $userId, string $key, $default = null)
    {
        $value = static::where('user_id', $userId)
            ->where('key', $key)
            ->value('value');

        if (!is_null($value)) {
            return $value;
        }

        return $default;
    }

    public static function setValue(int $userId, string $key, $value): void
    {
        static::updateOrCreate(
            [
                'user_id' => $userId,
                'key' => $key,
            ],
            [
                'value' => $value,
            ]
        );
    }
}