<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:60'],
            'description' => ['nullable', 'string', 'max:1200'],
            'location' => ['nullable', 'string', 'max:80'],

            'category' => ['nullable', 'in:family,personal,admin,school,travel,medical,other'],
            'visibility' => ['required', 'in:family,private'],
            'is_important' => ['nullable', 'boolean'],

            'date' => ['required', 'date'],
            'time' => ['nullable', 'date_format:H:i'],
            'all_day' => ['nullable', 'boolean'],

            'add_end' => ['nullable', 'boolean'],
            'end_date' => ['nullable', 'date'],
            'end_time' => ['nullable', 'date_format:H:i'],

            'notify' => ['nullable', 'boolean'],
            'reminder_minutes' => ['nullable', 'integer', 'in:0,15,60,1440'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Le titre est requis.',
            'title.min' => 'Le titre doit faire au moins 3 caractères.',
            'title.max' => 'Le titre ne peut pas dépasser 60 caractères.',
            'description.max' => 'La description ne peut pas dépasser 1200 caractères.',
            'location.max' => 'Le lieu ne peut pas dépasser 80 caractères.',
            'visibility.required' => 'Choisis une visibilité.',
            'date.required' => 'La date est requise.',
        ];
    }
}
