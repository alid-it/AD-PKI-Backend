<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Services\Notifications\NotificationEngine;
use App\Services\AuditService;

class UserController extends Controller
{
    private const SUPPORTED_LOCALES = ['de', 'en', 'es', 'fr', 'it', 'tr'];

    public function index()
    {
        // Kein Audit — read-only
        $users = User::with(['role', 'team'])->get();

        return response()->json(
            $users->map(function ($user) {
                return $this->formatUserResponse($user);
            })
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:255', 'unique:users,username'],
            'firstname' => ['required', 'string', 'min:3', 'max:255'],
            'lastname' => ['required', 'string', 'min:3', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')],
            'team_id' => ['nullable', 'integer', Rule::exists('teams', 'id')],
            'locale' => ['nullable', 'string', Rule::in(self::SUPPORTED_LOCALES)],
        ]);

        $user = User::create([
            'username' => $validated['username'],
            'firstname' => $validated['firstname'],
            'lastname' => $validated['lastname'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role_id' => $validated['role_id'],
            'team_id' => $validated['team_id'] ?? null,
        ]);

        if (!empty($validated['locale'])) {
            UserSetting::setValue($user->id, 'locale', $validated['locale']);
        }

        AuditService::log(AuditService::USER_CREATED, $user, [
            'username' => $user->username,
            'email' => $user->email,
            'role_id' => $user->role_id,
            'team_id' => $user->team_id,
            'locale' => UserSetting::getValue($user->id, 'locale'),
        ]);

        $user->load(['role', 'team']);

        app(NotificationEngine::class)->dispatch('user_created', [
            'username' => $user->username,
            'firstname' => $user->firstname,
            'lastname' => $user->lastname,
            'email' => $user->email,
            'role' => $user->role?->name,
            'user_email' => $user->email,
        ]);

        return response()->json([
            'message_key' => 'backend.user.created',
            'user' => $this->formatUserResponse($user),
        ], 201);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'username' => [
                'required',
                'string',
                'min:3',
                'max:255',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
            'firstname' => ['required', 'string', 'min:3', 'max:255'],
            'lastname' => ['required', 'string', 'min:3', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')],
            'team_id' => ['nullable', 'integer', Rule::exists('teams', 'id')],
            'locale' => ['nullable', 'string', Rule::in(self::SUPPORTED_LOCALES)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $oldRoleId = $user->role_id;
        $oldTeamId = $user->team_id;
        $oldLocale = UserSetting::getValue($user->id, 'locale');

        $user->username = $validated['username'];
        $user->firstname = $validated['firstname'];
        $user->lastname = $validated['lastname'];
        $user->email = $validated['email'];
        $user->role_id = $validated['role_id'];
        $user->team_id = $validated['team_id'] ?? null;

        if (!empty($validated['password'])) {
            $user->password = $validated['password'];
        }

        $user->save();

        if (!empty($validated['locale'])) {
            UserSetting::setValue($user->id, 'locale', $validated['locale']);
        }

        $newLocale = UserSetting::getValue($user->id, 'locale');

        AuditService::log(AuditService::USER_UPDATED, $user, [
            'username' => $user->username,
            'email' => $user->email,
            'role_changed' => $oldRoleId !== $validated['role_id'],
            'team_changed' => $oldTeamId !== ($validated['team_id'] ?? null),
            'locale_changed' => $oldLocale !== $newLocale,
            'locale' => $newLocale,
        ]);

        if ($oldRoleId !== $validated['role_id']) {
            AuditService::log(AuditService::USER_ROLE_CHANGED, $user, [
                'username' => $user->username,
                'old_role_id' => $oldRoleId,
                'new_role_id' => $validated['role_id'],
            ]);
        }

        $user->load(['role', 'team']);

        return response()->json([
            'message_key' => 'backend.user.updated',
            'user' => $this->formatUserResponse($user),
        ]);
    }

    public function destroy(User $user)
    {
        if ($user->id === 1) {
            return response()->json([
                'error_key' => 'backend.user.root_cannot_be_deleted',
            ], 403);
        }

        $data = [
            'username' => $user->username,
            'user_email' => $user->email,
        ];

        AuditService::log(AuditService::USER_DELETED, null, [
            'username' => $user->username,
            'email' => $user->email,
            'locale' => UserSetting::getValue($user->id, 'locale'),
        ]);

        $user->delete();

        app(NotificationEngine::class)->dispatch('user_deleted', $data);

        return response()->json([
            'message_key' => 'backend.user.deleted',
        ]);
    }

    private function formatUserResponse(User $user): array
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'firstname' => $user->firstname,
            'lastname' => $user->lastname,
            'email' => $user->email,
            'role' => $user->role?->name,
            'team_id' => $user->team_id,
            'team' => $user->team ? [
                'id' => $user->team->id,
                'name' => $user->team->name,
            ] : null,
            'locale' => UserSetting::getValue($user->id, 'locale'),
        ];
    }
}