<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\NotificationEvent;
use App\Services\AuditService;

class NotificationEventController extends Controller
{
    /**
     * 🔥 Recipients speichern (wird vom Frontend genutzt)
     */
    public function updateRecipients(Request $request, $id)
    {
        $event = NotificationEvent::findOrFail($id);

        // Alte löschen
        $event->recipients()->delete();

        // Neue speichern
        foreach ($request->roles as $role) {
            $event->recipients()->create([
                'role' => $role
            ]);
        }

        AuditService::log(AuditService::NOTIFICATION_SETTINGS_CHANGED, $event, [
            'event'  => $event->event,
            'roles'  => $request->roles,
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * 🔥 Events inkl. Recipients laden
     */
    public function index()
    {
        // Kein Audit — read-only
        return NotificationEvent::with('recipients')->get();
    }
}
