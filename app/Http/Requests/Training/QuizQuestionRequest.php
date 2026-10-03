<?php

namespace App\Http\Requests\Training;

use App\Enums\QuizQuestionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class QuizQuestionRequest extends FormRequest
{
    /**
     * Fixed at exactly 4 choices (A–D). Single-answer questions mark exactly
     * one correct by index; multi-answer questions mark 2 or 3 (marking all
     * 4 would make the question meaningless).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isSingle = $this->input('type', QuizQuestionType::Single->value) === QuizQuestionType::Single->value;

        return [
            'prompt' => ['required', 'string', 'max:500'],
            'type' => ['required', new Enum(QuizQuestionType::class)],
            'options' => ['required', 'array', 'size:4'],
            'options.*' => ['required', 'string', 'max:255'],
            'correct_index' => [Rule::requiredIf($isSingle), 'integer', 'between:0,3'],
            'correct_indexes' => [Rule::requiredIf(! $isSingle), 'array', 'min:2', 'max:3'],
            'correct_indexes.*' => ['integer', 'between:0,3', 'distinct'],
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
