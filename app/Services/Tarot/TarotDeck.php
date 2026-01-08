<?php

namespace App\Services\Tarot;

use Illuminate\Support\Arr;

class TarotDeck
{
    /** @return array<int, array{name:string, keywords:string}> */
    public function draw(int $count): array
    {
        $deck = config('tarot.cards', []);
        if (!is_array($deck) || count($deck) === 0) {
            return [];
        }

        $count = max(1, min($count, count($deck)));

        /** @var array<int, array{name:string, keywords:string}> $picked */
        $picked = Arr::random($deck, $count);

        return array_values($picked);
    }
}
