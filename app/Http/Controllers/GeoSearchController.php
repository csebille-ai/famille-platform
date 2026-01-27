<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class GeoSearchController extends Controller
{
    public function search(Request $request)
    {
        $query = trim((string) $request->input('q', ''));
        
        if (strlen($query) < 3) {
            return response()->json([]);
        }

        // Rate limiting: max 1 req/sec globally
        $rateLimitKey = 'nominatim:global';
        if (!RateLimiter::attempt($rateLimitKey, 1, function() {}, 1)) {
            return response()->json(['error' => 'Rate limit exceeded'], 429);
        }

        // Cache results for 30 days
        $cacheKey = 'geo:' . md5(strtolower($query));
        
        return Cache::remember($cacheKey, now()->addDays(30), function () use ($query) {
            try {
                $response = Http::withHeaders([
                    'User-Agent' => 'FamillePlatform/1.0 (contact@famille.app)',
                ])
                ->timeout(5)
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $query,
                    'format' => 'json',
                    'limit' => 5,
                    'addressdetails' => 1,
                ]);

                if (!$response->successful()) {
                    Log::warning('Nominatim API error', ['status' => $response->status()]);
                    return response()->json([]);
                }

                $results = $response->json();
                
                // Format results
                $formatted = collect($results)->map(function ($item) {
                    return [
                        'label' => $item['display_name'] ?? '',
                        'lat' => (float) ($item['lat'] ?? 0),
                        'lon' => (float) ($item['lon'] ?? 0),
                    ];
                })->toArray();

                return response()->json($formatted);
            } catch (\Exception $e) {
                Log::error('Geo search error', ['error' => $e->getMessage()]);
                return response()->json([]);
            }
        });
    }
}
