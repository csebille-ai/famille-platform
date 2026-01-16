<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FamilyController extends Controller
{
    public function index(Request $request): View
    {
        $adults = Person::query()
            ->where('is_child', false)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $children = Person::query()
            ->where('is_child', true)
            ->with(['guardians:id,name'])
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        return view('family.index', [
            'adults' => $adults,
            'children' => $children,
        ]);
    }

    public function createChild(Request $request): View
    {
        $this->authorize('createChild', Person::class);

        $users = User::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('family.children.create', [
            'users' => $users,
        ]);
    }

    public function storeChild(Request $request): RedirectResponse
    {
        $this->authorize('createChild', Person::class);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date'],
            'guardians' => ['nullable', 'array'],
        ]);

        $child = new Person();
        $child->first_name = (string) $validated['first_name'];
        $child->last_name = $validated['last_name'] !== null && trim((string) $validated['last_name']) !== ''
            ? (string) $validated['last_name']
            : null;
        $child->birth_date = $validated['birth_date'] ?? null;
        $child->is_child = true;
        $child->save();

        $sync = $this->buildGuardiansSyncPayload($request, $request->user()->id);
        $child->guardians()->sync($sync);

        return redirect()->route('family.index');
    }

    public function editChild(Request $request, Person $person): View
    {
        if (!$person->is_child) {
            abort(404);
        }

        $this->authorize('update', $person);

        $person->load(['guardians:id,name']);

        $users = User::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('family.children.edit', [
            'child' => $person,
            'users' => $users,
        ]);
    }

    public function updateChild(Request $request, Person $person): RedirectResponse
    {
        if (!$person->is_child) {
            abort(404);
        }

        $this->authorize('update', $person);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date'],
            'guardians' => ['nullable', 'array'],
        ]);

        $person->first_name = (string) $validated['first_name'];
        $person->last_name = $validated['last_name'] !== null && trim((string) $validated['last_name']) !== ''
            ? (string) $validated['last_name']
            : null;
        $person->birth_date = $validated['birth_date'] ?? null;
        $person->save();

        $sync = $this->buildGuardiansSyncPayload($request, $request->user()->id);
        $person->guardians()->sync($sync);

        return redirect()->route('family.index');
    }

    /**
     * Build a `sync()` payload from posted `guardians[<id>]` fields.
     * Ensures the current user remains an editor guardian.
     *
     * @return array<int,array{can_edit:bool,notify:bool}>
     */
    private function buildGuardiansSyncPayload(Request $request, int $currentUserId): array
    {
        $raw = $request->input('guardians', []);
        $raw = is_array($raw) ? $raw : [];

        $selectedIds = [];
        foreach ($raw as $userId => $settings) {
            if (!is_array($settings)) {
                continue;
            }

            $enabled = (bool) ($settings['enabled'] ?? false);
            if (!$enabled) {
                continue;
            }

            $id = (int) $userId;
            if ($id <= 0) {
                continue;
            }

            $selectedIds[$id] = [
                'can_edit' => (bool) ($settings['can_edit'] ?? false),
                'notify' => (bool) ($settings['notify'] ?? false),
            ];
        }

        // Always keep the current user as editor guardian to prevent lock-out.
        $selectedIds[$currentUserId] = [
            'can_edit' => true,
            'notify' => (bool) (($selectedIds[$currentUserId]['notify'] ?? true)),
        ];

        // Filter to existing users only.
        $existing = User::query()
            ->whereIn('id', array_keys($selectedIds))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $sync = [];
        foreach ($existing as $id) {
            $sync[$id] = $selectedIds[$id];
        }

        return $sync;
    }
}
