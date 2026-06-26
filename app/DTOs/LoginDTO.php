<?php

namespace App\DTOs;

use App\Http\Requests\Api\LoginRequest;

readonly class LoginDTO
{
    public function __construct(
        public string $email,
        public string $password,
    ) {}

    /**
     * Create a DTO from a LoginRequest.
     *
     * @param LoginRequest $request
     * @return self
     */
    public static function fromRequest(LoginRequest $request): self
    {
        return new self(
            email: $request->validated('email'),
            password: $request->validated('password'),
        );
    }

    /**
     * Convert DTO to credentials array for Auth guard.
     *
     * @return array
     */
    public function toCredentials(): array
    {
        return [
            'email' => $this->email,
            'password' => $this->password,
        ];
    }
}
