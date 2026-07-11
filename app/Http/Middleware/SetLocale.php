<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locales = config('obiora.locales', ['fr', 'en']);
        $locale = $request->session()->get('locale')
            ?? $request->cookie('obiora_locale')
            ?? config('obiora.default_locale', 'fr');

        if (in_array($locale, $locales, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
