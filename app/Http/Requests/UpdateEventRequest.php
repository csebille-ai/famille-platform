<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEventRequest extends FormRequest
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
            'location_label' => ['nullable', 'string', 'max:80'],
            'location_lat' => ['nullable', 'numeric'],
            'location_lon' => ['nullable', 'numeric'],
            'household_key' => ['nullable', 'string', 'max:50'],

            'category' => ['nullable', 'in:family,personal,admin,school,travel,medical,other'],
            'visibility' => ['required', 'in:family,private'],
            'is_important' => ['nullable', 'boolean'],

            'shared_user_ids' => ['nullable', 'array'],
            'shared_user_ids.*' => ['integer', 'exists:users,id'],

            'date' => ['required', 'date'],
            'time' => ['nullable', 'date_format:H:i'],
            'all_day' => ['nullable', 'boolean'],

            'add_end' => ['nullable', 'boolean'],
            'end_date' => ['nullable', 'date'],
            'end_time' => ['nullable', 'date_format:H:i'],

            'notify' => ['nullable', 'boolean'],
            'reminder_minutes' => ['nullable', 'integer', 'in:0,15,60,1440'],

            'status' => ['nullable', 'in:active,cancelled,archived'],
        ];
    }
}
