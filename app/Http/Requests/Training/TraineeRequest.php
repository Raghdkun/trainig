<?php

namespace App\Http\Requests\Training;

use App\Enums\Position;
use App\Models\Trainee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Editing a trainee. Unlike adding one (StoreTraineeRequest), the details are
 * optional, but a position still has to come from the list. The trainee's
 * current position is always accepted, so an older value from before the
 * list existed is kept as-is when only other details change.
 */
class TraineeRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255', Rule::in($this->allowedPositions())],
            'hired_at' => ['nullable', 'date'],
            // The controller checks the chosen store is one the user may use
            // (any store for super admins, the manager's own stores otherwise).
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'position.in' => __('Please choose a position from the list.'),
        ];
    }

    /**
     * @return list<string>
     */
    private function allowedPositions(): array
    {
        $allowed = array_map(fn (Position $position): string => $position->value, Position::enabled());
        $trainee = $this->route('trainee');

        if ($trainee instanceof Trainee && $trainee->position !== null) {
            $allowed[] = $trainee->position;
        }

        return $allowed;
    }
}
