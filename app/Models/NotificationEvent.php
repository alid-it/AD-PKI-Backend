<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationEvent extends Model
{
    protected $fillable = [
        'event',
        'enabled',
        'mail',
        'webhook',
        'telegram',
        'title_template',
        'message_template',
        'recipient_type',
        'recipient_value',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'mail' => 'boolean',
        'webhook' => 'boolean',
        'telegram' => 'boolean',
    ];

    /**
     * 🔥 NEU: Recipients Relation
     */
    public function recipients()
    {
        return $this->hasMany(NotificationEventRecipient::class);
    }
}