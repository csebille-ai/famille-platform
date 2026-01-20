<?php

namespace Tests\Unit;

use App\Services\Tarot\TarotInterpreter;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TarotInterpreterOrientationTest extends TestCase
{
    public function test_it_sends_orientation_per_card_to_openai_prompt(): void
    {
        config([
            'services.openai.key' => 'test-key',
            'services.openai.base_url' => 'https://api.openai.com/v1',
            'services.openai.model' => 'gpt-test',
            'tarot.max_chars' => 1200,
        ]);

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"interpretation":"ok","spoken_text":"ok"}',
                    ],
                ]],
            ], 200),
        ]);

        /** @var TarotInterpreter $interpreter */
        $interpreter = app(TarotInterpreter::class);

        $interpreter->interpretBundle(
            question: 'Test orientation',
            spread: 'three',
            cards: [
                [
                    'n' => 12,
                    'slug' => 'le-pendu',
                    'name' => 'Le Pendu',
                    'keywords' => 'blocage, retard',
                    'orientation' => 'reversed',
                ],
                [
                    'n' => 1,
                    'slug' => 'le-bateleur',
                    'name' => 'Le Bateleur',
                    'keywords' => 'initiative',
                    'orientation' => 'upright',
                ],
            ],
        );

        Http::assertSent(function ($request) {
            $data = $request->data();
            $user = (string) ($data['messages'][1]['content'] ?? '');
            $system = (string) ($data['messages'][0]['content'] ?? '');

            return str_contains($user, 'Le Pendu')
                && str_contains($user, 'orientation: Renversée')
                && str_contains($user, 'Le Bateleur')
                && str_contains($user, 'orientation: Droite')
                && str_contains($system, 'orientation (Droite ou Renversée)');
        });
    }
}
