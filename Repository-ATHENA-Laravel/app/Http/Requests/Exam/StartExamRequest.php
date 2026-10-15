<?php

namespace App\Http\Requests\Exam;

use Illuminate\Foundation\Http\FormRequest;

class StartExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'exam_type' => 'nullable|string|in:quick,full,practice',
            'difficulty' => 'nullable|string|in:easy,medium,hard',
            'subject_ids' => 'nullable|array',
            'subject_ids.*' => 'exists:subjects,id',
            'topic_id' => 'nullable|integer|exists:topics,id',
        ];
    }
}
