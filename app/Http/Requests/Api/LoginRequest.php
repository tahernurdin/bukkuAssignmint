<?php

namespace App\Http\Requests\Api;

use App\DTOs\LoginDTO;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Build the DTO for the login attempt.
     */
    public function toDto(): LoginDTO
    {
        return new LoginDTO(
            email: $this->validated('email'),
            password: $this->validated('password'),
        );
    }
}
