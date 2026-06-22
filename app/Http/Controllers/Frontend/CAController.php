<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use Illuminate\Support\Carbon;

class CAController extends Controller
{
    public function index()
    {
        $now = Carbon::now();

        $root = Certificate::where('type', 'root')->first();
        $intermediates = Certificate::where('type', 'intermediate')->get();

        $mapCert = function ($cert) use ($now) {
            if (!$cert) return null;

            $days = $now->diffInDays($cert->valid_to, false);

            if ($cert->revoked) {
                $status = 'revoked';
            } elseif ($days <= 2) {
                $status = 'critical';
            } elseif ($days <= 7) {
                $status = 'warning';
            } else {
                $status = 'ok';
            }

            return [
                'id'            => $cert->id,
                'int_id'        => 'int-' . $cert->id,
                'cn'            => $cert->common_name,
                'valid_to'      => $cert->valid_to,
                'serial_number' => $cert->serial_number,
                'status'        => $status,
            ];
        };

        return response()->json([
            'root'          => $mapCert($root),
            'intermediates' => $intermediates->map($mapCert),
        ]);
    }
}