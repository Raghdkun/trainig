<?php

namespace App\Http\Requests\Training;

use Illuminate\Foundation\Http\FormRequest;

class AssessmentSkillRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            // Optional Content Builder station whose content is suggested
            // when this station/skill is a development need.
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
