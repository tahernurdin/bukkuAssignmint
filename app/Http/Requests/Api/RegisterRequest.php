<?php

namespace App\Http\Requests\Api;

use App\DTOs\RegisterDTO;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['nullable', 'string', 'in:admin,user'],
        ];
    }

    /**
     * Build the DTO for the registration; role defaults to 'user'.
     */
    public function toDto(): RegisterDTO
    {
        return new RegisterDTO(
            name: $this->validated('name'),
            email: $this->validated('email'),
            password: $this->validated('password'),
            role: $this->validated('role') ?? 'user',
        );
    }
}
