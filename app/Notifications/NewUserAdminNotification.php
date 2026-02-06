<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent to the system administrator when a new user registers.
 */
class NewUserAdminNotification extends Notification implements ShouldQueue
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
            ->subject("New User Registered: {$this->user->name}")
            ->greeting('New User Registered')
            ->line('A new user has signed up with the following details:')
            ->line("**Name:** {$this->user->name}")
            ->line("**Email:** {$this->user->email}")
            ->line("**Role:** {$this->user->role->label()}")
            ->line("**Registered at:** {$this->user->created_at->toDateTimeString()}");
    }
}
