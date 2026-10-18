<?php

declare(strict_types=1);

namespace Modules\Identity\Tests\Feature\Http\Controllers\Auth;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Modules\Identity\Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class NewPasswordControllerTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    private const string URL = '/api/v1/auth/reset-password';

    public function test_valid_token_sets_new_password_and_revokes_every_token(): void
    {
        Event::fake([PasswordReset::class]);
        $user = $this->createUser(attributes: ['email' => 'student@example.com']);
        $user->createToken('web');
        $user->createToken('mobile');
        $token = Password::broker()->createToken($user);

        $this->postJson(self::URL, [
            'token' => $token,
            'email' => 'student@example.com',
            'password' => 'NewSecret123',
            'password_confirmation' => 'NewSecret123',
        ])->assertOk()->assertJsonPath('message', 'تم تغيير كلمة المرور بنجاح.');

        $this->assertTrue(Hash::check('NewSecret123', (string) $user->fresh()?->getAuthPassword()));
        $this->assertSame(0, $user->tokens()->count());
        Event::assertDispatched(PasswordReset::class);
    }

    public function test_invalid_token_returns_422_and_keeps_old_password(): void
    {
        $user = $this->createUser(attributes: ['email' => 'student@example.com']);

        $this->postJson(self::URL, [
            'token' => 'not-a-real-token',
            'email' => 'student@example.com',
            'password' => 'NewSecret123',
            'password_confirmation' => 'NewSecret123',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'رابط إعادة تعيين كلمة المرور غير صالح أو منتهي الصلاحية.']);

        $this->assertTrue(Hash::check('Password1', (string) $user->fresh()?->getAuthPassword()));
    }
}
