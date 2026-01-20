<?php

namespace App\Http\Controllers;

use App\Models\TarotReading;
use App\Services\Tarot\TarotDeck;
use App\Services\Tarot\TarotInterpreter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TarotController extends Controller
{
    public function index(Request $request): View
    {
        $draft = $request->session()->get('tarot.draft');

        return view('tarot.index', [
            'draft' => is_array($draft) ? $draft : null,
        ]);
    }

    public function draw(Request $request, TarotDeck $deck, TarotInterpreter $interpreter): RedirectResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'max:500'],
            'spread' => ['required', 'in:three,five'],
        ]);

        $count = $validated['spread'] === 'five' ? 5 : 3;
        $cards = $deck->draw($count);

        $cards = array_map(function (array $card): array {
            $reversed = (random_int(0, 1) === 1);
            $card['reversed'] = $reversed;
            $card['orientation'] = $reversed ? 'reversed' : 'upright';
            return $card;
        }, $cards);

        if (count($cards) !== $count) {
            return back()->withErrors(['question' => 'Deck tarot indisponible.'])->withInput();
        }

        try {
            $bundle = $interpreter->interpretBundle(
                question: $validated['question'],
                spread: $validated['spread'],
                cards: $cards,
            );
        } catch (\Throwable $e) {
            return back()
                ->withErrors(['question' => $e->getMessage() ?: 'Impossible de générer le tirage pour le moment.'])
                ->withInput();
        }

        $interpretation = (string) ($bundle['interpretation'] ?? '');
        $spokenText = (string) ($bundle['spoken_text'] ?? '');

        $maybeJson = trim($interpretation);
        if ($maybeJson !== '' && str_starts_with($maybeJson, '{')) {
            $decoded = json_decode($maybeJson, true);
            if (is_array($decoded)) {
                if (isset($decoded['interpretation']) && is_string($decoded['interpretation'])) {
                    $interpretation = $decoded['interpretation'];
                }
                if ((trim($spokenText) === '') && isset($decoded['spoken_text']) && is_string($decoded['spoken_text'])) {
                    $spokenText = $decoded['spoken_text'];
                }
            }
        }

        $draft = [
            'draw_id' => (string) Str::uuid(),
            'question' => $validated['question'],
            'spread' => $validated['spread'],
            'cards' => $cards,
            'interpretation' => $interpretation,
            'spoken_text' => $spokenText,
            'generated_at' => now()->toIso8601String(),
        ];

        $request->session()->put('tarot.draft', $draft);

        return redirect()->to(route('tarot.index') . '#tarot-result');
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->session()->forget('tarot.draft');

        return redirect()->route('tarot.index');
    }

    public function save(Request $request): RedirectResponse
    {
        $draft = $request->session()->get('tarot.draft');
        if (!is_array($draft)) {
            return redirect()->route('tarot.index')->with('status', 'Aucun tirage à enregistrer.');
        }

        /** @var int $userId */
        $userId = (int) $request->user()->id;

        $data = [
            'user_id' => $userId,
            'question' => (string) ($draft['question'] ?? ''),
            'spread' => (string) ($draft['spread'] ?? 'one'),
            'cards' => (array) ($draft['cards'] ?? []),
            'interpretation' => (string) ($draft['interpretation'] ?? ''),
            'is_shared' => false,
        ];

        if (\Illuminate\Support\Facades\Schema::hasColumn('tarot_readings', 'spoken_text')) {
            $spokenText = trim((string) ($draft['spoken_text'] ?? ''));
            $data['spoken_text'] = $spokenText !== '' ? $spokenText : null;
        }

        $reading = TarotReading::create($data);

        $request->session()->forget('tarot.draft');

        return redirect()->route('tarot.history.show', $reading)->with('status', 'Tirage enregistré.');
    }

    public function history(): View
    {
        $readings = TarotReading::query()
            ->where('user_id', auth()->id())
            ->latest()
            ->paginate(20);

        return view('tarot.history', [
            'readings' => $readings,
        ]);
    }

    public function show(TarotReading $reading): View
    {
        abort_unless((int) $reading->user_id === (int) auth()->id(), 404);

        return view('tarot.show', [
            'reading' => $reading,
        ]);
    }
}
