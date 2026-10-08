<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DraftReplyRequest extends FormRequest
{
    /**
     * Ownership is checked here, before validation, so a user can't learn
     * anything about another user's ticket from validation errors.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('ticket'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'guidance' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
