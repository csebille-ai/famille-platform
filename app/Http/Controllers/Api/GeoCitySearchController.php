<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GeoCitySearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json([
                'items' => [],
            ]);
        }

        $cacheKey = 'geo:cities:' . md5(mb_strtolower($q, 'UTF-8'));

        $items = Cache::remember($cacheKey, now()->addHours(12), function () use ($q) {
            $resp = Http::timeout(4)
                ->retry(1, 150)
                ->get('https://api-adresse.data.gouv.fr/search/', [
                    'q' => $q,
                    'limit' => 8,
                    'type' => 'municipality',
                    'autocomplete' => 1,
                ]);

            if (!$resp->ok()) {
                return [];
            }

            $data = $resp->json();
            $features = is_array($data) ? ($data['features'] ?? []) : [];
            if (!is_array($features)) {
                return [];
            }

            $out = [];
            foreach ($features as $f) {
                if (!is_array($f)) {
                    continue;
                }

                $props = is_array($f['properties'] ?? null) ? $f['properties'] : [];
                $geom = is_array($f['geometry'] ?? null) ? $f['geometry'] : [];

                $label = trim((string) ($props['label'] ?? ''));
                $city = trim((string) ($props['city'] ?? ''));
                $postcode = trim((string) ($props['postcode'] ?? $props['postcodes'][0] ?? ''));

                $coords = $geom['coordinates'] ?? null;
                $lon = null;
                $lat = null;
                if (is_array($coords) && count($coords) >= 2) {
                    $lon = is_numeric($coords[0]) ? (float) $coords[0] : null;
                    $lat = is_numeric($coords[1]) ? (float) $coords[1] : null;
                }

                if ($label === '' || $lat === null || $lon === null) {
                    continue;
                }

                $out[] = [
                    'label' => $label,
                    'city' => $city !== '' ? $city : $label,
                    'postcode' => $postcode,
                    'latitude' => $lat,
                    'longitude' => $lon,
                ];
            }

            return $out;
        });

        return response()->json([
            'items' => $items,
        ]);
    }
}
