<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMembershipStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization is handled by the Controller's Gate
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:' . \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE . ',' . \App\Modules\Tenancy\Models\Membership::STATUS_INACTIVE],
        ];
    }
}
