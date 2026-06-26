<?php

namespace App\Services;

use App\DTOs\RegisterDTO;
use App\DTOs\LoginDTO;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class AuthService
{
    /**
     * Register a new user.
     *
     * @param RegisterDTO $dto
     * @return User
     */
    public function register(RegisterDTO $dto): User
    {
        return User::create([
            'name' => $dto->name,
            'email' => $dto->email,
            'password' => Hash::make($dto->password),
            'role' => $dto->role,
        ]);
    }

    /**
     * Attempt login and return JWT token.
     *
     * @param LoginDTO $dto
     * @return string|null
     */
    public function login(LoginDTO $dto): ?string
    {
        $token = Auth::guard('api')->attempt($dto->toCredentials());

        if (!$token) {
            return null;
        }

        return $token;
    }

    /**
     * Log out the current user.
     *
     * @return void
     */
    public function logout(): void
    {
        Auth::guard('api')->logout();
    }

    /**
     * Refresh the JWT token.
     *
     * @return string
     */
    public function refresh(): string
    {
        return Auth::guard('api')->refresh();
    }

    /**
     * Get the authenticated user.
     *
     * @return User|null
     */
    public function me(): ?User
    {
        return Auth::guard('api')->user();
    }
}
