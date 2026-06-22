<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Models\User;


class CertificateController extends Controller
{
    public function index(Request $request)
    {
        $now  = Carbon::now();
        /** @var User|null $user */
        $user = Auth::user();

        // 🔥 Pagination
        $perPage = min($request->get('per_page', 100), 200);

        $query = Certificate::query()
            ->whereNotIn('type', ['root', 'intermediate']); // 🔥 CA-Certs nicht anzeigen

        // =====================================================
        // 🔥 DREI-STUFEN ZUGRIFFSKONTROLLE
        // =====================================================

        if ($user->hasPermission('certificate.view.all')) {
            // 🔥 alles sehen — kein Filter

        } elseif ($user->hasPermission('certificate.view.team') && $user->team_id) {
            // 🔥 eigene + Team
            $query->where(function ($q) use ($user) {
                $q->where('requested_by', $user->id)
                    ->orWhere('team_id', $user->team_id);
            });
        } else {
            // 🔥 nur eigene (Basis für alle mit certificate.view)
            $query->where('requested_by', $user->id);
        }

        // =====================================================
        // 🔥 FILTER
        // =====================================================

        if ($request->search) {
            $search = strtolower($request->search);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(common_name) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(serial_number) LIKE ?', ["%{$search}%"]);
            });
        }

        if ($request->status) {
            if ($request->status === 'revoked') {
                $query->where('revoked', true);
            } else {
                $query->where('status', $request->status)
                    ->where('revoked', false);
            }
        }

        $certs = $query
            ->orderBy('valid_to', 'asc')
            ->paginate($perPage);

        // =====================================================
        // 🔥 TRANSFORM
        // =====================================================

        $data = $certs->getCollection()->map(function ($cert) use ($now) {
            $days = $now->diffInDays($cert->valid_to, false);

            if ($cert->revoked) {
                $healthStatus = 'revoked';
            } elseif ($days <= 2) {
                $healthStatus = 'critical';
            } elseif ($days <= 7) {
                $healthStatus = 'warning';
            } else {
                $healthStatus = 'ok';
            }

            return [
                'id'              => $cert->id,
                'cn'              => $cert->common_name,
                'type'            => $cert->type,
                'valid_to'        => $cert->valid_to,
                'expires_in_days' => $days,
                'status'          => $cert->status,
                'health_status'   => $healthStatus,
                'is_acme'         => $cert->is_acme,
                'san'             => $cert->san,
                'serial_number'   => $cert->serial_number,
                'revoked'         => $cert->revoked,
                'revoked_at'      => $cert->revoked_at,
                'has_key'         => !empty($cert->key_path),
                'key_type'        => $cert->key_type,
                'key_size'        => $cert->key_size,
                'curve'           => $cert->curve,
                'key_info'        => $cert->key_type === 'rsa'
                    ? 'RSA ' . $cert->key_size
                    : ($cert->key_type === 'ecdsa'
                        ? 'ECDSA ' . $cert->curve
                        : null),
                'team_id'         => $cert->team_id, // 🔥 NEU
                'team_name' => $cert->team?->name,
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $certs->currentPage(),
                'last_page'    => $certs->lastPage(),
                'total'        => $certs->total(),
            ],
        ]);
    }
}
