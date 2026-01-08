<?php

namespace App\Http\Controllers\Api;

use App\Services\Tarot\TarotOpenAiTts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TarotTtsController
{
    public function __invoke(Request $request, TarotOpenAiTts $tts): JsonResponse
    {
        abort_unless($request->user() !== null, 401);

        $validated = $request->validate([
            'text' => ['required', 'string', 'max:6000'],
        ]);

        $url = $tts->synthesizeToPublicUrl((string) $validated['text']);

        return response()->json([
            'ok' => true,
            'url' => $url,
        ]);
    }
}
