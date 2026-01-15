<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewsItem;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
        $bucket = trim((string) $request->query('bucket', ''));
        $cursor = trim((string) $request->query('cursor', ''));

        if ($bucket === 'all') {
            $bucket = '';
        }

        $bucketIsFiltered = in_array($bucket, ['infos', 'sorties', 'sport'], true);

        $query = NewsItem::query();
        if ($tag !== '') {
            $query->where('tag', $tag);
        }
        if ($bucketIsFiltered) {
            $query->where('bucket', $bucket);
        }

        if ($cursor !== '') {
            $decoded = self::decodeCursor($cursor);
            if ($decoded) {
                [$ts, $id] = $decoded;

                if ($bucketIsFiltered) {
                    $query->where(function ($q) use ($ts, $id) {
                        $q->where('published_at', '<', $ts)
                            ->orWhere(function ($q2) use ($ts, $id) {
                                $q2->where('published_at', '=', $ts)
                                    ->where('id', '<', $id);
                            });
                    });
                } else {
                    // "Tout": sort by content recency (published_at when available, else fetched_at).
                    $expr = 'COALESCE(published_at, fetched_at)';
                    $query->where(function ($q) use ($expr, $ts, $id) {
                        $q->whereRaw("$expr < ?", [$ts])
                            ->orWhere(function ($q2) use ($expr, $ts, $id) {
                                $q2->whereRaw("$expr = ?", [$ts])
                                    ->where('id', '<', $id);
                            });
                    });
                }
            }
        }

        $items = $query
            ->when($bucketIsFiltered, function ($q) {
                $q->orderByDesc('published_at');
            }, function ($q) {
                $q->orderByRaw('COALESCE(published_at, fetched_at) DESC');
            })
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
                'bucket',
                'sub_category',
                'published_at',
                'fetched_at',
            ]);

        $nextCursor = null;
        $last = $items->last();
        if ($last) {
            $ts = $bucketIsFiltered
                ? $last->published_at
                : ($last->published_at ?? $last->fetched_at);
            if ($ts) {
                $nextCursor = self::encodeCursor($ts, (int) $last->id);
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
                'bucket' => $it->bucket !== null ? (string) $it->bucket : null,
                'sub_category' => $it->sub_category !== null ? (string) $it->sub_category : null,
                'published_at' => $it->published_at ? $it->published_at->toIso8601String() : null,
            ];
        })->values();

        $latestFetchedAt = null;
        try {
            $raw = NewsItem::query()->max('fetched_at');
            if (is_string($raw) && trim($raw) !== '') {
                $latestFetchedAt = Carbon::parse($raw)->toIso8601String();
            } elseif ($raw instanceof Carbon) {
                $latestFetchedAt = $raw->toIso8601String();
            }
        } catch (\Throwable $e) {
            $latestFetchedAt = null;
        }

        return response()->json([
            'ok' => true,
            'items' => $itemsForUi,
            'next_cursor' => $nextCursor,
            'latest_fetched_at' => $latestFetchedAt,
        ]);
    }

    private static function encodeCursor(Carbon $publishedAt, int $id): string
    {
        $payload = json_encode([
            // Backward compatible key name; represents the sort timestamp.
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
