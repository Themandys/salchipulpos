<?php

namespace App\Http\Requests;

use App\Models\Order;
use App\Models\Sale;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleRequest extends FormRequest
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
            'payment_method' => ['required', Rule::in([Sale::MethodCash, Sale::MethodCard])],
            'paid' => ['required_if:payment_method,'.Sale::MethodCash, 'nullable', 'numeric', 'min:0', 'max:100000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')->where('is_active', true)],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:999'],
            'order_id' => ['nullable', 'integer', Rule::exists('orders', 'id')->where('status', Order::StatusPending)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'El pedido está vacío.',
            'items.*.id.exists' => 'Uno de los productos ya no está disponible.',
            'paid.required_if' => 'Indica con cuánto pagó el cliente.',
            'order_id.exists' => 'Ese pedido ya fue cobrado o cancelado.',
        ];
    }
}
