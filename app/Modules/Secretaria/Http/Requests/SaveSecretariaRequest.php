<?php

namespace App\Modules\Secretaria\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveSecretariaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // A autorização real ocorre no Controller via Policy
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ];
    }
}
