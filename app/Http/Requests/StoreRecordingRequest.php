<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecordingRequest extends FormRequest
{
    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'mime_type' => ['required', 'string', Rule::in(array_keys(config('recordings.mime_types')))],
            'size_bytes' => ['required', 'integer', 'min:1', 'max:'.config('recordings.max_bytes')],
            'duration_seconds' => ['required', 'integer', 'min:1', 'max:'.config('recordings.max_seconds')],
            'consent' => ['required', 'accepted'],
        ];
    }
}
