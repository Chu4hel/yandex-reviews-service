<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConnectOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'url' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    $value = trim($value);
                    $isValid = preg_match('/^\d+$/', $value)
                        || preg_match('/yandex\.[a-z]+\/maps/i', $value)
                        || preg_match('/maps\.yandex\.[a-z]+/i', $value)
                        || preg_match('/[?&]oid=\d+/', $value);

                    if (!$isValid) {
                        $fail('Укажите корректную ссылку на организацию в Яндекс.Картах (например: https://yandex.ru/maps/org/... или числовой ID)');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'url.required' => 'Поле ссылки на Яндекс.Карты обязательно для заполнения',
        ];
    }
}
