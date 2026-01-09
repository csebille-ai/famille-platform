<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewsItem;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class NewsIndexController extends Controller
{
    public function __invoke(Request $request)
    {
        $limit = (int) $request->query('limit', 20);
        if ($limit < 1) {
            $limit = 20;
        }
        if ($limit > 50) {
            $limit = 50;
        }

        $tag = trim((string) $request->query('tag', ''));
        $cursor = trim((string) $request->query('cursor', ''));

        $query = NewsItem::query();
        if ($tag !== '') {
            $query->where('tag', $tag);
        }

        if ($cursor !== '') {
            $decoded = self::decodeCursor($cursor);
            if ($decoded) {
                [$publishedAt, $id] = $decoded;
                $query->where(function ($q) use ($publishedAt, $id) {
                    $q->where('published_at', '<', $publishedAt)
                        ->orWhere(function ($q2) use ($publishedAt, $id) {
                            $q2->where('published_at', '=', $publishedAt)
                                ->where('id', '<', $id);
                        });
                });
            }
        }

        $items = $query
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get([
                'id',
                'title',
                'url',
                'excerpt',
                'image_url',
                'source',
                'tag',
                'published_at',
            ]);

        $nextCursor = null;
        $last = $items->last();
        if ($last) {
            $publishedAt = $last->published_at;
            if ($publishedAt) {
                $nextCursor = self::encodeCursor($publishedAt, (int) $last->id);
            }
        }

        $itemsForUi = $items->map(function (NewsItem $it) {
            return [
                'title' => (string) $it->title,
                'url' => (string) $it->url,
                'excerpt' => $it->excerpt !== null ? (string) $it->excerpt : null,
                'image_url' => $it->image_url !== null ? (string) $it->image_url : null,
                'source' => $it->source !== null ? (string) $it->source : null,
                'tag' => $it->tag !== null ? (string) $it->tag : null,
                'published_at' => $it->published_at ? $it->published_at->toIso8601String() : null,
            ];
        })->values();

        return response()->json([
            'ok' => true,
            'items' => $itemsForUi,
            'next_cursor' => $nextCursor,
        ]);
    }

    private static function encodeCursor(Carbon $publishedAt, int $id): string
    {
        $payload = json_encode([
            'p' => $publishedAt->toIso8601String(),
            'id' => $id,
        ], JSON_UNESCAPED_SLASHES);

        return rtrim(strtr(base64_encode($payload ?: ''), '+/', '-_'), '=');
    }

    /**
     * @return array{0:Carbon,1:int}|null
     */
    private static function decodeCursor(string $cursor): ?array
    {
        $cursor = Str::of($cursor)->trim()->toString();
        if ($cursor === '') {
            return null;
        }

        $raw = base64_decode(strtr($cursor, '-_', '+/'), true);
        if ($raw === false || $raw === '') {
            return null;
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return null;
        }

        $p = $data['p'] ?? null;
        $id = $data['id'] ?? null;
        if (!is_string($p) || (!is_int($id) && !is_string($id))) {
            return null;
        }

        try {
            $publishedAt = Carbon::parse($p);
        } catch (\Throwable $e) {
            return null;
        }

        return [$publishedAt, (int) $id];
    }
}
