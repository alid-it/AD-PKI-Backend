<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationEventRecipient extends Model
{
    protected $fillable = [
        'notification_event_id',
        'role',
    ];

    public function event()
    {
        return $this->belongsTo(NotificationEvent::class);
    }
}