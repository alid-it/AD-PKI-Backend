<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\CertificateTemplate;
use App\Services\AuditService;
use Illuminate\Http\Request;

class CertificateTemplateController extends Controller
{
    public function index()
    {
        // Kein Audit — read-only
        return CertificateTemplate::all();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'         => 'required|string|max:255',
            'ou'           => 'nullable|string',
            'organization' => 'nullable|string',
            'locality'     => 'nullable|string',
            'state'        => 'nullable|string',
            'country'      => 'nullable|string',
            'email'        => 'nullable|string',
        ]);

        $template = CertificateTemplate::create($data);

        AuditService::log(AuditService::TEMPLATE_CREATED, $template, [
            'name' => $template->name,
        ]);

        return $template;
    }

    public function destroy($id)
    {
        $template = CertificateTemplate::findOrFail($id);

        AuditService::log(AuditService::TEMPLATE_DELETED, $template, [
            'name' => $template->name,
        ]);

        $template->delete();

        return response()->json(['success' => true]);
    }
}
