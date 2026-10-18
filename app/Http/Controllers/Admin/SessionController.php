<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsurePosAdmin;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SessionController extends Controller
{
    /**
     * Indica si esta sesión ya desbloqueó la caja.
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json(['unlocked' => (bool) $request->session()->get(EnsurePosAdmin::SessionKey, false)]);
    }

    /**
     * Desbloquea el panel de administración con el PIN.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate(['pin' => ['required', 'string', 'max:8']]);

        if (! hash_equals(Setting::value('business_pin'), (string) $request->input('pin'))) {
            throw ValidationException::withMessages(['pin' => 'PIN incorrecto.']);
        }

        $request->session()->regenerate();
        $request->session()->put(EnsurePosAdmin::SessionKey, true);

        return response()->json(['unlocked' => true]);
    }

    /**
     * Bloquea de nuevo el panel de administración.
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->session()->forget(EnsurePosAdmin::SessionKey);
        $request->session()->regenerateToken();

        return response()->json(['unlocked' => false, 'csrf_token' => csrf_token()]);
    }
}
