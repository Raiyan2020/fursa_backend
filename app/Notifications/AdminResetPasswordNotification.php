<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Password reset mail for dashboard admins.
 *
 * Separate from the volunteer/organization flow because the link has to land on
 * the dashboard reset screen and the token lives in its own broker table.
 */
class AdminResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(public string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('admin.password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        $expire = (int) config('auth.passwords.admins.expire', 60);

        return (new MailMessage)
            ->subject(__('Reset your Forsa dashboard password'))
            ->greeting(__('Hello').' '.($notifiable->name ?: '').',')
            ->line(__('You are receiving this email because we received a password reset request for your dashboard account.'))
            ->action(__('Reset password'), $url)
            ->line(__('This password reset link will expire in :count minutes.', ['count' => $expire]))
            ->line(__('If you did not request a password reset, no further action is required.'));
    }
}
