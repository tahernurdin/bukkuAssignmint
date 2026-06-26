<?php

namespace App\DTOs;

use App\Http\Requests\Api\RegisterRequest;

readonly class RegisterDTO
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public ?string $role = 'user',
    ) {}

    /**
     * Create a DTO from a RegisterRequest.
     *
     * @param RegisterRequest $request
     * @return self
     */
    public static function fromRequest(RegisterRequest $request): self
    {
        return new self(
            name: $request->validated('name'),
            email: $request->validated('email'),
            password: $request->validated('password'),
            role: $request->validated('role') ?? 'user',
        );
    }

    /**
     * Convert DTO to an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
            'role' => $this->role,
        ];
    }
}
