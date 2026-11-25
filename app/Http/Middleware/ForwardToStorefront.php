<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForwardToStorefront
{
    public function handle(Request $request, Closure $next): Response
    {
        $storefront = config('app.storefront_forward_url');

        if (! $storefront) {
            return $next($request);
        }

        if (! $request->isMethodSafe()) {
            return response()->json(['message' => 'B-CRUNCH se mudó. Abre el nuevo sitio para continuar.'], 409);
        }

        return redirect()->away(rtrim($storefront, '/').$request->getRequestUri(), 302, ['Cache-Control' => 'no-store']);
    }
}
