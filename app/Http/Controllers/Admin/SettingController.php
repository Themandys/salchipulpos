<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class SettingController extends Controller
{
    /**
     * Guarda los ajustes del negocio (nombre, pie de ticket, fondo de caja y PIN).
     */
    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        foreach ($request->validated() as $key => $value) {
            Setting::put($key, (string) $value);
        }

        return response()->json(['settings' => Setting::publicSettings()]);
    }
}
