<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    /**
     * Access is guarded by the pos.admin middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'business_name' => ['sometimes', 'required', 'string', 'max:80'],
            'ticket_footer' => ['sometimes', 'nullable', 'string', 'max:160'],
            'opening_cash' => ['sometimes', 'required', 'numeric', 'min:0', 'max:1000000'],
            'business_pin' => ['sometimes', 'required', 'digits_between:4,8'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'business_pin.digits_between' => 'El PIN debe tener de 4 a 8 números.',
        ];
    }
}
