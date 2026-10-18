<?php

declare(strict_types=1);

namespace Modules\Identity\Tests\Feature\Http\Middleware;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\Enums\AccountStatus;
use Modules\Identity\Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class EnsureAccountIsActiveTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    public function test_surviving_token_of_disabled_account_returns_403(): void
    {
        $user = $this->createUser();
        $token = $user->createToken('web')->plainTextToken;
        $user->forceFill(['status' => AccountStatus::Disabled])->save();

        $this->withToken($token)
            ->getJson('/api/v1/me')
            ->assertForbidden()
            ->assertJsonPath('code', 'account_disabled');
    }
}
