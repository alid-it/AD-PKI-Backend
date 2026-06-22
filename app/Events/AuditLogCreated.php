<?php

namespace App\Events;

use App\Models\AuditLog;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AuditLogCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public AuditLog $auditLog;

    public function __construct(AuditLog $auditLog)
    {
        $this->auditLog = $auditLog;
    }

    public function broadcastOn(): array
    {
        // 🔥 Public Channel — kein Auth nötig für interne WebUI
        return [
            new Channel('audit-logs'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'AuditLogCreated';
    }

    public function broadcastWith(): array
    {
        return [
            'id'           => $this->auditLog->id,
            'log_id'       => $this->auditLog->meta['log_id'] ?? null,
            'username'     => $this->auditLog->user?->username ?? 'System',
            'action'       => $this->auditLog->action,
            'subject_type' => $this->auditLog->subject_type,
            'subject_id'   => $this->auditLog->subject_id,
            'ip_address'   => $this->auditLog->ip_address,
            'created_at'   => $this->auditLog->created_at,
            'meta'         => $this->auditLog->meta,
        ];
    }
}
