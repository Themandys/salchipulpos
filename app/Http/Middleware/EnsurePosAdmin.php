<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePosAdmin
{
    public const string SessionKey = 'pos_admin';

    /**
     * Only let through requests whose session unlocked the admin area with the PIN.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->get(self::SessionKey, false)) {
            return response()->json(['message' => 'Ingresa el PIN de administrador.'], 401);
        }

        return $next($request);
    }
}
