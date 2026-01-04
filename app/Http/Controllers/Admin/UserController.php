<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
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
