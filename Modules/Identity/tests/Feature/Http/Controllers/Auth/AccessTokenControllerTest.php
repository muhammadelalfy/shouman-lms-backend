<?php

declare(strict_types=1);

namespace Modules\Identity\Tests\Feature\Http\Controllers\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\Identity\Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class AccessTokenControllerTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    private const string LOGIN_URL = '/api/v1/auth/login';

    private const string LOGOUT_URL = '/api/v1/auth/logout';

    public function test_valid_credentials_return_token_expiring_in_30_days_and_record_login(): void
    {
        $this->travelTo(Carbon::parse('2026-10-06 12:00:00', 'UTC'));
        $user = $this->createUser(attributes: ['email' => 'student@example.com']);

        $response = $this->postJson(self::LOGIN_URL, [
            'email' => 'student@example.com',
            'password' => 'Password1',
            'device_name' => 'iPhone',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.expires_at', '2026-11-05T12:00:00+00:00')
            ->assertJsonPath('data.user.id', $user->id);
        $this->assertNotEmpty($response->json('data.token'));
        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $user->id, 'name' => 'iPhone']);
        $this->assertSame('2026-10-06 12:00:00', $user->fresh()?->last_login_at?->toDateTimeString());
    }

    public function test_email_lookup_ignores_case(): void
    {
        $this->createUser(attributes: ['email' => 'student@example.com']);

        $this->postJson(self::LOGIN_URL, ['email' => 'Student@Example.com', 'password' => 'Password1'])
            ->assertOk();
    }

    public function test_wrong_password_returns_422_with_generic_message(): void
    {
        $this->createUser(attributes: ['email' => 'student@example.com']);

        $this->postJson(self::LOGIN_URL, ['email' => 'student@example.com', 'password' => 'Wrong1234'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'بيانات الدخول غير صحيحة.']);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_unknown_email_returns_the_same_generic_422(): void
    {
        $this->postJson(self::LOGIN_URL, ['email' => 'nobody@example.com', 'password' => 'Password1'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'بيانات الدخول غير صحيحة.']);
    }

    public function test_disabled_account_with_correct_password_returns_403_without_token(): void
    {
        $this->createUser(attributes: ['email' => 'student@example.com', 'status' => 'disabled']);

        $this->postJson(self::LOGIN_URL, ['email' => 'student@example.com', 'password' => 'Password1'])
            ->assertForbidden()
            ->assertJsonPath('code', 'account_disabled')
            ->assertJsonPath('message', 'تم تعطيل هذا الحساب. يرجى التواصل مع إدارة الأكاديمية.');
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_sixth_attempt_for_the_same_account_within_a_minute_returns_429(): void
    {
        $this->createUser(attributes: ['email' => 'student@example.com']);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson(self::LOGIN_URL, ['email' => 'student@example.com', 'password' => 'Wrong1234'])
                ->assertUnprocessable();
        }

        $this->postJson(self::LOGIN_URL, ['email' => 'student@example.com', 'password' => 'Password1'])
            ->assertTooManyRequests();
    }

    public function test_logout_revokes_only_the_current_token_and_returns_204(): void
    {
        $user = $this->createUser();
        $current = $user->createToken('web');
        $other = $user->createToken('mobile');

        $this->withToken($current->plainTextToken)->postJson(self::LOGOUT_URL)->assertNoContent();

        $this->assertSame([$other->accessToken->id], $user->tokens()->pluck('id')->all());
    }

    public function test_logout_without_token_returns_401(): void
    {
        $this->postJson(self::LOGOUT_URL)->assertUnauthorized();
    }
}
