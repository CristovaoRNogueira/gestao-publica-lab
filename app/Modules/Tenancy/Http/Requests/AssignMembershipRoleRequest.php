<?php

namespace App\Modules\Tenancy\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignMembershipRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Resolvido na Policy
    }

    public function rules(): array
    {
        return [
            'role_id' => ['required', 'integer'],
        ];
    }
}
