<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    // 🔹 Zentrale Defaults (nur was wirklich Sinn macht!)
    protected static array $defaults = [
        'company_name' => 'AD-PKI',

        'color_primary' => '#3b82f6',
        'color_secondary' => '#e5e7eb',
        'color_success' => '#22c55e',
        'color_danger' => '#ef4444',
        'color_bg' => '#f5f6f8',
        'color_card' => '#ffffff',
        'color_navbar' => '#1e293b',
        'color_navbar_text' => '#f1f5f9',
        'mail_enabled' => '0',
        'mail_host' => '',
        'mail_port' => '587',
        'mail_username' => '',
        'mail_password' => '',
        'mail_from_email' => '',
        'mail_from_name' => '',
        'mail_encryption' => 'tls',

        'webhook_enabled' => '0',
        'webhook_url' => '',
        'webhook_method' => 'POST',
        'webhook_secret' => '',

        'telegram_enabled' => '0',
        'telegram_bot_token' => '',
        'telegram_chat_id' => '',

        'app.default_locale' => 'en',
    ];

    public static function getValue(string $key, $default = null)
    {
        $value = static::where('key', $key)->value('value');

        // 🔹 DB hat Vorrang
        if (!is_null($value)) {
            return $value;
        }

        // 🔹 System Defaults (z. B. Branding)
        if (array_key_exists($key, static::$defaults)) {
            return static::$defaults[$key];
        }

        // 🔹 Fallback (optional)
        return $default;
    }

    public function settings()
    {
        return $this->hasMany(\App\Models\UserSetting::class);
    }

    // 🔹 optional aber sehr sauber
    public static function setValue(string $key, $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }
}
