<?php

declare(strict_types=1);

namespace Modules\Identity\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Identity\Models\User;

/**
 * Links to the Next.js reset page; the token never touches a Laravel view.
 */
final class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60];

    public int $timeout = 30;

    public function __construct(public readonly string $token)
    {
        $this->onQueue('notifications');
    }

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $url = rtrim((string) config('app.frontend_url'), '/').'/reset-password?'.http_build_query([
            'token' => $this->token,
            'email' => $notifiable->email,
        ]);

        $expires = (int) config('auth.passwords.users.expire');

        return (new MailMessage)
            ->subject(__('identity.reset_password.subject'))
            ->greeting(__('identity.reset_password.greeting', ['name' => $notifiable->name]))
            ->line(__('identity.reset_password.intro'))
            ->action(__('identity.reset_password.action'), $url)
            ->line(__('identity.reset_password.expiry', ['minutes' => $expires]))
            ->line(__('identity.reset_password.ignore'));
    }
}
