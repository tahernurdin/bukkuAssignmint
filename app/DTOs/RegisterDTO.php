<?php

namespace App\DTOs;

/**
 * Immutable, layer-neutral carrier for a registration. The FormRequest builds
 * one (request -> DTO); AuthService maps it onto a new user.
 */
readonly class RegisterDTO
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public ?string $role = 'user',
    ) {}
}
