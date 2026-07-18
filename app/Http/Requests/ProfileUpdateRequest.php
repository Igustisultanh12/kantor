<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            // Pastikan aturan validasi ini ada agar data tidak dibuang saat masuk ke Controller
            'pangkat' => ['nullable', 'string', 'max:100'],
            'nrp'     => ['nullable', 'string', 'max:50'],
            'phone'   => ['nullable', 'string', 'max:20'],
        ];
    }
}