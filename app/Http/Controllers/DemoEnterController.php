<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\PanelTimezone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class DemoEnterController
{
    public function __invoke(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403);
        abort_unless($user->is_demo && $user->is_active, 403);

        if ($user->isDemoExpired()) {
            return redirect()
                ->route('login')
                ->with('error', 'Votre démo a expiré. Créez-en une nouvelle sur obiora.io.');
        }

        $panelTz = PanelTimezone::resolve();
        if ($user->timezone === null || $user->timezone === '' || $user->timezone === 'UTC') {
            $user->forceFill(['timezone' => $panelTz])->save();
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
