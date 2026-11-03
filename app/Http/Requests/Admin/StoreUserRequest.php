<?php

namespace App\Http\Requests\Admin;

use App\Enums\Permission;
use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreUserRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', new Enum(Role::class)],
            'store_ids' => ['array', Rule::requiredIf($this->input('role') === Role::Manager->value)],
            'store_ids.*' => ['integer', 'exists:stores,id'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['distinct', new Enum(Permission::class)],
        ];
    }
}
