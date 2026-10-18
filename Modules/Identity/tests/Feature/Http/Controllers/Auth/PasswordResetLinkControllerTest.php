<?php

declare(strict_types=1);

namespace Modules\Identity\Tests\Feature\Http\Controllers\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Identity\Notifications\ResetPasswordNotification;
use Modules\Identity\Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class PasswordResetLinkControllerTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    private const string URL = '/api/v1/auth/forgot-password';

    private const string GENERIC_MESSAGE = 'إذا كان البريد الإلكتروني مسجلًا لدينا فستصلك رسالة تحتوي على رابط إعادة تعيين كلمة المرور.';

    public function test_registered_email_gets_queued_link_to_frontend_and_returns_202(): void
    {
        Notification::fake();
        config(['app.frontend_url' => 'https://academy.example']);
        $user = $this->createUser(attributes: ['email' => 'student@example.com']);

        $this->postJson(self::URL, ['email' => 'Student@example.com'])
            ->assertStatus(202)
            ->assertJsonPath('message', self::GENERIC_MESSAGE);

        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($user): bool {
            $url = $notification->toMail($user)->actionUrl;

            return $notification->queue === 'notifications'
                && str_starts_with($url, 'https://academy.example/reset-password?token=')
                && str_contains($url, 'email=student%40example.com');
        });
    }

    public function test_unknown_email_returns_the_same_202_without_sending(): void
    {
        Notification::fake();

        $this->postJson(self::URL, ['email' => 'nobody@example.com'])
            ->assertStatus(202)
            ->assertJsonPath('message', self::GENERIC_MESSAGE);

        Notification::assertNothingSent();
    }

    public function test_missing_email_returns_422(): void
    {
        $this->postJson(self::URL, [])->assertUnprocessable()->assertJsonValidationErrors(['email']);
    }
}
