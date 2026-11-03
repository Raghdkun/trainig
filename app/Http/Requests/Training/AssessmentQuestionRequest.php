<?php

namespace App\Http\Requests\Training;

use App\Enums\AssessmentAnswerType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class AssessmentQuestionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'max:500'],
            'answer_type' => ['required', new Enum(AssessmentAnswerType::class)],
        ];
    }
}
