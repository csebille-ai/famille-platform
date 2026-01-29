<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CloudNode;
use App\Models\SlidingPuzzle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

class SlidingPuzzleController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('manage-users');

        $puzzles = SlidingPuzzle::query()
            ->orderByDesc('is_active')
            ->orderBy('grid_size')
            ->orderBy('title')
            ->get();

        return view('admin.sliding-puzzles.index', [
            'puzzles' => $puzzles,
        ]);
    }

    public function create(Request $request)
    {
        Gate::authorize('manage-users');

        return view('admin.sliding-puzzles.create', [
            'puzzle' => new SlidingPuzzle([
                'grid_size' => 3,
                'image_source_type' => 'media',
                'is_active' => true,
            ]),
            'recentImages' => $this->recentImages(),
        ]);
    }

    public function store(Request $request)
    {
        Gate::authorize('manage-users');

        $data = $this->validatePuzzle($request);

        $puzzle = SlidingPuzzle::query()->create($data);

        return redirect()
            ->route('admin.sliding-puzzles.edit', $puzzle)
            ->with('status', 'Taquin créé.');
    }

    public function edit(Request $request, SlidingPuzzle $puzzle)
    {
        Gate::authorize('manage-users');

        return view('admin.sliding-puzzles.edit', [
            'puzzle' => $puzzle,
            'recentImages' => $this->recentImages(),
        ]);
    }

    public function update(Request $request, SlidingPuzzle $puzzle)
    {
        Gate::authorize('manage-users');

        $data = $this->validatePuzzle($request);

        $puzzle->fill($data);
        $puzzle->save();

        return redirect()
            ->route('admin.sliding-puzzles.edit', $puzzle)
            ->with('status', 'Taquin mis à jour.');
    }

    public function destroy(Request $request, SlidingPuzzle $puzzle)
    {
        Gate::authorize('manage-users');

        $puzzle->delete();

        return redirect()
            ->route('admin.sliding-puzzles.index')
            ->with('status', 'Taquin supprimé.');
    }

    /**
     * @return array<int, array{id:int, thumb_url:string, open_url:string, label:string}>
     */
    private function recentImages(): array
    {
        try {
            if (!Schema::hasTable('cloud_nodes')) {
                return [];
            }
        } catch (\Throwable) {
            return [];
        }

        $items = CloudNode::query()
            ->where('type', 'file')
            ->whereNotNull('stored_path')
            ->where('mime', 'like', 'image/%')
            ->orderByDesc('id')
            ->limit(18)
            ->get(['id', 'name', 'mime']);

        return $items->map(function ($n) {
            $id = (int) $n->id;
            $name = trim((string) ($n->name ?? ''));
            $label = $name !== '' ? $name : ('Image #' . $id);

            return [
                'id' => $id,
                'thumb_url' => route('images.thumb', ['node' => $id]),
                'open_url' => route('media.photos.show', ['node' => $id, 'return' => request()->getRequestUri()]),
                'label' => $label,
            ];
        })->values()->all();
    }

    /**
     * @return array{title:string,description:?string,image_source_type:string,image_source_id:?string,grid_size:int,is_active:bool}
     */
    private function validatePuzzle(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'grid_size' => ['required', 'integer', 'in:3,4'],
            'is_active' => ['sometimes', 'boolean'],

            'image_source_type' => ['required', 'string', 'in:media,external,avatar'],
            'image_source_id' => ['nullable', 'string', 'max:2048'],
        ]);

        $validator->after(function ($v) use ($request) {
            $type = strtolower(trim((string) $request->input('image_source_type', '')));
            $id = trim((string) $request->input('image_source_id', ''));

            if ($type === 'media') {
                if ($id !== '' && !ctype_digit($id)) {
                    $v->errors()->add('image_source_id', 'ID média invalide (doit être un entier).');
                }
            }

            if ($type === 'avatar') {
                if ($id !== '' && !preg_match('/^(?:user:)?\\d+$/', $id)) {
                    $v->errors()->add('image_source_id', 'Format invalide (ex: "123" ou "user:123").');
                }
            }

            // External can be empty: that means number tiles.
        });

        $validated = $validator->validate();

        $type = strtolower(trim((string) $validated['image_source_type']));
        $id = trim((string) ($validated['image_source_id'] ?? ''));

        return [
            'title' => (string) $validated['title'],
            'description' => ($validated['description'] ?? null) !== null ? (string) $validated['description'] : null,
            'image_source_type' => $type,
            'image_source_id' => $id !== '' ? $id : null,
            'grid_size' => (int) $validated['grid_size'],
            'is_active' => (bool) $request->boolean('is_active'),
        ];
    }
}
