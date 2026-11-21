<?php

namespace App\Http\Requests;

use App\Models\SupportReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupportReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reported_user_id' => [
                'required_without:reported_email',
                'prohibits:reported_email',
                'nullable',
                'integer',
                'exists:users,id',
                Rule::notIn([$this->user()?->id]),
            ],
            'reported_email' => [
                'required_without:reported_user_id',
                'prohibits:reported_user_id',
                'nullable',
                'email',
                'max:255',
                'exists:users,email',
                Rule::notIn([$this->user()?->email]),
            ],
            'category' => ['required', Rule::in(array_keys(SupportReport::CATEGORIES))],
            'details' => ['required', 'string', 'max:5000'],
            'proofs' => ['nullable', 'array', 'max:3'],
            'proofs.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }
}
