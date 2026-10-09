<?php

namespace App\Notifications;


use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class VerifyEmailNotification extends Notification
{
    use Queueable;

    /**
     * Get the notification channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Build the email verification message.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(
                config('verification.expire', 60)
            ),
            [
                'id' => $notifiable->getKey(),

                'hash' => sha1(
                    $notifiable->getEmailForVerification()
                ),
            ]
        );

        return (new MailMessage)
            ->subject('Verify Your Email Address')
            ->view('emails.auth.verify-email', [
                'user' => $notifiable,
                'url' => $verificationUrl,
                'expiresIn' => config('verification.expire', 60),
            ]);
    }
}