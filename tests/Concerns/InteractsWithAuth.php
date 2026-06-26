<?php

namespace Tests\Concerns;

use App\Models\User;

trait InteractsWithAuth
{
    /**
     * Create (or accept) a user and return Authorization headers carrying a
     * freshly minted JWT for them.
     *
     * @return array<string, string>
     */
    protected function authHeaders(?User $user = null): array
    {
        $user ??= User::factory()->create();
        $token = auth('api')->login($user);

        return ['Authorization' => 'Bearer ' . $token];
    }
}
