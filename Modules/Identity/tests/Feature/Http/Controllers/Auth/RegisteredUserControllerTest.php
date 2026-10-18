<?php

declare(strict_types=1);

namespace Modules\Identity\Tests\Feature\Http\Controllers\Auth;

use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Identity\Enums\AccountStatus;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Identity\Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class RegisteredUserControllerTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    private const string URL = '/api/v1/auth/register';

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'أحمد محمد',
            'email' => 'ahmed@example.com',
            'phone' => '+201001234567',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
            ...$overrides,
        ];
    }

    public function test_valid_payload_creates_active_student_and_returns_201_with_token(): void
    {
        Event::fake([Registered::class]);

        $response = $this->postJson(self::URL, $this->payload());

        $response->assertCreated()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', 'ahmed@example.com')
            ->assertJsonPath('data.user.roles', ['student'])
            ->assertJsonPath('data.user.status', 'active');
        $this->assertNotEmpty($response->json('data.token'));

        $user = User::query()->where('email', 'ahmed@example.com')->sole();
        $this->assertSame(AccountStatus::Active, $user->status);
        $this->assertSame('+201001234567', $user->phone);
        $this->assertTrue($user->hasRole(Role::Student));
        $this->assertSame(1, $user->tokens()->count());
        Event::assertDispatched(Registered::class);
    }

    public function test_email_is_stored_lower_cased(): void
    {
        $this->postJson(self::URL, $this->payload(['email' => '  Ahmed@Example.COM ']))->assertCreated();

        $this->assertDatabaseHas('users', ['email' => 'ahmed@example.com']);
    }

    public function test_role_and_status_in_payload_are_ignored(): void
    {
        $this->postJson(self::URL, $this->payload([
            'roles' => ['system-administrator'],
            'role' => 'system-administrator',
            'status' => 'disabled',
        ]))->assertCreated();

        $user = User::query()->where('email', 'ahmed@example.com')->sole();
        $this->assertSame(['student'], $user->getRoleNames()->all());
        $this->assertSame(AccountStatus::Active, $user->status);
    }

    public function test_email_registered_in_another_case_returns_422(): void
    {
        $this->createUser(attributes: ['email' => 'ahmed@example.com']);

        $this->postJson(self::URL, $this->payload(['email' => 'AHMED@example.com']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'البريد الإلكتروني مستخدم من قبل.']);
    }

    public function test_empty_payload_returns_422_for_required_fields(): void
    {
        $this->postJson(self::URL, [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_password_without_uppercase_returns_422(): void
    {
        $this->postJson(self::URL, $this->payload(['password' => 'secret123', 'password_confirmation' => 'secret123']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password' => 'يجب أن تحتوي كلمة المرور على حرف كبير وحرف صغير على الأقل.']);
    }

    public function test_malformed_phone_returns_422_with_format_hint(): void
    {
        $this->postJson(self::URL, $this->payload(['phone' => '010-abc']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone' => 'رقم الهاتف غير صالح. استخدم الصيغة الدولية مثل +201001234567.']);
    }

    public function test_eleventh_registration_attempt_in_a_minute_returns_429(): void
    {
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->postJson(self::URL, [])->assertUnprocessable();
        }

        $this->postJson(self::URL, $this->payload())->assertTooManyRequests();
        $this->assertDatabaseMissing('users', ['email' => 'ahmed@example.com']);
    }
}
