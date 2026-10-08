<?php

namespace App\Http\Requests\Training;

use App\Enums\EvaluationGrade;
use App\Services\Training\StationAssessment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * The manager's Development Zone evaluation: an overall grade and points,
 * plus an answer to every station assessment question.
 */
class StoreDevelopmentEvaluationRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'grade' => ['required', new Enum(EvaluationGrade::class)],
            'points' => ['required', 'integer', 'between:0,100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            ...app(StationAssessment::class)->answerRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'grade.required' => __('Please choose an evaluation grade.'),
            'points.required' => __('Please enter the overall points.'),
            'points.between' => __('Overall points must be between 0 and 100.'),
            'answers.*.required' => __('Answer every assessment question.'),
            'answers.*.between' => __('That answer is out of range.'),
        ];
    }

    /**
     * @return array{notes: string|null, grade: string, points: int, answers: array<int, int>}
     */
    public function evaluationData(): array
    {
        return [
            'notes' => $this->validated('notes'),
            'grade' => $this->validated('grade'),
            'points' => (int) $this->validated('points'),
            'answers' => self::answersFrom($this->validated('answers') ?? []),
        ];
    }

    /**
     * @param  array<int|string, mixed>  $answers
     * @return array<int, int>
     */
    public static function answersFrom(array $answers): array
    {
        $normalized = [];

        foreach ($answers as $questionId => $value) {
            $normalized[(int) $questionId] = (int) $value;
        }

        return $normalized;
    }
}
