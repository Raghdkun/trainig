<?php

namespace App\Http\Requests\Training;

use App\Enums\QuizQuestionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class QuizQuestionRequest extends FormRequest
{
    /** A question has between 2 and 6 answer options (A–F). */
    public const MIN_OPTIONS = 2;

    public const MAX_OPTIONS = 6;

    /** A multi-answer question needs a wrong option left over, so at least 3. */
    private const MIN_OPTIONS_MULTI = 3;

    /**
     * Single-answer questions mark exactly one correct option by index;
     * multi-answer questions mark 2 or more, but never every option (that
     * would make the question meaningless).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isSingle = $this->input('type', QuizQuestionType::Single->value) === QuizQuestionType::Single->value;
        $optionCount = is_array($this->input('options')) ? count($this->input('options')) : 0;
        $lastIndex = max($optionCount - 1, 0);

        return [
            'prompt' => ['required', 'string', 'max:500'],
            // Shown to the trainee on their results after they submit.
            'explanation' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', new Enum(QuizQuestionType::class)],
            'options' => [
                'required',
                'array',
                'min:'.($isSingle ? self::MIN_OPTIONS : self::MIN_OPTIONS_MULTI),
                'max:'.self::MAX_OPTIONS,
            ],
            'options.*' => ['required', 'string', 'max:255'],
            'correct_index' => [Rule::requiredIf($isSingle), 'integer', 'between:0,'.$lastIndex],
            'correct_indexes' => [Rule::requiredIf(! $isSingle), 'array', 'min:2', 'max:'.max($lastIndex, 2)],
            'correct_indexes.*' => ['integer', 'between:0,'.$lastIndex, 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'options.min' => __('Add at least :min answer options.'),
            'options.max' => __('A question can have at most :max answer options.'),
        ];
    }

    /**
     * The correct option indexes, normalized to an array regardless of
     * whether this is a single- or multi-answer question.
     *
     * @return array<int, int>
     */
    public function correctIndexes(): array
    {
        if ($this->validated('type') === QuizQuestionType::Multi->value) {
            return array_map(fn ($index): int => (int) $index, $this->validated('correct_indexes'));
        }

        return [(int) $this->validated('correct_index')];
    }
}
