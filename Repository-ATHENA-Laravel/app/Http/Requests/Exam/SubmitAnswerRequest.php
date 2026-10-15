<?php

namespace App\Http\Requests\Exam;

use Illuminate\Foundation\Http\FormRequest;

class SubmitAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'question_id' => 'required|integer|exists:questions,id',
            'selected_option_id' => 'nullable|integer|exists:question_options,id',
            'time_spent_seconds' => 'nullable|integer|min:0',
            'time' => 'nullable|integer|min:0',
        ];
    }
}
