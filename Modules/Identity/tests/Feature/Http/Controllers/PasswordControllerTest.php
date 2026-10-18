<?php

declare(strict_types=1);

namespace Modules\Identity\Tests\Feature\Http\Controllers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Identity\Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class PasswordControllerTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    private const string URL = '/api/v1/me/password';

    public function test_correct_current_password_changes_password_and_signs_out_other_devices(): void
    {
        $user = $this->createUser();
        $current = $user->createToken('web');
        $user->createToken('mobile');

        $this->withToken($current->plainTextToken)->putJson(self::URL, [
            'current_password' => 'Password1',
            'password' => 'NewSecret123',
            'password_confirmation' => 'NewSecret123',
        ])->assertNoContent();

        $this->assertTrue(Hash::check('NewSecret123', (string) $user->fresh()?->getAuthPassword()));
        $this->assertSame([$current->accessToken->id], $user->tokens()->pluck('id')->all());
    }

    public function test_wrong_current_password_returns_422(): void
    {
        $user = $this->createUser();

        $this->withToken($user->createToken('web')->plainTextToken)->putJson(self::URL, [
            'current_password' => 'Wrong1234',
            'password' => 'NewSecret123',
            'password_confirmation' => 'NewSecret123',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password' => 'كلمة المرور الحالية غير صحيحة.']);

        $this->assertTrue(Hash::check('Password1', (string) $user->fresh()?->getAuthPassword()));
    }

    public function test_reusing_the_current_password_returns_422(): void
    {
        $user = $this->createUser();

        $this->withToken($user->createToken('web')->plainTextToken)->putJson(self::URL, [
            'current_password' => 'Password1',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
        ])->assertUnprocessable()->assertJsonValidationErrors(['password']);
    }
}
