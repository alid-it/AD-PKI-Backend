<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        // 🔥 Pagination
        $perPage = min($request->get('per_page', 50), 200);

        $query = AuditLog::with('user')
            ->orderBy('created_at', 'desc');

        // 🔥 Filter: Aktion
        if ($request->action) {
            $query->where('action', $request->action);
        }

        // 🔥 Filter: User
        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        // 🔥 Filter: Datumsbereich
        if ($request->date_from) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
        }

        $logs = $query->paginate($perPage);

        $data = $logs->getCollection()->map(function (AuditLog $log) {
            return [
                'id'           => $log->id,
                'log_id'       => $log->meta['log_id'] ?? null,
                'username'     => $log->user?->username ?? 'System',
                'action'       => $log->action,
                'subject_type' => $log->subject_type,
                'subject_id'   => $log->subject_id,
                'ip_address'   => $log->ip_address,
                'created_at'   => $log->created_at,
                'meta'         => $log->meta,
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page'    => $logs->lastPage(),
                'total'        => $logs->total(),
            ]
        ]);
    }

    public function actions()
    {
        // 🔥 Alle vorhandenen Aktionen für Filter-Dropdown
        $actions = AuditLog::select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        return response()->json($actions);
    }
}