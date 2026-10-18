<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'category' => ['sometimes', 'required', 'string', 'max:60'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0', 'max:100000'],
            'stock' => ['sometimes', 'required', 'integer', 'min:0', 'max:100000'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'calories' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:5000'],
            'emoji' => ['sometimes', 'nullable', 'string', 'max:16'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
