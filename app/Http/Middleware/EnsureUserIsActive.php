<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fortify::authenticateUsing() solo revisa is_active en el login con
 * contrasena. Este middleware lo revalida en CADA request del grupo web
 * (incluido /livewire/update): cierra la sesion de una cuenta desactivada
 * aunque ya estuviera abierta, haya entrado con la cookie "Recordarme" o
 * con passkey. logout() ademas rota el remember_token.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            Auth::guard('web')->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            if ($request->expectsJson() || $request->hasHeader('X-Livewire')) {
                abort(401, 'Esta cuenta está desactivada.');
            }

            return redirect()->route('login')->withErrors([
                'email' => 'Esta cuenta está desactivada. Contacta al administrador.',
            ]);
        }

        return $next($request);
    }
}
