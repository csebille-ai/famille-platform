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

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-users');

        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'in:member,editor,admin'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'city' => ['nullable', 'string', 'max:191'],
            'phone' => ['nullable', 'string', 'max:32'],
        ]);

        $user = new User();
        $user->email = $validated['email'];
        // Temporary placeholder; user will choose their username during invite acceptance.
        $user->name = Str::before((string) $user->email, '@') ?: 'Utilisateur';
        $user->role = $validated['role'];
        $user->password = Str::random(32);

        // Private portal: avoid blocking users behind email verification.
        $user->email_verified_at = now();

        $user->date_of_birth = $validated['date_of_birth'] ?? null;
        $user->address_line1 = $validated['address_line1'] ?? null;
        $user->address_line2 = $validated['address_line2'] ?? null;
        $user->postal_code = $validated['postal_code'] ?? null;
        $user->city = $validated['city'] ?? null;
        $user->phone = $validated['phone'] ?? null;

        $user->save();

        $token = Password::createToken($user);
        $acceptUrl = route('invite.create', ['token' => $token, 'email' => $user->email]);
        try {
            Mail::to($user->email)->send(new UserInviteMail($user, $token, $acceptUrl));

            $status = __('User created and invitation email sent.');
        } catch (\Throwable $e) {
            report($e);

            $status = __('User created, but invitation email could not be sent.');
        }

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

            $status = __('Invitation email sent.');
        } catch (\Throwable $e) {
            report($e);

            $status = __('Invitation email could not be sent.');
        }

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
