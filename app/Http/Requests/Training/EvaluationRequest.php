<?php

namespace App\Http\Requests\Training;

use App\Models\ChecklistItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EvaluationRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // A scored item can't be completed without a score AND a note. Items the
        // admin flagged as not-rated are just "done / not done" — no score, no
        // note required.
        $requiredWhenCompleted = Rule::requiredIf(
            fn (): bool => $this->requiresRating() && $this->boolean('completed'),
        );

        return [
            'completed' => ['required', 'boolean'],
            'rating' => ['nullable', 'integer', 'min:0', 'max:100', $requiredWhenCompleted],
            'notes' => ['nullable', 'string', 'max:2000', $requiredWhenCompleted],
        ];
    }

    /**
     * @return array{completed: bool, rating: int|null, notes: string|null}
     */
    public function evaluationData(): array
    {
        // Never persist a score for a not-rated item, so it can't sneak into the
        // averages even if one is submitted.
        $rating = $this->requiresRating() && $this->input('rating') !== null
            ? (int) $this->input('rating')
            : null;

        return [
            'completed' => $this->boolean('completed'),
            'rating' => $rating,
            'notes' => $this->input('notes'),
        ];
    }

    /**
     * Whether the item under evaluation is scored (defaults to true when the
     * item can't be resolved, matching the column default).
     */
    private function requiresRating(): bool
    {
        $item = $this->route('checklistItem');

        return ! ($item instanceof ChecklistItem) || $item->requires_rating;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rating.required' => __('Add a score before marking this step complete.'),
            'notes.required' => __('Add a note before marking this step complete.'),
        ];
    }
}
