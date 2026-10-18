<?php

declare(strict_types=1);

namespace Modules\Identity\Actions;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Modules\Identity\DTOs\IssuedToken;
use Modules\Identity\Exceptions\AccountDisabledException;
use Modules\Identity\Models\User;

/**
 * Authenticates credentials and issues a personal access token (login).
 */
final class IssueAccessToken
{
    public function __construct(
        private readonly Hasher $hasher,
        private readonly Config $config,
    ) {}

    public function handle(string $email, string $password, string $deviceName): IssuedToken
    {
        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! $this->hasher->check($password, $user->getAuthPassword())) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        if (! $user->isActive()) {
            Log::info('Login refused for disabled account.', ['module' => 'identity', 'user_id' => $user->id]);

            throw new AccountDisabledException;
        }

        return $this->forUser($user, $deviceName);
    }

    /**
     * Mint a token for an already-authenticated user (e.g. right after registration).
     */
    public function forUser(User $user, string $deviceName): IssuedToken
    {
        $expiration = $this->config->get('sanctum.expiration');
        $expiresAt = is_numeric($expiration) ? now()->addMinutes((int) $expiration) : null;

        $token = $user->createToken($deviceName, ['*'], $expiresAt);

        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        return new IssuedToken($user, $token->plainTextToken, $expiresAt);
    }
}
