<?php

namespace App\Http\Requests\Training;

use App\Services\Training\StationAssessment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * A training-team reassessment: the same station questions, answered again
 * to measure improvement. No grade or points — those belong to the
 * manager's original evaluation.
 */
class StoreDevelopmentReassessmentRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:2000'],
            ...app(StationAssessment::class)->answerRules(),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (app(StationAssessment::class)->skills()->isEmpty()) {
                $validator->errors()->add('answers', __('Add stations & skills with questions in Assessment setup before reassessing.'));
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'answers.*.required' => __('Answer every assessment question.'),
            'answers.*.between' => __('That answer is out of range.'),
        ];
    }

    /**
     * @return array{notes: string|null, answers: array<int, int>}
     */
    public function reassessmentData(): array
    {
        return [
            'notes' => $this->validated('notes'),
            'answers' => StoreDevelopmentEvaluationRequest::answersFrom($this->validated('answers') ?? []),
        ];
    }
}
