<?php

namespace App\Http\Requests\Admin;

use App\Enums\Permission;
use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateUserRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', new Enum(Role::class)],
            'store_ids' => ['array', Rule::requiredIf($this->input('role') === Role::Manager->value)],
            'store_ids.*' => ['integer', 'exists:stores,id'],
            // Optional: left out, a manager's granted permissions stay as they are.
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['distinct', new Enum(Permission::class)],
        ];
    }
}
