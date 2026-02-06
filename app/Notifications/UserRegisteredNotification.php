<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Confirmation notification sent to a newly registered user.
 */
class UserRegisteredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly User $user,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Build the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Welcome — Your Account Has Been Created')
            ->greeting("Hello, {$this->user->name}!")
            ->line('Your account has been successfully created.')
            ->line("You can now log in using the email address **{$this->user->email}**.")
            ->action('Visit Application', config('app.url'))
            ->line('Thank you for joining us!');
    }
}
