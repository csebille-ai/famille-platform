<?php

namespace App\Http\Controllers;

use App\Models\TarotReading;
use App\Services\Tarot\TarotDeck;
use App\Services\Tarot\TarotInterpreter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TarotController extends Controller
{
    public function index(Request $request): View
    {
        $justDrew = (bool) $request->session()->get('tarot.just_drew', false);

        if (!$justDrew) {
            $request->session()->forget('tarot.draft');
            $draft = null;
        } else {
            $draft = $request->session()->get('tarot.draft');
        }

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
            $interpretation = $interpreter->interpret(
                question: $validated['question'],
                spread: $validated['spread'],
                cards: $cards,
            );
        } catch (\Throwable $e) {
            return back()
                ->withErrors(['question' => $e->getMessage() ?: 'Impossible de générer le tirage pour le moment.'])
                ->withInput();
        }

        $draft = [
            'question' => $validated['question'],
            'spread' => $validated['spread'],
            'cards' => $cards,
            'interpretation' => $interpretation,
            'generated_at' => now()->toIso8601String(),
        ];

        $request->session()->put('tarot.draft', $draft);
        $request->session()->flash('tarot.just_drew', true);

        return redirect()->route('tarot.index');
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

        $reading = TarotReading::create([
            'user_id' => $userId,
            'question' => (string) ($draft['question'] ?? ''),
            'spread' => (string) ($draft['spread'] ?? 'one'),
            'cards' => (array) ($draft['cards'] ?? []),
            'interpretation' => (string) ($draft['interpretation'] ?? ''),
            'is_shared' => false,
        ]);

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
