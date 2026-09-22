<?php

namespace App\Http\Requests\Training;

use App\Models\DevelopmentEvaluationCriterion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDevelopmentEvaluationRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:2000'],
            'ratings' => ['required', 'array'],
            'ratings.*.criterion_id' => ['required', 'integer', 'distinct', Rule::in($this->activeCriterionIds())],
            'ratings.*.rating' => ['required', 'integer', 'between:1,5'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $submitted = collect($this->input('ratings', []))
                ->pluck('criterion_id')
                ->filter(fn ($id) => is_numeric($id))
                ->map(fn ($id) => (int) $id)
                ->sort()
                ->values();

            $required = $this->activeCriterionIds()->sort()->values();

            if ($submitted->all() !== $required->all()) {
                $validator->errors()->add('ratings', __('Rate every current evaluation question.'));
            }
        });
    }

    /**
     * @return Collection<int, int>
     */
    private function activeCriterionIds(): Collection
    {
        return DevelopmentEvaluationCriterion::active()->pluck('id');
    }

    /**
     * @return array{notes: string|null, ratings: array<int, array{criterion_id: int, rating: int}>}
     */
    public function evaluationData(): array
    {
        return [
            'notes' => $this->validated('notes'),
            'ratings' => collect($this->validated('ratings'))
                ->map(fn (array $rating): array => [
                    'criterion_id' => (int) $rating['criterion_id'],
                    'rating' => (int) $rating['rating'],
                ])
                ->all(),
        ];
    }
}
