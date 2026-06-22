<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Services\AuditService;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    // 🔹 GET /api/teams
    public function index()
    {
        $teams = Team::withCount(['users', 'certificates'])->get();

        return response()->json($teams);
    }

    // 🔹 POST /api/teams
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255|unique:teams,name',
            'description' => 'nullable|string|max:500',
        ]);

        $team = Team::create($validated);

        AuditService::log('team.created', $team, [
            'name' => $team->name,
        ]);

        return response()->json($team, 201);
    }

    // 🔹 PUT /api/teams/{id}
    public function update(Request $request, int $id)
    {
        $team = Team::findOrFail($id);

        $validated = $request->validate([
            'name'        => 'required|string|max:255|unique:teams,name,' . $id,
            'description' => 'nullable|string|max:500',
        ]);

        $team->update($validated);

        AuditService::log('team.updated', $team, [
            'name' => $team->name,
        ]);

        return response()->json($team);
    }

    // 🔹 DELETE /api/teams/{id}
    public function destroy(int $id)
    {
        $team = Team::findOrFail($id);

        // 🔥 Users + Certificates bekommen team_id = null (durch onDelete set null)
        $team->delete();

        AuditService::log('team.deleted', null, [
            'name' => $team->name,
        ]);

        return response()->json(['success' => true]);
    }
}
