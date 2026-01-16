<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\UserInviteMail;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('manage-users');

        $users = User::query()
            ->orderBy('email')
            ->paginate(50)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'roles' => ['member', 'editor', 'admin'],
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('manage-users');

        return view('admin.users.create', [
            'roles' => ['member', 'editor', 'admin'],
        ]);
    }

    public function show(Request $request, User $user): View
    {
        Gate::authorize('manage-users');

        $user->load('astroProfile');

        return view('admin.users.show', [
            'user' => $user,
        ]);
    }

    public function edit(Request $request, User $user): View
    {
        Gate::authorize('manage-users');

        $user->load('astroProfile');

        return view('admin.users.edit', [
            'user' => $user,
            'roles' => ['member', 'editor', 'admin'],
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('manage-users');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'gender' => ['nullable', 'in:male,female'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'role' => ['required', 'in:member,editor,admin'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'birth_time' => ['nullable', 'date_format:H:i'],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'birth_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'birth_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'city' => ['nullable', 'string', 'max:191'],
            'phone' => ['nullable', 'string', 'max:32'],
        ]);

        if ($user->id === Auth::id() && $validated['role'] !== 'admin') {
            return redirect()
                ->route('admin.users.edit', $user)
                ->withErrors(['role' => __('You cannot change your own role.')]);
        }

        $user->name = $validated['name'];
        $user->gender = $validated['gender'] ?? null;
        $user->email = $validated['email'];
        $user->role = $validated['role'];
        $user->date_of_birth = $validated['date_of_birth'] ?? null;
        $user->birth_time = $validated['birth_time'] ?? null;
        $user->birth_place = $validated['birth_place'] ?? null;
        $user->birth_latitude = $validated['birth_latitude'] ?? null;
        $user->birth_longitude = $validated['birth_longitude'] ?? null;
        $user->address_line1 = $validated['address_line1'] ?? null;
        $user->address_line2 = $validated['address_line2'] ?? null;
        $user->postal_code = $validated['postal_code'] ?? null;
        $user->city = $validated['city'] ?? null;
        $user->phone = $validated['phone'] ?? null;

        $user->save();

        return redirect()
            ->route('admin.users.show', $user)
            ->with('status', __('User updated.'));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-users');

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['nullable', 'in:male,female'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'in:member,editor,admin'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'birth_time' => ['nullable', 'date_format:H:i'],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'birth_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'birth_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'city' => ['nullable', 'string', 'max:191'],
            'phone' => ['nullable', 'string', 'max:32'],
        ]);

        $user = new User();
        $user->email = $validated['email'];
        $computedName = trim($validated['first_name'] . ' ' . $validated['last_name']);
        $user->name = $computedName !== ''
            ? $computedName
            : (Str::before((string) $user->email, '@') ?: 'Utilisateur');
        $user->gender = $validated['gender'] ?? null;
        $user->role = $validated['role'];
        $user->password = Str::random(32);

        // Private portal: avoid blocking users behind email verification.
        $user->email_verified_at = now();

        $user->date_of_birth = $validated['date_of_birth'] ?? null;
        $user->birth_time = $validated['birth_time'] ?? null;
        $user->birth_place = $validated['birth_place'] ?? null;
        $user->birth_latitude = $validated['birth_latitude'] ?? null;
        $user->birth_longitude = $validated['birth_longitude'] ?? null;
        $user->address_line1 = $validated['address_line1'] ?? null;
        $user->address_line2 = $validated['address_line2'] ?? null;
        $user->postal_code = $validated['postal_code'] ?? null;
        $user->city = $validated['city'] ?? null;
        $user->phone = $validated['phone'] ?? null;

        $user->save();

        // Do not auto-send invitation emails on creation.
        // Admin can send later (bulk or per-user) when ready.
        $status = __('User created. Invitation will be sent later.');

        return redirect()
            ->route('admin.users.index')
            ->with('status', $status);
    }

    public function resendInvite(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('manage-users');

        $token = Password::createToken($user);
        $acceptUrl = route('invite.create', ['token' => $token, 'email' => $user->email]);

        try {
            Mail::to($user->email)->send(new UserInviteMail($user, $token, $acceptUrl));

            $user->forceFill(['invited_at' => now()])->save();

            $status = __('Invitation email sent.');
        } catch (\Throwable $e) {
            report($e);

            $status = __('Invitation email could not be sent.');
        }

        return redirect()
            ->route('admin.users.index')
            ->with('status', $status);
    }

    public function sendPendingInvites(Request $request): RedirectResponse
    {
        Gate::authorize('manage-users');

        $limit = (int) $request->input('limit', 0);
        if ($limit < 0) {
            $limit = 0;
        }
        if ($limit > 500) {
            $limit = 500;
        }

        $q = User::query()
            ->whereNull('invited_at')
            ->orderBy('id');

        if ($limit > 0) {
            $q->limit($limit);
        }

        $users = $q->get();
        $sent = 0;
        $failed = 0;

        foreach ($users as $user) {
            $token = Password::createToken($user);
            $acceptUrl = route('invite.create', ['token' => $token, 'email' => $user->email]);

            try {
                Mail::to($user->email)->send(new UserInviteMail($user, $token, $acceptUrl));
                $user->forceFill(['invited_at' => now()])->save();
                $sent++;
            } catch (\Throwable $e) {
                report($e);
                $failed++;
            }
        }

        $status = $failed === 0
            ? "Invitations envoyées : {$sent}."
            : "Invitations envoyées : {$sent}. Échecs : {$failed}.";

        return redirect()
            ->route('admin.users.index')
            ->with('status', $status);
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('manage-users');

        $validated = $request->validate([
            'role' => ['required', 'in:member,editor,admin'],
        ]);

        if ($user->id === Auth::id() && $validated['role'] !== 'admin') {
            return redirect()
                ->route('admin.users.index')
                ->withErrors(['role' => __('You cannot change your own role.')]);
        }

        $user->update([
            'role' => $validated['role'],
        ]);

        return redirect()
            ->route('admin.users.index')
            ->with('status', __('Role updated.'));
    }
}
