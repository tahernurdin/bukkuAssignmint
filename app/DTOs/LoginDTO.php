<?php

namespace App\DTOs;

/**
 * Immutable, layer-neutral carrier for login credentials. The FormRequest
 * builds one (request -> DTO); AuthService maps it onto the auth guard.
 */
readonly class LoginDTO
{
    public function __construct(
        public string $email,
        public string $password,
    ) {}
}
