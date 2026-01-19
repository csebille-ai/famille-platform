<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Tarot\TarotDeck;
use Illuminate\Http\Request;

class TarotDrawController extends Controller
{
    public function __invoke(Request $request, TarotDeck $deck)
    {
        $normalizeKeywords = function ($raw): array {
            if (is_array($raw)) {
                $items = $raw;
            } else {
                $s = trim((string) $raw);
                if ($s === '') return [];
                $items = preg_split('/\s*(?:,|;|\||•)\s*/u', $s) ?: [];
            }
            $items = array_values(array_filter(array_map(fn ($v) => trim((string) $v), $items), fn ($v) => $v !== ''));
            return $items;
        };

        $countRaw = $request->input('count', 3);

        if (!is_numeric($countRaw) || (string) (int) $countRaw !== (string) $countRaw) {
            return response()->json(['ok' => false, 'error' => 'invalid_count'], 400);
        }

        $count = (int) $countRaw;
        $max = count((array) config('tarot.cards', []));

        if ($max <= 0) {
            return response()->json(['ok' => false, 'error' => 'deck_unavailable'], 500);
        }

        if ($count < 1 || $count > $max) {
            return response()->json(['ok' => false, 'error' => 'count_out_of_range', 'min' => 1, 'max' => $max], 400);
        }

        $allowReversed = (bool) $request->input('allowReversed', true);

        $cards = $deck->draw($count);

        $cards = array_map(function (array $card) use ($allowReversed, $normalizeKeywords): array {
            $reversed = $allowReversed ? (random_int(0, 1) === 1) : false;

            return [
                'n' => $card['n'] ?? null,
                'slug' => $card['slug'] ?? null,
                'label' => $card['name'] ?? null,
                'file' => $card['file'] ?? null,
                'keywords' => $normalizeKeywords($card['keywords'] ?? null),
                'orientation' => $reversed ? 'reversed' : 'upright',
                'reversed' => $reversed,
            ];
        }, $cards);

        return response()->json(['ok' => true, 'cards' => $cards], 200);
    }
}
