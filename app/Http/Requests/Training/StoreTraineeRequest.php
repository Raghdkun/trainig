<?php

namespace App\Http\Requests\Training;

use App\Enums\Position;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * Adding a trainee — every field is required and the position must be one of
 * the currently enabled positions. Editing keeps the looser TraineeRequest.
 */
class StoreTraineeRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'position' => ['required', (new Enum(Position::class))->only(Position::enabled())],
            'hired_at' => ['required', 'date'],
            // Required whenever there's a choice to make — the controller
            // enforces that (and that it's a store the user may use), since a
            // single-store manager's store is filled in automatically.
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'position.required' => __('Please choose a position.'),
            'position.enum' => __('Please choose a position from the list.'),
            'hired_at.required' => __('Please enter the hire date.'),
        ];
    }
}
