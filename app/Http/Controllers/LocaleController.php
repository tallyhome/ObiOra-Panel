<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class LocaleController
{
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        $locales = config('obiora.locales', ['fr', 'en']);

        if (! in_array($locale, $locales, true)) {
            abort(404);
        }

        $request->session()->put('locale', $locale);

        return redirect()
            ->back(fallback: route('login'))
            ->withCookie(cookie()->forever('obiora_locale', $locale));
    }
}
