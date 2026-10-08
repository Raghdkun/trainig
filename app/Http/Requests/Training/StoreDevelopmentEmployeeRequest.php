<?php

namespace App\Http\Requests\Training;

use App\Enums\Position;
use Illuminate\Validation\Rules\Enum;

/**
 * Adding a brand-new employee straight into the Development Zone: their
 * details plus the same evaluation used for existing trainees.
 */
class StoreDevelopmentEmployeeRequest extends StoreDevelopmentEvaluationRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'hired_at' => ['required', 'date', 'before_or_equal:today'],
            'position' => ['required', (new Enum(Position::class))->only(Position::enabled())],
            // Required whenever there's a choice to make — the controller
            // enforces that (and that it's a store the user may use).
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            ...parent::rules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'name.required' => __('Please enter the employee\'s full name.'),
            'position.required' => __('Please choose a position.'),
            'position.enum' => __('Please choose a position from the list.'),
            'hired_at.required' => __('Please enter the hire date.'),
            'hired_at.before_or_equal' => __('The hire date can\'t be in the future.'),
        ];
    }
}
