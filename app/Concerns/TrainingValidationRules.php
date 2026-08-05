<?php

namespace App\Concerns;

use App\Enums\Importance;
use App\Enums\MediaType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

trait TrainingValidationRules
{
    /**
     * Allowed category color palette keys (mirrored on the frontend).
     *
     * @var list<string>
     */
    public const CATEGORY_COLORS = [
        'slate', 'rose', 'orange', 'amber', 'emerald', 'teal', 'sky', 'violet',
    ];

    /**
     * @return array<string, array<int, ValidationRule|Enum|string>>
     */
    protected function sectionRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'icon' => ['nullable', 'string', 'max:255'],
            'pie_content_review' => ['nullable', 'string', 'max:255'],
            'screen_to_shoulder' => ['nullable', 'string', 'max:255'],
            'hands_on_shifts' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    protected function categoryRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'color' => ['nullable', Rule::in(self::CATEGORY_COLORS)],
        ];
    }

    /**
     * @param  int  $categoryId  Constrains the parent item to the same category.
     * @return array<string, array<int, ValidationRule|Enum|string>>
     */
    protected function checklistItemRules(int $categoryId): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string', 'max:10000'],
            'importance' => ['nullable', new Enum(Importance::class)],
            // Optional: absent leaves the value unchanged (DB defaults to true),
            // so partial updates and older clients keep working.
            'requires_rating' => ['sometimes', 'boolean'],
            'parent_id' => [
                'nullable',
                Rule::exists('checklist_items', 'id')->where('category_id', $categoryId),
            ],
        ];
    }

    /**
     * @return array<string, array<int, ValidationRule|Enum|string>>
     */
    protected function reorderRules(): array
    {
        return [
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer'],
            'items.*.order' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * Media validation switches on the submitted type.
     *
     * @return array<string, array<int, ValidationRule|Enum|string>>
     */
    protected function mediaRules(): array
    {
        $type = $this->input('type');

        $rules = [
            'type' => ['required', new Enum(MediaType::class)],
            'label' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'string', 'max:2048'],
            'file' => ['nullable', 'file'],
        ];

        $mediaType = MediaType::tryFrom((string) $type);

        if ($mediaType === MediaType::Link) {
            $rules['url'] = ['required', 'url', 'max:2048'];
        } elseif ($mediaType?->isUploaded()) {
            $rules['file'] = $mediaType->fileValidationRules();
        }

        return $rules;
    }
}
