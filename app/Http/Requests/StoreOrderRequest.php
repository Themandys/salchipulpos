<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    /**
     * Any visitor of the menu may place an order.
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
            'customer_name' => ['required', 'string', 'max:60'],
            'note' => ['nullable', 'string', 'max:200'],
            'items' => ['required', 'array', 'list', 'min:1', 'max:30'],
            'items.*' => ['required', 'array:id,qty,choices,combo,extras'],
            'items.*.id' => ['required', 'integer', 'min:1'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:20'],
            'items.*.choices' => ['sometimes', 'array', 'max:5'],
            'items.*.choices.*' => ['required', 'string', 'max:100'],
            'items.*.combo' => ['nullable', 'string', 'max:30'],
            'items.*.extras' => ['sometimes', 'array', 'list', 'max:6'],
            'items.*.extras.*' => ['required', 'array:id,choices'],
            'items.*.extras.*.id' => ['required', 'integer', 'min:1'],
            'items.*.extras.*.choices' => ['sometimes', 'array', 'max:2'],
            'items.*.extras.*.choices.*' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_name.required' => 'Escribe tu nombre para llamarte cuando esté listo.',
            'items.required' => 'Agrega al menos un producto.',
        ];
    }
}
