<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'slot_id' => ['required', 'integer', 'exists:slots,id'],
            'customer_name' => ['required', 'string', 'max:100'],
            'customer_email' => ['required', 'string', 'email', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'slot_id.required' => 'Не выбран слот.',
            'slot_id.integer' => 'Некорректный идентификатор слота.',
            'slot_id.exists' => 'Такого слота нет в расписании.',
            'customer_name.required' => 'Укажите имя.',
            'customer_name.string' => 'Имя должно быть строкой.',
            'customer_name.max' => 'Имя не длиннее :max символов.',
            'customer_email.required' => 'Укажите email.',
            'customer_email.string' => 'Email должен быть строкой.',
            'customer_email.email' => 'Проверьте формат email.',
            'customer_email.max' => 'Email не длиннее :max символов.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'customer_name' => is_string($this->customer_name) ? trim($this->customer_name) : $this->customer_name,
            'customer_email' => is_string($this->customer_email) ? trim($this->customer_email) : $this->customer_email,
        ]);
    }
}
