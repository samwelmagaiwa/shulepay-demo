<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Applies the language the user picked in the SPA header switch to this request.
 *
 * The frontend keeps the choice client-side (localStorage) and sends it on every
 * API call as `X-Locale`. Without this the server never learns about the switch,
 * so everything it renders itself — receipts, statements, report PDFs, enum
 * labels in JSON — stayed in one language regardless of what the UI showed.
 *
 * A `lang` query parameter is honoured as well so a PDF can still be opened from
 * a plain link (no custom headers) in a chosen language.
 */
class SetLocale
{
    /** Languages the application ships translations for. */
    public const SUPPORTED = ['en', 'sw'];

    public function handle(Request $request, Closure $next): mixed
    {
        $requested = $request->header('X-Locale') ?? $request->query('lang');

        if (is_string($requested)) {
            // "sw-TZ" / "en_US" / "SW" → "sw" / "en" / "sw"
            $requested = strtolower(substr($requested, 0, 2));

            if (in_array($requested, self::SUPPORTED, true)) {
                app()->setLocale($requested);
            }
        }

        return $next($request);
    }
}
