<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NoStoreForAuthenticated
{
    /**
     * Add strict no-store headers for authenticated pages.
     * This reduces the risk of private HTML being cached by browsers/proxies.
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        try {
            if (auth()->check()) {
                $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
                $response->headers->set('Pragma', 'no-cache');
                $response->headers->set('Expires', '0');
            }
        } catch (\Throwable $e) {
            // If auth isn't available for a specific request, don't block the response.
        }

        return $response;
    }
}
