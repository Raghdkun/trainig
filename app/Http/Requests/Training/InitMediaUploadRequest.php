<?php

namespace App\Http\Requests\Training;

use App\Enums\MediaType;
use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class InitMediaUploadRequest extends FormRequest
{
    /**
     * This endpoint is only ever called by the chunked-upload client, so always
     * answer validation failures with JSON rather than a redirect.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => $validator->errors()->first(),
            'errors' => $validator->errors()->toArray(),
        ], 422));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Only uploaded types are chunked; links go through the normal store.
            'type' => [
                'required',
                new Enum(MediaType::class),
                Rule::in([
                    MediaType::Image->value,
                    MediaType::Video->value,
                    MediaType::File->value,
                ]),
            ],
            'filename' => ['required', 'string', 'max:255'],
            'label' => ['nullable', 'string', 'max:255'],
            'size' => [
                'required',
                'integer',
                'min:1',
                // Reject over-limit files before a single byte is uploaded.
                function (string $attribute, mixed $value, Closure $fail): void {
                    $type = MediaType::tryFrom((string) $this->input('type'));

                    if ($type && (int) $value > $type->maxKilobytes() * 1024) {
                        $fail(__('The file is larger than the :type limit.', ['type' => $type->value]));
                    }
                },
            ],
        ];
    }
}
